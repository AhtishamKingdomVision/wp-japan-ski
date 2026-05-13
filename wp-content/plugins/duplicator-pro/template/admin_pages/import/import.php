<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 */
?> 

<div class="dupli-tab-content-wrapper" >
    <div id="dupli-import-phase-one" >
        <?php $tplMng->render('admin_pages/import/step1/import-step1'); ?>
    </div>
    <div id="dupli-import-phase-two" class="no-display" >
        <?php $tplMng->render('admin_pages/import/import-step2'); ?>
    </div>
</div>
<?php
$tplMng->render('admin_pages/tools/recovery/widget/recovery-widget-scripts');

$tplMng->render('admin_pages/import/import-scripts');
