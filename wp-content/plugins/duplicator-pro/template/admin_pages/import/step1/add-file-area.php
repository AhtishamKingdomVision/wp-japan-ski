<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Controllers\ImportPageController;
use Duplicator\Models\GlobalEntity;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 */

if (GlobalEntity::getInstance()->import_chunk_size == 0) {
    $footerChunkInfo = sprintf(__('<b>Chunk Size:</b> N/A &nbsp;|&nbsp; <b>Max Size:</b> %s', 'duplicator-pro'), size_format(wp_max_upload_size()));
    $toolTipContent  = __('If you need to upload a larger file, go to [Settings > Import] and set Upload Chunk Size', 'duplicator-pro');
} else {
    $footerChunkInfo = sprintf(
        __(
            '<b>Chunk Size:</b> %s &nbsp;|&nbsp; <b>Max Size:</b> No Limit',
            'duplicator-pro'
        ),
        size_format(ImportPageController::getChunkSize() * 1024)
    );
    $toolTipContent  = __(
        'The max file size limit is ignored when chunk size is enabled. 
        Use a large chunk size with fast connections and a small size with slower connections.
        You can change the chunk size by going to [Settings > Import].',
        'duplicator-pro'
    );
}

/** @var string */
$openTab = $tplData['defSubtab'];

$hlpUpload = __(
    'Upload speeds can be affected by different server connections and settings. 
    The chunk size can also impact upload speed [Settings > Import]. 
    If adjusting the chunk size doesn\'t help, try these steps to manually upload the archive:',
    'duplicator-pro'
);

$hlpUpload .= '<ul>' .
    '<li>' . __('1. Cancel the current upload.', 'duplicator-pro') . '</li>' .
    '<li>' . __('2. Manually upload the archive to:<br/> &nbsp; &nbsp; <i>/wp-content/duplicator-backups/imports/</i>', 'duplicator-pro') . '</li>' .
    '<li>' . __('3. Refresh the Import screen.', 'duplicator-pro') . '</li>' .
    '</ul>';
?>
<!-- ==============================
DRAG/DROP AREA -->
<div id="dupli-import-upload-tabs-wrapper" class="dupli-tabs-wrapper margin-bottom-2">
    <div
        id="dupli-import-upload-file-tab"
        class="tab-content <?php echo ($openTab == ImportPageController::L2_TAB_UPLOAD ? '' : 'no-display'); ?>">
        <div id="dupli-import-upload-file" class="dupli-import-upload-box"></div>
        <div class="no-display">
            <div id="dupli-import-upload-file-content" class="center-xy">
                <i class="fa fa-download fa-2x"></i>
                <span class="dup-drag-drop-message">
                    <?php esc_html_e("Drag & Drop Backup File Here", 'duplicator-pro'); ?>
                </span>
                <input
                    id="dup-import-dd-btn"
                    type="button"
                    class="button secondary hollow button-default dup-import-button margin-0"
                    name="dupli-files"
                    value="<?php esc_attr_e("Select File...", 'duplicator-pro'); ?>">
            </div>
        </div>
        <div id="dupli-import-upload-file-footer">
            <i
                class="fa-solid fa-question-circle fa-sm dark-gray-color"
                data-tooltip-title="<?php esc_html_e("Upload Chunk Size", 'duplicator-pro'); ?>"
                data-tooltip="<?php echo esc_attr($toolTipContent); ?>"></i>&nbsp;<?php echo wp_kses($footerChunkInfo, ['b' => []]); ?>&nbsp;|&nbsp;
            <span
                class="pointer link-style"
                data-tooltip-title="<?php esc_html_e("Improve Upload Speed", 'duplicator-pro'); ?>"
                data-tooltip="<?php echo esc_attr($hlpUpload); ?>">
                <i><?php esc_html_e('Slow Upload', 'duplicator-pro'); ?></i>&nbsp;<i class="fa-solid fa-question-circle fa-sm dark-gray-color"></i>
            </span>
        </div>
    </div>
    <div
        id="dupli-import-remote-file-tab"
        class="tab-content <?php echo ($openTab == ImportPageController::L2_TAB_REMOTE_URL ? '' : 'no-display'); ?>">
        <div class="dupli-import-upload-box">
            <div class="center-xy">
                <i class="fa fa-download fa-2x"></i>
                <span class="dup-drag-drop-message">
                    <?php esc_html_e("Import From Link", 'duplicator-pro'); ?>
                </span>
                <input
                    type="text"
                    id="dupli-import-remote-url"
                    class="inline-display margin-bottom-0"
                    placeholder="<?php esc_attr_e('Enter Full URL to Archive', 'duplicator-pro'); ?>">
                <button id="dupli-import-remote-upload" type="button" class="button primary button-default dup-import-button margin-bottom-0">
                    <?php esc_html_e("Upload", 'duplicator-pro'); ?>
                </button> <br />
                <small>
                    <?php
                    printf(
                        esc_html_x(
                            'For additional help visit the %1$sonline faq%2$s.',
                            '%1$s and %2$s are <a> tags',
                            'duplicator-pro'
                        ),
                        '<a href="' . esc_url(DUPLICATOR_DUPLICATOR_DOCS_URL . 'how-to-handle-import-install-upload-launch-issues')
                            . '" target="_blank">',
                        '</a>'
                    );
                    ?>
                </small>
            </div>
        </div>
    </div>
</div>