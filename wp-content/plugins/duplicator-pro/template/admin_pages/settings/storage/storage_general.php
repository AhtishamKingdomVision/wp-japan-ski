<?php

/**
 * @package Duplicator
 */

defined("ABSPATH") or die("");

use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Models\GlobalEntity;
use Duplicator\Models\Storages\AbstractStorageEntity;

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

$global = GlobalEntity::getInstance();
?>
<h3 class="title">
    <?php esc_html_e("Storage Settings", 'duplicator-pro'); ?>
</h3>
<hr>
<label class="lbl-larger">
    <?php esc_html_e("Storage", 'duplicator-pro'); ?>
</label>
<div class="margin-bottom-1">
    <p>
        <?php esc_html_e("Default Local Storage Path", 'duplicator-pro'); ?>:
        <b>
            <?php echo esc_html(SnapIO::safePath(DUPLICATOR_SSDIR_PATH)); ?>
        </b>
    </p>

    <input
        type="checkbox"
        name="_storage_htaccess_off"
        id="_storage_htaccess_off"
        value="1"
        class="margin-0"
        <?php checked($global->storage_htaccess_off); ?>>
    <label for="_storage_htaccess_off">
        <?php esc_html_e("Disable .htaccess File In Storage Directory", 'duplicator-pro') ?>
    </label>
    <p class="description">
        <?php esc_html_e("Disable if issues occur when downloading installer/archive files.", 'duplicator-pro'); ?>
    </p>
</div>

<label class="lbl-larger">
    <?php esc_html_e("Max Retries", 'duplicator-pro'); ?>
</label>
<div class="margin-bottom-1">
    <input
        class="width-small margin-0"
        type="text"
        name="max_storage_retries"
        id="max_storage_retries"
        data-parsley-required data-parsley-min="0"
        data-parsley-type="number"
        data-parsley-errors-container="#max_storage_retries_error_container"
        value="<?php echo (int) $global->max_storage_retries; ?>">
    <div id="max_storage_retries_error_container" class="duplicator-error-container"></div>
    <p class="description">
        <?php esc_html_e('Max upload/copy retries to attempt after failure encountered.', 'duplicator-pro'); ?>
    </p>
</div>

<label class="lbl-larger">
    <?php esc_html_e("Delete Backup Records", 'duplicator-pro'); ?>
    <i
        class="fa-solid fa-question-circle fa-sm dark-gray-color"
        data-tooltip-title="<?php esc_attr_e('Delete Backup Records', 'duplicator-pro'); ?>"
        data-tooltip="<?php echo esc_attr($tplMng->render('admin_pages/settings/storage/backup_record_delete_tooltip', [], true)); ?>">
    </i>
</label>
<div class="margin-bottom-1">
    <select
        class="width-large margin-0"
        name="purge_backup_records"
        id="purge_backup_records"
        data-parsley-required data-parsley-min="0"
        data-parsley-errors-container="#purge_backup_records_error_container"
        value="<?php echo (int) $global->getPurgeBackupRecords(); ?>">
        <option
            value="<?php echo (int) AbstractStorageEntity::BACKUP_RECORDS_REMOVE_ALL; ?>"
            <?php selected($global->getPurgeBackupRecords(), AbstractStorageEntity::BACKUP_RECORDS_REMOVE_ALL); ?>>
            When the backup archive is removed from all storages
        </option>
        <option
            value="<?php echo (int) AbstractStorageEntity::BACKUP_RECORDS_REMOVE_DEFAULT; ?>"
            <?php selected($global->getPurgeBackupRecords(), AbstractStorageEntity::BACKUP_RECORDS_REMOVE_DEFAULT); ?>>
            When maximum is reached for Default Local Storage
        </option>
        <option
            value="<?php echo (int) AbstractStorageEntity::BACKUP_RECORDS_REMOVE_NEVER; ?>"
            <?php selected($global->getPurgeBackupRecords(), AbstractStorageEntity::BACKUP_RECORDS_REMOVE_NEVER); ?>>
            Never
        </option>
    </select>
    <div id="purge_backup_records_error_container" class="duplicator-error-container"></div>
    <p id="pruge_backup_records_desc_<?php echo (int) AbstractStorageEntity::BACKUP_RECORDS_REMOVE_ALL; ?>" class="description">
        <?php esc_html_e("This option determines backup record handling in case the \"Max Backups\" limit is reached.", 'duplicator-pro'); ?>
    </p>
</div>