<?php

/**
 * @package Duplicator
 */

use Duplicator\Controllers\SettingsPageController;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

?>
<form 
    id="dup-settings-form" 
    action="<?php echo esc_url($ctrlMng->getCurrentLink()); ?>" 
    method="post"
    data-parsley-validate
>
    <?php $tplData['actions'][SettingsPageController::ACTION_SAVE_STORAGE]->getActionNonceFileds(); ?>

    <div class="dup-settings-wrapper margin-bottom-1">
        <?php $tplMng->render('admin_pages/settings/storage/storage_general'); ?>
        <hr>
        <?php $tplMng->render('admin_pages/settings/storage/storage_ssl'); ?>
        <?php $tplMng->render('admin_pages/settings/storage/storages_global_options'); ?>
    </div>

    <p class="submit dupli-save-submit">
        <input 
            type="submit" 
            name="submit" 
            id="submit" 
            class="button primary small" 
            value="<?php esc_attr_e('Save Settings', 'duplicator-pro') ?>"
        >
    </p>
</form>