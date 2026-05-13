<?php

/**
 * @package Duplicator
 */

defined("ABSPATH") or die("");

use Duplicator\Controllers\SettingsPageController;
use Duplicator\Core\Controllers\ControllersManager;

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 */

?>
<script>
    jQuery(document).ready(function ($) {
        // Quick fix for submint/enter error
        $(window).on('keyup keydown', function (e) {
            if (!$(e.target).is('textarea'))
            {
                var keycode = (typeof e.keyCode != 'undefined' && e.keyCode > -1 ? e.keyCode : e.which);
                if ((keycode === 13)) {
                    e.preventDefault();
                    return false;
                }
            }
        });

        // Removes the values of hidden input fields marked with class dup-empty-field-on-submit
        DupliJs.Storage.EmptyValues = function () {
            $(':hidden .dup-empty-field-on-submit').val('');
        }

        // Removes tags marked with class dup-remove-on-submit-if-hidden, if they are hidden
        DupliJs.Storage.RemoveMarkedHiddenTags = function () {
            $('.dup-remove-on-submit-if-hidden:hidden').each(function() {
                $(this).remove();
            });
        }

        DupliJs.Storage.PrepareForSubmit = function () {
            DupliJs.Storage.EmptyValues();
            if ($('#dup-storage-form').parsley().isValid()) {
                // The form is about to be submitted.
                DupliJs.Storage.RemoveMarkedHiddenTags();
            }
        }

        $('#dup-storage-form').submit(DupliJs.Storage.PrepareForSubmit);

        DupliJs.Storage.AuthMessages = function () {
            let reloadUrl = new URL(window.location.href);
            let authMessage = reloadUrl.searchParams.get('dup-auth-message');
            let revokeMessage = reloadUrl.searchParams.get('dup-revoke-message');

            if (authMessage || revokeMessage) {
                // Display messages
                if (authMessage) {
                    DupliJs.addAdminMessage(authMessage, 'notice');
                }
                if (revokeMessage) {
                    DupliJs.addAdminMessage(revokeMessage, 'notice');
                }

                // Remove the params from URL to prevent persistence
                reloadUrl.searchParams.delete('dup-auth-message');
                reloadUrl.searchParams.delete('dup-revoke-message');
                reloadUrl.searchParams.delete('dup-storage-id');
                window.history.replaceState({}, '', reloadUrl.href);
            }
        }

        DupliJs.Storage.RevokeAuth = function (storageId)
        {
            // Get current page slug from URL
            const currentPage = new URL(window.location.href).searchParams.get('page') || '';

            DupliJs.Util.ajaxWrapper(
                {
                    action: 'duplicator_revoke_storage',
                    storage_id: storageId,
                    current_page: currentPage,
                    nonce: '<?php echo esc_js(wp_create_nonce('duplicator_revoke_storage')); ?>'
                },
                function (result, data, funcData, textStatus, jqXHR) {
                    if (funcData.success) {
                        // Backend provides the complete redirect URL
                        if (funcData.redirect_url) {
                            window.location.href = funcData.redirect_url;
                        } else {
                            // Fallback: simple reload
                            window.location.reload();
                        }
                    } else {
                        DupliJs.addAdminMessage(funcData.message, 'error');
                    }
                    return '';
                }
            );
        }

        DupliJs.Storage.Authorize = function (storageId, storageType, extraData)
        {
            // Get current page slug from URL
            const currentPage = new URL(window.location.href).searchParams.get('page') || '';

            extraData.action       = 'duplicator_auth_storage';
            extraData.storage_id   = storageId;
            extraData.storage_type = storageType;
            extraData.current_page = currentPage;
            extraData.nonce        = '<?php echo esc_js(wp_create_nonce('duplicator_auth_storage')); ?>';

            DupliJs.Util.ajaxWrapper(
                extraData,
                function (result, data, funcData, textStatus, jqXHR) {
                    if (funcData.success) {
                        // Backend provides the complete redirect URL
                        if (funcData.redirect_url) {
                            // Set unsaved changes to false, not to trigger alert during finalization
                            DupliJs.UI.hasUnsavedChanges = false;
                            window.location.href = funcData.redirect_url;
                        } else {
                            // Fallback: simple reload
                            DupliJs.UI.hasUnsavedChanges = false;
                            window.location.reload();
                        }
                    } else {
                        DupliJs.addAdminMessage(funcData.message, 'error');
                    }
                    return '';
                }
            );

            return false;
        }

        // Toggles Save Provider button for existing Storages only
        DupliJs.UI.formOnChangeValues($('#dup-storage-form'), function() {
            $('#button_file_test').prop('disabled', true);
        });

        //Init
        DupliJs.Storage.AuthMessages();
        jQuery('#name').focus().select();
    });

</script>
