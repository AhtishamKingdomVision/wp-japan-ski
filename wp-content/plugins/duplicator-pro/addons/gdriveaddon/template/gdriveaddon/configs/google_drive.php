<?php

/**
 * Duplicator messages sections
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Addons\GDriveAddon\Models\GDriveStorage;
use Duplicator\Views\UI\UiDialog;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var GDriveStorage $storage
 */
$storage = $tplData["storage"];
/** @var string */
$storageFolder = $tplData["storageFolder"];
/** @var int */
$maxPackages =  $tplData["maxPackages"];
/** @var \VendorDuplicator\Google\Service\Drive\User $userInfo */
$userInfo = $tplData["userInfo"];
/** @var string */
$quotaString = $tplData["quotaString"];

$tplMng->render('admin_pages/storages/parts/provider_head');
?>
<tr>
    <th scope="row"><label for=""><?php esc_html_e("Authorization", 'duplicator-pro'); ?></label></th>
    <td>
        <?php if (!$storage->isAuthorized()) : ?>
            <div class='gdrive-authorization-state' id="gdrive-state-unauthorized">
                <!-- CONNECT -->
                <div id="dupli-gdrive-connect-btn-area">
                    <button
                        id="dupli-gdrive-connect-btn"
                        type="button"
                        class="button secondary hollow"
                        onclick="DupliJs.Storage.GDrive.GoogleGetAuthUrl();">
                        <i class="fa fa-plug"></i> <?php esc_html_e('Connect to Google Drive', 'duplicator-pro'); ?>
                        <img
                            src="<?php echo esc_url(DUPLICATOR_IMG_URL . '/google-drive.svg'); ?>"
                            style='vertical-align: middle; margin:-2px 0 0 3px; height:18px; width:18px' />
                    </button>
                </div>
                <div class="authorization-state" id="dupli-gdrive-connect-progress">
                    <div style="padding:10px">
                        <i class="fas fa-circle-notch fa-spin"></i> <?php esc_html_e('Getting Google Drive Request Token...', 'duplicator-pro'); ?>
                    </div>
                </div>

                <!-- STEPS -->
                <div id="dupli-gdrive-steps">
                    <div class="storage-auth-step">
                        <p>
                            <b><?php esc_html_e('Step 1:', 'duplicator-pro'); ?></b>&nbsp;
                            <?php
                            esc_html_e(
                                "Duplicator needs to authorize Google Drive. Make sure to allow all required permissions.",
                                'duplicator-pro'
                            ); ?>
                        </p>
                        <div class="auth-code-popup-note">
                            <?php esc_html_e(
                                'Note: Clicking the button below will open a new tab/window. Please be sure your browser does not block popups.',
                                'duplicator-pro'
                            ); ?>
                            <?php esc_html_e(
                                'If a new tab/window does not open check your browsers address bar to allow popups from this URL.',
                                'duplicator-pro'
                            ); ?>
                        </div>
                        <button
                            id="gdrive-auth-window-button"
                            class="button secondary hollow margin-bottom-0"
                            onclick="DupliJs.Storage.GDrive.OpenAuthPage(); return false;">
                            <i class="fa fa-user"></i> <?php esc_html_e("Authorize Google Drive", 'duplicator-pro'); ?>
                        </button>
                    </div>

                    <div class="storage-auth-step">
                        <p>
                            <b><?php esc_html_e('Step 2:', 'duplicator-pro'); ?></b>
                            <?php esc_html_e("Paste code from Google authorization page.", 'duplicator-pro'); ?>
                        </p>
                        <input style="width:400px" id="gdrive-auth-code" name="gdrive-auth-code" type="text" />
                    </div>

                    <div class="storage-auth-step">
                        <p>
                            <b><?php esc_html_e('Step 3:', 'duplicator-pro'); ?></b>
                            <?php esc_html_e('Finalize Google Drive setup by clicking the "Finalize Setup" button.', 'duplicator-pro') ?>
                        </p>
                        <button
                            id="gdrive-finalize-setup"
                            type="button"
                            class="button secondary margin-bottom-0">
                            <i class="fa fa-check-square"></i> <?php esc_html_e('Finalize Setup', 'duplicator-pro'); ?>
                        </button>
                    </div>
                </div>
            </div>
        <?php else : ?>
            <div class='gdrive-authorization-state' id="gdrive-state-authorized" style="margin-top:-10px">
                <?php if ($userInfo != null) : ?>
                    <h3>
                        <?php esc_html_e('Google Drive Account', 'duplicator-pro'); ?><br />
                        <i class="dupli-edit-info">
                            <?php esc_html_e('Duplicator has been authorized to access this user\'s Google Drive account', 'duplicator-pro'); ?>
                        </i>
                    </h3>
                    <div id="gdrive-account-info">
                        <label><?php esc_html_e('Name', 'duplicator-pro'); ?>:</label>
                        <?php echo esc_html($userInfo->getDisplayName()); ?><br />

                        <label><?php esc_html_e('Email', 'duplicator-pro'); ?>:</label> <?php echo esc_html($userInfo->getEmailAddress()); ?>
                        <?php if (strlen($quotaString) > 0) { ?>
                            <br>
                            <label><?php esc_html_e('Quota', 'duplicator-pro'); ?>:</label> <?php echo esc_html($quotaString); ?>
                        <?php } ?>
                    </div><br />
                <?php else : ?>
                    <div><?php esc_html_e('Error retrieving user information.', 'duplicator-pro'); ?></div>
                <?php endif ?>

                <button
                    id="dup-gdrive-cancel-authorization"
                    type="button"
                    class="button secondary hollow">
                    <?php esc_html_e('Cancel Authorization', 'duplicator-pro'); ?>
                </button><br />
                <i class="dupli-edit-info">
                    <?php
                    esc_html_e(
                        'Disassociates storage provider with the Google Drive account. Will require re-authorization.',
                        'duplicator-pro'
                    ); ?>
                </i>
            </div>
        <?php endif ?>
    </td>
</tr>
<tr>
    <th scope="row">
        <label for="_gdrive_storage_folder">
            <?php esc_html_e("Storage Folder", 'duplicator-pro'); ?>
        </label>
    </th>
    <td>
        <div class="horizontal-input-row">
            <label for="_gdrive_storage_folder">
                <b>//Google Drive/</b>
            </label>
            <input
                id="_gdrive_storage_folder"
                name="_gdrive_storage_folder"
                type="text"
                value="<?php echo esc_attr($storageFolder); ?>"
                class="dupli-storeage-folder-path">
        </div>
        <p>
            <i>
                <?php
                esc_html_e(
                    "Folder where backups will be stored. This should be unique for each web-site using Duplicator.",
                    'duplicator-pro'
                ); ?>
            </i>
            <?php
            $tipContent = __(
                'If the directory path above is already in Google Drive before connecting then a duplicate folder name will be made in the same path.',
                'duplicator-pro'
            ) . ' google_drive.php' . __('This is because the plugin only has rights to folders it creates.', 'duplicator-pro');
            ?>
            <i
                class="fa-solid fa-question-circle fa-sm dark-gray-color"
                data-tooltip-title="<?php esc_attr_e("Storage Folder Notice", 'duplicator-pro'); ?>"
                data-tooltip="<?php esc_attr($tipContent); ?>">
            </i>

        </p>
    </td>
</tr>
<tr>
    <th scope="row"><label for=""><?php esc_html_e("Max Backups", 'duplicator-pro'); ?></label></th>
    <td>
        <div class="horizontal-input-row">
            <input
                id="gdrive_max_files"
                name="gdrive_max_files"
                type="number"
                value="<?php echo (int) $maxPackages; ?>"
                min="0"
                maxlength="4"
                data-parsley-errors-container="#gdrive_max_files_error_container"
                data-parsley-required="true"
                data-parsley-type="number"
                data-parsley-min="0">&nbsp;
            <label for="gdrive_max_files">
                <?php esc_html_e("Number of Backups to keep in folder.", 'duplicator-pro'); ?> <br />
            </label>
        </div>
        <?php $tplMng->render('admin_pages/storages/parts/max_backups_description'); ?>
        <div id="gdrive_max_files_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<?php
$tplMng->render('admin_pages/storages/parts/provider_foot');

// Alerts for Google Drive
$alertConnStatus          = new UiDialog();
$alertConnStatus->title   = __('Google Drive Authorization Error', 'duplicator-pro');
$alertConnStatus->message = ''; // javascript inserted message
$alertConnStatus->initAlert();

?>
<script>
    jQuery(document).ready(function($) {
        DupliJs.Storage.GDrive = DupliJs.Storage.GDrive || {};

        $('#dup-gdrive-cancel-authorization').click(function(event) {
            event.stopPropagation();
            DupliJs.Storage.RevokeAuth(<?php echo (int) $storage->getId(); ?>);
        });

        DupliJs.Storage.GDrive.GoogleGetAuthUrl = function() {
            $('#dupli-gdrive-connect-btn-area').hide();
            $('#dupli-gdrive-steps').show();
            DupliJs.Storage.GDrive.AuthUrl = <?php echo json_encode($storage->getAuthorizationUrl()); ?>;
        }

        DupliJs.Storage.GDrive.OpenAuthPage = function() {
            window.open(DupliJs.Storage.GDrive.AuthUrl, '_blank');
        }

        $('#gdrive-finalize-setup').click(function(event) {
            event.stopPropagation();

            if ($('#gdrive-auth-code').val().length > 5) {
                DupliJs.Storage.PrepareForSubmit();

                //$("#dup-storage-form").submit();

                DupliJs.Storage.Authorize(
                    <?php echo (int) $storage->getId(); ?>,
                    <?php echo (int) $storage->getSType(); ?>, {
                        'name': $('#name').val(),
                        'notes': $('#notes').val(),
                        'storage_folder': $('#_gdrive_storage_folder').val(),
                        'max_packages': $('#gdrive_max_files').val(),
                        'auth_code': $('#gdrive-auth-code').val()
                    }
                );
            } else {
                <?php $alertConnStatus->showAlert(); ?>
                let alertMsg = "<i class='fas fa-exclamation-triangle'></i> " +
                    "<?php esc_html_e('Please enter your Google Drive authorization code!', 'duplicator-pro'); ?>";
                <?php $alertConnStatus->updateMessage("alertMsg"); ?>
            }

            return false;
        });
    });
</script>