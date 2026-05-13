<?php

/**
 * @package Duplicator
 */

use Duplicator\Addons\ProBase\LicensingController;
use Duplicator\Controllers\SettingsPageController;
use Duplicator\Core\CapMng;
use Duplicator\Core\Controllers\ControllersManager;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

$licensing_tab_url = ControllersManager::getMenuLink(
    ControllersManager::SETTINGS_SUBMENU_SLUG,
    SettingsPageController::L2_SLUG_GENERAL
);
$dashboard_url     = DUPLICATOR_BLOG_URL . 'my-account';
$img_url           = plugins_url('duplicator-pro/assets/img/warning.png');

?>
<span class='dashicons dashicons-warning'></span>
<div class="dup-sub-content">
    <h3>
        <?php esc_html_e('Duplicator Pro\'s license is deactivated because you\'re out of site activations.', 'duplicator-pro'); ?>
    </h3>
    <?php esc_html_e('You\'re currently missing:', 'duplicator-pro'); ?>
    <ul class="dupli-simple-style-disc" >
        <li><?php esc_html_e('Access to Advanced Features', 'duplicator-pro'); ?></li>
        <li><?php esc_html_e('Scheduled Backups', 'duplicator-pro'); ?></li>
        <li><?php esc_html_e('Storages Management', 'duplicator-pro'); ?></li>
        <li><?php esc_html_e('Templates Management', 'duplicator-pro'); ?></li>
        <li><?php esc_html_e('New Features', 'duplicator-pro'); ?></li>
        <li><?php esc_html_e('Important Updates for Security Patches', 'duplicator-pro'); ?></li>
        <li><?php esc_html_e('Bug Fixes', 'duplicator-pro'); ?></li>
        <li><?php esc_html_e('Support Requests', 'duplicator-pro'); ?></li>
    </ul>
    <?php
    if (CapMng::can(CapMng::CAP_LICENSE, false)) {
        printf(
            wp_kses(
                _x(
                    'Upgrade your license using the %1$sDuplicator Dashboard%2$s or deactivate plugin on old sites.<br/>
                    After making necessary changes %3$srefresh the license status%4$s.',
                    '1 and 2 are opening and 3 and 4 are closing anchor tags (<a> and </a>)',
                    'duplicator-pro'
                ),
                [
                    'br' => [],
                ]
            ),
            '<a href="' . esc_url($dashboard_url) . '" target="_blank">',
            '</a>',
            '<a href="' . esc_url($licensing_tab_url) . '">',
            '</a>'
        );
    } else {
        echo '<b>' . esc_html__(
            'Please contact the Duplicator license manager to activate it.',
            'duplicator-pro'
        ) . '</b>';
    }
    ?>
</div>