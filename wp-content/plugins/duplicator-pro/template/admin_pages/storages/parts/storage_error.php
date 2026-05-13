<?php

/**
 * Duplicator storage error template
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Ajax\ServicesTools;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Utils\Support\SupportToolkit;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var Exception $e
 */
$e            = $tplData["exception"];
$settings_url = ControllersManager::getMenuLink(ControllersManager::SETTINGS_SUBMENU_SLUG);
$fullError    = $e->getMessage() . "\n\n" . $e->getTraceAsString();
?>
<div class="dup-storage-error-wrapper">
    <div class="error-txt">
        <h3><?php esc_html_e('Oops, there was a problem...', 'duplicator-pro'); ?></h3>
        <p>
            <?php esc_html_e('An error has occurred while trying to read a storage item!  ', 'duplicator-pro'); ?>
            <?php esc_html_e(
                'To resolve this issue edit the storage, re-enter its information and if appropriate re-authorize the plugin.  ',
                'duplicator-pro'
            ); ?>
        </p>
        <p>
            <?php esc_html_e(
                'This problem can be due to a security plugin changing keys in wp-config.php, causing the storage information to become unreadable.',
                'duplicator-pro'
            ); ?>
            <?php
            printf(
                esc_html__(
                    'If such a plugin is doing this then either disable the key changing functionality 
                    in the security plugin or go to %1$sDuplicator Pro > Settings%2$s and disable settings encryption.',
                    'duplicator-pro'
                ),
                '<a href="' . esc_url($settings_url) . '">',
                '</a>'
            );
            ?>
        </p>
        <p>
            <?php esc_html_e('If the problem persists after doing these things then please contact the support team.', 'duplicator-pro'); ?>
            <?php if (SupportToolkit::isAvailable()) {
                printf(
                    esc_html__(
                        'Please make sure to attach the %1$sdiagnostic data%2$s and the error message below to your ticket.',
                        'duplicator-pro'
                    ),
                    '<a href="' . esc_url(SupportToolkit::getSupportToolkitDownloadUrl()) . '">',
                    '</a>'
                );
            } ?>
        </p>
    </div>
    <div class="error-trace-copy">
        <textarea class="dup-error-message-textarea" disabled ><?php echo esc_textarea($fullError); ?></textarea>
        <button
            data-dup-copy-value="<?php echo esc_attr($fullError); ?>"
            data-dup-copy-title="<?php echo esc_attr("Copy Error Message to clipboard"); ?>"
            data-dup-copied-title="<?php echo esc_attr("Error Message copied to clipboard"); ?>"
            class="button dup-btn-copy-error-message">
            <?php esc_html_e('Copy error details', 'duplicator-pro'); ?>
        </button>
    </div>
</div>
