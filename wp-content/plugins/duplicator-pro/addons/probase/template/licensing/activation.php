<?php

/**
 * @package Duplicator
 */

use Duplicator\Addons\ProBase\License\License;
use Duplicator\Models\DynamicGlobalEntity;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

$visibility = DynamicGlobalEntity::getInstance()->getValInt('license_key_visible', License::VISIBILITY_ALL);

?>
<h3 class="title">
    <?php esc_html_e('License', 'duplicator-pro') ?>
</h3>
<hr size="1">
<p>
    <?php esc_html_e('Your license key provides access to updates and addons.', 'duplicator-pro') ?>
</p>

<label class="lbl-larger">
    <?php esc_html_e("License Key", 'duplicator-pro'); ?>
</label>
<div class="margin-bottom-1">
    <?php $tplMng->render('licensing/license_key_actions'); ?>
    <?php if ($visibility !== License::VISIBILITY_NONE) { ?>
    <div id="dup-tr-license-type" >
        <?php $tplMng->render('licensing/license_info'); ?>
        <?php $tplMng->render('licensing/activation_issue_message'); ?>
    </div>
    <?php } ?>
</div>