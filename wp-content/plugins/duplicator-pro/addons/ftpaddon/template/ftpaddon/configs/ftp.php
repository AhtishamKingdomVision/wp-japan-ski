<?php

/**
 * Duplicator messages sections
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Addons\FtpAddon\Models\FTPStorage;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var FTPStorage $storage
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
$storageFolder = $tplData["storageFolder"];
/** @var int */
$maxPackages =  $tplData["maxPackages"];
/** @var int */
$timeout = $tplData["timeout"];
/** @var bool */
$useCurl = $tplData["useCurl"];
/** @var bool */
$isPassive = $tplData["isPassive"];
/** @var bool */
$useSSL = $tplData["useSSL"];
/** @var bool */
$isFtpAvailable = $tplData["isFtpAvailable"];
/** @var bool */
$isCurlAvailable = $tplData["isCurlAvailable"];

$isEditMode = ($storage->getId() > 0);

$tplMng->render('admin_pages/storages/parts/provider_head');
?>
<tr>
    <td class="dupli-sub-title" colspan="2"><b><?php esc_html_e("Credentials", 'duplicator-pro'); ?></b></td>
</tr>
<tr>
    <th scope="row"><label for="ftp_server"><?php esc_html_e("Server", 'duplicator-pro'); ?></label></th>
    <td>
        <div class="horizontal-input-row">
            <input
                id="ftp_server"
                class="dup-empty-field-on-submit"
                name="ftp_server"
                type="text"
                autocomplete="off"
                value="<?php echo esc_attr($server); ?>"
                data-parsley-errors-container="#ftp_server_error_container"
                data-parsley-required="true">
            <label for="ftp_server">
                <?php esc_html_e("Port", 'duplicator-pro'); ?>
            </label>
            <input
                name="ftp_port"
                id="ftp_port"
                type="number"
                min="1"
                max="65535"
                style="width:75px"
                value="<?php echo (int) $port; ?>"
                data-parsley-errors-container="#ftp_server_error_container"
                data-parsley-required="true"
                data-parsley-type="number"
                data-parsley-range="[1, 65535]">
        </div>
        <div id="ftp_server_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<tr>
    <th scope="row"><label for="ftp_username"><?php esc_html_e("Username", 'duplicator-pro'); ?></label></th>
    <td>
        <input
            id="ftp_username"
            class="dup-empty-field-on-submit"
            name="ftp_username"
            type="text"
            autocomplete="off"
            value="<?php echo esc_attr($username); ?>"
            data-parsley-errors-container="#ftp_username_error_container"
            data-parsley-required="true">
        <div id="ftp_username_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<tr>
    <th scope="row"><label for="ftp_password"><?php esc_html_e("Password", 'duplicator-pro'); ?></label></th>
    <td>
        <input
            id="ftp_password"
            name="ftp_password"
            type="password"
            class="dup-empty-field-on-submit"
            placeholder="<?php echo esc_attr(str_repeat("*", strlen($password))); ?>"
            autocomplete="off"
            value=""
            data-parsley-errors-container="#ftp_password_error_container"
            data-parsley-required="<?php echo ($isEditMode ? 'false' : 'true'); ?>">
        <div id="ftp_password_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<tr>
    <th scope="row"><label for="ftp_password2"><?php esc_html_e("Retype Password", 'duplicator-pro'); ?></label></th>
    <td>
        <input
            id="ftp_password2"
            class="dup-empty-field-on-submit"
            name="ftp_password2"
            type="password"
            placeholder="<?php echo esc_attr(str_repeat("*", strlen($password))); ?>"
            autocomplete="off"
            value=""
            data-parsley-errors-container="#ftp_password2_error_container"
            data-parsley-trigger="change"
            data-parsley-equalto="#ftp_password"
            data-parsley-required="<?php echo ($isEditMode ? 'false' : 'true'); ?>"
            data-parsley-equalto-message="<?php esc_html_e("Passwords do not match", 'duplicator-pro'); ?>">
        <div id="ftp_password2_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<tr>
    <td class="dupli-sub-title" colspan="2"><b><?php esc_html_e("Settings", 'duplicator-pro'); ?></b></td>
</tr>
<tr>
    <th scope="row"><label for="_ftp_storage_folder"><?php esc_html_e("Storage Folder", 'duplicator-pro'); ?></label></th>
    <td>
        <div class="horizontal-input-row">
            <input
                id="_ftp_storage_folder"
                name="_ftp_storage_folder"
                type="text"
                value="<?php echo esc_attr($storageFolder); ?>">
        </div>
        <p>
            <i>
                <?php
                esc_html_e(
                    "Folder where backups will be stored. This should be unique for each web-site using Duplicator.",
                    'duplicator-pro'
                ); ?>
            </i>
        </p>
    </td>
</tr>
<tr>
    <th scope="row"><label for="ftp_max_files"><?php esc_html_e("Max Backups", 'duplicator-pro'); ?></label></th>
    <td>
        <label for="ftp_max_files">
            <div class="horizontal-input-row">
                <input
                    id="ftp_max_files"
                    name="ftp_max_files"
                    type="number"
                    value="<?php echo (int) $maxPackages; ?>"
                    min="0"
                    maxlength="4"
                    data-parsley-errors-container="#ftp_max_files_error_container"
                    data-parsley-required="true"
                    data-parsley-type="number"
                    data-parsley-min="0">
                <label for="ftp_max_files"><?php esc_html_e("Number of Backups to keep in folder.", 'duplicator-pro'); ?></label>
            </div>
            <?php $tplMng->render('admin_pages/storages/parts/max_backups_description'); ?>
        </label>
        <div id="ftp_max_files_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<tr>
    <th scope="row"><label for="ftp_timeout_in_secs"><?php esc_html_e("Timeout", 'duplicator-pro'); ?></label></th>
    <td>
        <div class="horizontal-input-row">
            <input
                id="ftp_timeout"
                name="ftp_timeout_in_secs"
                type="number"
                min="10"
                value="<?php echo (int) $timeout; ?>"
                data-parsley-errors-container="#ftp_timeout_error_container"
                data-parsley-required="true"
                data-parsley-type="number"
                data-parsley-min="10">
            <label for="ftp_timeout_in_secs">
                <?php esc_html_e("seconds", 'duplicator-pro'); ?>
            </label>
        </div>
        <p>
            <i><?php esc_html_e("Do not modify this setting unless you know the expected result or have talked to support.", 'duplicator-pro'); ?> </i>
        </p>
        <div id="ftp_timeout_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<tr>
    <th scope="row"><label for="ftp_ssl"><?php esc_html_e("Explicit SSL", 'duplicator-pro'); ?></label></th>
    <td>
        <div class="horizontal-input-row">
            <input name="_ftp_ssl" <?php checked($useSSL); ?> class="checkbox" value="1" type="checkbox" id="_ftp_ssl">
            <label for="_ftp_ssl"><?php esc_html_e("Enable", 'duplicator-pro'); ?></label>
        </div>
    </td>
</tr>
<tr>
    <th scope="row"><label for="_ftp_passive_mode"><?php esc_html_e("Passive Mode", 'duplicator-pro'); ?></label></th>
    <td>
        <div class="horizontal-input-row">
            <input
                <?php checked($isPassive); ?>
                class="checkbox"
                value="1"
                type="checkbox"
                name="_ftp_passive_mode"
                id="_ftp_passive_mode">
            <label for="_ftp_passive_mode"><?php esc_html_e("Enable", 'duplicator-pro'); ?></label>
        </div>
    </td>
</tr>
<tr>
    <th scope="row"><label><?php esc_html_e("Transport Method", 'duplicator-pro'); ?></label></th>
    <td>
        <div class="horizontal-input-row">
            <input
                type="radio"
                name="_ftp_use_curl"
                id="_ftp_use_curl_1"
                value="1"
                <?php checked($useCurl); ?>
                <?php disabled(!$isCurlAvailable); ?>>
            <label for="_ftp_use_curl_1"><?php esc_html_e("cURL", 'duplicator-pro'); ?></label>

            <input
                type="radio"
                name="_ftp_use_curl"
                id="_ftp_use_curl_0"
                value="0"
                <?php checked(!$useCurl); ?>
                <?php disabled(!$isFtpAvailable); ?>>
            <label for="_ftp_use_curl_0"><?php esc_html_e("FTP", 'duplicator-pro'); ?></label>
        </div>
        <?php if (!$isCurlAvailable || !$isFtpAvailable) : ?>
            <p>
                <i>
                    <?php
                    if (!$isCurlAvailable && $isFtpAvailable) {
                        esc_html_e(
                            "cURL is not available. FTP will be used by default.",
                            'duplicator-pro'
                        );
                    } elseif ($isCurlAvailable && !$isFtpAvailable) {
                        esc_html_e(
                            "FTP extension is not available. cURL will be used by default.",
                            'duplicator-pro'
                        );
                    }
                    ?>
                </i>
            </p>
        <?php else : ?>
            <p><i><?php esc_html_e("Choose between PHP cURL or FTP extension for file transfers.", 'duplicator-pro'); ?></i></p>
        <?php endif; ?>
        <p>
            <?php
            echo wp_kses(
                __(
                    "<b>Note:</b> This setting is for FTP and FTPS (FTP/SSL) only.
                    To use SFTP (SSH File Transfer Protocol) change the type dropdown above.",
                    'duplicator-pro'
                ),
                [
                    'b' => [],
                ]
            );
            ?>
        </p>
    </td>
</tr>
<?php $tplMng->render('admin_pages/storages/parts/provider_foot'); ?>
