<?php

namespace Duplicator\Views;

use Closure;
use Duplicator\Controllers\ActivityLogPageController;
use Duplicator\Controllers\ToolsPageController;
use Duplicator\Core\CapMng;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapString;
use Duplicator\Libs\Snap\SnapURL;
use Duplicator\Models\ActivityLog\AbstractLogEvent;
use Duplicator\Models\RecommendedFix;
use Duplicator\Models\SystemGlobalEntity;
use Duplicator\Package\AbstractPackage;
use Duplicator\Package\PackageUtils;
use Duplicator\Utils\Autoloader;
use Duplicator\Utils\Support\SupportToolkit;
use Exception;

/**
 * Admin notices class, Used to display notices in the WordPress Admin area
 */
class AdminNotices
{
    const OPTION_KEY_INSTALLER_HASH_NOTICE          = 'dupli_opt_inst_hash_notice';
    const OPTION_KEY_ACTIVATE_PLUGINS_AFTER_INSTALL = 'dupli_opt_activate_plugins_after_installation';
    const OPTION_KEY_MIGRATION_SUCCESS_NOTICE       = 'dupli_opt_migration_success';
    const OPTION_KEY_S3_CONTENTS_FETCH_FAIL_NOTICE  = 'dupli_opt_s3_contents_fetch_fail';
    const QUICK_FIX_NOTICE                          = 'dupli_opt_quick_fix_notice';
    const FAILED_SCHEDULE_NOTICE                    = 'dupli_opt_failed_schedule_notice';
    const FAILED_BACKUP_NOTICE                      = 'dupli_opt_failed_backup_notice';
    const ACTIVITY_LOG_UPGRADE_NOTICE               = 'dupli_opt_activity_log_upgrade_notice';

    const GEN_INFO_NOTICE    = 0;
    const GEN_SUCCESS_NOTICE = 1;
    const GEN_WARNING_NOTICE = 2;
    const GEN_ERROR_NOTICE   = 3;

    /**
     * init notice actions
     *
     * @return void
     */
    public static function init(): void
    {
        // The priority needs to be > 10 to make sure that the notices are displayed after the SystemGlobalEntity is
        // updated. This fixes a bug where the schedule failure notice would not be removed after the link was clicked.
        add_action('admin_init', [self::class, 'adminInit'], 20);
        add_action('admin_enqueue_scripts', [self::class, 'unhookThirdPartyNotices'], 99999, 1);
        add_action('duplicator_package_after_set_status', [self::class, 'onPackageStatusChange'], 10, 2);
    }

    /**
     * Handle package status change to set failed backup notice
     *
     * @param AbstractPackage $package The package that changed status
     * @param int             $status  The new status
     *
     * @return void
     */
    public static function onPackageStatusChange(AbstractPackage $package, int $status): void
    {
        if ($status < AbstractPackage::STATUS_PRE_PROCESS) {
            update_option(self::FAILED_BACKUP_NOTICE, true);
        }
    }

    /**
     * HOOK admin_init
     *
     * @return void
     */
    public static function adminInit(): void
    {
        $notices   = [];
        $notices[] = [
            self::class,
            'migrationSuccessNotice',
        ]; // BEFORE MIGRATION SUCCESS NOTICE
        $notices[] = [
            self::class,
            's3ContentsFetchFailNotice',
        ];
        $notices[] = [
            self::class,
            'addonInitFailNotice',
        ];
        $notices[] = [
            self::class,
            'activatePluginsAfterInstall',
        ];
        $notices[] = [
            self::class,
            'orphanedPackagesNotice',
        ];
        $notices[] = [
            self::class,
            'failedBackupsNotice',
        ];
        $notices[] = [
            self::class,
            'activityLogUpgradeNotice',
        ];

        $system_global = SystemGlobalEntity::getInstance();
        foreach ($system_global->recommended_fixes as $fix) {
            if ($fix->recommended_fix_type === RecommendedFix::TYPE_TEXT || $fix->recommended_fix_type === RecommendedFix::TYPE_FIX) {
                $notices[] = [
                    self::class,
                    'showQuickFixNotice',
                ];
            }
        }
        if ($system_global->schedule_failed) {
            $notices[] = [
                self::class,
                'showFailedSchedule',
            ];
        }

        $notices = apply_filters('duplicator_admin_notices', $notices);
        $action  = is_multisite() ? 'network_admin_notices' : 'admin_notices';
        foreach ($notices as $notice) {
            add_action($action, $notice);
        }
    }

    /**
     * Addon init fail notice
     *
     * @return void
     */
    public static function addonInitFailNotice(): void
    {
        if (\Duplicator\Core\Addons\AddonsManager::getInstance()->isAddonsReady()) {
            return;
        }

        if (!CapMng::can(CapMng::CAP_BASIC, false)) {
            return;
        }
        ob_start();
        ?>
        <strong>Duplicator Pro</strong>
        <hr>
        <p>
            <?php _e(
                'The plugin cannot be activated due to problems during initialization. Please reinstall the plugin deleting the current installation',
                'duplicator-pro'
            ); ?>
        </p>
        <?php
        $content = (string) ob_get_clean();
        self::displayGeneralAdminNotice($content, self::GEN_ERROR_NOTICE, false);
    }


    /**
     * Remove all notices coming from other plugins
     *
     * @param string $hook Hook string
     *
     * @return void
     */
    public static function unhookThirdPartyNotices($hook): void
    {
        if (!ControllersManager::getInstance()->isDuplicatorPage()) {
            return;
        }

        global $wp_filter;
        $filterHooks = [
            'user_admin_notices',
            'admin_notices',
            'all_admin_notices',
            'network_admin_notices',
        ];
        foreach ($filterHooks as $filterHook) {
            if (empty($wp_filter[$filterHook]->callbacks) || !is_array($wp_filter[$filterHook]->callbacks)) {
                continue;
            }

            foreach ($wp_filter[$filterHook]->callbacks as $priority => $hooks) {
                foreach ($hooks as $name => $arr) {
                    if (is_object($arr['function']) && $arr['function'] instanceof Closure) {
                        unset($wp_filter[$filterHook]->callbacks[$priority][$name]);
                        continue;
                    }
                    if (
                        !empty($arr['function'][0]) &&
                        is_object($arr['function'][0]) &&
                        strpos(get_class($arr['function'][0]), Autoloader::ROOT_NAMESPACE) === 0
                    ) {
                        continue;
                    }
                    if (!empty($name) && strpos($name, Autoloader::ROOT_NAMESPACE) !== 0) {
                        unset($wp_filter[$filterHook]->callbacks[$priority][$name]);
                    }
                }
            }
        }
    }

    /**
     * Shows notice in case we were enable to fetch contents of S3 bucket
     *
     * @throws Exception
     * @return void
     */
    public static function s3ContentsFetchFailNotice(): void
    {
        if (
            get_option(self::OPTION_KEY_S3_CONTENTS_FETCH_FAIL_NOTICE, false) != true ||
            !ControllersManager::isCurrentPage(ControllersManager::PACKAGES_SUBMENU_SLUG)
        ) {
            return;
        }

        if (!CapMng::can(CapMng::CAP_CREATE, false)) {
            return;
        }

        $errorMessage = __(
            '<strong>Duplicator Pro</strong> was unable to fetch the contents of the S3 bucket to remove old Backups.',
            'duplicator-pro'
        ) . "<hr><br>" .
            sprintf(
                __(
                    '<strong>RECOMMENDATION:</strong> Please make sure your S3 bucket settings are aligned with our
                %1$sStep-by-Step guide%2$s and %3$sUser Bucket Policy%4$s.',
                    'duplicator-pro'
                ),
                '<a target="_blank" href="' . DUPLICATOR_DUPLICATOR_DOCS_URL . 'amazon-s3-step-by-step">',
                '</a>',
                '<a target="_blank" href="' . DUPLICATOR_DUPLICATOR_DOCS_URL . 'amazon-s3-step-by-step">',
                '</a>'
            );

        self::displayGeneralAdminNotice(
            $errorMessage,
            self::GEN_ERROR_NOTICE,
            true,
            ['dupli-quick-fix-notice'],
            [
                'data-to-dismiss' => self::OPTION_KEY_S3_CONTENTS_FETCH_FAIL_NOTICE,
            ]
        );
    }

    /**
     * Orphaned packages notice
     *
     * @throws Exception
     * @return void
     */
    public static function orphanedPackagesNotice(): void
    {
        $orphan_info = PackageUtils::getOrphanedPackageInfo();
        if (
            $orphan_info['count'] < 1 ||
            !ControllersManager::isCurrentPage(ControllersManager::PACKAGES_SUBMENU_SLUG)
        ) {
            return;
        }

        self::displayGeneralAdminNotice(
            TplMng::getInstance()->render('parts/packages/notices/orphaned_packages', [
                'count' => $orphan_info['count'],
                'size'  => SnapString::byteSize($orphan_info['size']),
                'url'   => ToolsPageController::getInstance()->getPurgeOrphanActionUrl(),
            ], false),
            self::GEN_ERROR_NOTICE,
            true,
            ['dupli-quick-fix-notice'],
            [
                'data-to-dismiss' => self::OPTION_KEY_S3_CONTENTS_FETCH_FAIL_NOTICE,
            ]
        );
    }

    /**
     * Failed backups notice
     *
     * @return void
     */
    public static function failedBackupsNotice(): void
    {
        // Check if notice option is set (set when a backup fails)
        if (get_option(self::FAILED_BACKUP_NOTICE, false) != true) {
            return;
        }

        if (!ControllersManager::isCurrentPage(ControllersManager::PACKAGES_SUBMENU_SLUG)) {
            return;
        }

        if (!CapMng::can(CapMng::CAP_BASIC, false)) {
            return;
        }

        $activityLogUrl = ActivityLogPageController::getInstance()->getMenuLink(
            null,
            null,
            ['filter_severity' => AbstractLogEvent::SEVERITY_ERROR]
        );

        self::displayGeneralAdminNotice(
            TplMng::getInstance()->render('parts/packages/notices/failed_backups', ['activityLogUrl' => $activityLogUrl], false),
            self::GEN_ERROR_NOTICE,
            true,
            ['dupli-failed-backup-notice'],
            [
                'data-to-dismiss' => self::FAILED_BACKUP_NOTICE,
            ],
            true
        );
    }

    /**
     * Activity Log integration upgrade notice
     *
     * @return void
     */
    public static function activityLogUpgradeNotice(): void
    {
        $count = get_transient(self::ACTIVITY_LOG_UPGRADE_NOTICE);
        if ($count === false) {
            return;
        }

        if (!CapMng::can(CapMng::CAP_BASIC, false)) {
            return;
        }

        self::displayGeneralAdminNotice(
            TplMng::getInstance()->render('parts/packages/notices/activity_log_upgrade', [
                'count'          => $count,
                'activityLogUrl' => ActivityLogPageController::getInstance()->getMenuLink(),
            ], false),
            self::GEN_INFO_NOTICE,
            true,
            ['dupli-activity-log-upgrade-notice'],
            [
                'data-to-dismiss' => self::ACTIVITY_LOG_UPGRADE_NOTICE,
            ],
            true
        );
    }


    /**
     * Shows a display message in the wp-admin if any reserved files are found
     *
     * @return void
     */
    public static function migrationSuccessNotice(): void
    {
        if (get_option(self::OPTION_KEY_MIGRATION_SUCCESS_NOTICE) != true) {
            return;
        }

        if (!CapMng::can(CapMng::CAP_BASIC, false)) {
            return;
        }

        if (!ToolsPageController::isGeneralPage()) {
            TplMng::getInstance()->render('parts/migration/almost-complete');
        }
    }

    /**
     * Shows the scheduled failed alert
     *
     * @return void
     */
    public static function showFailedSchedule(): void
    {
        if (!CapMng::can(CapMng::CAP_SCHEDULE, false)) {
            return;
        }
        $schedulesUrl = ControllersManager::getMenuLink(ControllersManager::SCHEDULES_SUBMENU_SLUG);
        $img_url      = plugins_url('duplicator-pro/assets/img/warning.png');
        $clear_url    = SnapURL::getCurrentUrl();
        $clear_url    = SnapURL::appendQueryValue($clear_url, 'duplicator_clear_schedule_failure', 1);
        $clear_url    = SnapURL::appendQueryValue($clear_url, 'nonce', wp_create_nonce('duplicator_clear_schedule_failure'));
        $html         = "<img src='" . esc_url($img_url) . "' style='float:left; padding:0 10px 0 5px' />" .
            sprintf(esc_html__('%1$sWarning! A Duplicator Pro scheduled backup has failed.%2$s', 'duplicator-pro'), '<b>', '</b> <br/>') .
            sprintf(
                esc_html__(
                    'This message will continue to be displayed until a %1$sscheduled build%2$s successfully runs. ',
                    'duplicator-pro'
                ),
                "<a href='" . esc_url($schedulesUrl) . "'>",
                '</a> '
            ) .
            sprintf(
                esc_html__('To ignore and clear this message %1$sclick here%2$s', 'duplicator-pro'),
                "<a href='" . esc_url($clear_url) . "'>",
                '</a>.<br/>'
            );

        self::displayGeneralAdminNotice(
            $html,
            self::GEN_ERROR_NOTICE,
            true,
            ['dupli-quick-fix-notice'],
            [
                'data-to-dismiss' => self::FAILED_SCHEDULE_NOTICE,
            ]
        );
    }

    /**
     * Shows the quick fix notice
     *
     * @return void
     */
    public static function showQuickFixNotice(): void
    {
        if (!ControllersManager::isCurrentPage(ControllersManager::PACKAGES_SUBMENU_SLUG)) {
            return;
        }
        if (!CapMng::can(CapMng::CAP_BASIC, false)) {
            return;
        }

        $system_global = SystemGlobalEntity::getInstance();
        $html          = '<b class="title"><i class="fa fa-exclamation-circle fa-3 color-alert" ></i> ' .
            __('Duplicator Pro Errors Detected', 'duplicator-pro') .
            '</b>';
        $html         .= '<p>' . __('Backup build error(s) were encountered.  Click the button(s) in the', 'duplicator-pro') .
            ' <i>' . __('Necessary Actions', 'duplicator-pro') . '</i> ' . __('section to reconfigure Duplicator Pro.', 'duplicator-pro') . "</p>";
        $html         .= '<p>';
        $html         .= '<b>' . __('Error(s):', 'duplicator-pro') . ' </b>';
        $html         .= '</p>';
        $html         .= '<ul style="list-style: disc; padding-left: 40px">';
        foreach ($system_global->recommended_fixes as $fix) {
            $html .= '<li>' . $fix->error_text . '</li>';
        }
        $html .= '</ul>';
        $html .= '<b>' . __('Necessary Action(s):', 'duplicator-pro') . ' </b>';
        foreach ($system_global->recommended_fixes as $fix) {
            if ($fix->recommended_fix_type == RecommendedFix::TYPE_FIX) {
                $html .= '<p id ="quick-fix-' . $fix->id . '">'
                    . '<button id="quick-fix-' . $fix->id . '-button" '
                    . 'type="button" class="dupli-quick-fix secondary hollow small button" '
                    . 'data-param="' . esc_attr((string) json_encode($fix->parameter2)) . '" '
                    . 'data-id="' . $fix->id . '" data-toggle="#quick-fix-' . $fix->id . '">'
                    . "<i class='fa fa-wrench' aria-hidden='true'></i>&nbsp; "
                    . __('Resolve This', 'duplicator-pro')
                    . '</button>'
                    . $fix->parameter1
                    . '</p>';
            } elseif ($fix->recommended_fix_type == RecommendedFix::TYPE_TEXT) {
                $html .= "<p><i class='fa fa-question-circle color-alert' data-tooltip='" .
                    esc_attr($fix->error_text) . "'></i>&nbsp; " . $fix->parameter1 . "</br></p>";
            }
        }

        self::displayGeneralAdminNotice(
            $html,
            self::GEN_ERROR_NOTICE,
            true,
            ['dupli-quick-fix-notice'],
            [
                'data-to-dismiss' => self::QUICK_FIX_NOTICE,
            ],
            true
        );
    }

    /**
     * display genral admin notice by printing it
     *
     * @param string              $htmlMsg       html code to be printed
     * @param integer             $noticeType    constant value of SELF::GEN_
     * @param boolean             $isDismissible whether the notice is dismissable or not. Default is true
     * @param string|string[]     $extraClasses  add more classes to the notice div
     * @param array<string,mixed> $extraAtts     assosiate array in which key as attr and value as value of the attr
     * @param bool                $blockContent  if false wraps htmlMsg in <p> otherwise allows to use block tags e.g. <div>
     *
     * @return void
     */
    public static function displayGeneralAdminNotice(
        $htmlMsg,
        $noticeType,
        $isDismissible = true,
        $extraClasses = [],
        $extraAtts = [],
        $blockContent = false
    ): void {
        if (empty($extraClasses)) {
            $classes = [];
        } elseif (is_array($extraClasses)) {
            $classes = $extraClasses;
        } else {
            $classes = [$extraClasses];
        }

        $classes[] = 'notice';
        switch ($noticeType) {
            case self::GEN_INFO_NOTICE:
                $classes[] = 'notice-info';
                break;
            case self::GEN_SUCCESS_NOTICE:
                $classes[] = 'notice-success';
                break;
            case self::GEN_WARNING_NOTICE:
                $classes[] = 'notice-warning';
                break;
            case self::GEN_ERROR_NOTICE:
                $classes[] = 'notice-error';
                break;
            default:
                throw new Exception('Invalid Admin notice type!');
        }
        $classes[] = 'dupli-admin-notice';

        if ($isDismissible) {
            $classes[] = 'is-dismissible';
        }

        $classesStr = implode(' ', $classes);
        $attsStr    = '';
        if (!empty($extraAtts)) {
            $attsStrArr = [];
            foreach ($extraAtts as $att => $attVal) {
                $attsStrArr[] = esc_attr($att) . '="' . esc_attr($attVal) . '"';
            }
            $attsStr = implode(' ', $attsStrArr);
        }

        // $htmlMsg = self::GEN_ERROR_NOTICE == $noticeType ? "<i class='fa fa-exclamation-triangle'></i>&nbsp;" . $htmlMsg : $htmlMsg;
        $htmlMsg = !$blockContent ? "<p>" . $htmlMsg . "</p>" : $htmlMsg;
        ?>
        <div class="<?php echo esc_attr($classesStr); ?>" <?php echo $attsStr; ?>>
            <?php echo $htmlMsg; ?>
        </div>
        <?php
    }

    /**
     * Displays notice for plugins deactivated during install,
     * and removes already activated from DB
     *
     * @return void
     */
    public static function activatePluginsAfterInstall(): void
    {
        if (!CapMng::can(CapMng::CAP_BASIC, false)) {
            return;
        }
        $pluginsToActive = get_option(AdminNotices::OPTION_KEY_ACTIVATE_PLUGINS_AFTER_INSTALL, false);
        if (!is_array($pluginsToActive) || empty($pluginsToActive)) {
            return;
        }

        $shouldBeActivated = [];
        $allPlugins        = get_plugins();
        foreach ($pluginsToActive as $index => $pluginSlug) {
            if (!isset($allPlugins[$pluginSlug])) {
                unset($pluginsToActive[$index]);
                continue;
            }

            $isActive = is_multisite() ? is_plugin_active_for_network($pluginSlug) : is_plugin_active($pluginSlug);

            if (!$isActive) {
                $shouldBeActivated[$pluginSlug] = $allPlugins[$pluginSlug]['Name'];
            } else {
                unset($pluginsToActive[$index]);
            }
        }

        if (empty($shouldBeActivated)) {
            delete_option(AdminNotices::OPTION_KEY_ACTIVATE_PLUGINS_AFTER_INSTALL);
            return;
        } else {
            update_option(AdminNotices::OPTION_KEY_ACTIVATE_PLUGINS_AFTER_INSTALL, $pluginsToActive);
        }

        $html = "<img src='" . esc_url(plugins_url('duplicator-pro/assets/img/warning.png')) . "' style='float:left; padding:0 10px 0 5px' />" .
            "<div style='margin-left: 70px;'><p><b>" .
            __('Warning!', 'duplicator-pro') . "</b> " . __('Migration Almost Complete!', 'duplicator-pro') . "<br/>" .
            __('Plugin(s) listed here must be activated, Please activate them:', 'duplicator-pro') . "</p><ul>";
        foreach ($shouldBeActivated as $slug => $title) {
            if (is_multisite()) {
                $activateURL = network_admin_url('plugins.php?action=activate&plugin=' . $slug);
            } else {
                $activateURL = admin_url('plugins.php?action=activate&plugin=' . $slug);
            }
            $activateURL = wp_nonce_url($activateURL, 'activate-plugin_' . $slug);
            $anchorTitle = sprintf(__('Activate %s', 'duplicator-pro'), $title);
            $html       .= '<li><a href="' . esc_attr($activateURL) . '" title="' . esc_attr($anchorTitle) . '">' .
                esc_attr($title) . '</a></li>';
        }

        $html .= "</ul></div>";
        AdminNotices::displayGeneralAdminNotice(
            $html,
            AdminNotices::GEN_WARNING_NOTICE,
            true,
            ['dupli-yellow-border'],
            [
                'data-to-dismiss' => AdminNotices::OPTION_KEY_ACTIVATE_PLUGINS_AFTER_INSTALL,
            ],
            true
        );
    }
}
