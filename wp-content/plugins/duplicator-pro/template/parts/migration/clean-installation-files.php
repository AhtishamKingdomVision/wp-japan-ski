<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Views\ViewHelper;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 */

$fileRemoved = $tplData['installerCleanupFiles'];
$removeError = $tplData['installerCleanupError'];
$purgeCaches = $tplData['installerCleanupPurge'];
?>
<div class="dupli-diagnostic-action-installer">
    <p>
        <b><?php esc_html_e('Installation cleanup ran!', 'duplicator-pro'); ?></b>
    </p>
    <?php
    if (count($fileRemoved) === 0) {
        ?>
        <p>
            <b><?php esc_html_e('No Duplicator files were found on this WordPress Site.', 'duplicator-pro'); ?></b>
        </p> <?php
    } else {
        foreach ($fileRemoved as $path => $success) {
            if ($success) {
                ?><div class="success">
                    <i class="fa fa-check"></i> <?php esc_html_e("Removed", 'duplicator-pro'); ?> - <?php echo esc_html($path); ?>
                </div><?php
            } else {
                ?><div class="failed">
                    <i class='fa fa-exclamation-triangle'></i> <?php esc_html_e("Found", 'duplicator-pro'); ?> - <?php echo esc_html($path); ?>
                </div>
                <?php
            }
        }
    }

    foreach ($purgeCaches as $message) {
        ?><div class="success">
            <i class="fa fa-check"></i> <?php echo wp_kses($message, ViewHelper::GEN_KSES_TAGS); ?>
        </div>
        <?php
    }

    if ($removeError) {
        ?>
        <p>
        <?php esc_html_e('Some of the installer files did not get removed, ', 'duplicator-pro'); ?>
            <span class="link-style" onclick="DupliJs.Tools.removeInstallerFiles();">
        <?php esc_html_e('please retry the installer cleanup process', 'duplicator-pro'); ?>
            </span><br>
        <?php esc_html_e(' If this process continues please see the previous FAQ link.', 'duplicator-pro'); ?>
        </p>
        <?php
    }
    ?>
    <div style="font-style: italic; max-width:900px; padding:10px 0 25px 0;">
        <p>
            <b><i class="fa fa-shield-alt"></i> <?php esc_html_e('Security Notes', 'duplicator-pro'); ?>:</b>
            <?php
            esc_html_e(
                'If the installer files do not successfully get removed with this action, 
                then they WILL need to be removed manually through your hosts control panel or FTP. 
                Please remove all installer files to avoid any security issues on this site.',
                'duplicator-pro'
            );
            ?><br>
            <?php
            printf(
                esc_html_x(
                    'For more details please visit the FAQ link %1$sWhich files need to be removed after an install?%2$s',
                    '%1$s and %2$s represents the opening and closing HTML tags for an anchor or link',
                    'duplicator-pro'
                ),
                '<a href="' . esc_url(DUPLICATOR_DUPLICATOR_DOCS_URL . 'which-files-need-to-be-removed-after-an-install') . '" target="_blank">',
                '</a>'
            );
            ?>
        </p>
        <p>
            <b><i class="fa fa-thumbs-up"></i> <?php esc_html_e('Help Support Duplicator', 'duplicator-pro'); ?>:</b>
            <?php
            esc_html_e(
                'The Duplicator team has worked many years to make moving a WordPress site a much easier process. ',
                'duplicator-pro'
            );
            ?>
            <br>
            <?php
            printf(
                esc_html_x(
                    'Show your support with a %1$s5 star review%2$s! We would be thrilled if you could!',
                    '%1$s and %2$s represents the opening and closing HTML tags for an anchor or link',
                    'duplicator-pro'
                ),
                '<a href="https://wordpress.org/support/plugin/duplicator/reviews/?filter=5" target="_blank">',
                '</a>'
            );
            ?>
        </p>
    </div>
</div>