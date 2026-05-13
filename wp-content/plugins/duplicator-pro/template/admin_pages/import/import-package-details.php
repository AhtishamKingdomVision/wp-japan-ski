<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Installer\Package\PComponents;
use Duplicator\Libs\Snap\SnapString;
use Duplicator\Package\Create\BuildComponents;
use Duplicator\Package\Import\PackageImporter;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 */

/* passed values */
/** @var PackageImporter $importObj  */
$importObj = $tplData['importObj'];

if (!$importObj instanceof PackageImporter) {
    return;
}
?>
<div class="dupli-import-package-detail-content">
    <?php
    $errorMsg = '';
    if (!$importObj->encryptCheck($errorMsg)) {
        ?>
        <p class="maroon">
            <b>
                <i class="fas fa-exclamation-triangle"></i>
                <?php
                echo wp_kses(
                    $errorMsg,
                    [
                        'a' => [
                            'href'   => [],
                            'target' => [],
                        ],
                    ]
                );
                ?>
            </b>
        </p>
        <?php
    } elseif (!$importObj->passwordCheck()) {
        ?>
        <p class="maroon">
            <b><i class="fas fa-exclamation-triangle"></i> <?php esc_html_e('Valid password required', 'duplicator-pro'); ?></b>
        </p>
        <div class="dup-import-archive-password-request">
            <input type="password" class="dup-import-archive-password" name="password" value="">
            <button type="button" class="dup-import-set-archive-password button">
                <?php esc_html_e('Submit', 'duplicator-pro'); ?>
            </button>
        </div>
        <?php
    } elseif (!$importObj->isImportable($importFailMessage)) {
        ?>
        <p class="maroon">
            <b>
                <i class="fas fa-exclamation-triangle"></i>
                <?php
                echo wp_kses(
                    $importFailMessage,
                    [
                        'br' => [],
                        'a'  => [
                            'href'   => [],
                            'target' => [],
                        ],
                    ]
                );
                ?>
            </b>
        </p>
        <?php
    } elseif ($importObj->haveImportWaring($importWarnMessage)) {
        ?>
        <p class="gray">
            <b><i class="fas fa-exclamation-triangle"></i> <?php echo wp_kses($importWarnMessage, ['br' => []]); ?></b>
        </p>
        <?php
    } else {
        ?>
        <p class="green">
            <b>
                <i class="fas fa-check-circle"></i>
                <?php esc_html_e('This Backup is ready to install, click the continue button to proceed.', 'duplicator-pro'); ?>
            </b><br />
            <b><?php esc_html_e('The information below is related to the Backup and the source site where the Backup was created.', 'duplicator-pro'); ?></b>
        </p>
        <?php
    }

    if ($importObj->isValid()) {
        ?>
        <ul class="no-bullet">
            <li>
                <span class="label title"><?php esc_html_e('Site Details:', 'duplicator-pro'); ?></span>
            </li>
            <li>
                <span class="label"><?php esc_html_e('URL', 'duplicator-pro'); ?>:</span>
                <span class="value"><?php echo esc_html($importObj->getHomeUrl()); ?></span>
            </li>
            <li>
                <span class="label"><?php esc_html_e('Path', 'duplicator-pro'); ?>:</span>
                <span class="value"><?php echo esc_html($importObj->getHomePath()); ?></span>
            </li>
            <li>
                <span class="label title"><?php esc_html_e('Versions:', 'duplicator-pro'); ?></span>
            </li>
            <li>
                <span class="label"><?php esc_html_e('Duplicator', 'duplicator-pro'); ?>:</span>
                <span class="value"><?php echo esc_html($importObj->getDupVersion()); ?></span>
            </li>
            <li>
                <span class="label"><?php esc_html_e('WordPress', 'duplicator-pro'); ?>:</span>
                <span class="value"><?php echo esc_html($importObj->getWPVersion()); ?></span>
            </li>
            <li>
                <span class="label"><?php esc_html_e('PHP', 'duplicator-pro'); ?>:</span>
                <span class="value"><?php echo esc_html($importObj->getPhpVersion()); ?></span>
            </li>
            <?php if ($importObj->getPackageComponents() !== false) { ?>
                <li>
                    <span class="label title"><?php esc_html_e('Backup components:', 'duplicator-pro'); ?></span>
                </li>
                <?php
                $packComponents = $importObj->getPackageComponents();
                foreach (BuildComponents::COMPONENTS_DEFAULT as $component) {
                    ?>
                    <li>
                        <span class="label"><?php echo esc_html(BuildComponents::getLabel($component)); ?>:</span>
                        <span class="value">
                            <?php if (in_array($component, $packComponents)) { ?>
                                <i class="fas fa-check-circle green"></i> <?php esc_html_e('included', 'duplicator-pro'); ?>
                            <?php } else { ?>
                                <i class="fas fa-times-circle maroon"></i> <?php esc_html_e('excluded', 'duplicator-pro'); ?>
                            <?php } ?>
                        </span>
                    </li>
                <?php } ?>
            <?php } ?>
            <li>
                <span class="label title"><?php esc_html_e('Archive:', 'duplicator-pro'); ?></span>
            </li>
            <li>
                <span class="label"><?php esc_html_e('Created', 'duplicator-pro'); ?>:</span>
                <span class="value"><?php echo esc_html($importObj->getCreated()); ?></span>
            </li>
            <li>
                <span class="label"><?php esc_html_e('Size', 'duplicator-pro'); ?>:</span>
                <span class="value"><?php echo esc_html(SnapString::byteSize($importObj->getSize())); ?></span>
            </li>
            <?php if (!$importObj->isLite()) { ?>
                <li>
                    <span class="label"><?php esc_html_e('Folders', 'duplicator-pro'); ?>:</span>
                    <span class="value"><?php echo esc_html(number_format($importObj->getNumFolders())); ?></span>
                </li>
                <li>
                    <span class="label"><?php esc_html_e('Files', 'duplicator-pro'); ?>:</span>
                    <span class="value"><?php echo esc_html(number_format($importObj->getNumFiles())); ?></span>
                </li>
            <?php } ?>
            <li>
                <span class="label title"><?php esc_html_e('Database:', 'duplicator-pro'); ?></span>
            </li>
            <?php
            if (
                $importObj->getPackageComponents() === false ||
                in_array(PComponents::COMP_DB, $importObj->getPackageComponents())
            ) {
                ?>
                <li>
                    <span class="label"><?php esc_html_e('Size', 'duplicator-pro'); ?>:</span>
                    <span class="value"><?php echo esc_html($importObj->getDbSize()); ?></span>
                </li>
                <li>
                    <span class="label"><?php esc_html_e('Tables', 'duplicator-pro'); ?>:</span>
                    <span class="value"><?php echo (int) $importObj->getNumTables(); ?></span>
                </li>
                <li>
                    <span class="label"><?php esc_html_e('Rows', 'duplicator-pro'); ?>:</span>
                    <span class="value"><?php echo esc_html(number_format($importObj->getNumRows())); ?></span>
                </li>
            <?php } else { ?>
                <li>
                    <span class="label"><?php esc_html_e('Database', 'duplicator-pro'); ?>:</span>
                    <span class="value"><?php esc_html_e('Not Included', 'duplicator-pro'); ?></span>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>
</div>