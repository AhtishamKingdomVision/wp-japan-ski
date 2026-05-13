<?php

/**
 * @package Duplicator
 */

use Duplicator\Core\CapMng;
use Duplicator\Views\UI\UiDialog;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */


if (!CapMng::can(CapMng::CAP_CREATE, false)) {
    return;
}

$confirm1             = new UiDialog();
$confirm1->title      = __('Run Validator', 'duplicator-pro');
$confirm1->message    = __('This will run the scan validation check.  This may take several minutes.  Do you want to Continue?', 'duplicator-pro');
$confirm1->progressOn = false;
$confirm1->jsCallback = 'DupliJs.Tools.runScanValidator()';
$confirm1->initConfirm();
?>

<label class="lbl-larger">
    <?php esc_html_e('Scan Validator', 'duplicator-pro'); ?>
</label>
<div>
    <button
        id="scan-run-btn"
        type="button"
        class="button secondary small margin-bottom-0"
        onclick="DupliJs.Tools.ConfirmScanValidator()">
        <?php esc_html_e("Run Scan Integrity Validation", 'duplicator-pro'); ?>
    </button>
    <p class="description">
        <?php esc_html_e('This utility identifies unreadable files and sys-links, potentially causing scanning issues.', 'duplicator-pro'); ?>
    </p>
    <script id="hb-template" type="text/x-handlebars-template">
        <b><?php esc_html_e('Scan Paths:', 'duplicator-pro'); ?></b><br/>
        {{#if scanData.scanPaths}}
            {{#each scanData.scanPaths}}
                &nbsp; &nbsp; {{@index}} : {{this}}<br/>
            {{/each}}
        {{else}}
            <i><?php esc_html_e('Empty scan path', 'duplicator-pro'); ?></i> <br/>
        {{/if}}
        <br/>
        <b><?php esc_html_e('Scan Results', 'duplicator-pro'); ?></b><br/>
        <table>
            <tr>
                <td><b><?php esc_html_e('Files:', 'duplicator-pro'); ?></b></td>
                <td>{{scanData.fileCount}} </td>
                <td> &nbsp; </td>
                <td><b><?php esc_html_e('Dirs:', 'duplicator-pro'); ?></b></td>
                <td>{{scanData.dirCount}} </td>
            </tr>
        </table>
        <br/>

        <b><?php esc_html_e('Unreadable Dirs/Files:', 'duplicator-pro') ?></b> <br/>
        {{#if scanData.unreadable}}
            {{#each scanData.unreadable}}
                &nbsp; &nbsp; {{@index}} : {{this}}<br/>
            {{/each}}
        {{else}}
            <i><?php esc_html_e('No Unreadable items found', 'duplicator-pro'); ?></i> <br/>
        {{/if}}
        <br/>

        <b><?php esc_html_e('Symbolic Links:', 'duplicator-pro'); ?></b> <br/>
        {{#if scanData.symLinks}}
            {{#each scanData.symLinks}}
                &nbsp; &nbsp; {{@index}} : {{this}}<br/>
            {{/each}}
        {{else}}
            <i><?php esc_html_e('No Sym-links found', 'duplicator-pro') ?></i> <br/>
            <small> <?php esc_html_e("Note: Symlinks are not discoverable on Windows OS with PHP", 'duplicator-pro'); ?></small> <br/>
        {{/if}}
        <br/>

        <b><?php esc_html_e('Directory Name Checks:', 'duplicator-pro') ?></b> <br/>
        {{#if scanData.nameTestDirs}}
            {{#each scanData.nameTestDirs}}
                &nbsp; &nbsp; {{@index}} : {{this}}<br/>
            {{/each}}
        {{else}}
            <i><?php esc_html_e('No name check warnings located for directory paths', 'duplicator-pro'); ?></i> <br/>
        {{/if}}
        <br/>

        <b><?php esc_html_e('File Name Checks:', 'duplicator-pro') ?></b> <br/>
        {{#if scanData.nameTestFiles}}
            {{#each scanData.nameTestFiles}}
                &nbsp; &nbsp; {{@index}} : {{this}}<br/>
            {{/each}}
        {{else}}
            <i><?php esc_html_e('No name check warnings located for directory paths', 'duplicator-pro'); ?></i> <br/>
        {{/if}}

        <br/>
    </script>
    <div id="hb-result"></div>
</div>
<script>
    jQuery(document).ready(function($) {
        DupliJs.Tools.ConfirmScanValidator = function() {
            <?php $confirm1->showConfirm(); ?>
        }


        //Run request to: admin-ajax.php?action=DUP_CTRL_Tools_runScanValidator
        DupliJs.Tools.runScanValidator = function() {
            tb_remove();
            var data = {
                action: 'duplicator_tool_scan_validator',
                nonce: '<?php echo wp_create_nonce('duplicator_tool_scan_validator'); ?>',
                'scan-recursive': 1
            };

            $('#hb-result').html('<?php esc_html_e("Scanning Environment... This may take a few minutes.", 'duplicator-pro'); ?>');
            $('#scan-run-btn').html('<i class="fas fa-circle-notch fa-spin fa-fw"></i> <?php echo esc_js(__('Running Please Wait...', 'duplicator-pro')) ?>');

            $.ajax({
                type: "POST",
                url: ajaxurl,
                dataType: "json",
                data: data,
                success: function(data) {
                    DupliJs.Tools.IntScanValidator(data);
                },
                error: function(data) {
                    console.log(data)
                },
                done: function(data) {
                    console.log(data)
                }
            });
        }

        //Process Ajax Template
        DupliJs.Tools.IntScanValidator = function(data) {
            var template = $('#hb-template').html();
            var templateScript = Handlebars.compile(template);
            var html = templateScript(data);
            $('#hb-result').html(html);
            $('#scan-run-btn').html('<?php esc_html_e("Run Scan Integrity Validation", 'duplicator-pro'); ?>');
        }
    });
</script>