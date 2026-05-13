<?php

/**
 * @package Duplicator
 */

use Duplicator\Addons\ProBase\License\License;
use Duplicator\Addons\ProBase\LicensingController;
use Duplicator\Core\Controllers\ControllersManager;
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
?>
<div class="dup-settings-wrapper" >
    <div class="dup-accordion-wrapper display-separators close">
        <div class="accordion-header">
            <h3 class="title">
                <?php esc_html_e("License Key Visibility", 'duplicator-pro') ?>
            </h3>
        </div>
        <div class="accordion-content">
            <p>
                <?php
                esc_html_e(
                    "This is an optional setting that prevents the 'License Key' from being copied. 
                    Select the desired visibility mode, enter a password and hit the 'Change Visibility' button.",
                    'duplicator-pro'
                );
                ?>
            </p>

            <form
                id="dup-license-visibility-form"
                action="<?php echo esc_url(ControllersManager::getCurrentLink()); ?>"
                method="post"
                data-parsley-validate>
                <?php $tplData['actions'][LicensingController::ACTION_CHANGE_VISIBILITY]->getActionNonceFileds(); ?>

                <label class="lbl-larger">
                    <?php esc_html_e("Visibility", 'duplicator-pro'); ?>
                </label>
                <div class="margin-bottom-1">
                    <label class="inline-display margin-right-1">
                        <input
                            type="radio"
                            name="license_key_visible"
                            value="<?php echo (int) License::VISIBILITY_ALL; ?>"
                            class="margin-0"
                            onclick="DupliJs.Licensing.VisibilityTemporary(<?php echo (int) License::VISIBILITY_ALL; ?>);"
                            <?php checked($licenseVisibility, License::VISIBILITY_ALL); ?>>
                        <?php esc_html_e("License Visible", 'duplicator-pro'); ?>
                    </label>
                    <label class="inline-display margin-right-1">
                        <input
                            type="radio"
                            name="license_key_visible"
                            value="<?php echo (int) License::VISIBILITY_INFO; ?>"
                            class="margin-0"
                            onclick="DupliJs.Licensing.VisibilityTemporary(<?php echo (int) License::VISIBILITY_INFO; ?>);"
                            <?php checked($licenseVisibility, License::VISIBILITY_INFO); ?>>
                        <?php esc_html_e("Info Only", 'duplicator-pro'); ?>
                    </label>
                    <label class="inline-display">
                        <input
                            type="radio"
                            name="license_key_visible"
                            value="<?php echo (int) License::VISIBILITY_NONE; ?>"
                            class="margin-0"
                            onclick="DupliJs.Licensing.VisibilityTemporary(<?php echo (int) License::VISIBILITY_NONE; ?>);"
                            <?php checked($licenseVisibility, License::VISIBILITY_NONE); ?>>
                        <?php esc_html_e("License Invisible", 'duplicator-pro'); ?>
                    </label>
                </div>

                <label class="lbl-larger">
                    <?php esc_html_e("Password", 'duplicator-pro'); ?>
                </label>
                <div class="margin-bottom-1">
                    <input
                        type="password"
                        class="dup-wide-input"
                        name="_key_password"
                        id="_key_password" size="50">
                </div>
                <?php if ($licenseVisibility == License::VISIBILITY_ALL) { ?>
                    <label class="lbl-larger">
                        <?php esc_html_e("Retype Password", 'duplicator-pro'); ?>
                    </label>
                    <div class="margin-bottom-1">
                        <input
                            type="password"
                            class="dup-wide-input"
                            name="_key_password_confirmation"
                            id="_key_password_confirmation"
                            data-parsley-equalto="#_key_password"
                            size="50">
                    </div>
                <?php } ?>

                <label class="lbl-larger">
                    &nbsp;
                </label>
                <div class="margin-bottom-1">
                    <button
                        class="button secondary hollow small margin-0"
                        id="show_hide"
                        onclick="DupliJs.Licensing.ChangeKeyVisibility(); return false;">
                        <?php esc_html_e('Change Visibility', 'duplicator-pro'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>