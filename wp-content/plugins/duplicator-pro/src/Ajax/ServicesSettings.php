<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Ajax;

use Duplicator\Utils\Logging\DupLog;
use Duplicator\Models\GlobalEntity;
use Duplicator\Models\ActivityLog\LogEventSettingsChange;
use Duplicator\Addons\ProBase\License\License;
use Duplicator\Core\CapMng;
use Duplicator\Core\Constants;
use Duplicator\Core\MigrationMng;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapNet;
use Duplicator\Libs\Snap\SnapString;
use Duplicator\Libs\Snap\SnapURL;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Models\DynamicGlobalEntity;
use Duplicator\Models\SystemGlobalEntity;
use Duplicator\Utils\Logging\ErrorHandler;
use Duplicator\Utils\Logging\TraceLogMng;
use Duplicator\Utils\Settings\MigrateSettings;
use Duplicator\Utils\ZipArchiveExtended;
use Exception;

class ServicesSettings extends AbstractAjaxService
{
    const USERS_PAGE_SIZE =  10;
    /**
     * Init ajax calls
     *
     * @return void
     */
    public function init(): void
    {
        $this->addAjaxCall("wp_ajax_duplicator_settings_cap_users_list", "capUsersList");
        $this->addAjaxCall('wp_ajax_duplicator_get_trace_log', 'getTraceLog');
        $this->addAjaxCall('wp_ajax_duplicator_delete_trace_log', 'deleteTraceLog');
        $this->addAjaxCall('wp_ajax_duplicator_export_settings', 'exportSettings');
        $this->addAjaxCall('wp_ajax_duplicator_quick_fix', 'quickFix');
    }

    /**
     * Return user list for capabilites select
     *
     * @return mixed[]
     */
    public static function capUsersListCallback(): array
    {
        $searchStr = SnapUtil::sanitizeTextInput(INPUT_POST, 'search');
        $page      = SnapUtil::sanitizeIntInput(INPUT_POST, 'page', 1);
        $result    = [
            'results'    => [],
            'pagination' => ['more' => false],
        ];
        if ($page == 1) {
            foreach (CapMng::getSelectableRoles() as $role => $roleName) {
                if (stripos($role, $searchStr) !== false) {
                    $result['results'][] = [
                        'id'   => $role,
                        'text' => $roleName,
                    ];
                }
            }
        }

        if (License::can(License::CAPABILITY_CAPABILITIES_MNG_PLUS)) {
            $args = [
                'search'         => '*' . $searchStr . '*',
                'search_columns' => [
                    'user_login',
                    'user_email',
                ],
                'number'         => self::USERS_PAGE_SIZE,
                'paged'          => $page,
            ];
            if (is_multisite()) {
                $args['blog_id'] = 0; // all users
                $superAdmins     = get_super_admins();
                if (count($superAdmins) > 0) {
                    $args['login__in'] = $superAdmins;
                }
            }
            $users = get_users($args);
            foreach ($users as $user) {
                $result['results'][] = [
                    'id'   => $user->ID,
                    'text' => $user->user_email,
                ];
            }

            // Check if there are more users
            $args['paged']                = $page + 1;
            $users                        = get_users($args);
            $result['pagination']['more'] = count($users) > 0;
        }

        return $result;
    }

    /**
     * Import upload action
     *
     * @return void
     */
    public function capUsersList(): void
    {
        AjaxWrapper::json(
            [
                self::class,
                'capUsersListCallback',
            ],
            'duplicator_settings_cap_users_list',
            SnapUtil::sanitizeTextInput(INPUT_POST, 'nonce'),
            CapMng::CAP_SETTINGS
        );
    }

    /**
     * Hook ajax wp_ajax_duplicator_get_trace_log
     *
     * @return never
     */
    public function getTraceLog(): void
    {
        /**
         * don't init ErrorHandler::init_error_handler() in get trace
         */
        check_ajax_referer('duplicator_get_trace_log', 'nonce');
        DupLog::trace("enter");

        try {
            CapMng::can(CapMng::CAP_CREATE);

            $zip_path = DUPLICATOR_SSDIR_PATH . "/" . Constants::ZIPPED_LOG_FILENAME;

            if (file_exists($zip_path)) {
                SnapIO::unlink($zip_path);
            }
            $zipArchive = new ZipArchiveExtended($zip_path);

            if ($zipArchive->open() == false) {
                throw new Exception('Can\'t open ZIP archive: ' . $zip_path);
            }

            foreach (TraceLogMng::getInstance()->getTraceFiles() as $traceFile) {
                if ($zipArchive->addFile($traceFile, basename($traceFile)) == false) {
                    throw new Exception('Can\'t add ZIP file ' . basename($traceFile) . ' size: ' . filesize($traceFile));
                }
            }

            if ($zipArchive->close() === false) {
                throw new Exception('Failed to close ZIP archive: ' . $zip_path);
            }

            if (($fp = fopen($zip_path, 'rb')) === false) {
                throw new Exception('Can\'t open ZIP archive: ' . $zip_path);
            }

            $zip_filename = basename($zip_path);

            header("Pragma: public");
            header("Expires: 0");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Cache-Control: private", false);
            header("Content-Transfer-Encoding: binary");
            header("Content-Type: application/octet-stream");
            header("Content-Disposition: attachment; filename=\"$zip_filename\";");

            // required or large files wont work
            if (ob_get_length()) {
                ob_end_clean();
            }

            DupLog::trace("streaming $zip_path");
            if (function_exists('fpassthru')) {
                fpassthru($fp);
            }
            fclose($fp);
            unlink($zip_path);
        } catch (Exception $e) {
            header("Content-Type: text/plain");
            header("Content-Disposition: attachment; filename=\"error.txt\";");
            $message = 'Create Log Zip error message: ' . $e->getMessage();
            DupLog::trace($message);
            echo esc_html($message);
        }
        die();
    }

    /**
     * Hook ajax wp_ajax_duplicator_delete_trace_log
     *
     * @return never
     */
    public function deleteTraceLog(): void
    {
        /**
         * don't init ErrorHandler::init_error_handler() in get trace
         */
        check_ajax_referer('duplicator_delete_trace_log', 'nonce');
        CapMng::can(CapMng::CAP_CREATE);

        $res = DupLog::deleteTraceLog();
        if ($res) {
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }

    /**
     * Hook ajax wp_ajax_duplicator_export_settings
     *
     * @return never
     */
    public function exportSettings(): void
    {
        ErrorHandler::init();
        check_ajax_referer('duplicator_export_settings', 'nonce');

        try {
            DupLog::trace("Export settings start");
            CapMng::can(CapMng::CAP_SETTINGS);

            $message = '';

            if (($filePath = MigrateSettings::export($message)) === false) {
                throw new Exception($message);
            }

            // Log the settings export action
            LogEventSettingsChange::create(LogEventSettingsChange::SUB_TYPE_IMPORT_EXPORT, [
                'changes'     => [], // Export doesn't change settings, just logs the action
                'action_type' => 'settings_export',
            ]);

            SnapNet::serveFileForDownload($filePath, basename($filePath), DUPLICATOR_BUFFER_DOWNLOAD_SIZE);
        } catch (Exception $ex) {
            // RSR TODO: set the error message to this $this->message = 'Error processing with export:' .  $e->getMessage();
            header("Content-Type: text/plain");
            header("Content-Disposition: attachment; filename=\"error.txt\";");
            $message = $ex->getMessage();
            DupLog::trace($message);
            echo esc_html($message);
        }
        die();
    }

    /**
     * Set default quick fix values automaticaly to help user
     *
     * @return void
     */
    public function quickFix(): void
    {
        AjaxWrapper::json(
            [
                self::class,
                'quickFixCallback',
            ],
            'duplicator_quick_fix',
            SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'nonce'),
            CapMng::CAP_CREATE
        );
    }

    /**
     * Set default quick fix values automaticaly to help user
     *
     * @return array<string, mixed>
     */
    public static function quickFixCallback(): array
    {
        $json      = [
            'success' => false,
            'message' => '',
        ];
        $inputData = filter_input_array(INPUT_POST, [
            'id'    => [
                'filter'  => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => ['default' => false],
            ],
            'setup' => [
                'filter'  => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags'   => FILTER_REQUIRE_ARRAY,
                'options' => ['default' => false],
            ],
        ]);

        /** @var array<string,mixed>|false */
        $setup = $inputData['setup'];
        $id    = $inputData['id'];

        if (!$id || empty($setup)) {
            throw new Exception(__("Invalid request.", 'duplicator-pro'));
        }

        $data      = [];
        $isSpecial = isset($setup['special']) && is_array($setup['special']) && count($setup['special']) > 0;

        /* ****************
         *  general setup
         * **************** */
        if (isset($setup['global']) && is_array($setup['global'])) {
            $global = GlobalEntity::getinstance();

            foreach ($setup['global'] as $object => $value) {
                $value = SnapString::getTypedVal($value);
                if (isset($global->$object)) {
                    // get current setup
                    $current = $global->$object;
                    // if setup is not the same - fix this
                    if ($current !== $value) {
                        // set new value
                        $global->$object = $value;
                        // check value
                        $data[$object] = $global->$object;
                    }
                }
            }
            $global->save();
        }

        /* ****************
         *  SPECIAL SETUP
         * **************** */
        if ($isSpecial) {
            $special              = $setup['special'];
            $stuck5percent        = isset($special['stuck_5percent_pending_fix']) && $special['stuck_5percent_pending_fix'] == 1;
            $basicAuth            = isset($special['set_basic_auth']) && $special['set_basic_auth'] == 1;
            $removeInstallerFiles = isset($special['remove_installer_files']) && $special['remove_installer_files'] == 1;
            /**
             * SPECIAL FIX: Backup build stuck at 5% or Pending?
             * */
            if ($stuck5percent) {
                $data = array_merge($data, self::quickFixStuck5Percent());
            }

            /**
             * SPECIAL FIX: Set basic auth username & password
             * */
            if ($basicAuth) {
                $data = array_merge($data, self::quickFixBasicAuth());
            }

            /**
             * SPECIAL FIX: Remove installer files
             * */
            if ($removeInstallerFiles) {
                $data = array_merge($data, self::quickFixRemoveInstallerFiles());
            }
        }

        // Save new property
        $find = count($data);
        if ($find > 0) {
            $system_global = SystemGlobalEntity::getInstance();
            if (strlen($id) > 0) {
                $system_global->removeFixById($id);
                $json['id'] = $id;
            }

            $json['success']           = true;
            $json['setup']             = $data;
            $json['fixed']             = $find;
            $json['recommended_fixes'] = count($system_global->recommended_fixes);

            // Log settings change for quick fix
            LogEventSettingsChange::create(LogEventSettingsChange::SUB_TYPE_QUICK_FIX, [
                'changes'     => [], // Quick fixes don't have specific setting changes to track
                'action_type' => 'quick_fix_apply',
            ]);
        }

        return $json;
    }

    /**
     * Quick fix for removing installer files
     *
     * @return array{removed_installer_files:bool} $data
     */
    private static function quickFixRemoveInstallerFiles(): array
    {
        $data        = [];
        $fileRemoved = MigrationMng::cleanMigrationFiles();
        $removeError = false;
        if (count($fileRemoved) > 0) {
            $data['removed_installer_files'] = true;
        } else {
            throw new Exception(esc_html__("Unable to remove installer files.", 'duplicator-pro'));
        }
        return $data;
    }

    /**
     * Quick fix for stuck at 5% or pending
     *
     * @return array<string, mixed> $data
     */
    private static function quickFixStuck5Percent(): array
    {
        $global = GlobalEntity::getInstance();

        $data    = [];
        $kickoff = true;
        $custom  = false;

        if ($global->ajax_protocol === 'custom') {
            $custom = true;
        }

        // Do things if SSL is active
        if (SnapURL::isCurrentUrlSSL()) {
            if ($custom) {
                // Set default admin ajax
                $custom_ajax_url = admin_url('admin-ajax.php', 'https');
                if ($global->custom_ajax_url != $custom_ajax_url) {
                    $global->custom_ajax_url = $custom_ajax_url;
                    $data['custom_ajax_url'] = $global->custom_ajax_url;
                    $kickoff                 = false;
                }
            } else {
                // Set HTTPS protocol
                if ($global->ajax_protocol === 'http') {
                    $global->ajax_protocol = 'https';
                    $data['ajax_protocol'] = $global->ajax_protocol;
                    $kickoff               = false;
                }
            }
        } else {
            // SSL is OFF and we must handle that
            if ($custom) {
                // Set default admin ajax
                $custom_ajax_url = admin_url('admin-ajax.php', 'http');
                if ($global->custom_ajax_url != $custom_ajax_url) {
                    $global->custom_ajax_url = $custom_ajax_url;
                    $data['custom_ajax_url'] = $global->custom_ajax_url;
                    $kickoff                 = false;
                }
            } else {
                // Set HTTP protocol
                if ($global->ajax_protocol === 'https') {
                    $global->ajax_protocol = 'http';
                    $data['ajax_protocol'] = $global->ajax_protocol;
                    $kickoff               = false;
                }
            }
        }

        // Set KickOff true if all setups are gone
        if ($kickoff) {
            if ($global->clientside_kickoff !== true) {
                $global->clientside_kickoff = true;
                $data['clientside_kickoff'] = $global->clientside_kickoff;
            }
        }

        $global->save();
        return $data;
    }

    /**
     * Quick fix for basic auth
     *
     * @return array{basic_auth_enabled:bool,basic_auth_user:string,basic_auth_password:string}
     */
    private static function quickFixBasicAuth(): array
    {
        $dGlobal  = DynamicGlobalEntity::getInstance();
        $username = SnapUtil::sanitizeTextInput(INPUT_SERVER, 'PHP_AUTH_USER', '');
        $password = SnapUtil::sanitizeTextInput(INPUT_SERVER, 'PHP_AUTH_PW', '');
        if ($username == '' || $password == '') {
            throw new Exception(esc_html__("Username or password were not set.", 'duplicator-pro'));
        }

        $data = [];
        $dGlobal->setValBool('basic_auth_enabled', true);
        $data['basic_auth_enabled'] = true;

        $dGlobal->setValString('basic_auth_user', $username);
        $data['basic_auth_user'] = $username;

        $dGlobal->setValString('basic_auth_password', $password);
        $data['basic_auth_password'] = "**Secure Info**";

        $dGlobal->save();

        return $data;
    }
}
