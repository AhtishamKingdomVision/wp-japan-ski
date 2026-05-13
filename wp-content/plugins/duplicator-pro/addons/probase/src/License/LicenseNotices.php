<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Addons\ProBase\License;

use Duplicator\Addons\ProBase\DrmHandler;
use Duplicator\Addons\ProBase\Models\LicenseData;
use Duplicator\Controllers\SettingsPageController;
use Duplicator\Core\CapMng;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Core\Views\TplMng;
use Duplicator\Views\AdminNotices;

class LicenseNotices
{
    /**
     * Init notice actions
     *
     * @return void
     */
    public static function init(): void
    {
        add_action('admin_init', [self::class, 'adminInit']);

        $path = plugin_basename(DUPLICATOR____FILE);

        // Important to make this priority 11 or greater to ensure the version cache is up to date by EDD
        add_action("after_plugin_row_{$path}", [self::class, 'noLicenseDisplay'], 11, 2);
    }

    /**
     * Function called on hook admin_init
     *
     * @return void
     */
    public static function adminInit(): void
    {
        $action = is_multisite() ? 'network_admin_notices' : 'admin_notices';
        add_action($action, [self::class, 'licenseAlertCheck']);
    }

    /**
     * Function called on hook admin_init
     *
     * @param string               $file   Path to the plugin file relative to the plugins directory
     * @param array<string, mixed> $plugin An array of plugin data
     *
     * @return void
     */
    public static function noLicenseDisplay($file, $plugin): void
    {
        $latest_version = License::getLatestVersion();

        // Only display this message when there is no update message
        if (($latest_version === false) || version_compare(DUPLICATOR_VERSION, $latest_version, '>=')) {
            $error_string = null;

            $licenseSettingsUrl = SettingsPageController::getInstance()->getMenuLink(
                SettingsPageController::L2_SLUG_GENERAL
            );

            switch (LicenseData::getInstance()->getStatus()) {
                case LicenseData::STATUS_INVALID:
                case LicenseData::STATUS_SITE_INACTIVE:
                    $error_string = sprintf(
                        esc_html_x(
                            'Your Duplicator Pro license key is invalid so you aren\'t getting important updates! 
                            %1$sActivate your license%2$s or %3$spurchase a license%4$s.',
                            '1,3: <a> tag, 2,4: </a> tag',
                            'duplicator-pro'
                        ),
                        '<a href="' . esc_url($licenseSettingsUrl) . '">',
                        '</a>',
                        '<a target="_blank" href="' . esc_url(DUPLICATOR_BLOG_URL . 'pricing/') . '">',
                        '</a>'
                    );
                    break;
                case LicenseData::STATUS_EXPIRED:
                    $error_string = sprintf(
                        __(
                            'Your Duplicator Pro license key has expired so you aren\'t getting important updates! %1$sRenew your license now%2$s',
                            'duplicator-pro'
                        ),
                        '<a target="_blank" href="' . esc_url(License::getLicenseCheckoutURL()) . '">',
                        '</a>'
                    );
                    break;
                default:
                    break;
            }

            if ($error_string == null) {
                return;
            } elseif (!CapMng::can(CapMng::CAP_LICENSE, false)) {
                // Overwrite error message if the user does not have the license capability
                $error_string =  esc_html__(
                    'Duplicator Pro license key is not activated. Please contact the Duplicator license manager to activate it.',
                    'duplicator-pro'
                );
            }

            ob_start();
            ?>
            <script>
                jQuery("[data-slug=\'duplicator-pro\']").addClass("update");
            </script>
            <tr style="border-top-color:black" class="plugin-update-tr active">
                <td colspan="4" class="plugin-update colspanchange">
                    <div class="update-message notice inline notice-error notice-alt">
                        <p>
                            <?php
                            echo wp_kses(
                                $error_string,
                                [
                                    'a' => [
                                        'href'   => [],
                                        'target' => [],
                                    ],
                                ]
                            );
                            ?>
                        </p>
                    </div>
                </td>
            </tr>
            <?php
            ob_end_flush();
        }
    }

    /**
     * Used by the WP action hook to detect the state of the endpoint license
     * which calls the various show* methods for which alert to display
     *
     * @return void
     */
    public static function licenseAlertCheck(): void
    {
        if (
            !CapMng::can(CapMng::CAP_BASIC, false) ||
            ControllersManager::isCurrentPage(
                ControllersManager::SETTINGS_SUBMENU_SLUG,
                SettingsPageController::L2_SLUG_GENERAL
            )
        ) {
            return;
        }

        if (file_exists(DUPLICATOR_SSDIR_PATH . "/ovr.dup")) {
            return;
        }

        $licenseData = LicenseData::getInstance();

        switch ($licenseData->getStatus()) {
            case LicenseData::STATUS_VALID:
                if (is_multisite() && !License::can(License::CAPABILITY_MULTISITE)) {
                    self::showMultisiteDRMAlert();
                }
                break;
            case LicenseData::STATUS_EXPIRED:
                self::showExpired();
                break;
            case LicenseData::STATUS_UNKNOWN:
            case LicenseData::STATUS_INVALID:
            case LicenseData::STATUS_INACTIVE:
            case LicenseData::STATUS_DISABLED:
            case LicenseData::STATUS_SITE_INACTIVE:
            default:
                if ($licenseData->haveNoActivationsLeft()) {
                    self::showNoActivationsLeft();
                } else {
                    self::showInvalidStandardNag();
                }
                break;
        }
    }

    /**
     * Shows the smaller standard nag screen
     *
     * @return void
     */
    private static function showInvalidStandardNag(): void
    {
        $problem_text = esc_html__('missing', 'duplicator-pro');

        $htmlMsg = TplMng::getInstance()->render(
            'licensing/notices/inactive_message',
            ['problem' => $problem_text],
            false
        );

        AdminNotices::displayGeneralAdminNotice(
            $htmlMsg,
            AdminNotices::GEN_ERROR_NOTICE,
            false,
            ['dup-license-message'],
            [],
            true
        );
    }

    /**
     * Shows the license count used up alert
     *
     * @return void
     */
    private static function showNoActivationsLeft(): void
    {
        $htmlMsg = TplMng::getInstance()->render(
            'licensing/notices/no_activation_left',
            [],
            false
        );
        AdminNotices::displayGeneralAdminNotice(
            $htmlMsg,
            AdminNotices::GEN_ERROR_NOTICE,
            false,
            ['dup-license-message'],
            [],
            true
        );
    }

    /**
     * Shows the expired message alert
     *
     * @return void
     */
    private static function showExpired(): void
    {
        $renewal_url = License::getLicenseCheckoutURL();
        $htmlMsg     = TplMng::getInstance()->render(
            'licensing/notices/expired',
            [
                'renewal_url'                => $renewal_url,
                'schedule_disalbe_days_left' => DrmHandler::getDaysTillScheduleDRM(),
            ],
            false
        );
        AdminNotices::displayGeneralAdminNotice(
            $htmlMsg,
            AdminNotices::GEN_ERROR_NOTICE,
            false,
            ['dup-license-message'],
            [],
            true
        );
    }

    /**
     * Shows the multisite DRM alert
     *
     * @return void
     */
    private static function showMultisiteDRMAlert(): void
    {
        $problem_text = esc_html__('missing', 'duplicator-pro');

        $htmlMsg = TplMng::getInstance()->render(
            'licensing/notices/drm_multisite_msg',
            [],
            false
        );

        AdminNotices::displayGeneralAdminNotice(
            $htmlMsg,
            AdminNotices::GEN_ERROR_NOTICE,
            false,
            ['dup-license-message'],
            [],
            true
        );
    }

    /**
     * Gets the upgrade link
     *
     * @param string $label The label of the link
     * @param bool   $echo  Whether to echo the link or return it
     *
     * @return string
     */
    public static function getUpsellLinkHTML($label = 'Upgrade', $echo = true)
    {
        ob_start();
        ?>
        <a class="dup-upgrade-license-link primary-color" href="<?php echo esc_url(License::getUpsellURL()); ?>" target="_blank">
            <?php echo esc_html($label); ?>
        </a>
        <?php
        if ($echo) {
            ob_end_flush();
            return '';
        } else {
            return ob_get_clean();
        }
    }
}
