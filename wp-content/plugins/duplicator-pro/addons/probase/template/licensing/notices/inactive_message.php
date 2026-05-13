<?php

/**
 * @package Duplicator
 */

use Duplicator\Controllers\SettingsPageController;
use Duplicator\Core\CapMng;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Views\ViewHelper;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

$img_url           = plugins_url('duplicator-pro/assets/img/warning.png');
$problem_text      = $tplData['problem'];
$licensing_tab_url = ControllersManager::getMenuLink(
    ControllersManager::SETTINGS_SUBMENU_SLUG,
    SettingsPageController::L2_SLUG_GENERAL
);
?>
<span class='dashicons dashicons-warning'></span>
<div class="dup-sub-content">
    <h3>
        <?php
        printf(
            esc_html_x('Your Duplicator Pro license key is %1$s ...', '%1$s represent the license status', 'duplicator-pro'),
            esc_html($tplData['problem'])
        );
        ?>
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
                    '<b>Please %1$sActivate Your License%2$s</b>. If you do not have a license key go to %3$sduplicator.com%4$s to get it.',
                    '1 and 2 are opening and 3 and 4 are closing anchor tags (<a> and </a>)',
                    'duplicator-pro'
                ),
                ViewHelper::GEN_KSES_TAGS
            ),
            '<a href="' . esc_url($licensing_tab_url) . '">',
            '</a>',
            '<a target="_blank" href="' . esc_url(DUPLICATOR_BLOG_URL . 'my-account') . '">',
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
