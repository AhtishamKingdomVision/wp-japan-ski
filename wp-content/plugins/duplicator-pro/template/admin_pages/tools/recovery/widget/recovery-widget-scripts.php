<?php

/**
 * @package Duplicator
 */

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */
?>
<script>
    DupliJs.Pack.SetRecoveryPoint = function (packageId, callbackOnSuccess, callbackOnError, topHeaderMessage) {
        topHeaderMessage = (typeof topHeaderMessage !== 'undefined') ? topHeaderMessage : true;

        let okMsgContent = <?php echo json_encode($tplMng->render('admin_pages/tools/recovery/widget/recovery-message-set-ok', [], false)); ?>;
        let errorMsgContent = <?php echo json_encode($tplMng->render('admin_pages/tools/recovery/widget/recovery-message-set-error', [], false)); ?>;

        DupliJs.Pack.removeRecoveryMessages();
        DupliJs.Util.ajaxWrapper(
            {
                action: 'duplicator_set_recovery',
                recovery_package: packageId,
                fromPageTab: <?php echo json_encode(\Duplicator\Core\Controllers\ControllersManager::getUniqueIdOfCurrentPage()); ?>,
                nonce: '<?php echo wp_create_nonce('duplicator_set_recovery'); ?>'
            },
            function (result, data, funcData, textStatus, jqXHR) {
                if (topHeaderMessage) {
                    DupliJs.addAdminMessage(okMsgContent, 'notice', {
                        updateCallback: function (msgNode) {
                            msgNode.find('.recovery-set-message-ok').html(funcData.adminMessage);
                            DuplicatorTooltip.reload();
                            msgNode.find('.dupli-recovery-download-launcher').click(function () {
                                DupliJs.Pack.downloadLauncher();
                            });
                        }
                    });
                }

                if (typeof callbackOnSuccess === "function") {
                    callbackOnSuccess(funcData, data, textStatus, jqXHR);
                }

                return '';
            },
            function (result, data, funcData, textStatus, jqXHR) {
                DupliJs.addAdminMessage(errorMsgContent, 'error', {
                    'updateCallback': function (msgNode) {
                        msgNode.find('.recovery-error-message').html(data.message);
                    }
                });

                if (typeof callbackOnError === "function") {
                    callbackOnError(funcData, data, textStatus, jqXHR);
                }

                return '';
            }
        );

    };


    DupliJs.Pack.ResetRecoveryPoint = function (callbackOnSuccess) {
        let okMsgContent = <?php echo json_encode($tplMng->render('admin_pages/tools/recovery/widget/recovery-message-reset-ok', [], false)); ?>;
        let errorMsgContent = <?php echo json_encode($tplMng->render('admin_pages/tools/recovery/widget/recovery-message-reset-error', [], false)); ?>;

        DupliJs.Pack.removeRecoveryMessages();
        DupliJs.Util.ajaxWrapper(
            {
                action: 'duplicator_reset_recovery',
                nonce: '<?php echo wp_create_nonce('duplicator_reset_recovery'); ?>',
                fromPageTab: <?php echo json_encode(\Duplicator\Core\Controllers\ControllersManager::getUniqueIdOfCurrentPage()); ?>,
            },
            function (result, data, funcData, textStatus, jqXHR) {
                DupliJs.addAdminMessage(okMsgContent, 'notice');

                if (typeof callbackOnSuccess === "function") {
                    callbackOnSuccess(funcData, data, textStatus, jqXHR);
                }

                return '';
            },
            function (result, data, funcData, textStatus, jqXHR) {
                DupliJs.addAdminMessage(errorMsgContent, 'error', {
                    'updateCallback': function (msgNode) {
                        msgNode.find('.recovery-error-message').html(data.message);
                    }
                });
                return '';
            }
        );
    };

    DupliJs.Pack.UpdatgeRecoveryWidget = function (callbackOnSuccess) {
        let okMsgContent = <?php echo json_encode($tplMng->render('admin_pages/tools/recovery/widget/recovery-message-reset-ok', [], false)); ?>;
        let errorMsgContent = <?php echo json_encode($tplMng->render('admin_pages/tools/recovery/widget/recovery-message-reset-error', [], false)); ?>;

        DupliJs.Pack.removeRecoveryMessages();
        DupliJs.Util.ajaxWrapper(
            {
                action: 'duplicator_get_recovery_widget',
                nonce: '<?php echo wp_create_nonce('duplicator_get_recovery_widget'); ?>',
                fromPageTab: <?php echo json_encode(\Duplicator\Core\Controllers\ControllersManager::getUniqueIdOfCurrentPage()); ?>,
            },
            function (result, data, funcData, textStatus, jqXHR) {
                if (typeof callbackOnSuccess === "function") {
                    callbackOnSuccess(funcData, data, textStatus, jqXHR);
                }
                return '';
            },
            function (result, data, funcData, textStatus, jqXHR) {
                return <?php json_encode(__('Can\'t update recovery widget', 'duplicator-pro')); ?>;
            }
        );
    };

    DupliJs.Pack.removeRecoveryMessages = function () {
        jQuery('#wpcontent .dupli-recovery-message').closest('.notice').remove();
    };

    DupliJs.Pack.SetRecoveryPackageDetails = function (wrapper, details, setColor) {
        const setDelayAnimation = 1000;
        const setDurationAnimationStart = 500;
        const setDurationAnimationEnd = 1000;

        let newDetails = jQuery(details);
        wrapper.replaceWith(newDetails);
        wrapper = newDetails;

        wrapper.closest('.dupli-import-box').find('.box-title .badge').each(function () {
            if (wrapper.find('.dupli-recovery-active-link-header .dupli-recovery-status').hasClass('green')) {
                jQuery(this).removeClass('badge-warn').addClass('badge-pass').text('<?php echo esc_js(__('Good', 'duplicator-pro')) ?>');
            } else {
                jQuery(this).removeClass('badge-pass').addClass('badge-warn').text('<?php echo esc_js(__('Notice', 'duplicator-pro')) ?>');
            }
        });

        wrapper.find('.dupli-recovery-point-selector-area select, .dupli-recovery-point-actions .copy-link')
                .stop()
                .animate({
                    backgroundColor: setColor
                }, setDurationAnimationStart)
                .delay(setDelayAnimation)
                .animate({
                    backgroundColor: "transparent"
                }, setDurationAnimationEnd);

        wrapper.find('.dupli-recovery-point-details')
                .stop()
                .css({
                    'outline': '5px solid transparent',
                    'outline-offset': '5px'
                })
                .animate({
                    outlineColor: setColor
                }, setDurationAnimationStart)
                .delay(setDelayAnimation)
                .animate({
                    outlineColor: "transparent",
                    'outline-width': '0',
                    'outline-offset': '0'
                }, setDurationAnimationEnd);

        DupliJs.Pack.initRecoveryWidget(wrapper);
    };

    DupliJs.Pack.downloadLauncher = function () {
        DupliJs.Util.ajaxWrapper(
            {
                action: 'duplicator_disaster_launcher_download',
                nonce: '<?php echo wp_create_nonce('duplicator_disaster_launcher_download'); ?>'
            },
            function (result, data, funcData, textStatus, jqXHR) {
                if (funcData.success) {
                    DupliJs.downloadContentAsfile(funcData.fileName, funcData.fileContent, 'text/html');
                } else {
                    DupliJs.addAdminMessage(funcData.message, 'error');
                }
                return '';        
            }
        );
    };

    DupliJs.Pack.initRecoveryWidget = function (widgetWrapper) {
        widgetWrapper.find('.recovery-reset').off().click(function () {
            DupliJs.Pack.ResetRecoveryPoint(function (funcData, data, textStatus, jqXHR) {
                widgetWrapper.find('.recovery-select').val('');
                DupliJs.Pack.SetRecoveryPackageDetails(widgetWrapper, funcData.packageDetails, '#e1f5c1');
            });
        });

        widgetWrapper.find('.recovery-set').off().click(function () {
            let packageId = widgetWrapper.find('.recovery-select').val();
            if (!packageId) {
                DupliJs.Pack.ResetRecoveryPoint(function (funcData, data, textStatus, jqXHR) {
                    DupliJs.Pack.SetRecoveryPackageDetails(widgetWrapper, funcData.packageDetails, '#e1f5c1');
                });
            } else {
                DupliJs.Pack.SetRecoveryPoint(packageId,
                        function (funcData, data, textStatus, jqXHR) {
                            DupliJs.Pack.SetRecoveryPackageDetails(widgetWrapper, funcData.packageDetails, '#e1f5c1');
                        },
                        function (funcData, data, textStatus, jqXHR) {
                            widgetWrapper.find('.recovery-select').val('');
                            DupliJs.Pack.SetRecoveryPackageDetails(widgetWrapper, '<p class="red" >' + data.message + '</span>', '#fcc3bd');
                        },
                        false);
            }
        });

        DuplicatorTooltip.reload();

        widgetWrapper.find('.dupli-recovery-windget-refresh').off().click(function () {
            DupliJs.Pack.UpdatgeRecoveryWidget(function (funcData, data, textStatus, jqXHR) {
                DupliJs.Pack.SetRecoveryPackageDetails(widgetWrapper, funcData.widget, '#e1f5c1');
            });
        });

        widgetWrapper.find('.dupli-recovery-download-launcher').off().click(function () {
            DupliJs.Pack.downloadLauncher();
        });
    };

    jQuery(document).ready(function ($)
    {
        $('.dupli-recovery-widget-wrapper').each(function () {
            let widgetWrapper = jQuery(this);
            DupliJs.Pack.initRecoveryWidget(widgetWrapper);
        });
    });
</script>
