<?php

/**
 * @package Duplicator
 */

use Duplicator\Views\UI\UiViewState;
use Duplicator\Models\GlobalEntity;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 */

$global    = GlobalEntity::getInstance();
$boxOpened = UiViewState::getValue('dup-pack-storage-panel');
?>
<div class="dup-box" id="dup-pack-storage-panel-area">
    <div class="dup-box-title" id="dupli-store-title">
        <i class="fas fa-server fa-sm"></i>
        <?php esc_html_e('Storage', 'duplicator-pro') ?> <sup id="dupli-storage-title-count" class="dup-box-title-badge"></sup>
        <button class="dup-box-arrow">
            <span class="screen-reader-text">
                <?php esc_html_e('Toggle panel:', 'duplicator-pro') ?> <?php esc_html_e('Storage Options', 'duplicator-pro') ?>
            </span>
        </button>
    </div>
    <div id="dup-pack-storage-panel" class="dup-box-panel <?php echo ($boxOpened ? '' : 'no-display'); ?>">
        <p>
            <?php esc_html_e('Choose the storage location(s) where the Backup and Installer files will be saved.', 'duplicator-pro') ?>
        </p>
        <?php $tplMng->render(
            'parts/storage/select_list',
            [
                'selectedStorageIds' => $global->getManualModeStorageIds(),
                'recoveryPointMsg'   => true,
            ]
        ); ?>
    </div>
</div>


<script>
    jQuery(function($) {
        DupliJs.Pack.UpdateStorageCount = function() {
            var store_count = $('#dup-pack-storage-panel input[name="_storage_ids[]"]:checked').length;
            $('#dupli-storage-title-count').html('(' + store_count + ')');
            (store_count == 0) ?
            $('#dupli-storage-title-count').css({
                'color': 'red',
                'font-weight': 'bold'
            }): $('#dupli-storage-title-count').css({
                'color': '#444',
                'font-weight': 'normal'
            });
        }

        $('#dup-pack-storage-panel input[name="_storage_ids[]"]').on('change', function() {
            DupliJs.Pack.UpdateStorageCount();
        });
    });

    //INIT
    jQuery(document).ready(function($) {
        DupliJs.Pack.UpdateStorageCount();
    });
</script>