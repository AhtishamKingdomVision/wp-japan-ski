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

use Duplicator\Models\GlobalEntity;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Models\DynamicGlobalEntity;
use Duplicator\Utils\LockUtil;
use Duplicator\Libs\WpUtils\WpArchiveUtils;

$global  = GlobalEntity::getInstance();
$dGlobal = DynamicGlobalEntity::getInstance();

$max_execution_time = (int) SnapUtil::phpIniGet("max_execution_time", 0);
$max_execution_time = $max_execution_time < 0 ? PHP_INT_MAX : $max_execution_time;
$max_execution_time = empty($max_execution_time) ? 30 : $max_execution_time;
$workerTimeCapRange = [
    10,
    min(180, max(30, (int) (0.7 * (float) $max_execution_time))),
];
$workerTimeValue    = (int) max($workerTimeCapRange[0], min((int) $global->php_max_worker_time_in_sec, $workerTimeCapRange[1]));
?>
<div class="dup-accordion-wrapper display-separators close">
    <div class="accordion-header">
        <h3 class="title">
            <?php esc_html_e("Advanced", 'duplicator-pro'); ?>
        </h3>
    </div>
    <div class="accordion-content">
        <label class="lbl-larger">
            <?php esc_html_e("Thread Lock", 'duplicator-pro'); ?>
        </label>
        <div class="margin-bottom-1">
            <input
                type="radio"
                name="lock_mode"
                id="lock_mode_flock"
                class="margin-0"
                value="<?php echo (int) LockUtil::LOCK_MODE_FILE; ?>"
                <?php checked($global->lock_mode, LockUtil::LOCK_MODE_FILE); ?>>
            <label for="lock_mode_flock">
                <?php esc_html_e("File", 'duplicator-pro'); ?>
            </label>&nbsp;
            <input
                type="radio"
                name="lock_mode"
                id="lock_mode_sql"
                class="margin-0"
                value="<?php echo (int) LockUtil::LOCK_MODE_SQL; ?>"
                <?php checked($global->lock_mode, LockUtil::LOCK_MODE_SQL); ?>>
            <label for="lock_mode_sql">
                <?php esc_html_e("SQL", 'duplicator-pro'); ?>
            </label>&nbsp;
        </div>

        <label class="lbl-larger">
            <?php esc_html_e("Max Worker Time", 'duplicator-pro'); ?>
        </label>
        <div class="margin-bottom-1">
            <input
                data-parsley-required
                data-parsley-errors-container="#php_max_worker_time_in_sec_error_container"
                data-parsley-range="<?php echo esc_attr(wp_json_encode($workerTimeCapRange)); ?>"
                data-parsley-type="number"
                class="width-small inline-display margin-0"
                type="text"
                name="php_max_worker_time_in_sec"
                id="php_max_worker_time_in_sec"
                value="<?php echo (int) $workerTimeValue; ?>">&nbsp;
            <span>
                <?php esc_html_e('Seconds', 'duplicator-pro'); ?>
            </span>
            <div id="php_max_worker_time_in_sec_error_container" class="duplicator-error-container"></div>
            <p class="description">
                <?php
                esc_html_e(
                    'This setting controls how long each processing chunk can run. A lower value makes the process more reliable but slower.',
                    'duplicator-pro'
                );
                ?><br />
                <?php
                esc_html_e(
                    "Try a low value (30 seconds or lower) if the build fails with the recommended setting.",
                    'duplicator-pro'
                ); ?>
            </p>
        </div>
        <label class="lbl-larger">
            <?php esc_html_e("Ajax", 'duplicator-pro'); ?>
        </label>
        <div class="margin-bottom-1">
            <input
                type="radio"
                id="ajax_protocol_1"
                name="ajax_protocol"
                class="ajax_protocol margin-0"
                value="admin"
                <?php checked($global->ajax_protocol, 'admin'); ?>>
            <label for="ajax_protocol_1">
                <?php esc_html_e("Auto", 'duplicator-pro'); ?>
            </label> &nbsp;
            <input
                type="radio"
                id="ajax_protocol_2"
                name="ajax_protocol"
                class="ajax_protocol margin-0"
                value="http"
                <?php checked($global->ajax_protocol == 'http'); ?>>
            <label for="ajax_protocol_2">
                <?php esc_html_e("HTTP", 'duplicator-pro'); ?>
            </label> &nbsp;
            <input
                type="radio"
                id="ajax_protocol_3"
                name="ajax_protocol"
                class="ajax_protocol margin-0"
                value="https"
                <?php checked($global->ajax_protocol == 'https'); ?>>
            <label for="ajax_protocol_3">
                <?php esc_html_e("HTTPS", 'duplicator-pro'); ?>
            </label> &nbsp;
            <input
                type="radio"
                id="ajax_protocol_4"
                name="ajax_protocol"
                class="ajax_protocol margin-0"
                value="custom"
                <?php checked($global->ajax_protocol, 'custom'); ?>>
            <label for="ajax_protocol_4">
                <?php esc_html_e("Custom URL", 'duplicator-pro'); ?>
            </label> <br />
            <input
                type="<?php echo ($global->ajax_protocol == 'custom' ? 'text' : 'hidden'); ?>"
                id="custom_ajax_url"
                name="custom_ajax_url"
                class="width-xlarge"
                placeholder="<?php esc_attr_e('Consult support before changing.', 'duplicator-pro'); ?>"
                value="<?php echo esc_url($global->custom_ajax_url); ?>">
            <span id="custom_ajax_url_error" class="alert-color">
                <?php esc_html_e("Bad URL!", 'duplicator-pro'); ?>
            </span>
            <p class="description">
                <?php esc_html_e("Used to kick off build worker. Only change if Backups get stuck at the start of a build.", 'duplicator-pro'); ?>
            </p>
        </div>

        <label class="lbl-larger">
            <?php esc_html_e('Root path', 'duplicator-pro') ?>
        </label>
        <div class="margin-bottom-1">
            <input
                type="checkbox"
                name="homepath_as_abspath"
                id="homepath_as_abspath"
                class="margin-0"
                <?php disabled(WpArchiveUtils::isAbspathHomepathEquivalent()); ?>
                <?php checked($global->homepath_as_abspath); ?>
                value="1">
            <label for="homepath_as_abspath">
                <?php
                printf(
                    esc_html_x(
                        'Use ABSPATH %s as root path.',
                        '%s represents the ABSPATH surrounded with bold (<b>) tags',
                        'duplicator-pro'
                    ),
                    '<b>' . esc_html(WpArchiveUtils::getArchiveListPaths('abs')) . '</b>'
                );
                ?>
                <br>
            </label>
            <p class="description">
                <?php
                if (WpArchiveUtils::isAbspathHomepathEquivalent()) {
                    esc_html_e('Abspath and home path are equivalent so this option is disabled', 'duplicator-pro');
                } else {
                    ?>
                    <?php
                    printf(
                        esc_html_x(
                            'In this installation the default root path is %s.',
                            '%s represents the root path surrounded with bold (<b>) tags',
                            'duplicator-pro'
                        ),
                        '<b>' . esc_html(SnapIO::safePathUntrailingslashit(get_home_path(), true)) . '</b>'
                    ); ?><br>
                    <?php
                    esc_html_e(
                        'The path of the WordPress core is different. Activate this option if you want to consider ABSPATH as root path.',
                        'duplicator-pro'
                    );
                }
                ?>

            </p>
        </div>

        <label class="lbl-larger">
            <?php esc_html_e('Scan File Checks', 'duplicator-pro'); ?>
        </label>
        <div class="margin-bottom-1">
            <input
                type="checkbox"
                name="_skip_archive_scan"
                id="_skip_archive_scan"
                class="margin-0"
                <?php checked($global->skip_archive_scan); ?>>
            <label for="_skip_archive_scan">
                <?php esc_html_e("Skip", 'duplicator-pro') ?>
            </label><br />
            <p class="description">
                <?php
                esc_html_e(
                    'If enabled all files check on scan will be skipped before Backup creation.
                    In some cases, this option can be beneficial if the scan process is having issues running or returning errors.',
                    'duplicator-pro'
                );
                ?>
            </p>
        </div>

        <label class="lbl-larger">
            <?php esc_html_e('Client-side Kickoff', 'duplicator-pro'); ?>
        </label>
        <div class="margin-bottom-1">
            <input
                type="checkbox"
                name="_clientside_kickoff"
                id="_clientside_kickoff"
                class="margin-0"
                <?php checked($global->clientside_kickoff); ?> />
            <label for="_clientside_kickoff">
                <?php esc_html_e("Enabled", 'duplicator-pro') ?>
            </label><br />
            <p class="description">
                <?php esc_html_e('Initiate Backup build from client. Only check this if instructed to by Duplicator support.', 'duplicator-pro'); ?>
            </p>
        </div>

        <label class="lbl-larger">
            <?php esc_html_e("Password-Protected Access", 'duplicator-pro'); ?>
        </label>
        <div class="margin-bottom-1">
            <input
                type="checkbox"
                name="_basic_auth_enabled"
                id="_basic_auth_enabled"
                value="1"
                class="margin-0"
                <?php checked($dGlobal->getValBool('basic_auth_enabled')); ?>>
            <label for="_basic_auth_enabled">
                <?php esc_html_e("Enabled", 'duplicator-pro') ?>
            </label>
            <i class="fa-solid fa-question-circle fa-sm dark-gray-color"
                data-tooltip-title="<?php esc_attr_e("HTTP Basic authentication", 'duplicator-pro'); ?>"
                data-tooltip="<?php esc_attr_e(
                    "When HTTP Basic authentication is applied Duplicator needs to attach the login credentials to each request to the server.
                        This is required for the build process to work properly. If you see a browser popup login window when accessing the admin area,
                        then basic authentication should be enabled. If you are not sure, please consult your hosting provider.",
                    'duplicator-pro'
                ); ?>"></i>
            <div id="dup-basic-auth-login-wrapper">
                <input
                    autocomplete="off"
                    placeholder="<?php esc_attr_e('User', 'duplicator-pro'); ?>"
                    type="text"
                    name="basic_auth_user"
                    id="basic_auth_user"
                    class="margin-0"
                    value="<?php echo esc_attr($dGlobal->getValString('basic_auth_user')); ?>"
                    <?php disabled(!$dGlobal->getValBool('basic_auth_enabled')); ?>>
                <span class="dup-password-toggle">
                    <input
                        id="auth_password"
                        autocomplete="off"
                        placeholder="<?php esc_attr_e('Password', 'duplicator-pro'); ?>"
                        type="password"
                        name="basic_auth_password"
                        id="basic_auth_password"
                        class="margin-0"
                        value="<?php echo esc_attr($dGlobal->getValString('basic_auth_password')); ?>"
                        <?php disabled(!$dGlobal->getValBool('basic_auth_enabled')); ?>>
                    <button type="button">
                        <i class="fas fa-eye fa-sm"></i>
                    </button>
                </span>
            </div>
            <p class="description">
                <?php esc_html_e(
                    'Enable this function and provide username and password in case Backup creation error.
                    Essential for making authorized HTTP requests in a server protected folder.',
                    'duplicator-pro'
                ); ?>
            </p>
        </div>
    </div>
</div>
<script>
    (function($) {
        $('#_basic_auth_enabled').on('change', function() {
            if ($(this).is(':checked')) {
                $('#dup-basic-auth-login-wrapper input').prop('disabled', false);
            } else {
                $('#dup-basic-auth-login-wrapper input').prop('disabled', true);
            }
        });

        var url_error = $('#custom_ajax_url_error');
        // Check URL is valid
        $.urlExists = function(url) {
            var http = new XMLHttpRequest();
            try {
                http.open('HEAD', url, false);
                http.send();
            } catch (err) {
                $('#custom_ajax_url_error').html(err.message);
                return false;
            }
            return http.status != 404;
        };
        var debounce;
        $('#custom_ajax_url').on('input keyup keydown change paste focus', function(e) {
            clearTimeout(debounce);
            var $this = $(this);
            debounce = setTimeout(function() {
                $this.css({
                    'border': ''
                });
                url_error.hide();
                if (!$.urlExists($this.val())) {
                    $this.css({
                        'border': 'maroon 1px solid'
                    });
                    url_error.show();
                }
            }, 500);
        });

        (function($this) {
            $this.css({
                'border': ''
            });
            url_error.hide();
            setTimeout(function() {
                var isCustomAjaxUrl = $('#ajax_protocol_4').is(':checked');
                if (isCustomAjaxUrl && !$.urlExists($this.val())) {
                    $this.css({
                        'border': 'maroon 1px solid'
                    });
                    url_error.show();
                }
                if (isCustomAjaxUrl) {
                    $('#custom_ajax_url').attr('data-parsley-required', 'true');
                } else {
                    $('#custom_ajax_url').removeAttr('data-parsley-required');
                }
            }, 0);
        }($('#custom_ajax_url')))

        /*
         * DISPLAY OR HIDE CUSTOM_AJAX_URL
         */
        $('.ajax_protocol').on('input click change select touchstart', function(e) {
            // Setup and collect value
            var $this = $(this),
                value = $this.val(),
                hideField = $('#custom_ajax_url'),
                hideFieldState = hideField.attr('type'),
                offset = 200;
            url_error.hide();
            if (value == 'custom') {
                // Display hidden field
                if (hideFieldState == 'hidden') {
                    hideField.hide().attr('type', 'text').fadeIn(offset).attr('data-parsley-required', 'true');
                    hideField.css({
                        'border': ''
                    });
                    url_error.hide();
                    if (!$.urlExists(hideField.val())) {
                        hideField.css({
                            'border': 'maroon 1px solid'
                        });
                        url_error.show();
                    }
                }
            } else {
                // Hide field but keep it active for POST reading
                if (hideFieldState == 'text') {
                    var parsleyId = $('#custom_ajax_url').data('parsley-id');
                    var errorUlId = '#parsley-id-' + parsleyId;
                    if ($(errorUlId).length)
                        $(errorUlId).remove();
                    hideField.fadeOut(Math.round(offset / 2), function() {
                        $(this).attr('type', 'hidden').show();
                    }).removeAttr('data-parsley-required');;
                }
            }
        });
    }(window.jQuery || jQuery))
</script>
