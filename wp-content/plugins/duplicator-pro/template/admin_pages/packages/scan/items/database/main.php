<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Libs\WpUtils\WpDbUtils;

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
?>
<!-- ================================================================
DATABASE
================================================================ -->
<div class="scan-header">
    <i class="fas fa-database fa-fw fa-sm"></i>
    <?php esc_html_e("Database", 'duplicator-pro'); ?>
    <div class="scan-header-details">
        <small style="font-weight:normal; font-size:12px">
            <?php if ($package->Database->Compatible) { ?>
                <i style="color:maroon"><?php esc_html_e('Compatibility Mode Enabled', 'duplicator-pro'); ?></i>
            <?php } ?>
        </small>
        <div class="dup-scan-filter-status">
            <?php if ($package->Database->FilterOn) { ?>
                <i class="fa fa-filter fa-sm"></i>
                <?php esc_html_e('Enabled', 'duplicator-pro'); ?>
            <?php } ?>
        </div>
        <div id="data-db-size1"></div>
        <i class="fa fa-question-circle data-size-help"
            data-tooltip-title="<?php esc_attr_e("Database Size:", 'duplicator-pro'); ?>"
            data-tooltip="<?php
                            esc_html_e(
                                'The database size represents only the included tables. The process for gathering the size uses the query SHOW TABLE STATUS. 
                    The overall size of the database file can impact the final size of the Backup.',
                                'duplicator-pro'
                            ); ?>"></i>
        <div class="dup-data-size-uncompressed"><?php esc_html_e("uncompressed", 'duplicator-pro'); ?></div>
    </div>
</div>
<div id="dup-scan-db">
    <?php if ($package->isDBExcluded()) {
        $tplMng->render('admin_pages/packages/scan/items/database/excluded');
    } else {
        $tplMng->render('admin_pages/packages/scan/items/database/tables');

        if (WpDbUtils::getBuildMode() == WpDbUtils::BUILD_MODE_MYSQLDUMP) {
            $tplMng->render('admin_pages/packages/scan/items/database/mysqldump');
        }

        if (count($tplData['procedures']) > 0 || count($tplData['functions']) > 0) {
            $tplMng->render('admin_pages/packages/scan/items/database/procedures');
        }

        if (count($tplData['triggers'])) {
            $tplMng->render('admin_pages/packages/scan/items/database/triggers');
        }
    } ?>
</div>