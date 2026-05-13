<?php

/**
 * Runner class manages the schedule processes and package building operations.
 *
 * This class is responsible for:
 * - Managing and processing scheduled backups
 * - Handling package building states and transitions
 * - Monitoring and cancelling stuck or long-running processes
 * - Managing worker processes for background operations
 * - Enforcing system requirements and build constraints
 * - Coordinating storage processing after package creation
 *
 * The class implements a robust state machine to ensure reliable package creation
 * and proper handling of various edge cases like timeouts and resource constraints.
 *
 * @package   Duplicator
 * @copyright (c) 2024, Snap Creek LLC
 */

namespace Duplicator\Package;

use Duplicator\Models\GlobalEntity;
use Duplicator\Utils\Logging\DupLog;
use Duplicator\Package\DupPackage;
use Duplicator\Core\Constants;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Libs\Snap\SnapString;
use Duplicator\Libs\Snap\SnapURL;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Models\DynamicGlobalEntity;
use Duplicator\Models\ScheduleEntity;
use Duplicator\Models\Storages\StoragesUtil;
use Duplicator\Models\SystemGlobalEntity;
use Duplicator\Package\Archive\PackageArchive;
use Duplicator\Utils\LockUtil;
use Exception;

final class Runner
{
    const DEFAULT_MAX_BUILD_TIME_IN_MIN = 270;
    const PACKAGE_STUCK_TIME_IN_SEC     = 375; // 75 x 5;

    /** @var bool */
    public static $delayed_exit_and_kickoff = false;

    /**
     * Initialize Backup Runner
     *
     * @return void
     */
    public static function init(): void
    {
        if (self::shouldSkipInit()) {
            return;
        }

        // Open logs for the current package
        $package = DupPackage::getNextActive();
        if ($package instanceof AbstractPackage) {
            DupLog::open($package->getNameHash());
        }

        DupLog::trace('Running Backup runner init');

        if (!LockUtil::lockProcess()) {
            return;
        }
        DupLog::trace("Lock acquired. Executing Backup runner init core code");

        self::updatePackageCheckTimestamp();
        self::processPendingCancellations();

        // Process schedules if needed and determine whether to kick off a worker.
        $kickOffWorker = self::processSchedulesIfNeeded();

        LockUtil::unlockProcess();

        self::kickOffWorkerIfNeeded($kickOffWorker);

        // Close logs
        if ($package instanceof AbstractPackage) {
            DupLog::close();
        }

        if (self::$delayed_exit_and_kickoff) {
            self::$delayed_exit_and_kickoff = false;
            exit();
        }
    }

    /**
     * Determines if the initialization should be skipped.
     *
     * @return bool
     */
    private static function shouldSkipInit(): bool
    {
        $global       = GlobalEntity::getInstance();
        $systemGlobal = SystemGlobalEntity::getInstance();

        // Skip processing if clientside kickoff is disabled and the package check was performed recently.
        return (!$global->clientside_kickoff &&
            (time() - $systemGlobal->package_check_ts < Constants::PACKAGE_CHECK_TIME_IN_SEC));
    }

    /**
     * Updates the package check timestamp.
     *
     * @return void
     */
    private static function updatePackageCheckTimestamp(): void
    {
        $systemGlobal                   = SystemGlobalEntity::getInstance();
        $systemGlobal->package_check_ts = time();
        $systemGlobal->save();
    }

    /**
     * Processes any pending cancellations.
     *
     * @return void
     */
    private static function processPendingCancellations(): void
    {
        $pendingCancellations = DupPackage::getPendingCancellations();

        // Cancel any long-running processes.
        self::cancelLongRunning($pendingCancellations);

        if (empty($pendingCancellations)) {
            return;
        }

        foreach ($pendingCancellations as $packageId) {
            self::processPackageCancellation($packageId);
        }

        DupPackage::clearPendingCancellations();
    }

    /**
     * Processes cancellation for a specific package.
     *
     * @param int $packageId Package ID to be cancelled
     *
     * @return void
     */
    private static function processPackageCancellation(int $packageId): void
    {
        DupLog::trace("Processing cancellation for package: {$packageId}");
        $package = DupPackage::getById($packageId);
        if (!$package) {
            return;
        }

        $packageStatus = $package->getStatus();

        if ($package->getStatus() == AbstractPackage::STATUS_STORAGE_PROCESSING) {
            $isDownloadInProgress = $package->isDownloadInProgress();
            $lastUploadInfo       = end($package->upload_infos);
            $lastDownload         = $lastUploadInfo->isDownloadFromRemote();

            $package->cancelAllUploads();
            $package->processStorages();

            if (!$isDownloadInProgress && !$lastDownload) {
                $package->setStatus(AbstractPackage::STATUS_STORAGE_CANCELLED);
            } else {
                DupPackage::deleteDefaultLocalFiles($package->getNameHash(), true);
                $package->setStatus(AbstractPackage::STATUS_COMPLETE);
            }
        } else {
            $package->setStatus(AbstractPackage::STATUS_BUILD_CANCELLED);
        }

        if ($packageStatus < AbstractPackage::STATUS_STORAGE_PROCESSING) {
            $package->postScheduledBuildFailure();
        } else {
            $package->postScheduledStorageFailure();
        }
    }

    /**
     * Processes schedules if the current action is not the process worker.
     *
     * @return bool True if a package is running and a worker should be kicked off.
     */
    private static function processSchedulesIfNeeded(): bool
    {
        $action = ControllersManager::getInstance()->getAction();
        if ($action === false || $action !== 'duplicator_process_worker') {
            self::processSchedules();
            return DupPackage::isPackageRunning();
        }
        return false;
    }

    /**
     * Kicks off the worker process if necessary.
     *
     * @param bool $kickOffWorker Indicates if a worker should be kicked off.
     *
     * @return void
     */
    private static function kickOffWorkerIfNeeded(bool $kickOffWorker): void
    {
        if ($kickOffWorker || self::$delayed_exit_and_kickoff) {
            self::kickOffWorker();
        } elseif (is_admin() && ControllersManager::getInstance()->isDuplicatorPage()) {
            DupLog::trace("************kicking off slug worker");
            self::kickOffWorker(true);
        }
    }

    /**
     * Add javascript for cliean side Kick off
     *
     * @return void
     */
    public static function addKickoffWorkerJavascript(): void
    {
        $global                   = GlobalEntity::getInstance();
        $custom_url               = strtolower($global->custom_ajax_url);
        $CLIENT_CALL_PERIOD_IN_MS = 20000;
        // How often client calls into the service

        if ($global->ajax_protocol == 'custom') {
            if (SnapString::startsWith($custom_url, 'http')) {
                $ajax_url = $custom_url;
            } else {
                // Revert to http standard if they don't have the url correct
                $ajax_url = admin_url('admin-ajax.php', 'http');
                DupLog::trace("Even though custom ajax url configured, incorrect url set so reverting to $ajax_url");
            }
        } else {
            $ajax_url = admin_url('admin-ajax.php', $global->ajax_protocol);
        }

        $gateway = [
            'ajaxurl'                         => $ajax_url,
            'client_call_frequency'           => $CLIENT_CALL_PERIOD_IN_MS,
            'duplicator_process_worker_nonce' => wp_create_nonce('duplicator_process_worker'),
        ];
        wp_register_script('dupli-kick', DUPLICATOR_PLUGIN_URL . 'assets/js/dp-kick.js', ['jquery'], DUPLICATOR_VERSION);
        wp_localize_script('dupli-kick', 'dp_gateway', $gateway);
        DupLog::trace('KICKOFF: Client Side');
        wp_enqueue_script('dupli-kick');
    }

    /**
     * Checks active Backups for being stuck or running too long and adds them for canceling
     *
     * @param int[] $pending_cancellations List of Backup ids to be cancelled
     *
     * @return void
     */
    private static function cancelLongRunning(array &$pending_cancellations): void
    {
        if (!DupPackage::isPackageRunning()) {
            return;
        }

        $active_package = DupPackage::getNextActive();
        if ($active_package === null) {
            DupLog::trace("Active Backup returned null");
            return;
        }

        self::cancelMaxBuildTimeReached($pending_cancellations, $active_package);
        self::cancelMaxTransferTimeReached($pending_cancellations, $active_package);
    }

    /**
     * Checks if the active Backup has been building for too long and adds it for cancelling
     *
     * @param int[]           $pending_cancellations List of Backup ids to be cancelled
     * @param AbstractPackage $active_package        The active Backup
     *
     * @return void
     */
    private static function cancelMaxBuildTimeReached(array &$pending_cancellations, AbstractPackage $active_package): void
    {
        if ($active_package->getStatus() == AbstractPackage::STATUS_STORAGE_PROCESSING) {
            return;
        }

        $global                      = GlobalEntity::getInstance();
        $dGlobal                     = DynamicGlobalEntity::getInstance();
        $system_global               = SystemGlobalEntity::getInstance();
        $buildStarted                = $active_package->timer_start > 0;
        $active_package->timer_start = $buildStarted ? $active_package->timer_start : microtime(true);
        $elapsed_sec                 = $buildStarted ? microtime(true) - $active_package->timer_start : 0;
        $elapsed_minutes             = $elapsed_sec / 60;
        $addedForCancelling          = false;

        // If build has started & we are not uploading yet, we will consider the max build time.
        if ($global->max_package_runtime_in_min > 0 && $elapsed_minutes > $global->max_package_runtime_in_min) {
            if ($active_package->build_progress->current_build_mode != PackageArchive::BUILD_MODE_DUP_ARCHIVE) {
                $system_global->addQuickFix(
                    __('Backup was cancelled because it exceeded Max Build Time.', 'duplicator-pro'),
                    sprintf(
                        __(
                            'Click button to switch to the DupArchive engine. Please see this %1$sFAQ%2$s for other possible solutions.',
                            'duplicator-pro'
                        ),
                        '<a href="' . DUPLICATOR_DUPLICATOR_DOCS_URL . 'how-to-resolve-schedule-build-failures" target="_blank">',
                        '</a>'
                    ),
                    [
                        'global' => [
                            'archive_build_mode' => PackageArchive::BUILD_MODE_DUP_ARCHIVE,
                        ],
                    ]
                );
            } elseif ($global->max_package_runtime_in_min < self::DEFAULT_MAX_BUILD_TIME_IN_MIN) {
                $system_global->addQuickFix(
                    __('Backup was cancelled because it exceeded Max Build Time.', 'duplicator-pro'),
                    sprintf(
                        __(
                            'Click button to increase Max Build Time. Please see this %1$sFAQ%2$s for other possible solutions.',
                            'duplicator-pro'
                        ),
                        '<a href="' . DUPLICATOR_DUPLICATOR_DOCS_URL . 'how-to-resolve-schedule-build-failures" target="_blank">',
                        '</a>'
                    ),
                    [
                        'global' => [
                            'max_package_runtime_in_min' => self::DEFAULT_MAX_BUILD_TIME_IN_MIN,
                        ],
                    ]
                );
            }

            DupLog::infoTrace("Package {$active_package->getId()} has been going for $elapsed_minutes minutes so cancelling. ($elapsed_sec)");
            array_push($pending_cancellations, $active_package->getId());
            $addedForCancelling = true;
        }

        $currentStatus    = $active_package->getStatus();
        $isStuckCandidate = in_array($currentStatus, [
            AbstractPackage::STATUS_AFTER_SCAN,
            AbstractPackage::STATUS_PRE_PROCESS,
        ], true);
        $stateDuration    = $active_package->getStateDuration($currentStatus);
        $isStuck          = $isStuckCandidate && $stateDuration > self::PACKAGE_STUCK_TIME_IN_SEC;
        if ($global->clientside_kickoff == false && $isStuck) {
            DupLog::trace("*** STUCK");
            $showDefault = true;
            if (isset($_SERVER['AUTH_TYPE']) && $_SERVER['AUTH_TYPE'] == 'Basic' && !$dGlobal->getValBool('basic_auth_enabled')) {
                $system_global->addQuickFix(
                    __('Set authentication username and password', 'duplicator-pro'),
                    __('Automatically set basic auth username and password', 'duplicator-pro'),
                    [
                        'special' => ['set_basic_auth' => 1],
                    ]
                );
                $showDefault = false;
            }

            if (SnapURL::isCurrentUrlSSL() && $global->ajax_protocol == 'http') {
                $system_global->addQuickFix(
                    __('Communication to AJAX is blocked.', 'duplicator-pro'),
                    __('Click button to configure plugin to use HTTPS.', 'duplicator-pro'),
                    [
                        'special' => ['stuck_5percent_pending_fix' => 1],
                    ]
                );
            } elseif (!SnapURL::isCurrentUrlSSL() && $global->ajax_protocol == 'https') {
                $system_global->addQuickFix(
                    __('Communication to AJAX is blocked.', 'duplicator-pro'),
                    __('Click button to configure plugin to use HTTP.', 'duplicator-pro'),
                    [
                        'special' => ['stuck_5percent_pending_fix' => 1],
                    ]
                );
            } elseif ($global->ajax_protocol == 'custom') {
                $system_global->addQuickFix(
                    __('Communication to AJAX is blocked.', 'duplicator-pro'),
                    __('Click button to fix the admin-ajax URL setting.', 'duplicator-pro'),
                    [
                        'special' => ['stuck_5percent_pending_fix' => 1],
                    ]
                );
            } elseif ($showDefault) {
                $system_global->addTextFix(
                    __('Communication to AJAX is blocked.', 'duplicator-pro'),
                    sprintf(
                        _x(
                            'See FAQ: %1$sWhy is the Backup build stuck at 5%%?%2$s',
                            '%1$s and %2$s represent open and closing a tags',
                            'duplicator-pro'
                        ),
                        '<a href="' . DUPLICATOR_DUPLICATOR_DOCS_URL . 'how-to-resolve-builds-getting-stuck-at-a-certain-point/" target="_blank">',
                        '</a>'
                    )
                );
            }

            DupLog::infoTrace("Package {$active_package->getId()} has been stuck for $stateDuration seconds so cancelling.");
            array_push($pending_cancellations, $active_package->getId());
            $addedForCancelling = true;
        }

        if ($addedForCancelling) {
            $active_package->buildFail(
                'Backup was cancelled because it exceeded Max Build Time.',
                false
            );
        } else {
            $active_package->save();
        }
    }

    /**
     * Checks if the active Backup has been transferring for too long and adds it for cancelling
     *
     * @param int[]           $pending_cancellations List of Backup ids to be cancelled
     * @param AbstractPackage $active_package        The active Backup
     *
     * @return void
     */
    private static function cancelMaxTransferTimeReached(array &$pending_cancellations, AbstractPackage $active_package): void
    {
        if ($active_package->getStatus() != AbstractPackage::STATUS_STORAGE_PROCESSING) {
            return;
        }

        $latestInfos = $active_package->getLatestUploadInfos();
        if (empty($latestInfos[$active_package->active_storage_id])) {
            return;
        }
        $uploadInfo = $latestInfos[$active_package->active_storage_id];
        if ($uploadInfo->hasCompleted()) {
            return;
        }

        // We consider the Backup is in "uploading state" if it's in STORAGE_PROCESSING status and
        // has more than one upload_infos (i.e. the default storage processing done)
        $global               = GlobalEntity::getInstance();
        $system_global        = SystemGlobalEntity::getInstance();
        $uploadStartedAt      = $uploadInfo->started_timestamp;
        $uploadStartedAt      = $uploadStartedAt > 0 ? $uploadStartedAt : microtime(true);
        $uploadElapsedSec     = microtime(true) - $uploadStartedAt;
        $uploadElapsedMinutes = $uploadElapsedSec / 60;

        // If we are uploading the Backup, we consider the max Backup transfer time.
        if ($global->max_package_transfer_time_in_min < $uploadElapsedMinutes) {
            $system_global->addQuickFix(
                __('Backup transfer was cancelled because it exceeded Max Transfer Time.', 'duplicator-pro'),
                sprintf(
                    __(
                        'Click button to increase Max Transfer Time. Please see this %1$sFAQ%2$s for other possible solutions.',
                        'duplicator-pro'
                    ),
                    '<a href="' . DUPLICATOR_DUPLICATOR_DOCS_URL . 'how-to-resolve-schedule-build-failures" target="_blank">',
                    '</a>'
                ),
                [
                    'global' => [
                        'max_package_transfer_time_in_min' => self::DEFAULT_MAX_BUILD_TIME_IN_MIN,
                    ],
                ]
            );

            DupLog::infoTrace(
                "Package {$active_package->getId()} has been transferring for
                $uploadElapsedMinutes minutes so cancelling. ($uploadElapsedSec)"
            );
            array_push($pending_cancellations, $active_package->getId());
        }
    }

    /**
     * Kick off worker
     *
     * @param bool $run_only_if_client If true then only kick off worker if the request came from the client
     *
     * @return void
     */
    public static function kickOffWorker(bool $run_only_if_client = false): void
    {
        $global  = GlobalEntity::getInstance();
        $dGlobal = DynamicGlobalEntity::getInstance();

        if (!$run_only_if_client || $global->clientside_kickoff) {
            $calling_function_name = SnapUtil::getCallingFunctionName();
            DupLog::trace("Kicking off worker process as requested by $calling_function_name");
            $custom_url = strtolower($global->custom_ajax_url);
            if ($global->ajax_protocol == 'custom') {
                if (SnapString::startsWith($custom_url, 'http')) {
                    $ajax_url = $custom_url;
                } else {
                    // Revert to http standard if they don't have the url correct
                    $ajax_url = admin_url('admin-ajax.php', 'http');
                    DupLog::trace("Even though custom ajax url configured, incorrect url set so reverting to $ajax_url");
                }
            } else {
                $ajax_url = admin_url('admin-ajax.php', $global->ajax_protocol);
            }

            DupLog::trace("Attempting to use ajax url $ajax_url");
            if ($global->clientside_kickoff) {
                add_action('wp_enqueue_scripts', [self::class, 'addKickoffWorkerJavascript']);
                add_action('admin_enqueue_scripts', [self::class, 'addKickoffWorkerJavascript']);
            } else {
                // Server-side kickoff
                $ajax_url = SnapURL::appendQueryValue($ajax_url, 'action', 'duplicator_process_worker');
                $ajax_url = SnapURL::appendQueryValue($ajax_url, 'now', time());
                // $duplicator_process_worker_nonce = wp_create_nonce('duplicator_process_worker');
                //require_once(ABSPATH.'wp-includes/pluggable.php');
                //$ajax_url = wp_nonce_url($ajax_url, 'duplicator_process_worker', 'nonce');

                DupLog::trace('KICKOFF: Server Side');
                if ($dGlobal->getValBool('basic_auth_enabled')) {
                    $authString = 'Basic ' . base64_encode($dGlobal->getValString('basic_auth_user') . ':' . $dGlobal->getValString('basic_auth_password'));
                    $args       = [
                        'blocking' => false,
                        'headers'  => ['Authorization' => $authString],
                    ];
                } else {
                    $args = ['blocking' => false];
                }
                $args['sslverify'] = false;
                wp_remote_get($ajax_url, $args);
            }

            DupLog::trace("after sent kickoff request");
        }
    }

    /**
     * Process schedules by cron
     *
     * @return void
     */
    public static function process(): void
    {
        if (!defined('WP_MAX_MEMORY_LIMIT')) {
            define('WP_MAX_MEMORY_LIMIT', '512M');
        }

        if (SnapUtil::isIniValChangeable('memory_limit')) {
            @ini_set('memory_limit', WP_MAX_MEMORY_LIMIT);
        }

        @set_time_limit(7200);
        SnapUtil::ignoreUserAbort(true);

        if (SnapUtil::isIniValChangeable('pcre.backtrack_limit')) {
            @ini_set('pcre.backtrack_limit', (string) PHP_INT_MAX);
        }

        if (SnapUtil::isIniValChangeable('default_socket_timeout')) {
            @ini_set('default_socket_timeout', '7200');
            // 2 Hours
        }

        $global = GlobalEntity::getInstance();
        if ($global->clientside_kickoff) {
            DupLog::trace("PROCESS: From client");
            session_write_close();
        } else {
            DupLog::trace("PROCESS: From server");
        }

        if (!LockUtil::lockProcess()) {
            // File locked so another cron already running so just skip
            DupLog::trace("Process locked so skipping");
            return;
        }

        // Here we know that $acquired_lock == true
        self::processSchedules();
        $package = DupPackage::getNextActive();

        if ($package != null) {
            self::processPackage($package);
        }

        //$kick_off_worker = (DupPackage::getNextActive() != null);
        $kick_off_worker = DupPackage::isPackageRunning();

        LockUtil::unlockProcess();

        if ($kick_off_worker) {
            self::kickOffWorker();
        }
    }

    /**
     * Process Package
     *
     * @param AbstractPackage $package Package backup
     *
     * @return void
     */
    private static function processPackage(AbstractPackage $package): void
    {
        // Open logs
        DupLog::open($package->getNameHash());

        try {
            StoragesUtil::getDefaultStorage()->initStorageDirectory(true);
            $dup_tests = self::getRequirementsTests();
            if ($dup_tests['Success'] == true) {
                $start_time = time();
                DupLog::trace("PACKAGE {$package->getId()}:PROCESSING. STATUS: {$package->getStatus()}");
                SnapUtil::ignoreUserAbort(true);
                if ($package->getStatus() <= AbstractPackage::STATUS_SCANNING) {
                    // Scan step built into Backup build - used by schedules - NOT manual build where scan is done in web service.
                    DupLog::trace("PACKAGE {$package->getId()}:SCANNING");
                    $fileScanDone = false;
                    if ($package->getStatus() < AbstractPackage::STATUS_SCANNING) {
                        DupLog::trace("PACKAGE {$package->getId()}: SCAN FIRST CHUNK");
                        $fileScanDone = $package->Archive->scanFiles(true);
                        $package->setStatus(AbstractPackage::STATUS_SCANNING);
                    } else {
                        DupLog::trace("PACKAGE {$package->getId()}: CONTINUE SCAN");
                        $fileScanDone = $package->Archive->scanFiles();
                    }

                    if ($fileScanDone) {
                        DupLog::trace("PACKAGE {$package->getId()}: SCAN COMPLETE. NEED TO VALIDATE");
                        $package->setStatus(AbstractPackage::STATUS_SCAN_VALIDATION);
                    }

                    $scan_time = time() - $start_time;
                    DupLog::trace("SCAN CHUNK TIME=$scan_time seconds");
                } elseif ($package->getStatus() <= AbstractPackage::STATUS_SCAN_VALIDATION) {
                    //After scanner runs validate the index file
                    DupLog::trace("PACKAGE {$package->getId()}: SCAN VALIDATION");
                    if ($package->Archive->validateIndexFile()) {
                        DupLog::trace("PACKAGE {$package->getId()}: SCAN VALIDATION PASSED");
                        $package->createScanReport();
                        $package->setStatus(AbstractPackage::STATUS_AFTER_SCAN);
                    } else {
                        DupLog::infoTrace("PACKAGE {$package->getId()}:SCAN VALIDATION FAILED");
                        $package->setStatus(AbstractPackage::STATUS_ERROR);
                    }

                    // Save the package after each scan chunk
                    $package->update();

                    $scan_time = time() - $start_time;
                    DupLog::trace("SCAN VALIDATION TIME=$scan_time seconds");
                } elseif ($package->getStatus() < AbstractPackage::STATUS_COPIEDPACKAGE) {
                    DupLog::trace("PACKAGE {$package->getId()}:BUILDING");
                    $package->runBuild();
                    $end_time   = time();
                    $build_time = $end_time - $start_time;
                    DupLog::trace("BUILD TIME=$build_time seconds");
                } elseif ($package->getStatus() < AbstractPackage::STATUS_COMPLETE) {
                    DupLog::trace("PACKAGE {$package->getId()}:STORAGE PROCESSING");
                    $package->setStatus(AbstractPackage::STATUS_STORAGE_PROCESSING);
                    $package->processStorages();
                    $end_time   = time();
                    $build_time = $end_time - $start_time;
                    DupLog::trace("STORAGE CHUNK PROCESSING TIME=$build_time seconds");
                    if ($package->getStatus() == AbstractPackage::STATUS_COMPLETE) {
                        DupLog::trace("PACKAGE {$package->getId()} COMPLETE");
                    } elseif ($package->getStatus() == AbstractPackage::STATUS_ERROR) {
                        DupLog::trace("PACKAGE {$package->getId()} IN ERROR STATE");
                    }

                    $packageCompleteStatuses = [
                        AbstractPackage::STATUS_COMPLETE,
                        AbstractPackage::STATUS_ERROR,
                    ];
                    if (in_array($package->getStatus(), $packageCompleteStatuses)) {
                        $info  = "\n";
                        $info .= "********************************************************************************\n";
                        $info .= "********************************************************************************\n";
                        $info .= "DUPLICATOR PRO PACKAGE CREATION OR MANUAL STORAGE TRANSFER END: " . @date("Y-m-d H:i:s") . "\n";
                        $info .= "NOTICE: Do NOT post to public sites or forums \n";
                        $info .= "********************************************************************************\n";
                        $info .= "********************************************************************************\n";
                        DupLog::infoTrace($info);
                    }
                }

                SnapUtil::ignoreUserAbort(false);
            } else {
                DupLog::open($package->getNameHash());

                if ($dup_tests['RES']['INSTALL'] == 'Fail') {
                    DupLog::info('Installer files still present on site. Remove using Tools > Stored Data > "Remove Installer Files".');
                }

                DupLog::error(__('Requirements Failed', 'duplicator-pro'), print_r($dup_tests, true));
                DupLog::traceError('Requirements didn\'t pass so can\'t perform backup!');
                $package->postScheduledBuildFailure($dup_tests);
                $package->setStatus(AbstractPackage::STATUS_REQUIREMENTS_FAILED);
            }

            // Free index manager file lock
            $package->Archive->freeIndexManager();
        } catch (Exception $e) {
            DupLog::infoTraceException($e, 'Build failed');
            $message  = "Backup creation failed.\n"
            . " EXCEPTION message: " . $e->getMessage() . "\n";
            $message .= $e->getFile() . ' LINE: ' . $e->getLine() . "\n";
            $message .= $e->getTraceAsString();
            $package->buildFail($message, false);
        }

        // Close logs
        DupLog::close();
    }

    /**
     * Gets the requirements tests
     *
     * @return array<string,mixed>
     */
    private static function getRequirementsTests(): array
    {
        $dup_tests = BuildRequirements::getRequirments();
        if ($dup_tests['Success'] != true) {
            DupLog::traceObject('requirements', $dup_tests);
        }

        return $dup_tests;
    }

    /**
     * Get active schedules
     *
     * @return ScheduleEntity[]
     */
    private static function getActiveSchedules(): array
    {
        return apply_filters('duplicator_get_active_schedules', ScheduleEntity::getActive());
    }

    /**
     * Calculates the earliest schedule run time
     *
     * @return int
     */
    private static function calculateEarliestScheduleRunTime(): int
    {
        $next_run_time = PHP_INT_MAX;

        foreach (self::getActiveSchedules() as $schedule) {
            if ($schedule->next_run_time == -1) {
                $schedule->updateNextRuntime();
            }

            if ($schedule->next_run_time !== -1 && $schedule->next_run_time < $next_run_time) {
                $next_run_time = $schedule->next_run_time;
            }
        }

        if ($next_run_time == PHP_INT_MAX) {
            $next_run_time = -1;
        }

        return $next_run_time;
    }

    /**
     * Start schedule Backup creation
     *
     * @return void
     */
    private static function processSchedules(): void
    {
        // Hack fix - observed issue on a machine where schedule process bombs
        $next_run_time = self::calculateEarliestScheduleRunTime();
        if ($next_run_time != -1 && ($next_run_time <= time())) {
            $schedules = self::getActiveSchedules();
            foreach ($schedules as $schedule) {
                $schedule->process();
            }
        }
    }
}
