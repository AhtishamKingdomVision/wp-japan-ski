<?php

/**
 * @package Duplicator
 */

defined("ABSPATH") or die("");

use Duplicator\Controllers\ImportPageController;
use Duplicator\Controllers\SettingsPageController;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Models\GlobalEntity;

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

$global = GlobalEntity::getInstance();

?>
<form id="dup-settings-form" action="<?php echo esc_url(ControllersManager::getCurrentLink()); ?>" method="post" data-parsley-validate>
    <?php $tplMng->getAction(SettingsPageController::ACTION_IMPORT_SAVE_SETTINGS)->getActionNonceFileds(); ?>

    <h3 id="dupli-import-settings" class="title">
        <?php esc_html_e("Import Settings", 'duplicator-pro'); ?>
    </h3>
    <hr size="1" />
    <div class="dup-settings-wrapper margin-bottom-1">
        <label class="lbl-larger" for="input_import_chunk_size">
            <?php esc_html_e("Upload Chunk Size", 'duplicator-pro'); ?>
        </label>
        <div class="margin-bottom-1">
            <select name="import_chunk_size" id="input_import_chunk_size" class="postform width-medium margin-bottom-0">
                <?php foreach (ImportPageController::getChunkSizes() as $size => $label) { ?>
                    <option value="<?php echo esc_attr($size); ?>" <?php selected($global->import_chunk_size, $size); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php } ?>
            </select>
            <p class="description">
                <?php
                esc_html_e(
                    "If you have issue uploading a Backup start with a lower size. The upload chunk size is ordered from slowest to fastest.",
                    'duplicator-pro'
                );
                ?><br />
                <small>
                    <?php
                    esc_html_e("Note: This setting only applies to the 'Import File' option.", 'duplicator-pro');
                    ?>
                </small>
            </p>
        </div>

        <label class="lbl-larger" for="import_custom_path">
            <?php esc_html_e("Import custom path", 'duplicator-pro'); ?>
        </label>
        <div class="margin-bottom-1">
            <input
                class="width-xxlarge margin-bottom-0"
                type="text"
                name="import_custom_path"
                id="input_import_custom_path"
                value="<?php echo esc_attr($global->import_custom_path); ?>"
                placeholder="">
            <p class="description">
                <?php
                esc_html_e(
                    "Setting a custom path does not change the folder where Backups are uploaded but adds a folder to check for Backups list.",
                    'duplicator-pro'
                );
                ?>
                <br>
                <?php
                esc_html_e(
                    "This can be useful when you want to manually upload Backups to another location which can also be a local storage of current or other site.", // phpcs:ignore Generic.Files.LineLength
                    'duplicator-pro'
                );
                ?>
            </p>
        </div>

        <h3 class="title"><?php esc_html_e('Recovery', 'duplicator-pro') ?> </h3>
        <hr size="1" />

        <label class="lbl-larger" for="input_recovery_custom_path">
            <?php esc_html_e("Recovery custom path", 'duplicator-pro'); ?>
        </label>
        <div class="margin-bottom-1">
            <input
                class="width-xxlarge margin-bottom-0"
                type="text"
                name="recovery_custom_path"
                id="input_recovery_custom_path"
                value="<?php echo esc_attr($global->getRecoveryCustomPath()); ?>"
                placeholder="">
            <p class="description">
                <?php
                esc_html_e(
                    "Setting a custom path changes the location the recovery points are generated.",
                    'duplicator-pro'
                );
                ?>
            </p>
        </div>
    </div>
    <hr>
    <p class="submit dupli-save-submit">
        <input
            type="submit"
            name="submit"
            id="submit"
            class="button primary small"
            value="<?php esc_attr_e('Save Settings', 'duplicator-pro') ?>">
    </p>
</form>