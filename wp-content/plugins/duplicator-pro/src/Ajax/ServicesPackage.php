<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Ajax;

use Duplicator\Addons\DupCloudAddon\Models\DupCloudStorage;
use Duplicator\Utils\Logging\DupLog;
use Duplicator\Package\DupPackage;
use Duplicator\Models\TemplateEntity;
use Duplicator\Package\Storage\UploadInfo;
use Duplicator\Addons\ProBase\License\License;
use Duplicator\Core\CapMng;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapNet;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Libs\Snap\SnapWP;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\Storages\StoragesUtil;
use Duplicator\Package\AbstractPackage;
use Duplicator\Package\Archive\PackageArchive;
use Duplicator\Package\Create\Scan\Tree\Tree;
use Duplicator\Package\PackageUtils;
use Duplicator\Package\Runner;
use Duplicator\Package\TemporaryPackageUtils;
use Duplicator\Utils\LockUtil;
use Duplicator\Utils\Logging\ErrorHandler;
use Exception;
use stdClass;
use Throwable;
use WP_Error;

class ServicesPackage extends AbstractAjaxService
{
    const EXEC_STATUS_PASS = 1;
    /**
     * @deprecated Never used
     */
    const EXEC_STATUS_WARN = 2;

    const EXEC_STATUS_FAIL             = 3;
    const EXEC_STATUS_MORE_TO_SCAN     = 4;
    const EXEC_STATUS_SCHEDULE_RUNNING = 5;

    /**
     * Init ajax calls
     *
     * @return void
     */
    public function init(): void
    {
        $this->addAjaxCall('wp_ajax_duplicator_process_worker', 'processWorker');
        $this->addAjaxCall('wp_ajax_nopriv_duplicator_process_worker', 'processWorker');

        $this->addAjaxCall('wp_ajax_duplicator_download_package_file', 'downloadPackageFile');
        $this->addAjaxCall('wp_ajax_nopriv_duplicator_download_package_file', 'downloadPackageFile');

        if (!License::can(License::CAPABILITY_BASE_ADVANCED)) {
            return;
        }
        $this->addAjaxCall('wp_ajax_duplicator_add_quick_filters', 'addQuickFilters');
        $this->addAjaxCall('wp_ajax_duplicator_package_scan', 'packageScan');
        $this->addAjaxCall('wp_ajax_duplicator_package_delete', 'packageDelete');
        $this->addAjaxCall('wp_ajax_duplicator_reset_packages', 'resetPackages');
        $this->addAjaxCall('wp_ajax_duplicator_get_package_statii', 'packageStatii');
        $this->addAjaxCall('wp_ajax_duplicator_package_stop_build', 'stopBuild');
        $this->addAjaxCall('wp_ajax_duplicator_manual_transfer_storage', 'manualTransferStorage');
        $this->addAjaxCall('wp_ajax_duplicator_packages_details_transfer_get_package_vm', 'detailsTransferGetPackageVM');
        $this->addAjaxCall('wp_ajax_duplicator_get_folder_children', 'getFolderChildren');
        $this->addAjaxCall("wp_ajax_duplicator_get_remote_restore_download_options", "remoteRestoreDownloadOptions");
    }

    /**
     * Removed all reserved installer files names
     *
     * @return never
     */
    public function addQuickFilters(): void
    {
        ErrorHandler::init();
        check_ajax_referer('duplicator_add_quick_filters', 'nonce');
        $inputData = filter_input_array(INPUT_POST, [
            'dir_paths'  => [
                'filter'  => FILTER_DEFAULT,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => ['default' => ''],
            ],
            'file_paths' => [
                'filter'  => FILTER_DEFAULT,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => ['default' => ''],
            ],
        ]);
        $result    = [
            'success'      => false,
            'message'      => '',
            'filter-dirs'  => '',
            'filter-files' => '',
            'filter-names' => '',
        ];
        try {
            // CONTROLLER LOGIC
            // Need to update both the template and the temporary Backup because:
            // 1) We need to preserve preferences of this build for future manual builds - the manual template is used for this.
            // 2) Temporary Backup is used during this build - keeps all the settings/storage information.
            // Will be inserted into the Backup table after they ok the scan results.
            $template  = TemplateEntity::getManualTemplate();
            $dirPaths  = PackageArchive::parseDirectoryFilter(SnapUtil::sanitizeNSChars($inputData['dir_paths']));
            $filePaths = PackageArchive::parseFileFilter(SnapUtil::sanitizeNSChars($inputData['file_paths']));

            // If we are adding a new filter & we have filters disabled, clear out the old filters.
            if (!$template->archive_filter_on && (strlen($dirPaths) > 0 || strlen($filePaths) > 0)) {
                $template->archive_filter_dirs  = '';
                $template->archive_filter_files = '';
            }

            if (strlen($dirPaths) > 0) {
                $template->archive_filter_dirs .= strlen($template->archive_filter_dirs) > 0 ? ';' . $dirPaths : $dirPaths;
            }

            if (strlen($filePaths) > 0) {
                $template->archive_filter_files .= strlen($template->archive_filter_files) > 0 ? ';' . $filePaths : $filePaths;
            }

            if (!$template->archive_filter_on) {
                $template->archive_filter_exts = '';
            }

            $template->archive_filter_on    = true;
            $template->archive_filter_names = true;
            $template->save();

            $tmpPackage                       = TemporaryPackageUtils::getTemporaryPackage();
            $tmpPackage->Archive->FilterDirs  = $template->archive_filter_dirs;
            $tmpPackage->Archive->FilterFiles = $template->archive_filter_files;
            $tmpPackage->Archive->FilterOn    = true;
            $tmpPackage->Archive->FilterNames = $template->archive_filter_names;
            $tmpPackage->setStatus(AbstractPackage::STATUS_PRE_PROCESS);

            $result['success']      = true;
            $result['filter-dirs']  = $tmpPackage->Archive->FilterDirs;
            $result['filter-files'] = $tmpPackage->Archive->FilterFiles;
            $result['filter-names'] = $tmpPackage->Archive->FilterNames;
        } catch (Exception $exc) {
            $result['success'] = false;
            $result['message'] = $exc->getMessage();
        }

        wp_send_json($result);
    }

    /**
     *  Package Scan
     *
     *  @example to test: /wp-admin/admin-ajax.php?action=duplicator_package_scan
     *
     *  @return void
     */
    public function packageScan(): void
    {
        AjaxWrapper::json(
            [
                self::class,
                'packageScanCallback',
            ],
            'duplicator_package_scan',
            SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'nonce'),
            CapMng::CAP_CREATE
        );
    }

    /**
     *  Package Scan
     *
     *  @example to test: /wp-admin/admin-ajax.php?action=duplicator_package_scan
     *
     *  @return array<string, mixed>
     */
    public static function packageScanCallback()
    {
        ErrorHandler::init();
        try {
            // Keep the locking file opening and closing just to avoid adding even more complexity
            if (!LockUtil::lockProcess()) {
                // File is already locked indicating schedule is running
                DupLog::trace("Already locked when attempting manual build - schedule running");

                return ['Status' => self::EXEC_STATUS_SCHEDULE_RUNNING];
            }

            @set_time_limit(0);
            StoragesUtil::getDefaultStorage()->initStorageDirectory(true);

            $report  = [];
            $package = TemporaryPackageUtils::getTemporaryPackage();

            $firstChunk = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'firstChunk', false);
            DupLog::trace('First Chunk: ' . ($firstChunk ? 'true' : 'false'));
            if ($firstChunk) {
                DupLog::trace('First Scan Chunk');
                $package->setStatus(AbstractPackage::STATUS_SCANNING);
            } else {
                DupLog::trace('Continuing Scan');
            }

            if ($package->getStatus() <= AbstractPackage::STATUS_SCANNING) {
                $fileScanDone = $package->Archive->scanFiles($firstChunk);
                $report       = ['Status' => self::EXEC_STATUS_MORE_TO_SCAN];

                if ($fileScanDone) {
                    DupLog::trace('Scan done, next chunk validation');
                    $package->setStatus(AbstractPackage::STATUS_SCAN_VALIDATION);
                } else {
                    DupLog::trace('Scan not done yet');
                    $package->save();
                }
            } elseif ($package->getStatus() <= AbstractPackage::STATUS_SCAN_VALIDATION) {
                DupLog::trace('Starting Index File Validation');
                if ($package->Archive->validateIndexFile()) {
                    $report           = $package->createScanReport();
                    $report['Status'] = self::EXEC_STATUS_PASS;
                    $package->setStatus(AbstractPackage::STATUS_AFTER_SCAN);
                } else {
                    throw new Exception("Index file validation failed");
                }
            }

            $package->Archive->freeIndexManager();
            LockUtil::unlockProcess();
        } catch (Throwable $ex) {
            DupLog::infoTraceException($ex, "Error during manual build scan: ");
            return [
                'Status'  =>  self::EXEC_STATUS_FAIL,
                'Message' =>  sprintf(
                    esc_html__("Error occurred. Error message: %1\$s<br>\nTrace: %2\$s", 'duplicator-pro'),
                    $ex->getMessage(),
                    $ex->getTraceAsString()
                ),
                'File'    => $ex->getFile(),
                'Line'    => $ex->getLine(),
                'Trace'   => $ex->getTrace(),
            ];
        }

        return $report;
    }

    /**
     * Hook ajax wp_ajax_duplicator_package_delete
     * Deletes the files and database record entries
     *
     * @return void
     */
    public function packageDelete(): void
    {
        AjaxWrapper::json(
            [
                self::class,
                'packageDeleteCallback',
            ],
            'duplicator_package_delete',
            SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'nonce'),
            CapMng::CAP_CREATE
        );
    }

    /**
     * Hook ajax wp_ajax_duplicator_package_delete
     * Deletes the files and database record entries
     *
     * @return array<string, mixed>
     */
    public static function packageDeleteCallback(): array
    {
        $deletedCount = 0;

        $inputData     = filter_input_array(INPUT_POST, [
            'package_ids' => [
                'filter'  => FILTER_VALIDATE_INT,
                'flags'   => FILTER_REQUIRE_ARRAY,
                'options' => ['default' => false],
            ],
        ]);
        $packageIDList = $inputData['package_ids'];

        if (empty($packageIDList) || in_array(false, $packageIDList)) {
            throw new Exception(__("Invalid request.", 'duplicator-pro'));
        }

        DupLog::traceObject("Starting deletion of Backups by ids: ", $packageIDList);
        foreach ($packageIDList as $id) {
            if ($package = DupPackage::getById($id)) {
                if ($package->delete()) {
                    $deletedCount++;
                }
            } else {
                throw new Exception("Invalid Backup ID.");
            }
        }

        return [
            'ids'     => $packageIDList,
            'removed' => $deletedCount,
        ];
    }

    /**
     * Hook ajax wp_ajax_duplicator_reset_packages
     *
     * @return never
     */
    public function resetPackages(): void
    {
        ob_start();
        try {
            ErrorHandler::init();

            $error  = false;
            $result = [
                'data'    => ['status' => null],
                'html'    => '',
                'message' => '',
            ];

            $nonce = SnapUtil::sanitizeTextInput(INPUT_POST, 'nonce');
            if (!wp_verify_nonce($nonce, 'duplicator_reset_packages')) {
                DupLog::trace('Security issue');
                throw new Exception('Security issue');
            }
            CapMng::can(CapMng::CAP_SETTINGS);

            // first last Backup id
            $ids = DupPackage::getIdsByStatus(
                [
                    [
                        'op'     => '<',
                        'status' => AbstractPackage::STATUS_COMPLETE,
                    ],
                ],
                0,
                0,
                '`id` DESC'
            );
            foreach ($ids as $id) {
                // A smooth deletion is not performed because it is a forced reset.
                DupPackage::forceDelete($id);
            }
        } catch (Exception $e) {
            $error             = true;
            $result['message'] = $e->getMessage();
        }

        $result['html'] = ob_get_clean();
        if ($error) {
            wp_send_json_error($result);
        } else {
            wp_send_json_success($result);
        }
    }

    /**
     * Hook ajax wp_ajax_duplicator_get_package_statii
     *
     * @return void
     */
    public function packageStatii(): void
    {
        AjaxWrapper::json(
            [
                self::class,
                'packageStatiiCallback',
            ],
            'duplicator_get_package_statii',
            SnapUtil::sanitizeTextInput(INPUT_POST, 'nonce'),
            CapMng::CAP_BASIC
        );
    }

    /**
     * Hook ajax wp_ajax_duplicator_get_package_statii
     *
     * @return array<mixed>
     */
    public static function packageStatiiCallback(): array
    {
        $limit      = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'limit', 0);
        $offset     = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'offset', 0);
        $packageId  = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'packageId', -1);
        $backupType = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'backupType', PackageUtils::DEFAULT_BACKUP_TYPE);

        if ($packageId > 0) {
            if (($package = DupPackage::getById($packageId)) === false) {
                throw new Exception(__('Couldn\'t get Backup.', 'duplicator-pro'));
            }

            return [self::getPackageStatusInfo($package)];
        }

        // Filter out failed packages (status < 0)
        $statusConditions = [
            [
                'op'     => '>=',
                'status' => 0,
            ],
        ];

        $resultData = [];
        DupPackage::dbSelectByStatusCallback(
            function (DupPackage $package) use (&$resultData): void {
                $resultData[] = self::getPackageStatusInfo($package);
            },
            $statusConditions,
            $limit,
            $offset,
            '`id` DESC',
            [$backupType]
        );

        return $resultData;
    }

    /**
     * Returns the Backup status info
     *
     * @param DupPackage $package The Backup
     *
     * @return array<string, mixed> The status data
     */
    protected static function getPackageStatusInfo(DupPackage $package): array
    {
        $progress = $package->getProgress();

        $status                         = [];
        $status['ID']                   = $package->getId();
        $status['status']               = $package->getStatus();
        $status['status_progress']      = round($progress['percent'], 1);
        $status['size']                 = $package->getBuildSize();
        $status['status_progress_text'] = $progress['message'];
        $status['phase_name']           = $progress['phaseName'];

        return $status;
    }

    /**
     * Hook ajax wp_ajax_duplicator_package_stop_build
     *
     * @return void
     */
    public function stopBuild(): void
    {
        AjaxWrapper::json(
            [
                self::class,
                'stopBuildCallback',
            ],
            'duplicator_package_stop_build',
            SnapUtil::sanitizeTextInput(INPUT_POST, 'nonce'),
            CapMng::CAP_CREATE
        );
    }

    /**
     * Stop build callback
     *
     * @return array<string, mixed>
     */
    public static function stopBuildCallback(): array
    {
        $inputData = filter_input_array(INPUT_POST, [
            'package_id'  => [
                'filter'  => FILTER_VALIDATE_INT,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => ['default' => false],
            ],
            'stop_active' => [
                'filter'  => FILTER_VALIDATE_BOOLEAN,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => ['default' => false],
            ],
        ]);

        $packageId  = $inputData['package_id'];
        $stopActive = $inputData['stop_active'];

        try {
            $package = null;
            if ($stopActive) {
                DupLog::trace("Web service stop build of $packageId");
                $package = DupPackage::getNextActive();
            } elseif ($packageId != false) {
                DupLog::trace("Web service stop build of $packageId");
                $package = DupPackage::getById($packageId);
            }

            if ($package == null && $stopActive !== true) {
                DupLog::trace(
                    "Could not find Backup so attempting hard delete.
                    Old files may end up sticking around although chances are there isnt much if we couldnt nicely cancel it."
                );
                $result = DupPackage::forceDelete($packageId);

                if (!$result) {
                    throw new Exception('Hard delete failure');
                }

                return [
                    'success' => true,
                    'message' => 'Hard delete success',
                ];
            } else {
                DupLog::trace("set {$package->getId()} for cancel");
                $package->setForCancel();
            }
        } catch (Exception $ex) {
            DupLog::trace($ex->getMessage());
            throw $ex;
        }

        return ['success' => true];
    }

    /**
     * Hook ajax process worker
     *
     * @return never
     */
    public function processWorker(): void
    {
        ErrorHandler::init();
        header("HTTP/1.1 200 OK");

        DupLog::trace("Process worker request");
        Runner::process();
        DupLog::trace("Exiting process worker request");

        echo 'ok';
        exit();
    }

    /**
     * Hook ajax wp_ajax_duplicator_download_package_file
     *
     * @return never
     */
    public function downloadPackageFile(): void
    {
        ErrorHandler::init();
        $inputData = filter_input_array(INPUT_GET, [
            'fileType' => [
                'filter'  => FILTER_VALIDATE_INT,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => ['default' => false],
            ],
            'hash'     => [
                'filter'  => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => ['default' => false],
            ],
            'token'    => [
                'filter'  => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => ['default' => false],
            ],
        ]);

        try {
            if (
                $inputData['token'] === false ||
                $inputData['hash'] === false ||
                $inputData["fileType"] === false ||
                DupPackage::getLocalPackageAjaxDownloadToken($inputData['hash']) !== $inputData['token'] ||
                ($package = DupPackage::getByHash($inputData['hash'])) == false
            ) {
                throw new Exception(__("Invalid request.", 'duplicator-pro'));
            }

            switch ($inputData['fileType']) {
                case AbstractPackage::FILE_TYPE_INSTALLER:
                    $filePath = $package->getLocalPackageFilePath(AbstractPackage::FILE_TYPE_INSTALLER);
                    $fileName = $package->Installer->getDownloadName();
                    break;
                case AbstractPackage::FILE_TYPE_ARCHIVE:
                    $filePath = $package->getLocalPackageFilePath(AbstractPackage::FILE_TYPE_ARCHIVE);
                    $fileName = basename($filePath);
                    break;
                case AbstractPackage::FILE_TYPE_LOG:
                    $filePath = $package->getLocalPackageFilePath(AbstractPackage::FILE_TYPE_LOG);
                    $fileName = basename($filePath);
                    break;
                default:
                    throw new Exception(__("File type not supported.", 'duplicator-pro'));
            }

            if ($filePath == false) {
                throw new Exception(__("File don\'t exists", 'duplicator-pro'));
            }

            SnapNet::serveFileForDownload($filePath, $fileName, DUPLICATOR_BUFFER_DOWNLOAD_SIZE);
        } catch (Exception $ex) {
            DupLog::trace('Unable to download Backup file: ' . $ex->getMessage());
            wp_die(esc_html($ex->getMessage()));
        }
    }

    /**
     * Hook ajax transfer data
     *
     * @return void
     */
    public function detailsTransferGetPackageVM(): void
    {
        AjaxWrapper::json(
            [
                self::class,
                'detailsTransferGetPackageVMCallback',
            ],
            'duplicator_packages_details_transfer_get_package_vm',
            SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'nonce'),
            CapMng::CAP_CREATE
        );
    }

    /**
     * Hook ajax handler for packages_details_transfer_get_package_vm
     * Retrieve view model for the Packages/Details/Transfer screen
     * active_package_id: true/false
     * percent_text: Percent through the current transfer
     * text: Text to display
     * transfer_logs: array of transfer request vms (start, stop, status, message)
     *
     * @return array<string, mixed>
     */
    public static function detailsTransferGetPackageVMCallback(): array
    {
        $inputData = filter_input_array(INPUT_POST, [
            'package_id' => [
                'filter'  => FILTER_VALIDATE_INT,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => ['default' => false],
            ],
        ]);

        $package_id = $inputData['package_id'];
        if (!$package_id) {
            throw new Exception(__("Invalid request.", 'duplicator-pro'));
        }

        if (!CapMng::can(CapMng::CAP_STORAGE, false)) {
            throw new Exception('Security issue.');
        }

        $package = DupPackage::getById($package_id);
        if (!$package) {
            $msg = sprintf(__('Could not get Backup by ID %s', 'duplicator-pro'), $package_id);
            throw new Exception($msg);
        }

        $vm = new stdClass();

        /* -- First populate the transfer log information -- */

        // If this is the Backup being requested include the transfer details
        $vm->transfer_logs = [];

        $active_upload_info = null;

        $storages = AbstractStorageEntity::getAll();

        foreach ($package->upload_infos as &$upload_info) {
            if ($upload_info->getStorageId() === StoragesUtil::getDefaultStorageId()) {
                continue;
            }

            $status      = $upload_info->getStatus();
            $status_text = $upload_info->getStatusText();

            $transfer_log = new stdClass();

            if ($upload_info->getStartedTimestamp() == null) {
                $transfer_log->started = __('N/A', 'duplicator-pro');
            } else {
                $transfer_log->started = SnapWP::getLocalTimeFromGMTTicks($upload_info->getStartedTimestamp());
            }

            if ($upload_info->getStoppedTimestamp() == null) {
                $transfer_log->stopped = __('N/A', 'duplicator-pro');
            } else {
                $transfer_log->stopped = SnapWP::getLocalTimeFromGMTTicks($upload_info->getStoppedTimestamp());
            }

            $transfer_log->status_text = $status_text;
            $transfer_log->message     = $upload_info->getStatusMessage();

            $transfer_log->storage_type_text = __('Unknown', 'duplicator-pro');
            foreach ($storages as $storage) {
                if ($storage->getId() == $upload_info->getStorageId()) {
                    $transfer_log->storage_type_text = $storage->getStypeName();
                    // break;
                }
            }

            array_unshift($vm->transfer_logs, $transfer_log);

            if ($status == UploadInfo::STATUS_RUNNING) {
                if ($active_upload_info != null) {
                    DupLog::trace("More than one upload info is running at the same time for Backup {$package->getId()}");
                }

                $active_upload_info = &$upload_info;
            }
        }

        /* -- Now populate the activa Backup information -- */
        $active_package = DupPackage::getNextActive();

        if ($active_package == null) {
            // No active Backup
            $vm->active_package_id = -1;
            $vm->text              = __('No Backup is building.', 'duplicator-pro');
        } else {
            $vm->active_package_id = $active_package->getId();

            if ($active_package->getId() == $package_id) {
                if ($active_upload_info != null) {
                    $vm->percent_text = "{$active_upload_info->progress}%";
                    $vm->text         = $active_upload_info->getStatusMessage();
                } else {
                    // We see this condition at the beginning and end of the transfer so throw up a generic message
                    $vm->percent_text = "";
                    $vm->text         = __("Synchronizing with server...", 'duplicator-pro');
                }
            } else {
                $vm->text = __("Another Backup is presently running.", 'duplicator-pro');
            }

            if ($active_package->isCancelPending()) {
                // If it's getting cancelled override the normal text
                $vm->text = __("Cancellation pending...", 'duplicator-pro');
            }
        }

        return [
            'success' => true,
            'vm'      => $vm,
        ];
    }

    /**
     * Hook ajax manual transfer storage
     *
     * @return void
     */
    public function manualTransferStorage(): void
    {
        AjaxWrapper::json(
            [
                self::class,
                'manualTransferStorageCallback',
            ],
            'duplicator_manual_transfer_storage',
            SnapUtil::sanitizeTextInput(INPUT_POST, 'nonce'),
            CapMng::CAP_CREATE
        );
    }

    /**
     * Manual transfer storage callback
     *
     * @return array<string, mixed>
     */
    public static function manualTransferStorageCallback(): array
    {
        $isValid   = true;
        $inputData = SnapUtil::filterInputRequestArray([
            'storage_ids' => [
                'filter'  => FILTER_VALIDATE_INT,
                'flags'   => FILTER_REQUIRE_ARRAY,
                'options' => ['default' => false],
            ],
        ]);

        $package_id = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'package_id', -1);
        // Intentionally discard invalid IDs and proceed with valid ones, rather than rejecting the entire request.
        $storage_ids = array_filter($inputData['storage_ids'] ?: [], fn($v) => $v !== false);
        $isDownload  = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'download', false);
        $isValid     = $package_id > 0 && $storage_ids !== [];

        try {
            if (!$isValid) {
                throw new Exception(__("Invalid request.", 'duplicator-pro'));
            }

            DupLog::trace("Test if Backup is Running");
            if (DupPackage::isPackageRunning()) {
                DupLog::trace("Package running.");
                $msg = sprintf(__('Trying to queue a transfer for Backup %d but a Backup is already active!', 'duplicator-pro'), $package_id);
                throw new Exception($msg);
            } else {
                DupLog::trace("Package not running.");
            }

            $package = DupPackage::getById($package_id);
            DupLog::open($package->getNameHash());

            if (!$package) {
                throw new Exception(sprintf(esc_html__('Could not find Backup ID %d!', 'duplicator-pro'), $package_id));
            }

            if (empty($storage_ids)) {
                throw new Exception("Please select a storage.");
            }

            $info  = "\n";
            $info .= "********************************************************************************\n";
            $info .= "********************************************************************************\n";
            $info .= "PACKAGE MANUAL TRANSFER REQUESTED: " . @date("Y-m-d H:i:s") . "\n";
            $info .= "********************************************************************************\n";
            $info .= "********************************************************************************\n\n";
            DupLog::infoTrace($info);

            foreach ($storage_ids as $storage_id) {
                $result = $package->addUploadInfo($storage_id, $isDownload);
                if ($result instanceof WP_Error && $result->has_errors()) {
                    throw new Exception($result->get_error_message());
                }

                if (($storage = AbstractStorageEntity::getById($storage_id)) !== false) {
                    DupLog::infoTrace(
                        'Storage adding to the Backup "' . $package->getName() .
                            ' [Package Id: ' . $package_id . ']":: Storage Id: "' . $storage_id .
                            '" Storage Name: "' . esc_html($storage->getName()) .
                            '" Storage Type: "' . esc_html($storage->getStypeName()) . '"'
                    );
                }
            }

            $package->timer_start = microtime(true);
            $package->setStatus(AbstractPackage::STATUS_STORAGE_PROCESSING);
        } catch (Exception $ex) {
            DupLog::trace($ex->getMessage());
            throw $ex;
        }

        return ['success' => true];
    }

    /**
     * Hook ajax wp_ajax_duplicator_get_folder_children
     *
     * @return never
     */
    public function getFolderChildren(): void
    {
        ErrorHandler::init();
        check_ajax_referer('duplicator_get_folder_children', 'nonce');

        $json      = [];
        $isValid   = true;
        $inputData = filter_input_array(INPUT_GET, [
            'folder'  => [
                'filter'  => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => ['default' => false],
            ],
            'exclude' => [
                'filter'  => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags'   => FILTER_REQUIRE_ARRAY,
                'options' => [
                    'default' => [],
                ],
            ],
        ]);
        $folder    = $inputData['folder'];
        $exclude   = $inputData['exclude'];

        if ($folder === false) {
            $isValid = false;
        }

        ob_start();
        try {
            CapMng::can(CapMng::CAP_BASIC);

            if (!$isValid) {
                throw new Exception(__('Invalid request.', 'duplicator-pro'));
            }
            if (is_dir($folder)) {
                $package = TemporaryPackageUtils::getTemporaryPackage();

                $treeObj = new Tree($folder, true, $exclude);
                $treeObj->uasort(['PackageArchive', 'sortTreeByFolderWarningName']);
                $treeObj->treeTraverseCallback([$package->Archive, 'checkTreeNodesFolder']);

                $jsTreeData = PackageArchive::getJsTreeStructure($treeObj, '', false);
                $json       = $jsTreeData['children'];
            }
        } catch (Exception $e) {
            DupLog::trace($e->getMessage());
            $json['message'] = $e->getMessage();
        }
        ob_clean();
        wp_send_json($json);
    }

    /**
     * Show remote storage options from where the Backup can be downloaded
     *
     * @return void
     */
    public function remoteRestoreDownloadOptions(): void
    {
        AjaxWrapper::json(
            [
                self::class,
                'remoteRestoreDownloadOptionsCallback',
            ],
            'duplicator_get_remote_restore_download_options',
            SnapUtil::sanitizeTextInput(INPUT_POST, 'nonce'),
            [
                CapMng::CAP_BACKUP_RESTORE,
                CapMng::CAP_STORAGE,
            ]
        );
    }

    /**
     * Show remote storage options from where the Backup can be downloaded
     *
     * @return array<string,mixed>
     */
    public static function remoteRestoreDownloadOptionsCallback(): array
    {
        $result = [
            'success'       => true,
            'alreadyInUse'  => false,
            'cancelNeeded'  => false,
            'packageExists' => true,
            'message'       => '',
            'content'       => '',
        ];
        try {
            $packageId = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'packageId', -1);

            switch (SnapUtil::sanitizeStrictInput(SnapUtil::INPUT_REQUEST, 'remoteAction')) {
                case 'download':
                    $action = 'download';
                    break;
                case 'restore':
                    $action = 'restore';
                    break;
                default:
                    throw new Exception(__('Invalid action.', 'duplicator-pro'));
            }

            if ($packageId < 0 || ($package = DupPackage::getById($packageId)) === false) {
                throw new Exception(__('Invalid Backup ID.', 'duplicator-pro'));
            }

            if ($package->haveLocalStorage()) {
                throw new Exception(__('Backup already exists locally.', 'duplicator-pro'));
            }

            if (DupPackage::isPackageRunning()) {
                $result['cancelNeeded'] = true;
                $activePackage          = DupPackage::getNextActive();

                if ($activePackage !== null && $packageId === $activePackage->getId()) {
                    $result['alreadyInUse'] = true;
                }

                return $result;
            }

            $storages = $package->getValidStorages(true);

            if (count($storages) === 0) {
                $result['packageExists'] = false;
                $result['message']       = __('Backup does not exist in any remote storage.', 'duplicator-pro');
                return $result;
            }

            if ($action === 'restore') {
                $template = 'admin_pages/packages/remote_download/remote_restore_options';
            } else {
                $template = 'admin_pages/packages/remote_download/remote_download_options';
            }

            $result['content'] = TplMng::getInstance()->render($template, [
                'packageId'     => $package->getId(),
                'packageName'   => $package->getName(),
                'isStorageFull' => StoragesUtil::getDefaultStorage()->isFull(),
                'storages'      => $storages,
            ], false);
        } catch (Exception $ex) {
            DupLog::trace($ex->getMessage());
            throw $ex;
        }

        return $result;
    }
}
