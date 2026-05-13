<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Models\GlobalEntity;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 * @var \Duplicator\Package\DupPackage $package
 */

$package = $tplData['package'];
$global  = GlobalEntity::getInstance();
?>

<!-- ================================================================
ARCHIVE
================================================================ -->
<div class="details-title">
    <i class="far fa-file-archive fa-sm fa-fw"></i>&nbsp;<?php esc_html_e('Archive', 'duplicator-pro'); ?>
    <sup class="dup-small-ext-type">
        <?php if ($package->Installer->isSecure()) : ?>
            <i class="fas fa-lock fa-fw fa-sm" title="<?php esc_html_e('Requires Password to Extract', 'duplicator-pro'); ?>"></i>&nbsp;
        <?php endif; ?>
        <?php echo esc_html($global->getArchiveExtensionType()); ?>
    </sup>
</div>
<div class="scan-header scan-item-first">
    <i class="fas fa-folder-open fa-sm"></i>
    <?php esc_html_e("Files", 'duplicator-pro'); ?>
    <div class="scan-header-details">
        <div class="dup-scan-filter-status">
            <?php if ($package->isDBOnly()) { ?>
                <i class="fa fa-filter fa-sm"></i> <?php esc_html_e('Database Only', 'duplicator-pro'); ?>
            <?php } elseif ($package->Archive->FilterOn) { ?>
                <i class="fa fa-filter fa-sm"></i> <?php esc_html_e('Enabled', 'duplicator-pro'); ?>
            <?php } ?>
        </div>

        <div id="data-arc-size1"></div>
        <i class="fa fa-question-circle data-size-help"
            data-tooltip-title="<?php esc_attr_e("File Size:", 'duplicator-pro'); ?>"
            data-tooltip="<?php
                            esc_html_e(
                                'The files size represents only the included files before compression is applied.
                    It does not include the size of the database script and in most cases the Backup size
                    once completed will be smaller than this number unless shell execution zip with no compression is enabled.',
                                'duplicator-pro'
                            ); ?>"></i>
        <div class="dup-data-size-uncompressed"><?php esc_html_e("uncompressed", 'duplicator-pro'); ?></div>
    </div>
</div>
<?php if ($package->isDBOnly()) {
    $tplMng->render('admin_pages/packages/scan/items/archive/files_db_only');
} elseif ($global->skip_archive_scan) {
    $tplMng->render('admin_pages/packages/scan/items/archive/files_skip_scan');
} else {
    // SIZE CHECKS
    $tplMng->render('admin_pages/packages/scan/items/archive/files');
    // ADDON SITES
    $tplMng->render('admin_pages/packages/scan/items/archive/addons');
    // UNREADABLE FILES
    $tplMng->render('admin_pages/packages/scan/items/archive/unreadable');
} ?>
<?php if (is_multisite()) {
    $tplMng->render('admin_pages/packages/scan/items/archive/multisite');
} ?>
