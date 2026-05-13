<?php

/**
 * Duplicator messages sections
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Addons\DropboxAddon\Models\DropboxStorage;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 */
?>
<div class="dup-accordion-wrapper display-separators close" >
    <div class="accordion-header" >
        <h3 class="title"><?php echo esc_html(DropboxStorage::getStypeName()); ?></h3>
    </div>
    <div class="accordion-content">
        <label class="lbl-larger" >
            <?php esc_html_e("Upload Chunk Size", 'duplicator-pro'); ?>
        </label>
        <div class="margin-bottom-1" >
            <input
                class="text-right inline-display width-small margin-bottom-0"
                name="dropbox_upload_chunksize_in_kb"
                id="dropbox_upload_chunksize_in_kb"
                type="number"
                min="100"
                data-parsley-required
                data-parsley-type="number"
                data-parsley-errors-container="#dropbox_upload_chunksize_in_kb_error_container"
                value="<?php echo (int) $tplData['uploadChunkSize']; ?>"
            >&nbsp;<b>KB</b>
            <div id="dropbox_upload_chunksize_in_kb_error_container" class="duplicator-error-container"></div>
            <p class="description">
                <?php esc_html_e('How much should be uploaded to Dropbox per attempt. Higher=faster but less reliable.', 'duplicator-pro'); ?>
            </p>
        </div>

        <label class="lbl-larger" >
            <?php esc_html_e("Download Chunk Size", 'duplicator-pro'); ?>
        </label>
        <div class="margin-bottom-1" >
            <input
                class="text-right inline-display width-small margin-bottom-0"
                name="dropbox_download_chunksize_in_kb"
                id="dropbox_download_chunksize_in_kb"
                type="number"
                min="100"
                data-parsley-required
                data-parsley-type="number"
                data-parsley-errors-container="#dropbox_download_chunksize_in_kb_error_container"
                value="<?php echo (int) $tplData['downloadChunkSize']; ?>"
            >&nbsp;<b>KB</b>
            <div id="dropbox_download_chunksize_in_kb_error_container" class="duplicator-error-container"></div>
            <p class="description">
                <?php esc_html_e('How much should be downloaded from Dropbox per attempt. Higher=faster but less reliable.', 'duplicator-pro'); ?>
            </p>
        </div>
    </div>
</div>