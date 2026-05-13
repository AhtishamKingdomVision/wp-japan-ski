<?php

/**
 * Duplicator messages sections
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Addons\AmazonS3Addon\Models\AmazonS3CompatibleStorage;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var AmazonS3CompatibleStorage $storage
 */
$storage = $tplData["storage"];
/** @var int */
$maxPackages = $tplData["maxPackages"];
/** @var string */
$storageFolder = $tplData["storageFolder"];
/** @var string */
$accessKey = $tplData["accessKey"];
/** @var string */
$bucket = $tplData["bucket"];
/** @var string */
$region = $tplData["region"];
/** @var string */
$secretKey = $tplData["secretKey"];
/** @var string */
$storageClass = $tplData["storageClass"];
/** @var string */
$endpoint = $tplData["endpoint"];
/** @var string */
$aclFullControl = $tplData["aclFullControl"];
/** @var bool */
$isAutofillEndpoint = $tplData["isAutofillEndpoint"];
/** @var bool */
$isAutofillRegion = $tplData["isAutofillRegion"];
/** @var bool */
$isAclSupported = $tplData["isAclSupported"];
/** @var string */
$aclDescription = $tplData["aclDescription"];
/** @var array<int,array<string,string>> */
$documentationLinks = $tplData["documentationLinks"];

$tplMng->render('admin_pages/storages/parts/provider_head');
?>
<tr>
    <td colspan="2" style="padding-left:0">
        <i><?php printf(
            esc_html_x(
                'S3 Setup Guide: %1$sStep-by-Step%2$s and %3$sUser Bucket Policy%4$s.',
                '1%$s and %3$s are opening and %2$s and %4$s are closing <a> tags',
                'duplicator-pro'
            ),
            '<a target="_blank" href="' . esc_url(DUPLICATOR_DUPLICATOR_DOCS_URL . 'amazon-s3-step-by-step') . '">',
            '</a>',
            '<a href="' . esc_url(DUPLICATOR_DUPLICATOR_DOCS_URL . 'amazon-s3-policy-setup') . '" target="_blank">',
            '</a>'
        ); ?>
        </i>
        <i>
        <?php
        if (count($documentationLinks) > 0) {
            printf(
                esc_html_x(
                    'Documentation for %s: ',
                    '%s is the provider name',
                    'duplicator-pro'
                ),
                esc_html($storage->getStypeName())
            );

            foreach ($documentationLinks as $link) {
                ?>
                    <a target="_blank" href="<?php echo esc_url($link['url']) ?>"><?php echo esc_html($link['label']) ?></a>&nbsp;
                <?php
            }
        }
        ?>
        </i>
    </td>
</tr>
<tr>
    <th scope="row"><label for=""><?php esc_html_e("Authorization", 'duplicator-pro'); ?></label></th>
    <td class="dup-s3-auth-account">
        <h3>
            <?php
            if ($storage->getId() < 0) {
                echo wp_kses(
                    $storage->getStypeIcon(),
                    [
                        'i'   => [
                            'class' => [],
                        ],
                        'img' => [
                            'src'   => [],
                            'class' => [],
                            'alt'   => [],
                        ],
                    ]
                );
            }
            echo esc_html($storage->getStypeName()) . ' ' . esc_html__('Account', 'duplicator-pro');
            ?>
        </h3>
        <?php if ($storage->getSType() === AmazonS3CompatibleStorage::getSType()) {
            $tplMng->render('amazons3addon/parts/s3_compatible_msg');
        } ?>
        <table class="dup-form-sub-area margin-top-1">
            <tr>
                <th scope="row">
                    <label for="s3_access_key_<?php echo (int) $storage->getSType(); ?>">
                        <?php echo esc_html($storage->getFieldLabel('accessKey')); ?>:
                    </label>
                </th>
                <td>
                    <input
                        id="s3_access_key_<?php echo (int) $storage->getSType(); ?>"
                        name="s3_access_key"
                        class="margin-0"
                        data-parsley-errors-container="#s3_access_key_<?php echo (int) $storage->getSType(); ?>_error_container"
                        type="text"
                        autocomplete="off"
                        value="<?php echo esc_attr($accessKey); ?>"
                        data-parsley-required="true"
                    >
                    <div id="s3_access_key_<?php echo (int) $storage->getSType(); ?>_error_container" class="duplicator-error-container"></div>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="s3_secret_key_<?php echo (int) $storage->getSType(); ?>">
                        <?php echo esc_html($storage->getFieldLabel('secretKey')); ?>:
                    </label>
                </th>

                <td>
                    <input
                        id="s3_secret_key_<?php echo (int) $storage->getSType(); ?>"
                        name="s3_secret_key"
                        class="margin-0"
                        type="password"
                        placeholder="<?php echo esc_attr(str_repeat("*", strlen($secretKey))); ?>"
                        data-parsley-errors-container="#s3_secret_key_<?php echo (int) $storage->getSType(); ?>_error_container"
                        data-parsley-required="true"
                        autocomplete="off"
                        value=""
                    >
                    <div id="s3_secret_key_<?php echo (int) $storage->getSType(); ?>_error_container" class="duplicator-error-container"></div>
                </td>
            </tr>
        </table>
    </td>
</tr>
<tr>
    <th scope="row"></th>
    <td>
        <table class="dup-form-sub-area dup-s3-auth-provider">
            <tr>
                <th>
                    <label for="s3_endpoint_<?php echo (int) $storage->getSType(); ?>">
                        <?php echo esc_html($storage->getFieldLabel('endpoint')); ?>:
                    </label>
                </th>
                <td>
                    <input
                        type="text"
                        id="s3_endpoint_<?php echo (int) $storage->getSType(); ?>"
                        class="margin-0"
                        name="s3_endpoint"
                        value="<?php echo esc_attr($endpoint); ?>"
                        data-parsley-required="true"
                        >
                    <?php if ($isAutofillEndpoint) : ?>
                    <p class="description">
                        <i><?php esc_html_e('The endpoint URL will be autofilled based on the region.', 'duplicator-pro'); ?></i>
                    </p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="s3_region_<?php echo (int) $storage->getSType(); ?>">
                        <?php echo esc_html($storage->getFieldLabel('region')); ?>:
                    </label>
                </th>
                <td>
                    <input
                        type="text"
                        id="s3_region_<?php echo (int) $storage->getSType(); ?>"
                        name="s3_region"
                        class="margin-0"
                        value="<?php echo esc_attr($region); ?>"
                        data-parsley-required="true"
                        data-parsley-pattern="[0-9a-zA-Z-_]+"
                    >
                    <?php if ($isAutofillRegion) : ?>
                    <p class="description">
                        <i><?php esc_html_e('The region will be autodetected from the endpoint URL.', 'duplicator-pro'); ?></i>
                    </p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr class="invisible_out_of_screen">
                <th>
                    <label for="s3_storage_class_<?php echo (int) $storage->getSType(); ?>">
                        <?php esc_html_e("Storage Class", 'duplicator-pro'); ?>:
                    </label>
                </th>
                <td>
                    <select id="s3_storage_class_<?php echo (int) $storage->getSType(); ?>" name="s3_storage_class">
                        <option <?php selected(true); ?> value="STANDARD"><?php esc_html_e("Standard", 'duplicator-pro'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="s3_bucket_<?php echo (int) $storage->getSType(); ?>">
                        <?php echo esc_html($storage->getFieldLabel('bucket')); ?>
                    </label>
                </th>
                <td>
                    <input
                        id="s3_bucket_<?php echo (int) $storage->getSType(); ?>"
                        name="s3_bucket"
                        class="margin-0"
                        type="text"
                        value="<?php echo esc_attr($bucket); ?>"
                        data-parsley-required="true"
                    >
                    <p class="description">
                        <i><?php esc_html_e("S3 Bucket where you want to save the backups.", 'duplicator-pro'); ?></i>
                    </p>
                </td>
            </tr>
        </table>
    </td>
</tr>
<tr>
    <th><label for="_s3_storage_folder_<?php echo (int) $storage->getSType(); ?>"><?php esc_html_e("Storage Folder", 'duplicator-pro'); ?>:</label></th>
    <td>
        <div class="horizontal-input-row">
            <input
                id="_s3_storage_folder_<?php echo (int) $storage->getSType(); ?>"
                name="_s3_storage_folder"
                type="text"
                value="<?php echo esc_attr($storageFolder); ?>"
            >
        </div>
        <p class="description">
            <i>
                <?php
                esc_html_e(
                    "Folder where backups will be stored. This should be unique for each web-site using Duplicator.",
                    'duplicator-pro'
                );
                ?>
            </i>
        </p>
    </td>
</tr>
<tr>
    <th scope="row"><label for="s3_max_files_<?php echo (int) $storage->getSType(); ?>"><?php esc_html_e("Max Backups", 'duplicator-pro'); ?></label></th>
    <td>
        <div class="horizontal-input-row">
            <input
                id="s3_max_files_<?php echo (int) $storage->getSType(); ?>"
                class="s3_max_files"
                name="s3_max_files"
                type="number"
                value="<?php echo (int) $maxPackages; ?>"
                min="0"
                maxlength="4"
                data-parsley-errors-container="#s3_max_files_<?php echo (int) $storage->getSType(); ?>_error_container"
                data-parsley-required="true"
                data-parsley-type="number"
                data-parsley-min="0"
            >
            <label for="s3_max_files_<?php echo (int) $storage->getSType(); ?>">
                <?php esc_html_e("Number of Backups to keep in folder.", 'duplicator-pro'); ?>
            </label>
        </div>
        <?php $tplMng->render('admin_pages/storages/parts/max_backups_description'); ?>
        <div id="s3_max_files_<?php echo (int) $storage->getSType(); ?>_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<?php if ($isAclSupported) : ?>
    <tr class="s3-acl-row" valign="top">
        <th scope="row"><label><?php echo esc_html($storage->getFieldLabel('aclFullControl')); ?></label></th>
        <td>
            <div class="horizontal-input-row">
                <input
                    type="checkbox"
                    name="s3_ACL_full_control"
                    id="s3_ACL_full_control_<?php echo (int) $storage->getSType(); ?>"
                    value="1"
                    <?php checked($aclFullControl, true); ?>
                >
                <label for="s3_ACL_full_control_<?php echo (int) $storage->getSType(); ?>">
                    <?php esc_html_e("Give bucket owner full control (ACL) to all files uploaded by Duplicator Pro.", 'duplicator-pro'); ?>
                </label>
            </div>
            <p class="description">
                <i>
                    <?php echo esc_html($aclDescription); ?>
                </i>
            </p>
        </td>
    </tr>
<?php endif; ?>
<?php $tplMng->render('admin_pages/storages/parts/provider_foot');
