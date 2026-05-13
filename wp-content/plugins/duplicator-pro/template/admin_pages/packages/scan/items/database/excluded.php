<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */
?>

<div class="scan-item">
    <div class="title" onclick="DupliJs.Pack.toggleScanItem(this);">
        <div class="text"><i class="fa fa-caret-right"></i> <?php esc_html_e('Database excluded', 'duplicator-pro');?></div>
        <div id="data-db-status-size1"></div>
    </div>
    <div class="info">
        <?php esc_html_e(
            'The database is excluded from the Backup build process. 
            To include it make sure to check the "Database" Backup component checkbox at Step 1 of the build process.',
            'duplicator-pro'
        ); ?>
    </div>
</div> 
