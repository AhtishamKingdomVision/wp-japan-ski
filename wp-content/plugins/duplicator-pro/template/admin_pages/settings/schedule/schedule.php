<?php

/**
 * @package Duplicator
 */

defined("ABSPATH") or die("");

use Duplicator\Models\GlobalEntity;
use Duplicator\Controllers\SettingsPageController;
use Duplicator\Core\Controllers\ControllersManager;

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 * @var Duplicator\Core\Controllers\PageAction[] $tplData['actions']
 */

$global = GlobalEntity::getInstance();
?>
<form
    id="dup-settings-form"
    action="<?php echo esc_attr(ControllersManager::getCurrentLink()); ?>"
    method="post" data-parsley-validate>
    <?php $tplData['actions'][SettingsPageController::ACTION_SAVE_SCHEDULE]->getActionNonceFileds(); ?>
    <h3 class="title"><?php esc_html_e("Schedule Notifications", 'duplicator-pro'); ?></h3>
    <hr size="1" />
    <div class="dup-settings-wrapper margin-bottom-1">
        <label class="lbl-larger" for="send_email_on_build_mode">
            <?php esc_html_e("Send Build Email", 'duplicator-pro'); ?>
        </label>
        <div class="margin-bottom-1">
            <input
                type="radio"
                name="send_email_on_build_mode"
                id="send_email_on_build_mode_never"
                class="margin-0"
                value="<?php echo (int) GlobalEntity::EMAIL_BUILD_MODE_NEVER; ?>"
                <?php checked($global->send_email_on_build_mode, GlobalEntity::EMAIL_BUILD_MODE_NEVER); ?>>
            <label for="send_email_on_build_mode_never"><?php esc_attr_e("Never", 'duplicator-pro'); ?></label> &nbsp;
            <input
                type="radio"
                name="send_email_on_build_mode"
                id="send_email_on_build_mode_failure"
                class="margin-0"
                value="<?php echo (int) GlobalEntity::EMAIL_BUILD_MODE_FAILURE; ?>"
                <?php checked($global->send_email_on_build_mode, GlobalEntity::EMAIL_BUILD_MODE_FAILURE); ?>>
            <label for="send_email_on_build_mode_failure"><?php esc_attr_e("On Failure", 'duplicator-pro'); ?></label> &nbsp;
            <input
                type="radio"
                name="send_email_on_build_mode"
                id="send_email_on_build_mode_always"
                class="margin-0"
                value="<?php echo (int) GlobalEntity::EMAIL_BUILD_MODE_ALL; ?>"
                <?php checked($global->send_email_on_build_mode, GlobalEntity::EMAIL_BUILD_MODE_ALL); ?>>
            <label for="send_email_on_build_mode_always"><?php esc_attr_e("Always", 'duplicator-pro'); ?></label> &nbsp;
            <p class="description">
                <?php
                esc_html_e("When to send emails after a scheduled build.", 'duplicator-pro');
                ?>
            </p>
        </div>

        <label class="lbl-larger" for="notification_email_address">
            <?php esc_html_e("Email Address", 'duplicator-pro'); ?>
        </label>
        <div class="margin-bottom-1">
            <input
                data-parsley-errors-container="#notification_email_address_error_container"
                data-parsley-type="email"
                type="email"
                name="notification_email_address"
                id="notification_email_address"
                class="width-large margin-bottom-0"
                value="<?php echo esc_attr($global->notification_email_address); ?>">
            <p class="description">
                <?php esc_html_e('Admininstrator default email will be used if empty.', 'duplicator-pro'); ?>
            </p>
            <div id="notification_email_address_error_container" class="duplicator-error-container"></div>
        </div>
    </div>
    <hr>
    <p class="submit dupli-save-submit">
        <input
            type="submit"
            name="submit"
            id="submit"
            class="button primary small"
            value="<?php esc_attr_e('Save Settings', 'duplicator-pro') ?>">
    </p>
</form>