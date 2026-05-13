<?php

/**
 * @package Duplicator
 */

use Duplicator\Views\UI\UiDialog;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

$nonce = wp_create_nonce('duplicator_export_settings');
?>
<form id="dup-tools-form-export" method="post">
    <input type="hidden" name="action" value="dupli-export">
    <div class="dup-settings-wrapper margin-bottom-1">
        <h3 class="title">
            <?php esc_html_e("Export Duplicator Settings", 'duplicator-pro') ?>
        </h3>
        <hr size="1" />
        <p class="width-xxlarge">
            <?php
            esc_html_e(
                'Exports all schedules, storage locations, templates and settings from this Duplicator Pro instance into a downloadable export file.
                The export file can then be used to import data settings from this instance of Duplicator Pro into another plugin instance of Duplicator Pro.',
                'duplicator-pro'
            );
            ?>
        </p>
        <label class="lbl-larger">
            <?php esc_html_e("Export Settings File", 'duplicator-pro'); ?>
        </label>
        <div class="margin-bottom-1">
            <input
                type="button"
                class="button secondary small margin-0"
                value="<?php esc_attr_e("Export Data", 'duplicator-pro'); ?>"
                onclick="return DupliJs.Tools.ExportDialog();">
        </div>
    </div>
</form>

<div id="modal-window-export" style="display:none;">
    <p>
        <?php esc_html_e("This process will:", 'duplicator-pro') ?><br />
        <i class="far fa-check-circle"></i>
        <?php esc_html_e("Export schedules, storage and templates to a file for import into another Duplicator instance.", 'duplicator-pro'); ?> <br />
        <span class="alert-color">
            <i class="fas fa-exclamation-triangle fa-sm"></i>
            <?php esc_html_e("For security purposes, restrict access to this file and delete after use.", 'duplicator-pro'); ?>
        </span>
    </p>
    <div class="float-right">
        <input
            type="button"
            class="button secondary hollow small"
            value="<?php esc_attr_e("Cancel", 'duplicator-pro') ?>"
            onclick="tb_remove();">&nbsp;
        <input
            type="button"
            class="button primary small"
            value="<?php esc_attr_e("Run Export", 'duplicator-pro') ?>"
            onclick="DupliJs.Tools.ExportProcess();setTimeout(function() { tb_remove(); }, 4000);"
            title="<?php esc_attr_e("Generate and Download the Export File.", 'duplicator-pro') ?>">
    </div>
</div>
<script>
    DupliJs.Tools.ExportProcess = function() {
        var actionLocation = ajaxurl + '?action=duplicator_export_settings' + '&nonce=' + '<?php echo esc_js($nonce); ?>';
        location.href = actionLocation;
    }

    DupliJs.Tools.ExportDialog = function() {
        var url = "#TB_inline?width=610&height=250&inlineId=modal-window-export";
        tb_show("<?php esc_html_e("Export Duplicator Pro Data ?", 'duplicator-pro') ?>", url);
        jQuery('#TB_window').addClass(<?php echo json_encode(UiDialog::TB_WINDOW_CLASS); ?>);
        return false;
    }
</script>