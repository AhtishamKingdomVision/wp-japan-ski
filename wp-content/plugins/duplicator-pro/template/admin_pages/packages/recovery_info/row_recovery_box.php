<?php

/**
 * Duplicator Backup row in table Backups list
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Package\Recovery\RecoveryPackage;
use Duplicator\Views\ViewHelper;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var \Duplicator\Package\DupPackage $package
 */

$package       = $tplData['package'];
$isRecoverable = RecoveryPackage::isPackageIdRecoverable($package->getId());

if ($isRecoverable) {
    $tplMng->render('admin_pages/packages/recovery_info/row_recovery_box_available');
} else {
    $tplMng->render('admin_pages/packages/recovery_info/row_recovery_box_unavailable');
}
?>
<hr class="margin-top-1 margin-bottom-1">
<small><i>
        <?php
        echo wp_kses(
            sprintf(
                _x(
                    '%1$sDisaster Recovery%2$s makes it quick and easy to restore your site during an emergency.
            You don’t need WordPress to be working for this.
            Just keep the Disaster Recovery Link in a safe spot and use it when needed, or use the Launcher to restore your backup.',
                    '%1$s and %2$s represents the opening and closing HTML tags for an anchor or link',
                    'duplicator-pro'
                ),
                '<a href="' . esc_url(DUPLICATOR_DUPLICATOR_DOCS_URL . 'tools-recovery') . '" target="_blank">',
                '</a>'
            ),
            ViewHelper::GEN_KSES_TAGS
        );
        ?>
        <br>
        <?php
        echo wp_kses(
            sprintf(
                _x(
                    'If your backup isn’t eligible for Disaster Recovery, you can still use the %1$sRestore Backup button.%2$s',
                    '%1$s and %2$s represents the opening and closing HTML tags for an anchor or link',
                    'duplicator-pro'
                ),
                '<a href="' . esc_url(DUPLICATOR_DUPLICATOR_DOCS_URL . 'restoring-your-backup') . '" target="_blank">',
                '</a>'
            ),
            ViewHelper::GEN_KSES_TAGS
        );
        ?>
    </i></small>
