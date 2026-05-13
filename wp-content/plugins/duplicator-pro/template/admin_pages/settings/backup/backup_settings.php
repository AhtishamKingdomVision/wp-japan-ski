<?php

/**
 * @package Duplicator
 */

use Duplicator\Controllers\SettingsPageController;
use Duplicator\Core\Controllers\ControllersManager;

defined("ABSPATH") or die("");


/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

?>
<form
    id="dup-settings-form" class="dup-settings-pack-basic"
    action="<?php echo esc_url(ControllersManager::getCurrentLink()); ?>"
    method="post" data-parsley-validate
>
    <?php $tplData['actions'][SettingsPageController::ACTION_PACKAGE_BASIC_SAVE]->getActionNonceFileds(); ?>
    <div class="dup-settings-wrapper margin-bottom-1">
        <?php $tplMng->render('admin_pages/settings/backup/database_settings'); ?>
        <?php $tplMng->render('admin_pages/settings/backup/archive_settings'); ?>
        <?php $tplMng->render('admin_pages/settings/backup/processing_settings'); ?>
        <?php $tplMng->render('admin_pages/settings/backup/installer_settings'); ?>
        <?php $tplMng->render('admin_pages/settings/backup/advanced_settings'); ?>
    </div>
    <p class="submit dupli-save-submit">
    <input
        type="submit" 
        name="submit" 
        id="submit" 
        class="button primary small"
        value="<?php esc_attr_e('Save Settings', 'duplicator-pro') ?>" style="display: inline-block;"
    >
    </p>
</form>

<script>
    jQuery(document).ready(function ($)
    {

        DupliJs.UI.SetDBEngineMode = function ()
        {
            var isMysqlDump = $('#package_mysqldump').is(':checked');
            var isPHPMode = $('#package_phpdump').is(':checked');
            var isPHPChunkMode = $('#package_phpchunkingdump').is(':checked');

            $('#dbengine-details-1, #dbengine-details-2').hide();
            switch (true) {
                case isMysqlDump :
                    $('#dbengine-details-1').show();
                    break;
                case isPHPMode  :
                case isPHPChunkMode :
                    $('#dbengine-details-2').show();
                    break;
            }
        }

        DupliJs.UI.setZipArchiveMode = function ()
        {
            $('#dupli-ziparchive-mode-st, #dupli-ziparchive-mode-mt').hide();
            if ($('#ziparchive_mode').val() == 0) {
                $('#dupli-ziparchive-mode-mt').show();
            } else {
                $('#dupli-ziparchive-mode-st').show();
            }
        }

        DupliJs.UI.SetArchiveOptionStates = function ()
        {
            var php70 = <?php echo (version_compare(PHP_VERSION, '7', '>=') ? 'true' : 'false'); ?>;
            var isShellZipSelected = $('#archive_build_mode1').is(':checked');
            var isZipArchiveSelected = $('#archive_build_mode2').is(':checked');
            var isDupArchiveSelected = $('#archive_build_mode3').is(':checked');

            if (isShellZipSelected || isDupArchiveSelected) {
                $("[name='archive_compression']").prop('disabled', false);
                $("[name='ziparchive_mode']").prop('disabled', true);
            } else {
                $("[name='ziparchive_mode']").prop('disabled', false);
                if (php70) {
                    $("[name='archive_compression']").prop('disabled', false);
                } else {
                    $('#archive_compression_on').prop('checked', true);
                    $("[name='archive_compression']").prop('disabled', true);
                }
            }

            $('#engine-details-1, #engine-details-2, #engine-details-3').hide();
            switch (true) {
                case isShellZipSelected       :
                    $('#engine-details-1').show();
                    break;
                case isZipArchiveSelected   :
                    $('#engine-details-2').show();
                    break;
                case isDupArchiveSelected   :
                    $('#engine-details-3').show();
                    break;
            }
            DupliJs.UI.setZipArchiveMode();
        }

        //INIT
        DupliJs.UI.SetArchiveOptionStates();
        DupliJs.UI.SetDBEngineMode();

        DupliJs.UI.cleanupModeRadioSwitched = function() {
            if ($('#cleanup_mode_Cleanup_Off').is(":checked")){
                $('#auto_cleanup_hours').attr('readonly','readonly');
                $('#cleanup_email').attr('readonly','readonly');
            } else if ($('#cleanup_mode_Email_Notice').is(":checked")) {
                $('#auto_cleanup_hours').attr('readonly','readonly');
                $("#cleanup_email").removeAttr('readonly');
            } else if ($('#cleanup_mode_Auto_Cleanup').is(":checked")) {
                $("#auto_cleanup_hours").removeAttr('readonly');
                $("#cleanup_email").removeAttr('readonly');
            }
        }

        $('input[type=radio][name=cleanup_mode]').change(function () {
            DupliJs.UI.cleanupModeRadioSwitched();
        });
        // We must call this also once in the beginning, after UI is loaded
        DupliJs.UI.cleanupModeRadioSwitched();

    });
</script>