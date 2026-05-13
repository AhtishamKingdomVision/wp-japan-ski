<?php

/**
 * @package Duplicator
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
<div class="dupli-recovery-widget-wrapper" >
    <?php if ($tplData['displayDetails']) { ?>
    <div class="dupli-recovery-point-details margin-bottom-1">
        <?php $tplMng->render('admin_pages/tools/recovery/widget/recovery-widget-details', $tplData); ?>
    </div>
    <?php } ?>
    <?php if ($tplData['selector']) {
        $tplMng->render('admin_pages/tools/recovery/widget/recovery-widget-selector', $tplData);
    } ?>
    <div class="dupli-recovery-point-actions">
        <?php if ($tplData['recoverablePackages']) {
            $tplMng->render('admin_pages/tools/recovery/widget/recovery-widget-link-actions', $tplData);
        } ?>
    </div>
</div>
