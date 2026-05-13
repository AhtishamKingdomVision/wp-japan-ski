<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Ajax;

use Duplicator\Package\DupPackage;
use Duplicator\Package\Create\Scan\ScanToolValidator;
use Duplicator\Addons\ProBase\License\License;
use Duplicator\Core\CapMng;
use Duplicator\Libs\Snap\SnapURL;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Package\Archive\PackageArchive;
use Duplicator\Package\PackageUtils;
use Duplicator\Utils\Support\SupportToolkit;
use Exception;
use Duplicator\Package\Storage\Status\StatusChecker;
use Duplicator\Utils\Logging\ErrorHandler;

class ServicesTools extends AbstractAjaxService
{
    /** @var int Maximum number of remote storage backup checks before stopping */
    const MAX_AJAX_BACKUP_REMOTE_STORAGE_CHECKS = 1000;

    /**
     * Init ajax calls
     *
     * @return void
     */
    public function init(): void
    {
        if (!License::can(License::CAPABILITY_BASE_ADVANCED)) {
            return;
        }
        $this->addAjaxCall('wp_ajax_duplicator_tool_scan_validator', 'runScanValidator');
        $this->addAjaxCall('wp_ajax_duplicator_download_support_toolkit', 'downloadSupportToolkit');
        $this->addAjaxCall('wp_ajax_duplicator_check_remote_backups', 'checkRemoteBackups');
    }

    /**
     * Calls the ScanValidator and returns display JSON result
     *
     * @return void
     */
    public function runScanValidator(): void
    {
        ErrorHandler::init();
        check_ajax_referer('duplicator_tool_scan_validator', 'nonce');

        // Let's setup execution time on proper way (multiserver supported)
        try {
            if (function_exists('set_time_limit')) {
                set_time_limit(0); // unlimited
            } else {
                if (function_exists('ini_set') && SnapUtil::isIniValChangeable('max_execution_time')) {
                    ini_set('max_execution_time', '0'); // unlimited
                }
            }

            // there is error inside PHP because of PHP versions and server setup,
            // let's try to made small hack and set some "normal" value if is possible
        } catch (Exception $ex) {
            if (function_exists('set_time_limit')) {
                @set_time_limit(HOUR_IN_SECONDS);
            } else {
                if (function_exists('ini_set') && SnapUtil::isIniValChangeable('max_execution_time')) {
                    @ini_set('max_execution_time', (string) HOUR_IN_SECONDS);
                }
            }
        }

        //scan-recursive
        $isValid   = true;
        $inputData = filter_input_array(INPUT_POST, [
            'scan-recursive' => [
                'filter' => FILTER_VALIDATE_BOOLEAN,
                'flags'  => FILTER_NULL_ON_FAILURE,
            ],
        ]);

        if (is_null($inputData['scan-recursive'])) {
            $isValid = false;
        }

        $result = [
            'success'  => false,
            'message'  => '',
            'scanData' => null,
        ];

        try {
            if (!$isValid) {
                throw new Exception(__("Invalid Request.", 'duplicator-pro'));
            }

            $scanner            = new ScanToolValidator();
            $scanner->recursion = $inputData['scan-recursive'];
            $result['scanData'] = $scanner->run(PackageArchive::getScanPaths());
            $result['success']  = ($result['scanData']->fileCount > 0);
        } catch (Exception $exc) {
            $result['success'] = false;
            $result['message'] = $exc->getMessage();
        }

        wp_send_json($result);
    }

    /**
     * Function to download diagnostic data
     *
     * @return never
     */
    public function downloadSupportToolkit(): void
    {
        AjaxWrapper::fileDownload(
            [
                self::class,
                'downloadSupportToolkitCallback',
            ],
            'duplicator_download_support_toolkit',
            SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'nonce'),
            CapMng::CAP_BASIC
        );
    }

    /**
     * Function to create diagnostic data
     *
     * @return array{path:string,name:string}
     */
    public static function downloadSupportToolkitCallback(): array
    {
        $domain = SnapURL::wwwRemove(SnapURL::parseUrl(network_home_url(), PHP_URL_HOST));

        return [
            'path' => SupportToolkit::getToolkit(),
            'name' => SupportToolkit::SUPPORT_TOOLKIT_PREFIX .
                substr(sanitize_file_name($domain), 0, 12) . '_' .
                date(DupPackage::PACKAGE_HASH_DATE_FORMAT) . '.zip',
        ];
    }

    /**
     * Check remote backups status
     *
     * @return array{success: bool, message: string, processed: int, totalProcessed: int}
     */
    public function checkRemoteBackups(): array
    {
        AjaxWrapper::json(
            [
                self::class,
                'checkRemoteBackupsCallback',
            ],
            'duplicator_check_remote_backups',
            SnapUtil::sanitizeTextInput(INPUT_POST, 'nonce'),
            CapMng::CAP_BASIC
        );
    }

    /**
     * Check remote backups status
     *
     * @return array{success:bool,message:string,processed:int,totalProcessed:int}
     */
    public static function checkRemoteBackupsCallback(): array
    {
        $result = [
            'success'        => false,
            'message'        => '',
            'processed'      => -1,
            'totalProcessed' => 0,
        ];

        try {
            $totalProcessed = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'totalProcessed', 0);
            $processed      = StatusChecker::processNextChunk(StatusChecker::MIN_INTERVAL_MANUAL);

            if ($processed >= 0) {
                $totalProcessed += $processed;
            }

            $result['success']        = ($processed >= 0);
            $result['message']        = sprintf(
                _n(
                    'Successfully checked %d backup.',
                    'Successfully checked %d backups.',
                    $totalProcessed,
                    'duplicator-pro'
                ),
                $totalProcessed
            );
            $result['processed']      = $processed;
            $result['totalProcessed'] = $totalProcessed;
        } catch (Exception $e) {
            $result['success'] = false;
            $result['message'] = $e->getMessage();
        }

        return $result;
    }
}
