<?php

/**
 * @package Duplicator
 */

use Duplicator\Models\GlobalEntity;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Core\Views\TplMng;
use Duplicator\Controllers\SettingsPageController;
use Duplicator\Core\Controllers\PageAction;
use Duplicator\Views\UI\UiDialog;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

$global = GlobalEntity::getInstance();

/** @var PageAction */
$resetAction = $tplData['actions'][SettingsPageController::ACTION_RESET_SETTINGS];

?>
<?php do_action('duplicator_settings_general_before'); ?>

<form id="dup-settings-form" action="<?php echo esc_url(ControllersManager::getCurrentLink()); ?>" method="post" data-parsley-validate>
    <?php $tplData['actions'][SettingsPageController::ACTION_GENERAL_SAVE]->getActionNonceFileds(); ?>

    <div class="dup-settings-wrapper margin-bottom-1">
        <?php $tplMng->render('admin_pages/settings/general/plugin_settings'); ?>
        <hr>
        <?php TplMng::getInstance()->render('admin_pages/settings/general/email_summary'); ?>
        <?php TplMng::getInstance()->render('admin_pages/settings/general/debug_settings'); ?>
        <?php TplMng::getInstance()->render('admin_pages/settings/general/advanced_settings'); ?>
    </div>

    <p>
        <input
            type="submit" name="submit" id="submit"
            class="button primary small"
            value="<?php esc_attr_e('Save Settings', 'duplicator-pro') ?>">
    </p>
</form>

<?php
$resetSettingsDialog                 = new UiDialog();
$resetSettingsDialog->title          = __('Reset Settings?', 'duplicator-pro');
$resetSettingsDialog->message        = __('Are you sure you want to reset settings to defaults?', 'duplicator-pro');
$resetSettingsDialog->progressText   = __('Resetting settings, Please Wait...', 'duplicator-pro');
$resetSettingsDialog->jsCallback     = 'DupliJs.Pack.ResetAll()';
$resetSettingsDialog->progressOn     = false;
$resetSettingsDialog->okText         = __('Yes', 'duplicator-pro');
$resetSettingsDialog->cancelText     = __('No', 'duplicator-pro');
$resetSettingsDialog->closeOnConfirm = true;
$resetSettingsDialog->initConfirm();

$deleteLogsDialog                 = new UiDialog();
$deleteLogsDialog->title          = __('Delete Activity Logs?', 'duplicator-pro');
$deleteLogsDialog->message        = __('Are you sure you want to delete all activity logs? This action cannot be undone.', 'duplicator-pro');
$deleteLogsDialog->progressText   = __('Deleting logs, Please Wait...', 'duplicator-pro');
$deleteLogsDialog->jsCallback     = 'DupliJs.Settings.DeleteActivityLogs()';
$deleteLogsDialog->progressOn     = false;
$deleteLogsDialog->okText         = __('Yes', 'duplicator-pro');
$deleteLogsDialog->cancelText     = __('No', 'duplicator-pro');
$deleteLogsDialog->closeOnConfirm = true;
$deleteLogsDialog->initConfirm();
?>

<script>
    jQuery(document).ready(function($) {
        // which: 0=installer, 1=archive, 2=sql file, 3=log
        DupliJs.Pack.DownloadTraceLog = function() {
            var actionLocation = ajaxurl + '?action=duplicator_get_trace_log&nonce=' +
                '<?php echo esc_js(wp_create_nonce('duplicator_get_trace_log')); ?>';
            location.href = actionLocation;
        };

        DupliJs.Pack.ConfirmResetAll = function() {
            <?php $resetSettingsDialog->showConfirm(); ?>
        };

        DupliJs.Pack.ResetAll = function() {
            let resetUrl = <?php echo wp_json_encode($resetAction->getUrl()); ?>;
            location.href = resetUrl;
        };

        DupliJs.Settings.ConfirmDeleteActivityLogs = function() {
            <?php $deleteLogsDialog->showConfirm(); ?>
        };

        DupliJs.Settings.DeleteActivityLogs = function() {
            var $button = $('#dup-delete-activity-logs');
            var nonce = $button.data('nonce');

            $button.prop('disabled', true);

            $.ajax({
                type: 'POST',
                url: ajaxurl,
                data: {
                    action: 'duplicator_activity_log_delete_all',
                    nonce: nonce
                },
                success: function(response) {
                    var funcData = response.data && response.data.funcData ? response.data.funcData : {};
                    if (response.success && funcData.success) {
                        DupliJs.addAdminMessage(funcData.message, 'notice');
                    } else {
                        var errorMsg = funcData.message
                            ? funcData.message
                            : '<?php echo esc_js(__('An error occurred while deleting activity logs.', 'duplicator-pro')); ?>';
                        DupliJs.addAdminMessage(errorMsg, 'error');
                    }
                    $button.prop('disabled', false);
                },
                error: function() {
                    DupliJs.addAdminMessage('<?php echo esc_js(__('An error occurred while deleting activity logs.', 'duplicator-pro')); ?>', 'error');
                    $button.prop('disabled', false);
                }
            });
        };

        //Init
        $("#_trace_log_enabled").click(function() {
            $('#_send_trace_to_error_log').attr('disabled', !$(this).is(':checked'));
        });

    });
</script>