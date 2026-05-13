<?php

/**
 * Duplicator messages sections
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Addons\OneDriveAddon\Models\OneDriveStorage;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 */
?>
<div class="dup-accordion-wrapper display-separators close">
    <div class="accordion-header">
        <h3 class="title"><?php echo esc_html(OneDriveStorage::getStypeName()); ?></h3>
    </div>
    <div class="accordion-content">
        <label class="lbl-larger">
            <?php esc_html_e("Upload Chunk Size", 'duplicator-pro'); ?>
        </label>
        <div class="margin-bottom-1">
            <input
                class="text-right inline-display width-small margin-bottom-0"
                name="onedrive_upload_chunksize_in_kb"
                id="onedrive_upload_chunksize_in_kb"
                type="number"
                min="<?php echo intval(OneDriveStorage::UPLOAD_CHUNK_MIN_SIZE_IN_KB); ?>"
                step="320"
                data-parsley-required
                data-parsley-type="number"
                data-parsley-errors-container="#onedrive_upload_chunksize_in_kb_error_container"
                value="<?php echo (int) $tplData['uploadChunkSize']; ?>">&nbsp;<b>KB</b>
            <div id="onedrive_upload_chunksize_in_kb_error_container" class="duplicator-error-container"></div>
            <p class="description">
                <?php
                printf(
                    esc_html__(
                        'How much should be uploaded to OneDrive per attempt. It should be multiple of %1$dkb. 
                        Higher=faster but less reliable. Default size %2$dkb. Min size %3$dkb.',
                        'duplicator-pro'
                    ),
                    (int) OneDriveStorage::UPLOAD_CHUNK_MIN_SIZE_IN_KB,
                    (int) OneDriveStorage::UPLOAD_CHUNK_DEFAULT_SIZE_IN_KB,
                    (int) OneDriveStorage::UPLOAD_CHUNK_MIN_SIZE_IN_KB
                );
                ?>
            </p>
        </div>

        <label class="lbl-larger">
            <?php esc_html_e("Download Chunk Size", 'duplicator-pro'); ?>
        </label>
        <div class="margin-bottom-1">
            <input
                class="text-right inline-display width-small margin-bottom-0"
                name="onedrive_download_chunksize_in_kb"
                id="onedrive_download_chunksize_in_kb"
                type="number"
                min="<?php echo (int) OneDriveStorage::MIN_DOWNLOAD_CHUNK_SIZE_IN_KB; ?>"
                data-parsley-required
                data-parsley-type="number"
                data-parsley-errors-container="#onedrive_download_chunksize_in_kb_error_container"
                value="<?php echo (int) $tplData['downloadChunkSize']; ?>">&nbsp;<b>KB</b>
            <div id="onedrive_download_chunksize_in_kb_error_container" class="duplicator-error-container"></div>
            <p class="description">
                <?php esc_html_e('How much should be downloaded from OneDrive per attempt.', 'duplicator-pro');
                printf(
                    esc_html__('Default size %1$dkb. Min size %2$dkb.', 'duplicator-pro'),
                    (int) OneDriveStorage::DEFAULT_DOWNLOAD_CHUNK_SIZE_IN_KB,
                    (int) OneDriveStorage::MIN_DOWNLOAD_CHUNK_SIZE_IN_KB
                ); ?>
            </p>
        </div>
    </div>
</div>