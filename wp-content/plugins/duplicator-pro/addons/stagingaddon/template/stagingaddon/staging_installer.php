<?php

/**
 * Staging installer page - loads installer in iframe
 */

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

use Duplicator\Addons\StagingAddon\Controllers\StagingPageController;

$installerUrl = $tplData['installerUrl'] ?? '';
$stagingId    = $tplData['stagingId'] ?? 0;
$stagingTitle = $tplData['stagingTitle'] ?? __('Staging Site', 'duplicator-pro');
$error        = $tplData['error'] ?? '';

// Polling configuration for installation status checks
$pollIntervalMs  = 5000;  // Check every 5 seconds
$pollMaxAttempts = 120;   // Stop after 10 minutes (5s * 120 = 600s)

if (!empty($error)) : ?>
    <div class="wrap dup-styles">
        <h1><?php esc_html_e("Install Staging Site", 'duplicator-pro'); ?></h1>
        <div class="notice notice-error">
            <p><?php echo esc_html($error); ?></p>
        </div>
        <p>
            <a href="<?php echo esc_url(StagingPageController::getStagingPageLink()); ?>" class="button">
                <i class="fa fa-caret-left"></i> <?php esc_html_e("Back to Staging", 'duplicator-pro'); ?>
            </a>
        </p>
    </div>
<?php else : ?>
    <div id="dupli-staging-installer-wrapper" class="dup-styles">
        <div id="dupli-staging-installer-top-bar">
            <span class="dupli-staging-installer-title">
                <i class="fas fa-clone"></i>
                <?php echo esc_html(sprintf(__('Installing: %s', 'duplicator-pro'), $stagingTitle)); ?>
            </span>
            <span id="dupli-staging-status-indicator" class="dupli-staging-status-creating">
                <span class="spinner is-active"></span>
                <?php esc_html_e('Creating...', 'duplicator-pro'); ?>
            </span>
        </div>
        <iframe id="dupli-staging-installer-iframe" src="<?php echo esc_url($installerUrl); ?>"></iframe>
    </div>

    <!-- Success bar template (hidden, cloned by JS when installation completes) -->
    <template id="dupli-staging-success-template">
        <div id="dupli-staging-success-bar">
            <span class="dupli-success-icon"><i class="fas fa-check"></i></span>
            <span class="dupli-success-message">
                <?php esc_html_e('Staging site is ready! Log in to the admin to finalize the installation.', 'duplicator-pro'); ?>
            </span>
        </div>
    </template>

    <script type="text/javascript">
    (function($) {
        'use strict';

        var stagingId = <?php echo (int) $stagingId; ?>;
        var checkNonce = '<?php echo esc_js(wp_create_nonce('duplicator_staging_check_complete')); ?>';
        var pollInterval = null;
        var pollCount = 0;

        // Polling configuration (from PHP)
        var pollIntervalMs = <?php echo (int) $pollIntervalMs; ?>;
        var maxPolls = <?php echo (int) $pollMaxAttempts; ?>;

        // i18n strings
        var i18n = {
            stillProcessing: <?php echo wp_json_encode(__('Still processing...', 'duplicator-pro')); ?>,
            complete: <?php echo wp_json_encode(__('Complete!', 'duplicator-pro')); ?>
        };

        function checkCompletion() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'duplicator_staging_check_complete',
                    nonce: checkNonce,
                    staging_id: stagingId
                },
                success: function(response) {
                    var funcData = response.data && response.data.funcData ? response.data.funcData : {};
                    if (response.success && funcData.complete) {
                        stopPolling();
                        showComplete(funcData);
                    } else {
                        pollCount++;
                        if (pollCount >= maxPolls) {
                            stopPolling();
                            showTimeout();
                        }
                    }
                },
                error: function() {
                    pollCount++;
                    if (pollCount >= maxPolls) {
                        stopPolling();
                        showTimeout();
                    }
                }
            });
        }

        function stopPolling() {
            if (pollInterval) {
                clearInterval(pollInterval);
                pollInterval = null;
            }
        }

        function showTimeout() {
            $('#dupli-staging-status-indicator').removeClass('dupli-staging-status-creating')
                .html('<i class="fas fa-clock"></i> ' + i18n.stillProcessing);
        }

        function showComplete(data) {
            $('#dupli-staging-status-indicator').removeClass('dupli-staging-status-creating')
                .addClass('dupli-staging-status-ready')
                .html('<i class="fas fa-check-circle"></i> ' + i18n.complete);

            // Clone and show success bar from template
            var template = document.getElementById('dupli-staging-success-template');
            var successBar = template.content.cloneNode(true);

            $('#dupli-staging-installer-top-bar').after(successBar);

            // Installation complete - allow closing the window
            window.removeEventListener('beforeunload', beforeUnloadHandler);
        }

        // Prevent accidental window close during installation
        function beforeUnloadHandler(e) {
            e.preventDefault();
            e.returnValue = '';
        }

        $(document).ready(function() {
            // Block window close while installing
            window.addEventListener('beforeunload', beforeUnloadHandler);

            // Start polling at configured interval
            pollInterval = setInterval(checkCompletion, pollIntervalMs);

            // Initial check
            checkCompletion();
        });

    })(jQuery);
    </script>
<?php endif; ?>
