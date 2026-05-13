<?php

/**
 * @package Duplicator
 */

use Duplicator\Addons\ProBase\License\License;
use Duplicator\Addons\ProBase\LicensingController;
use Duplicator\Addons\ProBase\Models\LicenseData;
use Duplicator\Core\Controllers\PageAction;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 * @var Duplicator\Core\Controllers\PageAction $refreshAction
 */
$refreshAction = $tplData['actions'][LicensingController::ACTION_FORCE_REFRESH];
?>
<p>
    <i>
        <?php
        printf(
            esc_html_x(
                'If your license has been updated or is incorrect, then please %1$sForce a Refresh%2$s.',
                '1: <a> tag, 2: </a> tag',
                'duplicator-pro'
            ),
            '<a href="' . esc_url($refreshAction->getUrl()) . '">',
            '</a>'
        );
        ?>
    </i>
</p>
<div id="dup-license-info">
    <?php
    switch (LicenseData::getInstance()->getStatus()) {
        case LicenseData::STATUS_VALID:
            $statusClass = "";
            $statusText  = '<b>' . License::getLicenseToString() . '</b> ' . __('License expires on', 'duplicator-pro') . ' ';
            $statusText .= '<b>' . LicenseData::getInstance()->getExpirationDate(get_option('date_format')) . '</b> ';

            $expDays     = LicenseData::getInstance()->getExpirationDays();
            $statusText .= ' (';
            if ($expDays === false) {
                $statusText .= __('no data', 'duplicator-pro');
            } elseif ($expDays <= 0) {
                $statusText .= __('expired', 'duplicator-pro');
            } elseif ($expDays == PHP_INT_MAX) {
                $statusText .= __('no expiration', 'duplicator-pro');
            } else {
                $statusText .= sprintf(__('%d days left', 'duplicator-pro'), $expDays);
            }
            $statusText .= ')';
            break;
        case LicenseData::STATUS_INACTIVE:
            $statusClass = "alert-color";
            $statusText  = __('License Inactive', 'duplicator-pro');
            break;
        case LicenseData::STATUS_SITE_INACTIVE:
            $statusClass = "alert-color";
            if (LicenseData::getInstance()->haveNoActivationsLeft()) {
                $statusText = __('License Inactive (out of site licenses).', 'duplicator-pro') . '<br>' . License::getNoActivationLeftMessage();
            } else {
                $statusText = __('License Inactive', 'duplicator-pro');
            }
            break;
        case LicenseData::STATUS_EXPIRED:
            $statusClass = "alert-color";
            $statusText  = sprintf(
                _x(
                    'Your Duplicator Pro license key has expired so you aren\'t getting important updates! %1$sRenew your license now%2$s',
                    '1: <a> tag, 2: </a> tag',
                    'duplicator-pro'
                ),
                '<a target="_blank" href="' . esc_url(License::getLicenseCheckoutURL()) . '">',
                '</a>'
            );
            break;
        case LicenseData::STATUS_INVALID:
        case LicenseData::STATUS_UNKNOWN:
        default:
            // https://duplicator.com/knowledge-base/how-to-resolve-license-activation-issues/
            $statusClass = "alert-color";
            $statusText  = sprintf(
                _x(
                    'License %1$s',
                    '1: License status (Invalid, Unknown)',
                    'duplicator-pro'
                ),
                '</b>' . LicenseData::getStatusLabel(LicenseData::getInstance()->getStatus()) . '<br/>'
            );
            break;
    }
    ?>
    <div class="<?php echo esc_attr($statusClass); ?>">
        <?php
        echo wp_kses(
            $statusText,
            [
                'a'  => [
                    'href'   => [],
                    'target' => [],
                ],
                'b'  => [],
                'br' => [],
            ]
        );
        ?>
    </div>
    <ul class="dup-license-type-info no-bullet">
        <li>
            <?php
            $checkedClass = (LicenseData::getInstance()->getStatus() == LicenseData::STATUS_VALID ? 'far fa-check-circle' : 'far fa-circle');
            $sitesLimit   = (License::isUnlimited() ? __('Unlimited', 'duplicator-pro') :  LicenseData::getInstance()->getLicenseLimit());
            $sitesCount   = (LicenseData::getInstance()->getSiteCount() < 0 ? '?' : LicenseData::getInstance()->getSiteCount());
            ?>
            <i class="<?php echo esc_attr($checkedClass); ?>"></i>
            <?php
            printf(
                esc_html_x(
                    'Site Licenses: %1$s of %2$s',
                    '1 = (string) Site Licenses, 2 = (number) Active sites, 3 = (number) License limit',
                    'duplicator-pro'
                ),
                esc_html($sitesCount),
                esc_html($sitesLimit)
            );
            $tipContent = __(
                'Indicates the number of sites the plugin can be active on at any one time. 
                At any point you may deactivate/uninstall the plugin to free up the license and use the plugin elsewhere if needed.',
                'duplicator-pro'
            );
            ?>
            <i
                class='fa-solid fa-question-circle fa-sm dark-gray-color'
                data-tooltip-title='<?php esc_attr_e('Site Licenses', 'duplicator-pro') ?>'
                data-tooltip='<?php echo esc_attr($tipContent) ?>'>
            </i>
        </li>
        <li>
            <?php
            $checkedClass = License::can(License::CAPABILITY_POWER_TOOLS) ? 'far fa-check-circle' : 'far fa-circle';
            $tipContent   = __(
                'Enhanced features that greatly improve the productivity of serious users. Include hourly schedules, 
                installer branding, salt & key replacement, priority support and more.',
                'duplicator-pro'
            );
            ?>
            <i class="<?php echo esc_attr($checkedClass); ?>"></i>
            <?php esc_html_e('Powertools', 'duplicator-pro') ?>
            <i
                class='fa-solid fa-question-circle fa-sm dark-gray-color'
                data-tooltip-title='<?php echo esc_attr_e('Powertools', 'duplicator-pro') ?>'
                data-tooltip='<?php echo esc_attr($tipContent) ?>'>
            </i>
        </li>
        <li>
            <?php
            $checkedClass = License::can(License::CAPABILITY_MULTISITE_PLUS) ? 'far fa-check-circle' : 'far fa-circle';
            $tipContent   = __(
                'Adds the ability to install a subsite as a standalone site,
                insert a standalone site into a multisite, or insert a subsite from the same/different multisite into a multisite.',
                'duplicator-pro'
            );
            ?>
            <i class="<?php echo esc_attr($checkedClass) ?>"></i>
            <?php esc_html_e('Multisite Plus+', 'duplicator-pro') ?>
            <i
                class='fa-solid fa-question-circle fa-sm dark-gray-color'
                data-tooltip-title='<?php echo esc_attr_e('Multisite Plus+', 'duplicator-pro') ?>'
                data-tooltip='<?php echo esc_attr($tipContent) ?>'>
            </i>
        </li>
        <?php if (License::canBeUpgraded()) { ?>
            <li>
                <div class="margin-top-1">
                    <?php
                    printf(
                        esc_html_x(
                            '🔥 To unlock more features see our %1$sUpgrade Offers%2$s.',
                            '1: <a> tag, 2: </a> tag',
                            'duplicator-pro'
                        ),
                        '<a class="dup-upgrade-license-link primary-color" href="' . esc_url(License::getUpsellURL()) . '" target="_blank">',
                        '</a>'
                    );
                    ?>
                </div>
            </li>
        <?php } ?>
    </ul>

</div>