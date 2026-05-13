<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Ajax;

use Duplicator\Utils\Logging\DupLog;
use Duplicator\Package\DupPackage;
use Duplicator\Ajax\AbstractAjaxService;
use Duplicator\Ajax\AjaxWrapper;
use Duplicator\Controllers\RecoveryController;
use Duplicator\Controllers\SettingsPageController;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Controllers\ToolsPageController;
use Duplicator\Core\CapMng;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Libs\Snap\SnapWP;
use Duplicator\Package\AbstractPackage;
use Duplicator\Package\Recovery\BackupPackage;
use Duplicator\Package\Recovery\RecoveryPackage;
use Duplicator\Libs\WpUtils\WpArchiveUtils;
use Duplicator\Package\PackageUtils;
use Exception;

class ServicesRecovery extends AbstractAjaxService
{
    /**
     * Init ajax calls
     *
     * @return void
     */
    public function init(): void
    {
        $this->addAjaxCall('wp_ajax_duplicator_get_recovery_widget', 'getWidget');
        $this->addAjaxCall('wp_ajax_duplicator_set_recovery', 'setRecovery');
        $this->addAjaxCall('wp_ajax_duplicator_reset_recovery', 'resetRecovery');
        $this->addAjaxCall('wp_ajax_duplicator_backup_redirect', 'restoreBackupRedirect');
        $this->addAjaxCall('wp_ajax_duplicator_disaster_launcher_download', 'launcherDownload');
        $this->addAjaxCall('wp_ajax_duplicator_get_recovery_box_content', 'recoveryBoxContent');
        $this->addAjaxCall('wp_ajax_duplicator_restore_backup_prepare', 'restoreBackupPrepare');
    }

    /**
     * Get recovery widget detail elements
     *
     * @param string $fromPageTab from page/tab unique id
     *
     * @return bool[]
     */
    protected static function getRecoveryDetailsOptions($fromPageTab)
    {
        if ($fromPageTab == ControllersManager::getPageUniqueId(ControllersManager::TOOLS_SUBMENU_SLUG, ToolsPageController::L2_SLUG_RECOVERY)) {
            $detailsOptions = [
                'selector'   => true,
                'copyLink'   => true,
                'copyButton' => true,
                'launch'     => true,
                'download'   => true,
                'info'       => true,
            ];
        } elseif ($fromPageTab == ControllersManager::getPageUniqueId(ControllersManager::IMPORT_SUBMENU_SLUG)) {
            $detailsOptions = [
                'selector'   => true,
                'launch'     => false,
                'download'   => true,
                'copyLink'   => true,
                'copyButton' => true,
                'info'       => true,
            ];
        } else {
            $detailsOptions = [];
        }

        return $detailsOptions;
    }

    /**
     * Set recovery callback
     *
     * @return array<string, mixed>
     */
    public static function setRecoveryCallback(): array
    {
        $recPackageId = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'recovery_package', -1);
        DupLog::trace("SET RECOVERY PACKAGE ID {$recPackageId}");
        if ($recPackageId !== RecoveryPackage::getRecoverPackageId()) {
            DupLog::trace("RESET OLD RECORY PACKAGE ID " . RecoveryPackage::getRecoverPackageId());
            RecoveryPackage::removeRecoveryFolder();

            $errorMessage = '';
            if (!RecoveryPackage::setRecoveablePackage($recPackageId, $errorMessage)) {
                $urlImport = ControllersManager::getMenuLink(ControllersManager::SETTINGS_SUBMENU_SLUG, SettingsPageController::L2_SLUG_IMPORT);

                $msg  = sprintf(__("Error: <b>%s</b>", 'duplicator-pro'), $errorMessage) . '<br><br>';
                $msg .= __("The old Recovery Point was removed but this Backup can’t be set as the Recovery Point.", 'duplicator-pro') . '<br>';
                $msg .= __("Possible solutions:", 'duplicator-pro') . '<br>';
                $msg .= sprintf(
                    _x(
                        '- In some hosting the execution of PHP scripts are blocked in the wp-content folder, %1$s[try set a custom recovery path]%2$s',
                        '%1$s and %2$s represents the opening and closing HTML tags for an anchor or link',
                        'duplicator-pro'
                    ),
                    '<a href="' . esc_url($urlImport) . '" target="_blank">',
                    '</a>'
                ) . '<br>';
                $msg .= __(
                    "- you may still be able to to download the Backup manually and perform an import or a classic backup installation.
                    If you wish to install the Backup on the site where it was create the restore backup mode should be activated.",
                    'duplicator-pro'
                );
                throw new Exception($msg);
            }
            DupLog::trace("RECOVER PACKAGE SET");
        }

        $recoverPackage = RecoveryPackage::getRecoverPackage();
        DupLog::trace("RECOVER PACKAGE READED");
        if (!$recoverPackage instanceof RecoveryPackage) {
            throw new Exception(esc_html__('Can\'t get recover Backup', 'duplicator-pro'));
        }
        $fromPageTab    = SnapUtil::sanitizeDefaultInput(INPUT_POST, 'fromPageTab', false);
        $detailsOptions = self::getRecoveryDetailsOptions($fromPageTab);
        DupLog::trace("RECOVER PACKAGE DETAILS OPTIONS READED");

        $subtitle = __('Copy the Link and keep it in case of need or download Disaster Recovery Launcher.', 'duplicator-pro');
        return [
            'id'             => $recoverPackage->getPackageId(),
            'name'           => $recoverPackage->getPackageName(),
            'recoveryLink'   => $recoverPackage->getInstallLink(),
            'adminMessage'   => RecoveryController::renderRecoveryWidged([
                'selector'   => false,
                'subtitle'   => $subtitle,
                'copyLink'   => false,
                'copyButton' => true,
                'launch'     => false,
                'download'   => true,
                'info'       => true,
            ], false),
            'packageDetails' => RecoveryController::renderRecoveryWidged($detailsOptions, false),
        ];
    }

    /**
     * Set recovery action
     *
     * @return void
     */
    public function setRecovery(): void
    {
        AjaxWrapper::json(
            [
                self::class,
                'setRecoveryCallback',
            ],
            'duplicator_set_recovery',
            SnapUtil::sanitizeTextInput(INPUT_POST, 'nonce', ''),
            CapMng::CAP_BACKUP_RESTORE
        );
    }

    /**
     * Get widget callback
     *
     * @return string[]
     */
    public static function getWidgetCallback(): array
    {
        $fromPageTab    = SnapUtil::sanitizeDefaultInput(INPUT_POST, 'fromPageTab', false);
        $detailsOptions = self::getRecoveryDetailsOptions($fromPageTab);

        return [
            'widget' => RecoveryController::renderRecoveryWidged($detailsOptions, false),
        ];
    }

    /**
     * Get widget action
     *
     * @return void
     */
    public function getWidget(): void
    {
        AjaxWrapper::json(
            [
                self::class,
                'getWidgetCallback',
            ],
            'duplicator_get_recovery_widget',
            SnapUtil::sanitizeTextInput(INPUT_POST, 'nonce', ''),
            CapMng::CAP_BACKUP_RESTORE
        );
    }

    /**
     * Reset recovery callback
     *
     * @return string[]
     */
    public static function resetRecoveryCallback(): array
    {
        if (RecoveryController::actionResetRecoveryPoint() === false) {
            throw new Exception(RecoveryController::getErrorMessage());
        }

        $fromPageTab    = SnapUtil::sanitizeDefaultInput(INPUT_POST, 'fromPageTab', false);
        $detailsOptions = self::getRecoveryDetailsOptions($fromPageTab);

        return [
            'adminMessage'   => RecoveryController::renderRecoveryWidged([], false),
            'packageDetails' => RecoveryController::renderRecoveryWidged($detailsOptions, false),
        ];
    }

    /**
     * Reset recovery action
     *
     * @return void
     */
    public function resetRecovery(): void
    {
        AjaxWrapper::json(
            [
                self::class,
                'resetRecoveryCallback',
            ],
            'duplicator_reset_recovery',
            SnapUtil::sanitizeTextInput(INPUT_POST, 'nonce', ''),
            CapMng::CAP_BACKUP_RESTORE
        );
    }

    /**
     * Prepare restore backup and redirect to the installer URL
     *
     * @return array<string,scalar>
     */
    public static function restoreBackupRedirectCallback(): array
    {
        $result = [
            'success'      => false,
            'message'      => '',
            'redirect_url' => '',
        ];

        try {
            $packageId = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'packageId', 0);

            if (($package = DupPackage::getById($packageId)) === false) {
                throw new Exception(__('Backup is invalid', 'duplicator-pro'));
            }

            if (!$package->haveLocalStorage()) {
                throw new Exception(__('Backup isn\'t local', 'duplicator-pro'));
            }

            $arachivePath = $package->getLocalPackageFilePath(AbstractPackage::FILE_TYPE_ARCHIVE);
            if (!file_exists($arachivePath)) {
                throw new Exception(__('Backup archive file doesn\'t exist', 'duplicator-pro'));
            }

            $restore = new BackupPackage($arachivePath, $package);

            $result['redirect_url'] = $restore->prepareToInstall();
            $result['success']      = true;
        } catch (Exception $ex) {
            $result['success'] = false;
            $result['message'] = $ex->getMessage();
            DupLog::traceError($ex->getMessage());
        }

        return $result;
    }

    /**
     * Reset recovery action
     *
     * @return void
     */
    public function restoreBackupRedirect(): void
    {
        AjaxWrapper::json(
            [
                self::class,
                'restoreBackupRedirectCallback',
            ],
            'duplicator_backup_redirect',
            SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'nonce', ''),
            CapMng::CAP_BACKUP_RESTORE
        );
    }


    /**
     * Launcher download callback
     *
     * @return array<string,scalar>
     */
    public static function launcherDownloadCallback(): array
    {
        $result = [
            'success'     => false,
            'message'     => '',
            'fileContent' => '',
            'fileName'    => '',
        ];

        try {
            if (($recoverPackage = RecoveryPackage::getRecoverPackage()) == false) {
                throw new Exception(__('Can\'t get recover Backup', 'duplicator-pro'));
            }

            $result['fileContent'] = TplMng::getInstance()->render(
                'parts/recovery/launcher_content',
                ['recoverPackage' => $recoverPackage],
                false
            );

            $result['fileName'] = $recoverPackage->getLauncherFileName();
            $result['success']  = true;
        } catch (Exception $ex) {
            $result['success'] = false;
            $result['message'] = $ex->getMessage();
            DupLog::traceError($ex->getMessage());
        }

        return $result;
    }

    /**
     * Reset recovery action
     *
     * @return void
     */
    public function launcherDownload(): void
    {
        AjaxWrapper::json(
            [
                self::class,
                'launcherDownloadCallback',
            ],
            'duplicator_disaster_launcher_download',
            SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'nonce', ''),
            CapMng::CAP_BACKUP_RESTORE
        );
    }


    /**
     * Prepare restore backup and redirect to the installer URL
     *
     * @return array<string,scalar>
     */
    public static function recoveryBoxContentCallback(): array
    {
        $result = [
            'success'       => false,
            'message'       => '',
            'content'       => '',
            'isRecoverable' => false,
        ];

        try {
            $packageId = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'packageId', 0);

            if (($package = DupPackage::getById($packageId)) === false) {
                throw new Exception(__('Backup is invalid', 'duplicator-pro'));
            }

            $result['content']       = TplMng::getInstance()->render(
                'admin_pages/packages/recovery_info/row_recovery_box',
                ['package' => $package],
                false
            );
            $result['isRecoverable'] = RecoveryPackage::isPackageIdRecoverable($package->getId());
            $result['success']       = true;
        } catch (Exception $ex) {
            $result['success'] = false;
            $result['message'] = $ex->getMessage();
            DupLog::traceError($ex->getMessage());
        }

        return $result;
    }

    /**
     * Reset recovery action
     *
     * @return void
     */
    public function recoveryBoxContent(): void
    {
        AjaxWrapper::json(
            [
                self::class,
                'recoveryBoxContentCallback',
            ],
            'duplicator_get_recovery_box_content',
            SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'nonce', ''),
            CapMng::CAP_BACKUP_RESTORE
        );
    }

    /**
     * Restore backup prepare callback
     *
     * @return string
     */
    public static function restoreBackupPrepareCallback(): string
    {
        $packageId = filter_input(INPUT_POST, 'packageId', FILTER_VALIDATE_INT);
        if (!$packageId) {
            throw new Exception('Invalid Backup ID in request.');
        }
        $result = [];

        if (($package = DupPackage::getById($packageId)) === false) {
            throw new Exception(esc_html__('Invalid Backup ID', 'duplicator-pro'));
        }
        $updDirs = wp_upload_dir();

        $result = DUPLICATOR_SSDIR_URL . '/' . $package->Installer->getInstallerName() . '?dup_folder=dupinst_' . $package->getHash();

        $installerParams = [
            'inst_mode'              => ['value' => 2], // mode restore backup
            'url_old'                => ['formStatus' => "st_skip"],
            'url_new'                => [
                'value'      => WpArchiveUtils::getOriginalUrls('home'),
                'formStatus' => "st_infoonly",
            ],
            'path_old'               => ['formStatus' => "st_skip"],
            'path_new'               => [
                'value'      => SnapWP::getHomePath(true),
                'formStatus' => "st_infoonly",
            ],
            'dbaction'               => [
                'value'      => 'empty',
                'formStatus' => "st_infoonly",
            ],
            'dbhost'                 => [
                'value'      => DB_HOST,
                'formStatus' => "st_infoonly",
            ],
            'dbname'                 => [
                'value'      => DB_NAME,
                'formStatus' => "st_infoonly",
            ],
            'dbuser'                 => [
                'value'      => DB_USER,
                'formStatus' => "st_infoonly",
            ],
            'dbpass'                 => [
                'value'      => DB_PASSWORD,
                'formStatus' => "st_infoonly",
            ],
            'dbtest_ok'              => ['value' => true],
            'siteurl_old'            => ['formStatus' => "st_skip"],
            'siteurl'                => [
                'value'      => 'site_url',
                'formStatus' => "st_skip",
            ],
            'path_cont_old'          => ['formStatus' => "st_skip"],
            'path_cont_new'          => [
                'value'      => WP_CONTENT_DIR,
                'formStatus' => "st_skip",
            ],
            'path_upl_old'           => ['formStatus' => "st_skip"],
            'path_upl_new'           => [
                'value'      => $updDirs['basedir'],
                'formStatus' => "st_skip",
            ],
            'url_cont_old'           => ['formStatus' => "st_skip"],
            'url_cont_new'           => [
                'value'      => content_url(),
                'formStatus' => "st_skip",
            ],
            'url_upl_old'            => ['formStatus' => "st_skip"],
            'url_upl_new'            => [
                'value'      => $updDirs['baseurl'],
                'formStatus' => "st_skip",
            ],
            'exe_safe_mode'          => ['formStatus' => "st_skip"],
            'remove-redundant'       => ['formStatus' => "st_skip"],
            'blogname'               => ['formStatus' => "st_infoonly"],
            'replace_mode'           => ['formStatus' => "st_skip"],
            'empty_schedule_storage' => [
                'value'      => false,
                'formStatus' => "st_skip",
            ],
            'wp_config'              => [
                'value'      => 'original',
                'formStatus' => "st_infoonly",
            ],
            'ht_config'              => [
                'value'      => 'original',
                'formStatus' => "st_infoonly",
            ],
            'other_config'           => [
                'value'      => 'original',
                'formStatus' => "st_infoonly",
            ],
            'zip_filetime'           => [
                'value'      => 'original',
                'formStatus' => "st_infoonly",
            ],
            'mode_chunking'          => [
                'value'      => 3,
                'formStatus' => "st_infoonly",
            ],
        ];
        PackageUtils::writeOverwriteParams(DUPLICATOR_SSDIR_PATH, $package->getPrimaryInternalHash(), $installerParams);

        return $result;
    }

    /**
     * Hook ajax restore backup prepare
     *
     * @return void
     */
    public function restoreBackupPrepare(): void
    {
        AjaxWrapper::json(
            [
                self::class,
                'restoreBackupPrepareCallback',
            ],
            'duplicator_restore_backup_prepare',
            SnapUtil::sanitizeTextInput(INPUT_POST, 'nonce'),
            CapMng::CAP_BACKUP_RESTORE
        );
    }
}
