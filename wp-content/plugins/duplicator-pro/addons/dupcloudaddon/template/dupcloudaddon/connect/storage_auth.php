<?php

/**
 * Template for Duplicator Cloud Connect Step 1
 *
 * @package   Duplicator\Addons\DupCloudAddon
 * @copyright (c) 2024, Snap Creek LLC
 */

use Duplicator\Addons\DupCloudAddon\Ajax\ServicesDupCloud;
use Duplicator\Addons\DupCloudAddon\Models\DupCloudStorage;
use Duplicator\Addons\DupCloudAddon\Models\QuickConnect;
use Duplicator\Addons\DupCloudAddon\Utils\DupCloudClient;
use Duplicator\Libs\Snap\SnapString;
use Duplicator\Views\UI\UiDialog;

defined('ABSPATH') || exit;

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 */
$storage               = $tplMng->getDataValueObjRequired('storage', DupCloudStorage::class);
$errorMsg              = '';
$showLicenseConnection = QuickConnect::isLicenseConnectionAvailable();
$showTokenConnection   = $tplMng->getDataValueBool('token_connection', false);
$bothConnection        = $showLicenseConnection && $showTokenConnection;

if (!$storage->isAuthorized()) : ?>
    <div class='dupcloud-authorization-state' id="dupcloud-state-unauthorized">
        <?php if ($showLicenseConnection) { ?>
            <button
                id="dupli-dupcloud-license-connect-btn"
                type="button"
                class="button secondary hollow margin-bottom-0"
            >
                <i class="fa fa-key"></i> <?php esc_html_e('Connect via Duplicator Pro License', 'duplicator-pro'); ?>
            </button>
            <?php
        }
        if ($bothConnection) { ?>
            &nbsp;<strong>or</strong>&nbsp;
            <?php
        }
        if ($showTokenConnection) { ?>
        <button
            id="dupli-dupcloud-connect-btn"
            type="button"
            class="button secondary hollow margin-bottom-0"
            onclick="DupliJs.Storage.DupCloud.ShowTokenInput();">
            <i class="fa fa-plug"></i> <?php esc_html_e('Connect Duplicator Cloud Token', 'duplicator-pro'); ?>
        </button>
        <?php } ?>

        <div id="dupli-dupcloud-token-area" style="display:none;">
            <div class="storage-auth-step">
                <p>
                    <b><?php esc_html_e('Step 1:', 'duplicator-pro'); ?></b>&nbsp;
                    <?php esc_html_e('Get your authentication token from Duplicator.com', 'duplicator-pro'); ?>
                </p>
                <button
                    type="button"
                    class="button secondary hollow margin-bottom-0"
                    onclick="window.open('<?php echo esc_js(DupCloudClient::getManageLicenseStorageUrl()); ?>', '_blank');">
                    <i class="fa fa-external-link"></i> <?php esc_html_e('Get Connection Token', 'duplicator-pro'); ?>
                </button>
            </div>

            <div class="storage-auth-step">
                <p>
                    <b><?php esc_html_e('Step 2:', 'duplicator-pro'); ?></b>&nbsp;
                    <?php esc_html_e('Paste your authentication token below:', 'duplicator-pro'); ?>
                </p>
                <input id="dupcloud-compound-token" name="dupcloud-compound-token" style="width: 500px" type="text">
            </div>

            <div class="storage-auth-step">
                <button
                    id="dupcloud-finalize-setup"
                    type="button"
                    class="button secondary margin-bottom-0">
                    <i class="fa fa-check-square"></i> <?php esc_html_e('Finalize Setup', 'duplicator-pro'); ?>
                </button>
            </div>
        </div>
    </div>
<?php else : ?>
    <div class='dupcloud-authorization-state' id="dupcloud-state-authorized" style="margin-top:-10px">
        <?php if (strlen($storage->getUserName()) > 0) : ?>
            <h3>
                <?php esc_html_e('Duplicator Cloud Account', 'duplicator-pro'); ?><br />
                <i class="dupli-edit-info">
                    <?php esc_html_e('Duplicator has been authorized to access this user\'s Duplicator Cloud account', 'duplicator-pro'); ?>
                </i>
            </h3>
            <?php if (!$storage->isValid($errorMsg, true)) : ?>
                <div class="alert-color margin-bottom-1">
                    <p><b><?php esc_html_e('The storage is currently not in a valid state.', 'duplicator-pro'); ?></b></p>
                    <p><b><?php esc_html_e('Error:', 'duplicator-pro'); ?></b> <?php echo esc_html($errorMsg); ?></p>
                </div>
            <?php endif; ?>
            <div id="dupcloud-account-info">
                <label><?php esc_html_e('Name', 'duplicator-pro'); ?>:</label>
                <?php echo esc_html($storage->getUserName()); ?><br />

                <label><?php esc_html_e('Email', 'duplicator-pro'); ?>:</label> <?php echo esc_html($storage->getUserEmail()); ?><br />
                <label><?php esc_html_e('Space', 'duplicator-pro'); ?>:</label>
            <?php if ($storage->getFreeSpace() > 0) : ?>
                <?php printf(
                    '%1$s of %2$s is is used',
                    esc_html(SnapString::byteSize($storage->getUsedSpace())),
                    esc_html(SnapString::byteSize($storage->getTotalSpace()))
                ); ?>
            <?php else : ?>
                <b class="alert-color">
                    <?php printf(
                        '%1$s of %2$s is used',
                        esc_html(SnapString::byteSize($storage->getUsedSpace())),
                        esc_html(SnapString::byteSize($storage->getTotalSpace()))
                    ); ?>
                </b>
                <br />
                <br />
                <b class="alert-color"><?php esc_html_e('Warning! Storage is full and cannot be used.', 'duplicator-pro'); ?></b>
            <?php endif ?>
            </div><br />
        <?php else : ?>
            <div><?php esc_html_e('Error retrieving user information.', 'duplicator-pro'); ?></div>
        <?php endif ?>
        <a href="<?php echo esc_url($storage->getBackupsUrl()); ?>" target="_blank"
            id="dup-dupcloud-manage-website"
            class="button margin-right-1 button-primary"
            target="_blank"
        >
            <?php esc_html_e('Manage Backups', 'duplicator-pro'); ?>
        </a>
        <button
            id="dup-dupcloud-cancel-authorization"
            type="button"
            class="button gray hollow">
            <?php esc_html_e('Cancel Authorization', 'duplicator-pro'); ?>
        </button><br />
        <i class="dupli-edit-info">
            <?php
            esc_html_e(
                'Disassociation of storage provider will require re-authorization.',
                'duplicator-pro'
            ); ?>
        </i>
    </div>
<?php endif;


$alertConnStatus          = new UiDialog();
$alertConnStatus->title   = __('Duplicator Cloud Authorization Error', 'duplicator-pro');
$alertConnStatus->message = ''; // javascript inserted message
$alertConnStatus->initAlert();
?>
<script>
    jQuery(document).ready(function($) {
        DupliJs.Storage.DupCloud = DupliJs.Storage.DupCloud || {};

        DupliJs.Storage.DupCloud.ShowTokenInput = function() {
            $('#dupli-dupcloud-connect-btn-area').hide();
            $('#dupli-dupcloud-token-area').show();
        }

        DupliJs.Storage.DupCloud.LicenseConnect = function($button, $skipNoLicenses = false) {
            var originalText = $button.html();
            $button.html('<i class="fa fa-spinner fa-spin"></i> <?php esc_html_e("Getting tokens...", "duplicator-pro"); ?>').prop('disabled', true);

            // AJAX call to get storage tokens
            DupliJs.Util.ajaxWrapper({
                action: '<?php echo esc_js(ServicesDupCloud::AJAX_ACTION_QUICK_CONNECT); ?>',
                nonce: '<?php echo esc_js(wp_create_nonce(ServicesDupCloud::AJAX_ACTION_QUICK_CONNECT)); ?>'
            },
            function(result, data, funcData, textStatus, jqXHR) {

                if (result.success) {
                    var tokens = funcData.tokens;
                    var modalHtml = funcData.html;

                    if (tokens.length === 0) {
                        $button.html(originalText).prop('disabled', false);
                        if ($skipNoLicenses == false) {
                            // No storage tokens available - show modal with purchase message
                            DupliJs.Storage.DupCloudLicense.showModal(modalHtml);
                        }
                    } else if (tokens.length === 1) {
                        // Single storage - auto connect (no modal)
                        DupliJs.Storage.DupCloudLicense.autoConnect(tokens[0]);
                    } else {
                        // Multiple storages - show selection modal
                        DupliJs.Storage.DupCloudLicense.showModal(modalHtml, tokens);
                    }
                } else {
                    $button.html(originalText).prop('disabled', false);

                    // Skip error messages in silence mode
                    if (data.message && $skipNoLicenses == false) {
                        DupliJs.addAdminMessage(data.message, 'error');
                    }
                }
            });
        }

        $('#dup-dupcloud-manage-website').click(function(e) {
            e.stopPropagation();
            window.open('<?php echo esc_js(DupCloudClient::manageWebsitesUrl()); ?>', '_blank');
            return false;
        });

        $('#dup-dupcloud-cancel-authorization').click(function(e) {
            e.stopPropagation();
            DupliJs.Storage.RevokeAuth(<?php echo (int) $storage->getId(); ?>);
            return false;
        });

        $('#dupcloud-finalize-setup').click(function(event) {
            event.stopPropagation();

            var compoundToken = $('#dupcloud-compound-token').val().trim();

            if (compoundToken.length > 0) {
                // Validate token format (should contain a dot)
                if (compoundToken.indexOf('.') === -1) {
                    <?php $alertConnStatus->showAlert(); ?>
                    let alertMsg = "<i class='fas fa-exclamation-triangle'></i> " +
                        "<?php esc_html_e('Invalid token format. Please ensure you copied the complete authentication token.', 'duplicator-pro'); ?>";
                    <?php $alertConnStatus->updateMessage("alertMsg"); ?>
                    return false;
                }

                DupliJs.Storage.PrepareForSubmit();

                DupliJs.Storage.Authorize(
                    <?php echo (int) $storage->getId(); ?>,
                    <?php echo (int) $storage->getSType(); ?>, {
                        'name': $('#name').val(),
                        'notes': $('#notes').val(),
                        'access_token': compoundToken  // Send compound token as access_token
                    }
                );
            } else {
                <?php $alertConnStatus->showAlert(); ?>
                let alertMsg = "<i class='fas fa-exclamation-triangle'></i> " +
                    "<?php esc_html_e('Please paste your authentication token!', 'duplicator-pro'); ?>";
                <?php $alertConnStatus->updateMessage("alertMsg"); ?>
            }

            return false;
        });

        // License-based connection handler
        $('#dupli-dupcloud-license-connect-btn').click(function(event) {
            event.stopPropagation();
            var $button = $(this);
            DupliJs.Storage.DupCloud.LicenseConnect($button);
            return false;
        });
    });

    // License connection namespace
    DupliJs.Storage.DupCloudLicense = DupliJs.Storage.DupCloudLicense || {};

    DupliJs.Storage.DupCloudLicense.showModal = function(htmlContent, tokens = null) {
        var modal = new DuplicatorModalBox({
            htmlContent: htmlContent,
            closeInContent: true,
            closeColor: "#000",
            openCallback: function(content, modalObj) {
                // If tokens provided, setup click handlers for storage selection
                if (tokens && tokens.length > 1) {
                    $(content).find('[data-token-select]').click(function() {
                        var tokenIndex = $(this).data('token-select');
                        if (tokens[tokenIndex]) {
                            modalObj.close();
                            DupliJs.Storage.DupCloudLicense.autoConnect(tokens[tokenIndex]);
                        }
                    });
                }
            }
        });
        modal.open();
    };

    DupliJs.Storage.DupCloudLicense.autoConnect = function(storageToken) {
        // Set the token in the input field
        $('#dupcloud-compound-token').val(storageToken.token);

        // Automatically trigger finalize setup
        setTimeout(function() {
            $('#dupcloud-finalize-setup').click();
        }, 100);
    };
</script>
