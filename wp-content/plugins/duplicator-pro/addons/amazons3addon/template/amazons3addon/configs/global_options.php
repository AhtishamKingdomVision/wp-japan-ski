<?php

/**
 * Duplicator messages sections
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Addons\AmazonS3Addon\Models\AmazonS3Storage;

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
        <h3 class="title"><?php echo esc_html(AmazonS3Storage::getStypeName()); ?></h3>
    </div>
    <div class="accordion-content">
        <label class="lbl-larger" >
            <?php esc_html_e("Upload Chunk Size", 'duplicator-pro'); ?>
        </label>
        <div class="margin-bottom-1" >
            <input
                class="text-right inline-display width-small margin-bottom-0"
                name="s3_upload_part_size_in_kb"
                id="s3_upload_part_size_in_kb"
                type="number"
                min="<?php echo (int) AmazonS3Storage::UPLOAD_CHUNK_MIN_SIZE_IN_KB; ?>"
                max="<?php echo (int) AmazonS3Storage::UPLOAD_CHUNK_MAX_SIZE_IN_KB; ?>"
                data-parsley-required
                data-parsley-type="number"
                data-parsley-errors-container="#s3_upload_chunksize_in_kb_error_container"
                value="<?php echo (int) $tplData['uploadPartSizeInKb']; ?>"
            >&nbsp;<b>KB</b>
            <div id="s3_upload_chunksize_in_kb_error_container" class="duplicator-error-container"></div>
            <p class="description">
                <?php esc_html_e('How much should be uploaded to Amazon S3 per attempt. Higher=faster but less reliable.', 'duplicator-pro'); ?>
                <?php echo esc_html(sprintf(__('Min size %skb.', 'duplicator-pro'), AmazonS3Storage::UPLOAD_CHUNK_MIN_SIZE_IN_KB)); ?>
            </p>
        </div>

        <label class="lbl-larger" >
            <?php esc_html_e("Download Chunk Size", 'duplicator-pro'); ?>
        </label>
        <div class="margin-bottom-1" >
            <input
                class="text-right inline-display width-small margin-bottom-0"
                name="s3_download_part_size_in_kb"
                id="s3_download_part_size_in_kb"
                type="number"
                min="<?php echo (int) AmazonS3Storage::DOWNLOAD_CHUNK_MIN_SIZE_IN_KB; ?>"
                max="<?php echo (int) AmazonS3Storage::DOWNLOAD_CHUNK_MAX_SIZE_IN_KB; ?>"
                data-parsley-required
                data-parsley-type="number"
                data-parsley-errors-container="#s3_download_chunksize_in_kb_error_container"
                value="<?php echo (int) $tplData['downloadPartSizeInKb']; ?>"
            >&nbsp;<b>KB</b>
            <div id="s3_download_chunksize_in_kb_error_container" class="duplicator-error-container"></div>
            <p class="description">
                <?php esc_html_e('How much should be downloaded from Amazon S3 per attempt. Higher=faster but less reliable.', 'duplicator-pro'); ?>
                <?php echo esc_html(sprintf(__('Min size %skb.', 'duplicator-pro'), AmazonS3Storage::DOWNLOAD_CHUNK_MIN_SIZE_IN_KB)); ?>
            </p>
        </div>
    </div>
</div>
