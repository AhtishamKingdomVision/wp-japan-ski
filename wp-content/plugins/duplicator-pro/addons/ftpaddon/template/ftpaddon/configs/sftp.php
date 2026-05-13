<?php

/**
 * Duplicator messages sections
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Addons\FtpAddon\Models\SFTPStorage;
use Duplicator\Views\ViewHelper;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var SFTPStorage $storage
 */
$storage = $tplData["storage"];
/** @var string */
$server = $tplData["server"];
/** @var int */
$port = $tplData["port"];
/** @var string */
$username = $tplData["username"];
/** @var string */
$password = $tplData["password"];
/** @var string */
$privateKey = $tplData["privateKey"];
/** @var string */
$privateKeyPwd = $tplData["privateKeyPwd"];
/** @var string */
$storageFolder = $tplData["storageFolder"];
/** @var int */
$maxPackages =  $tplData["maxPackages"];
/** @var int */
$timeout = $tplData["timeout"];

$isEditMode = ($storage->getId() > 0);

$tplMng->render('admin_pages/storages/parts/provider_head');
?>
<tr>
    <td class="dupli-sub-title" colspan="2"><b><?php esc_html_e("Credentials", 'duplicator-pro'); ?></b></td>
</tr>
<tr>
    <th scope="row"><label for="sftp_server"><?php esc_html_e("Server", 'duplicator-pro'); ?></label></th>
    <td>
        <div class="horizontal-input-row">
            <input
                id="sftp_server"
                class="dup-empty-field-on-submit"
                name="sftp_server"
                data-parsley-errors-container="#sftp_server_error_container"
                data-parsley-required="true"
                type="text"
                autocomplete="off"
                value="<?php echo esc_attr($server); ?>">
            <label for="sftp_server">
                <?php esc_html_e("Port", 'duplicator-pro'); ?>
            </label>
            <input
                name="sftp_port"
                id="sftp_port"
                data-parsley-errors-container="#sftp_server_error_container"
                data-parsley-required="true"
                data-parsley-type="number"
                data-parsley-range="[1, 65535]"
                type="number"
                min="1"
                max="65535"
                style="width:75px"
                value="<?php echo (int) $port; ?>">
        </div>
        <div id="sftp_server_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<tr>
    <th scope="row"><label for="sftp_username"><?php esc_html_e("Username", 'duplicator-pro'); ?></label></th>
    <td>
        <input
            id="sftp_username"
            class="dup-empty-field-on-submit"
            name="sftp_username"
            type="text"
            autocomplete="off"
            value="<?php echo esc_attr($username); ?>"
            data-parsley-errors-container="#sftp_username_error_container"
            data-parsley-required="true">
        <div id="sftp_username_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<tr>
    <th scope="row"><label for="sftp_private_key"><?php esc_html_e("Private Key File", 'duplicator-pro'); ?></label></th>
    <td>
        <input
            id="sftp_private_key_file"
            class="dup-empty-field-on-submit margin-bottom-0"
            name="sftp_private_key_file"
            onchange="DuplicatorReadPrivateKey(this);"
            type="file"
            accept="ppk"
            value=""
            data-parsley-errors-container="#sftp_private_key_error_container"><br />
        <input type="hidden" name="sftp_private_key" id="sftp_private_key" value="<?php echo esc_attr($privateKey); ?>" />
        <div id="sftp_private_key_error_container" class="duplicator-error-container"></div>
        <p>
            <?php
            echo wp_kses(
                __("<b>Optional:</b> Private key file used for secure authentication with SFTP servers instead of password-based login.<br />
                Upload a PuTTY-formatted key file (.ppk) for establishing SSH connections.", 'duplicator-pro'),
                ViewHelper::GEN_KSES_TAGS
            );
            ?>
        </p>
    </td>
</tr>
<tr>
    <th scope="row"><label for="sftp_password"><?php esc_html_e("Password", 'duplicator-pro'); ?></label></th>
    <td>
        <div class="horizontal-input-row">
            <input
                id="sftp_password"
                class="dup-empty-field-on-submit inline-display"
                name="sftp_password"
                type="password"
                placeholder="<?php echo esc_attr(str_repeat("*", strlen($password))); ?>"
                autocomplete="off"
                value=""
                data-parsley-required="<?php echo ($isEditMode ? 'false' : 'true'); ?>"
                data-parsley-errors-container="#sftp_password_error_container"
                data-parsley-trigger="change">
            <i
                class="fa-solid fa-question-circle fa-sm dark-gray-color margin-left-1"
                data-tooltip-title="<?php esc_attr_e("Private Key Password", 'duplicator-pro'); ?>"
                data-tooltip="<?php esc_attr_e("If you are using a private key, you can enter the password here.", 'duplicator-pro'); ?>">
            </i>
        </div>
        <div id="sftp_password_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<tr>
    <th scope="row"><label for="sftp_password2"><?php esc_html_e("Retype Password", 'duplicator-pro'); ?></label></th>
    <td>
        <input
            id="sftp_password2"
            class="dup-empty-field-on-submit"
            name="sftp_password2"
            type="password"
            placeholder="<?php echo esc_attr(str_repeat("*", strlen($password))); ?>"
            autocomplete="off"
            value=""
            data-parsley-errors-container="#sftp_password2_error_container"
            data-parsley-trigger="change"
            data-parsley-equalto="#sftp_password"
            data-parsley-required="<?php echo ($isEditMode ? 'false' : 'true'); ?>"
            data-parsley-equalto-message="<?php esc_attr_e("Passwords do not match", 'duplicator-pro'); ?>">
        <div id="sftp_password2_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<tr>
    <td class="dupli-sub-title" colspan="2"><b><?php esc_html_e("Settings", 'duplicator-pro'); ?></b></td>
</tr>
<tr>
    <th scope="row"><label for="_sftp_storage_folder"><?php esc_html_e("Storage Folder", 'duplicator-pro'); ?></label></th>
    <td>
        <div class="horizontal-input-row">
            <input id="_sftp_storage_folder" name="_sftp_storage_folder" type="text" value="<?php echo esc_attr($storageFolder); ?>">
        </div>
        <p>
            <i>
                <?php
                printf(
                    esc_html_x(
                        'Folder where backups will be stored. This should be %1$san absolute path, not a relative path%2$s
                            and be unique for each web-site using Duplicator.',
                        '%1$s representes the opening and %2$s the closing bold (<b>) tag',
                        'duplicator-pro'
                    ),
                    '<b>',
                    '</b>'
                );
                ?>
            </i>
        </p>
    </td>
</tr>
<tr>
    <th scope="row"><label for="sftp_max_files"><?php esc_html_e("Max Backups", 'duplicator-pro'); ?></label></th>
    <td>
        <div class="horizontal-input-row">
            <input
                id="sftp_max_files"
                name="sftp_max_files"
                data-parsley-errors-container="#sftp_max_files_error_container"
                type="text"
                value="<?php echo (int) $maxPackages; ?>">
            <label for="sftp_max_files"><?php esc_html_e("Number of Backups to keep in folder.", 'duplicator-pro'); ?></label>
        </div>
        <?php $tplMng->render('admin_pages/storages/parts/max_backups_description'); ?>
        <div id="sftp_max_files_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<tr>
    <th scope="row"><label for="sftp_timeout_in_secs"><?php esc_html_e("Timeout", 'duplicator-pro'); ?></label></th>
    <td>
        <div class="horizontal-input-row">
            <input
                id="sftp_timeout"
                name="sftp_timeout_in_secs"
                data-parsley-errors-container="#sftp_timeout_error_container"
                type="text"
                value="<?php echo (int) $timeout; ?>">
            <label for="sftp_timeout_in_secs">
                <?php esc_html_e("seconds", 'duplicator-pro'); ?>
            </label>
        </div>
        <p>
            <i>
                <?php
                esc_html_e(
                    "Do not modify this setting unless you know the expected result or have talked to support.",
                    'duplicator-pro'
                ); ?>
            </i>
        </p>
        <div id="sftp_timeout_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<?php $tplMng->render('admin_pages/storages/parts/provider_foot'); ?>
<script>
    jQuery(document).ready(function($) {
        DuplicatorReadPrivateKey = function(file_obj) {
            var files = file_obj.files;
            var private_key = files[0];
            var reader = new FileReader();
            reader.onload = function(e) {
                $("#sftp_private_key").val(e.target.result);
            }
            reader.readAsText(private_key);
        }
    });
</script>
