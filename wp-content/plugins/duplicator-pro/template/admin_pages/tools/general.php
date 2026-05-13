<?php

/**
 * @package Duplicator
 */

use Duplicator\Ajax\ServicesTools;
use Duplicator\Controllers\ToolsPageController;
use Duplicator\Core\CapMng;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Package\PackageUtils;
use Duplicator\Utils\Support\SupportToolkit;
use Duplicator\Views\UI\UiDialog;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

$orphaned_filepaths = PackageUtils::getOrphanedPackageFiles();
$tplMng->render('admin_pages/diagnostics/purge_orphans_message');
$tplMng->render('admin_pages/diagnostics/clean_tmp_cache_message');
$tplMng->render('parts/migration/migration-message');

$resetPackagesDialog                 = new UiDialog();
$resetPackagesDialog->title          = __('Reset Backups ?', 'duplicator-pro');
$resetPackagesDialog->message        = __('This will clear and reset all of the current temporary Backups.  Would you like to continue?', 'duplicator-pro');
$resetPackagesDialog->progressText   = __('Resetting settings, Please Wait...', 'duplicator-pro');
$resetPackagesDialog->jsCallback     = 'DupliJs.Pack.ResetPackages()';
$resetPackagesDialog->progressOn     = false;
$resetPackagesDialog->okText         = __('Yes', 'duplicator-pro');
$resetPackagesDialog->cancelText     = __('No', 'duplicator-pro');
$resetPackagesDialog->closeOnConfirm = true;
$resetPackagesDialog->initConfirm();

$maxAjaxBackupsChecksMessage = sprintf(
    __(
        'Maximum number of backup checks reached (%d). 
        Process stopped. You can start the check again to update the remaining backups.',
        'duplicator-pro'
    ),
    ServicesTools::MAX_AJAX_BACKUP_REMOTE_STORAGE_CHECKS
);
?>
<form id="dup-tools-form" action="<?php echo ControllersManager::getCurrentLink(); ?>" method="post">
    <h2>
        <?php esc_html_e('General Tools', 'duplicator-pro'); ?>
    </h2>
    <hr>

    <div class="dup-settings-wrapper">
        <label class="lbl-larger">
            <?php esc_html_e('Diagnostic Data', 'duplicator-pro'); ?>
        </label>
        <div class="margin-bottom-1">
            <button
                type="button"
                id="download-diagnostic-data-btn"
                class="button secondary small margin-bottom-0"
                data-url="<?php echo esc_attr(SupportToolkit::getSupportToolkitDownloadUrl()); ?>"
                <?php disabled(!SupportToolkit::isAvailable()); ?>>
                <?php esc_html_e('Get Diagnostic Data', 'duplicator-pro'); ?>
            </button>
            <p class="description">
                <?php if (SupportToolkit::isAvailable()) : ?>
                    <?php esc_html_e('Downloads a ZIP archive with all relevant diagnostic information.', 'duplicator-pro'); ?>
                <?php else : ?>
                    <i class="fa fa-question-circle data-size-help" data-tooltip-title="Diagnostic Data" data-tooltip="
                    <?php esc_attr_e(
                        'It is currently not possible to download the diagnostic data from your system,
                        as the ZipArchive extensions is required to create it.',
                        'duplicator-pro'
                    ); ?>" aria-expanded="false">
                    </i>
                    <?php printf(
                        esc_html__(
                            'If you were asked to include the diagnostic data to a support ticket,
                            please instead provide available %1$sBackup%2$s, %3$strace%4$s and debug logs.',
                            'duplicator-pro'
                        ),
                        '<a href="' . esc_url(DUPLICATOR_DUPLICATOR_DOCS_URL . 'how-do-i-read-the-package-build-log/') . '" target="_blank">',
                        '</a>',
                        '<a href="' . esc_url(DUPLICATOR_DUPLICATOR_DOCS_URL . 'how-do-i-read-the-package-trace-log/') . '" target="_blank">',
                        '</a>'
                    ); ?>
                <?php endif; ?>
            </p>
        </div>
        <?php if (CapMng::can(CapMng::CAP_CREATE, false)) { ?>
            <label class="lbl-larger">
                <?php esc_html_e('Backups Cleanup', 'duplicator-pro'); ?>
            </label>
            <div class="margin-bottom-1">
                <table class="dupli-reset-opts">
                    <tr valign="top">
                        <td>
                            <button
                                class="dupli-store-fixed-btn button secondary hollow tiny margin-bottom-0"
                                onclick="DupliJs.Pack.ConfirmResetPackages(); return false;">
                                <?php esc_attr_e('Delete Incomplete Backups', 'duplicator-pro'); ?>
                            </button>
                        </td>
                        <td>
                            <?php esc_html_e("Delete all unfinished Backups. So those with error and being created.", 'duplicator-pro'); ?>
                            <div class="maring-bottom-1">&nbsp;</div>
                        </td>
                    </tr>
                    <tr valign="top">
                        <td>
                            <button
                                id="check-remote-backups"
                                type="button"
                                class="dupli-store-fixed-btn button secondary hollow tiny margin-bottom-0">
                                <?php esc_html_e("Check Remote Backups", 'duplicator-pro'); ?>
                            </button>
                        </td>
                        <td>
                            <?php esc_html_e("Check if backups still exist in remote storages.", 'duplicator-pro'); ?>
                            <i
                                class="fa-solid fa-question-circle fa-sm dark-gray-color"
                                data-tooltip-title="<?php esc_attr_e('Check Remote Backups', 'duplicator-pro'); ?>"
                                data-tooltip="<?php esc_attr_e('This will check if backups marked as stored in remote storages still exist in those storages.
                                    If a backup is not found in a storage, it will be removed from that storage\'s list.', 'duplicator-pro'); ?>"
                                data-tooltip-width="400"></i>
                            <div class="maring-bottom-1">&nbsp;</div>
                        </td>
                    </tr>
                    <tr valign="top">
                        <td>
                            <a
                                type="button"
                                class="dupli-store-fixed-btn button secondary hollow tiny margin-bottom-0"
                                href="<?php echo esc_url(ToolsPageController::getInstance()->getPurgeOrphanActionUrl()); ?>">
                                <?php esc_html_e("Delete Backup Orphans", 'duplicator-pro'); ?>
                            </a>
                        </td>
                        <td>
                            <?php esc_html_e("Removes all Backup files NOT found in the Backups screen.", 'duplicator-pro'); ?>
                            <i
                                class="fa-solid fa-question-circle fa-sm dark-gray-color"
                                data-tooltip-title="<?php esc_attr_e("Delete Backup Orphans", 'duplicator-pro'); ?>"
                                data-tooltip="<?php echo esc_attr($tplMng->render('admin_pages/tools/parts/delete_backups_orphans_tooltip', [], false)); ?>"
                                data-tooltip-width="400"></i>
                            <div class="maring-bottom-1">&nbsp;</div>
                        </td>
                    </tr>
                </table>
            </div>
        <?php } ?>

        <label class="lbl-larger">
            <?php esc_html_e('General Cleanup', 'duplicator-pro'); ?>
        </label>
        <div class="margin-bottom-1">
            <table class="dupli-reset-opts">
                <tr valign="top">
                    <td>
                        <button
                            type="button"
                            class="dupli-store-fixed-btn button secondary hollow tiny margin-bottom-0"
                            id="dupli-remove-installer-files-btn"
                            onclick="DupliJs.Tools.removeInstallerFiles()">
                            <?php esc_html_e("Delete Installation Files", 'duplicator-pro'); ?>
                        </button>
                    </td>
                    <td>
                        <?php esc_html_e("Removes all reserved installation files.", 'duplicator-pro'); ?>&nbsp;
                        <i
                            class="fa-solid fa-question-circle fa-sm dark-gray-color"
                            data-tooltip-title="<?php esc_attr_e("Delete Installation Files", 'duplicator-pro'); ?>"
                            data-tooltip="<?php echo esc_attr($tplMng->render('admin_pages/tools/parts/delete_install_file_tooltip', [], false)); ?>"
                            data-tooltip-width="400"></i>
                        <div class="maring-bottom-1">&nbsp;</div>
                    </td>
                </tr>
                <?php if (CapMng::can(CapMng::CAP_CREATE, false)) { ?>
                    <tr>
                        <td>
                            <button
                                type="button"
                                class="dupli-store-fixed-btn button secondary hollow tiny margin-bottom-0"
                                onclick="DupliJs.Tools.ClearBuildCache()">
                                <?php esc_html_e("Clear Build Cache", 'duplicator-pro'); ?>
                            </button>
                        </td>
                        <td>
                            <?php esc_html_e('Removes all build data from:', 'duplicator-pro'); ?>&nbsp;
                            <b><?php echo esc_html(DUPLICATOR_SSDIR_PATH_TMP); ?></b>
                        </td>
                    </tr>
                <?php } ?>
            </table>
        </div>
        <?php $tplMng->render('admin_pages/tools/general_validator'); ?>
    </div>
</form>
<?php
$deleteOptConfirm               = new UiDialog();
$deleteOptConfirm->title        = __('Are you sure you want to delete?', 'duplicator-pro');
$deleteOptConfirm->message      = __('Delete this option value.', 'duplicator-pro');
$deleteOptConfirm->progressText = __('Removing, Please Wait...', 'duplicator-pro');
$deleteOptConfirm->jsCallback   = 'DupliJs.Settings.DeleteThisOption(this)';
$deleteOptConfirm->initConfirm();

$removeCacheConfirm               = new UiDialog();
$removeCacheConfirm->title        = __('This process will remove all build cache files.', 'duplicator-pro');
$removeCacheConfirm->message      = __('Be sure no Backups are currently building or else they will be cancelled.', 'duplicator-pro');
$removeCacheConfirm->progressText = $deleteOptConfirm->progressText;
$removeCacheConfirm->jsCallback   = 'DupliJs.Tools.ClearBuildCacheRun()';
$removeCacheConfirm->initConfirm();
?>
<script>
    jQuery(document).ready(function($) {
        DupliJs.Tools.removeInstallerFiles = function() {
            window.location = <?php echo json_encode(ToolsPageController::getInstance()->getCleanFilesAcrtionUrl()); ?>;
            return false;
        };

        DupliJs.Tools.ClearBuildCache = function() {
            <?php $removeCacheConfirm->showConfirm(); ?>
        };

        DupliJs.Tools.ClearBuildCacheRun = function() {
            window.location = <?php echo json_encode(ToolsPageController::getInstance()->getRemoveCacheActionUrl()); ?>;
        };

        DupliJs.Pack.CheckRemoteBackups = function(processed = 0, displayMessage = true, callbackAfterCheck = null) {
            if (processed >= <?php echo ServicesTools::MAX_AJAX_BACKUP_REMOTE_STORAGE_CHECKS; ?>) {
                if (displayMessage) {
                    DupliJs.addAdminMessage("<?php echo esc_js($maxAjaxBackupsChecksMessage); ?>", 'warning');
                }
                DupliJs.Util.ajaxProgressHide();
                return;
            }

            DupliJs.Util.ajaxProgressShow();

            DupliJs.Util.ajaxWrapper({
                    action: 'duplicator_check_remote_backups',
                    nonce: "<?php echo esc_js(wp_create_nonce('duplicator_check_remote_backups')); ?>",
                    totalProcessed: processed
                },
                function(result, data, funcData, textStatus, jqXHR) {
                    if (funcData.processed > 0) {
                        DupliJs.Pack.CheckRemoteBackups(funcData.totalProcessed, displayMessage, callbackAfterCheck);
                    } else {
                        DupliJs.Util.ajaxProgressHide();
                        if (displayMessage) {
                            if (funcData.processed === -1) {
                                DupliJs.addAdminMessage(funcData.message, 'error');
                            } else {
                                DupliJs.addAdminMessage(funcData.message);
                            }
                        }

                        if (callbackAfterCheck) {
                            callbackAfterCheck();
                        }
                    }
                },
                function(result, data, funcData, textStatus, jqXHR) {
                    if (funcData && funcData.message) {
                        return funcData.message;
                    }
                    return data.message;
                }, {
                    showProgress: false
                }
            );
        };

        $('#download-diagnostic-data-btn').click(function() {
            window.location = $(this).data('url');
        });

        DupliJs.Pack.ConfirmResetPackages = function() {
            <?php $resetPackagesDialog->showConfirm(); ?>
        };

        DupliJs.Pack.ResetPackages = function() {
            $.ajax({
                type: "POST",
                url: ajaxurl,
                dataType: "json",
                data: {
                    action: 'duplicator_reset_packages',
                    nonce: '<?php echo esc_js(wp_create_nonce('duplicator_reset_packages')); ?>'
                },
                success: function(result) {
                    if (result.success) {
                        var message = '<?php esc_html_e('Backups successfully reset', 'duplicator-pro'); ?>';
                        DupliJs.addAdminMessage(message);
                    } else {
                        var message = '<?php esc_html_e('RESPONSE ERROR!', 'duplicator-pro'); ?>' + '<br><br>' + result.data.message;
                        DupliJs.addAdminMessage(message, 'error');
                    }
                },
                error: function(result) {
                    var message = '<?php esc_html_e('Ajax request error', 'duplicator-pro'); ?>';
                    DupliJs.addAdminMessage(message, 'error');
                }
            });
        };

        $('#check-remote-backups').click(function() {
            DupliJs.Pack.CheckRemoteBackups();
        });
    });
</script>