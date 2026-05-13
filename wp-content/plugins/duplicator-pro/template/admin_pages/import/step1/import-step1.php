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
 * @var bool $blur
 */

$blur = $tplData['blur'];

if ($tplData['adminMessageViewModeSwtich'] && !$blur) {
    $tplMng->render('admin_pages/import/step1/message-view-mode-switch');
}

?>

<div class="dupli-import-header" >
    <h2 class="title">
        <b>
            <?php printf(esc_html__("Step %s of 2: Upload Backup", 'duplicator-pro'), '<span class="red">1</span>'); ?>
        </b>
    </h2>
</div>

<div class="dup-import-header-content-wrapper <?php echo ($blur ? 'dup-mock-blur' : ''); ?>" >
    <?php $tplMng->render('admin_pages/import/step1/add-file-area'); ?>
    <?php $tplMng->render('admin_pages/import/step1/packages-list'); ?>
</div>


