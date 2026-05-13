<?php

/**
 * Duplicator Pro remote download scripts
 *
 * @package   Duplicator
 * @copyright (c) 2024, Snap Creek LLC
 */

use Duplicator\Controllers\PackagesPageController;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Views\UI\UiDialog;
use Duplicator\Models\GlobalEntity;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 */

$afterDownloadAction      = $tplData['afterDownloadAction'] ?? '';
$remoteDownloadPackageId  = isset($tplData['remoteDownloadPackageId']) ? (int) $tplData['remoteDownloadPackageId'] : -1;
$downloadStarted          = $remoteDownloadPackageId > 0;
$afterDownloadActionNonce = strlen($afterDownloadAction) > 0 ? PackagesPageController::getInstance()->getActionByKey($afterDownloadAction)->getNonce() : '';

$alreadyDownloadDlg              = new UiDialog();
$alreadyDownloadDlg->width       = 550;
$alreadyDownloadDlg->height      = 200;
$alreadyDownloadDlg->showButtons = true;
$alreadyDownloadDlg->title       = __('Backup is already being downloaded', 'duplicator-pro');
$alreadyDownloadDlg->message     = __('The backup is currently being downloaded. Please wait for the download to finish.', 'duplicator-pro');
$alreadyDownloadDlg->boxClass    = 'duplication-remote-download-options-dlg';
$alreadyDownloadDlg->initAlert();

$remoteDownloadOptionsDlg              = new UiDialog();
$remoteDownloadOptionsDlg->width       = 750;
$remoteDownloadOptionsDlg->height      = 395;
$remoteDownloadOptionsDlg->showButtons = false;
$remoteDownloadOptionsDlg->title       = __('Download From Remote Storage', 'duplicator-pro');
$remoteDownloadOptionsDlg->message     = __('Loading Please Wait...', 'duplicator-pro');
$remoteDownloadOptionsDlg->boxClass    = 'duplicatior-remote-download-options-dlg';
$remoteDownloadOptionsDlg->initAlert();

$downloadProgressDlg               = new UiDialog();
$downloadProgressDlg->height       = 475;
$downloadProgressDlg->width        = 750;
$downloadProgressDlg->showButtons  = false;
$downloadProgressDlg->title        = __('Downloading Backup...', 'duplicator-pro');
$downloadProgressDlg->templatePath = 'admin_pages/packages/remote_download/download_progress';
$downloadProgressDlg->boxClass     = 'dupli-download-progress-dlg';
$downloadProgressDlg->initAlert();

$removeBackupRecordConfirm                 = new UiDialog();
$removeBackupRecordConfirm->title          = __('Remove Backup Record?', 'duplicator-pro');
$removeBackupRecordConfirm->message        = __('The backup does not exist in any storage. Would you like to remove the backup record?', 'duplicator-pro');
$removeBackupRecordConfirm->progressText   = __('Removing Backup Record, Please Wait...', 'duplicator-pro');
$removeBackupRecordConfirm->jsCallback     = 'DupliJs.Pack.DeleteBackupRecord(this)';
$removeBackupRecordConfirm->progressOn     = false;
$removeBackupRecordConfirm->okText         = __('Yes', 'duplicator-pro');
$removeBackupRecordConfirm->cancelText     = __('No', 'duplicator-pro');
$removeBackupRecordConfirm->closeOnConfirm = true;
$removeBackupRecordConfirm->initConfirm();
?>

<script>
    jQuery(document).ready(function($) {
        let remoteDownloadModal = $('.<?php echo esc_html($remoteDownloadOptionsDlg->boxClass); ?>');
        let remoteDownloadInProgress = <?php echo wp_json_encode($downloadStarted); ?>;
        let remoteDownloadModalOpen = false;

        if (remoteDownloadInProgress) {
            setTimeout(function() {
                <?php $downloadProgressDlg->showAlert(); ?>
                remoteDownloadModalOpen = true;
            }, 500);
        }

        $(document).on('thickbox:removed', function() {
            if (!remoteDownloadInProgress && !remoteDownloadModalOpen) {
                return;
            }

            remoteDownloadModalOpen = false;
        });

        DupliJs.Pack.DeleteBackupRecord = function(e) {
            var id = $(e).attr('data-id');
            $("tr[data-package-id=" + id + "] input[type=checkbox]").prop('checked', true);
            DupliJs.Pack.Delete()
        }

        DupliJs.Pack.IsRemoteDownloadModalOpen = function() {
            return remoteDownloadModalOpen;
        }

        DupliJs.Pack.afterRemoteDownloadAction = function() {
            let packageId = <?php echo wp_json_encode($remoteDownloadPackageId); ?>;
            let action = <?php echo wp_json_encode($afterDownloadAction); ?>;
            let nonceVal = <?php echo wp_json_encode($afterDownloadActionNonce); ?>;

            if (action.length === 0 || nonceVal.length === 0 || packageId <= 0) {
                location.reload();
            } else {
                DupliJs.Util.dynamicFormSubmit('', 'post', {
                    packageId: packageId,
                    action: action,
                    _wpnonce: nonceVal
                });
            }
        }

        /**
         * Show remote download options in modal window
         * 
         * @param {number} packageId
         * @param {string} remoteAction
         * 
         * @return {boolean}
         */
        DupliJs.Pack.ShowRemoteDownloadOptions = function(
            packageId,
            remoteAction,
        ) {
            DupliJs.Util.ajaxWrapper({
                    action: 'duplicator_get_remote_restore_download_options',
                    packageId: packageId,
                    remoteAction: remoteAction,
                    nonce: "<?php echo esc_js(wp_create_nonce('duplicator_get_remote_restore_download_options')); ?>"
                },
                function(result, data, funcData, textStatus, jqXHR) {
                    if (funcData.alreadyInUse) {
                        <?php $alreadyDownloadDlg->showAlert(); ?>
                    } else if (!funcData.packageExists) {
                        <?php if (GlobalEntity::getInstance()->getPurgeBackupRecords() === AbstractStorageEntity::BACKUP_RECORDS_REMOVE_ALL) {
                            $removeBackupRecordConfirm->showConfirm();
                        } else { ?>
                            DupliJs.addAdminMessage(funcData.message, 'error');
                        <?php } ?>
                        $("#<?php echo esc_js($removeBackupRecordConfirm->getID()); ?>-confirm").attr('data-id', packageId);
                        $("tr[data-package-id='" + packageId + "'] button[data-needs-download]").prop('disabled', true);
                        $("#dup-row-pack-id-" + packageId + " .remote-storage-flag").remove();
                    } else {
                        <?php $remoteDownloadOptionsDlg->showAlert(); ?>
                        remoteDownloadModal.html(data.funcData.content);
                    }
                    return '';
                },
                function(result, data, funcData, textStatus, jqXHR) {
                    DupliJs.addAdminMessage(data.message, 'error');
                    console.log(data);
                    return '';
                }, {
                    timeout: 300000
                } //Fetching validity of multiple storages can take a while
            );

            return false;
        }
    });
</script>