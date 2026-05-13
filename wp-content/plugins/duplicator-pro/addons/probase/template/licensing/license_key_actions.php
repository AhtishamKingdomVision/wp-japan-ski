<?php

/**
 * @package Duplicator
 */

use Duplicator\Addons\ProBase\License\License;
use Duplicator\Addons\ProBase\Models\LicenseData;
use Duplicator\Models\DynamicGlobalEntity;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

$dGlobal           = DynamicGlobalEntity::getInstance();
$licenseVisibility = $dGlobal->getValInt('license_key_visible', License::VISIBILITY_ALL);

if ($licenseVisibility === License::VISIBILITY_ALL) {
    ?>
    <div id="dup-tr-license-key-and-description" class="inline-display">
        <input
            type="text"
            class="dup-license-key-input width-large inline-display margin-0"
            name="_license_key"
            id="_license_key"
            value="<?php echo esc_attr(License::getLicenseKey()); ?>">
        <span class="dup-license-check-icon">
            <?php if (LicenseData::getInstance()->getStatus() === LicenseData::STATUS_VALID) { ?>
                <i class="fa-solid fa-circle-check fa-lg success-color"></i>
            <?php } else { ?>
                <i class="fa-solid fa-circle-xmark fa-lg alert-color"></i>
            <?php } ?>
        </span>
        &nbsp;
    </div>
<?php } ?>
<div class="dup-license-key-btns inline-display">
    <?php
    if (LicenseData::getInstance()->getStatus() === LicenseData::STATUS_VALID) {
        $echostring = 'false';
        $buttonText = __('Deactivate', 'duplicator-pro');
    } else {
        $echostring = 'true';
        $buttonText = __('Activate', 'duplicator-pro');
    }
    ?>
    <button
        id="dup-license-activation-btn"
        class="button secondary hollow small margin-0"
        onclick="DupliJs.Licensing.ChangeActivationStatus(<?php echo esc_js($echostring); ?>);return false;">
        <?php echo esc_html($buttonText); ?>
    </button>
    &nbsp;
    <button
        id="dup-license-clear-btn"
        class="button secondary hollow small margin-0"
        onclick="DupliJs.Licensing.ClearActivationStatus();return false;">
        <?php esc_html_e('Clear Key', 'duplicator-pro') ?>
    </button>
</div>