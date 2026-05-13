<?php

/**
 * Template for Duplicator Cloud Connect Step 1
 *
 * @package   Duplicator\Addons\DupCloudAddon
 * @copyright (c) 2024, Snap Creek LLC
 */

use Duplicator\Addons\DupCloudAddon\Models\DupCloudStorage;
use Duplicator\Core\CapMng;

defined('ABSPATH') || exit;

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 */

$storage      = $tplMng->getDataValueObjRequired('storage', DupCloudStorage::class);
$autoActivate = $tplMng->getDataValueBool('auto_activate_storage', false);

?>
<div class="dup-settings-wrapper" >
    <h3 class="title">
        <?php esc_html_e('Duplicator Cloud', 'duplicator-pro') ?>
    </h3>
    <hr size="1">
    <?php if ($storage->isAuthorized()) { ?>
        <label class="lbl-larger">
            <?php esc_html_e("Cloud Info", 'duplicator-pro'); ?>
        </label> 
    <?php } else { ?>
        <p>
            <?php esc_html_e('Connect to Duplicator Cloud Storage.', 'duplicator-pro'); ?>
        </p>
        <label class="lbl-larger">
            <?php esc_html_e("Authorization", 'duplicator-pro'); ?>
        </label>
    <?php } ?>
    <div class="margin-bottom-1">
        <form 
            id="dup-storage-form" 
            method="post" 
            data-parsley-ui-enabled="true" 
            target="_self"
        >
            <input type="hidden" name="storage_id" id="storage_id" value="<?php echo (int) $storage->getId(); ?>">
            <input type="hidden" id="name" name="name" value="<?php echo esc_attr($storage->getName()); ?>">
            <?php $tplMng->render('dupcloudaddon/connect/storage_auth'); ?>
        </form>
    </div>
</div>
<?php
$tplMng->render('admin_pages/storages/storage_scripts');


if ($autoActivate && CapMng::can(CapMng::CAP_STORAGE, false)) {
    ?>
<script>
    jQuery(document).ready(function($) {
        var $button = $('#dupli-dupcloud-license-connect-btn');
        if ($button.length) {
            DupliJs.Storage.DupCloud.LicenseConnect($button, true);
        }
    });
</script>
    <?php
}