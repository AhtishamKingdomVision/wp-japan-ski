<?php

/**
 * @package Duplicator
 */

defined("ABSPATH") or die("");

use Duplicator\Controllers\SettingsPageController;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Views\AdminNotices;

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 * @var bool $blur
 */
$blur = $tplData['blur'];
/** @var int */
$storage_id = $tplData["storage_id"];
/** @var Duplicator\Models\Storages\AbstractStorageEntity */
$storage = $tplData["storage"];
/** @var ?string */
$error_message = $tplData["error_message"];
/** @var ?string */
$success_message = $tplData["success_message"];

$relativeEditUrl = ControllersManager::getMenuLink(
    ControllersManager::STORAGE_SUBMENU_SLUG,
    SettingsPageController::L2_SLUG_STORAGE,
    null,
    [ControllersManager::QUERY_STRING_INNER_PAGE => 'edit']
);

$fullEditUrl = ControllersManager::getMenuLink(
    ControllersManager::STORAGE_SUBMENU_SLUG,
    SettingsPageController::L2_SLUG_STORAGE,
    null,
    [ControllersManager::QUERY_STRING_INNER_PAGE => 'edit'],
    false
);

?>
<form 
    id="dup-storage-form" 
    class="dup-monitored-form <?php echo ($blur ? 'dup-mock-blur' : ''); ?>"
    action="<?php echo esc_url($relativeEditUrl); ?>" 
    method="post" 
    data-parsley-ui-enabled="true" 
    target="_self"
>
    <?php $tplData['actions']['save']->getActionNonceFileds(); ?>
    <input type="hidden" name="storage_id" id="storage_id" value="<?php echo (int) $storage->getId(); ?>">

    <?php
    $tplMng->render('admin_pages/storages/parts/edit_toolbar');

    if (!is_null($error_message)) {
        AdminNotices::displayGeneralAdminNotice($error_message, AdminNotices::GEN_ERROR_NOTICE, true);
    } elseif (!is_null($success_message)) {
        AdminNotices::displayGeneralAdminNotice($success_message, AdminNotices::GEN_SUCCESS_NOTICE, true);
    }
    ?>
    <div class="form-table dup-settings-wrapper">
        <label class="lbl-larger">
            <?php esc_html_e("Name", 'duplicator-pro'); ?>
        </label>
        <div class="margin-bottom-1">
            <?php if ($storage->isDefault()) {
                esc_html_e('Default', 'duplicator-pro');
                $tCont = __('The "Default" storage type is a built in type that cannot be removed.', 'duplicator-pro') . ' ' .
                __(' This storage type is used by default should no other storage types be available.', 'duplicator-pro') . ' ' .
                __('This storage type is always stored to the local server.', 'duplicator-pro');
                ?>
                <i 
                    class="fa-solid fa-question-circle fa-sm dark-gray-color"
                    data-tooltip-title="<?php esc_attr_e("Default Storage Type", 'duplicator-pro'); ?>"
                    data-tooltip="<?php echo esc_attr($tCont); ?>"
                >
                </i>
            <?php } else { ?>
                <input 
                    data-parsley-errors-container="#name_error_container" 
                    type="text" 
                    id="name" 
                    name="name" 
                    value="<?php echo esc_attr($storage->getName()); ?>" autocomplete="off" 
                >
            <?php } ?>
            <div id="name_error_container" class="duplicator-error-container"></div>
        </div>
        <label class="lbl-larger">
            <?php esc_html_e("Notes", 'duplicator-pro'); ?>
        </label>
        <div class="margin-bottom-1">
            <textarea id="notes" name="notes" style="width:100%; max-width: 500px"><?php echo esc_textarea($storage->getNotes()); ?></textarea>
        </div>
        <label class="lbl-larger">
            <?php esc_html_e("Type", 'duplicator-pro'); ?>
        </label>
        <div class="margin-bottom-0">
            <?php $tplMng->render('admin_pages/storages/parts/storage_type_select'); ?>
        </div>
    </div>
    <hr size="1" />
    <?php
    if ($storage->getId() > 0) {
        $storage->renderConfigFields();
    } else {
        $types = AbstractStorageEntity::getResisteredTypes();
        foreach ($types as $type) {
            AbstractStorageEntity::renderSTypeConfigFields($type);
        }
    }

    $tplMng->render('admin_pages/storages/parts/test_button');
    ?>
    <br style="clear:both" />
    <button 
        id="button_save_provider" 
        class="button primary small" 
        type="submit"
    >
        <?php esc_html_e('Save Provider', 'duplicator-pro'); ?>
    </button>
</form>
<?php
$tplMng->render('admin_pages/storages/storage_scripts');