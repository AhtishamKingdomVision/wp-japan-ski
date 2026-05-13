/*! dup admin script */
jQuery(document).ready(function ($) {
    $(document).on('click', '.dupli-admin-notice[data-to-dismiss] .notice-dismiss', function (event) {
        event.preventDefault();
        event.stopImmediatePropagation();
        var notice = $(this).closest('.dupli-admin-notice[data-to-dismiss]');
        $.post(ajaxurl, {
            action: 'duplicator_admin_notice_to_dismiss',
            notice: notice.data('to-dismiss'),
            nonce: dupli_global_data.nonce_admin_notice_to_dismiss
        });
    });

    function dupDashboardUpdate() {
        jQuery.ajax({
            type: "POST",
            url: dupli_global_data.ajaxurl,
            dataType: "json",
            data: {
                action: 'duplicator_dashboad_widget_info',
                nonce: dupli_global_data.nonce_dashboard_widged_info
            },
            success: function (result, textStatus, jqXHR) {
                if (result.success) {
                    $('#duplicator_dashboard_widget .dup-last-backup-info').html(result.data.funcData.lastBackupInfo);

                    if (result.data.funcData.isRunning) {
                        $('#duplicator_dashboard_widget #dupli-create-new').addClass('disabled');
                    } else {
                        $('#duplicator_dashboard_widget #dupli-create-new').removeClass('disabled');
                    }
                }
            },
            complete: function() {
                setTimeout(
                    function(){
                        dupDashboardUpdate();
                    }, 
                    5000
                );
            }
        });
    }
    
    if ($('#duplicator_dashboard_widget').length) {
        dupDashboardUpdate();

        $('#duplicator_dashboard_widget #dup-dash-widget-section-recommended').on('click', function (event) {
            event.stopPropagation();
            
            $(this).closest('.dup-section-recommended').fadeOut();

            jQuery.ajax({
                type: "POST",
                url: dupli_global_data.ajaxurl,
                dataType: "json",
                data: {
                    action: 'duplicator_dismiss_recommended_plugin',
                    nonce: dupli_global_data.nonce_dashboard_widged_dismiss_recommended
                },
                success: function (result, textStatus, jqXHR) {
                    // do nothing
                }
            });
        });
    }
});
