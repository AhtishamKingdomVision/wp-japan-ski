<?php

/**
 * @package Duplicator
 */

use Duplicator\Models\GlobalEntity;
use Duplicator\Models\StaticGlobal;
use Duplicator\Utils\Crypt\CryptBlowfish;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

$global = GlobalEntity::getInstance();

?>

<h3 class="title">
    <?php esc_html_e("Plugin", 'duplicator-pro') ?>
</h3>
<hr size="1" />

<label class="lbl-larger">
    <?php esc_html_e("Version", 'duplicator-pro'); ?>
</label>
<div class="margin-bottom-1">
    <?php echo esc_html(DUPLICATOR_VERSION); ?>
</div>

<label class="lbl-larger">
    <?php esc_html_e("Uninstall", 'duplicator-pro'); ?>
</label>
<div class="margin-bottom-1">
    <input
        type="checkbox"
        name="uninstall_settings"
        id="uninstall_settings"
        value="1"
        class="margin-0"
        <?php checked(StaticGlobal::getUninstallSettingsOption()); ?>>
    <label for="uninstall_settings"><?php esc_html_e("Delete plugin settings", 'duplicator-pro'); ?> </label><br />

    <input
        type="checkbox"
        name="uninstall_packages"
        id="uninstall_packages"
        value="1"
        class="margin-0"
        <?php checked(StaticGlobal::getUninstallPackageOption()); ?>>
    <label for="uninstall_packages"><?php esc_html_e("Delete entire storage directory", 'duplicator-pro'); ?></label><br />
</div>

<label class="lbl-larger">
    <?php esc_html_e("Encrypt Settings", 'duplicator-pro'); ?>
</label>
<div class="margin-bottom-1">
    <input
        type="checkbox"
        name="crypt"
        id="crypt"
        value="1"
        class="margin-0"
        <?php checked(StaticGlobal::getCryptOption()); ?>>
    <label for="crypt"><?php esc_html_e("Enable settings encryption", 'duplicator-pro'); ?> </label><br />
    <p class="description">
        <?php if (CryptBlowfish::isEncryptAvailable()) { ?>
            <?php esc_html_e(
                "When this option is enabled, all sensitive data (such as passwords, storage data, and license data) 
                will be saved encrypted in the database. Disable this option only in case of problems in saving data.",
                'duplicator-pro'
            ); ?>
        <?php } else { ?>
            <span class="maroon">
                <?php esc_html_e('Encryption is not available on this server.', 'duplicator-pro'); ?>
            </span>
        <?php } ?>
    </p>
</div>

<label class="lbl-larger">
    <?php esc_html_e("Usage statistics", 'duplicator-pro'); ?>
</label>
<div class="margin-bottom-1">
    <?php if (DUPLICATOR_USTATS_DISALLOW) {  // @phpstan-ignore-line
        ?>
        <span class="maroon">
            <?php esc_html_e('Usage statistics are hardcoded disallowed.', 'duplicator-pro'); ?>
        </span>
    <?php } else { ?>
        <input
            type="checkbox"
            name="usage_tracking"
            id="usage_tracking"
            value="1"
            class="margin-0"
            <?php checked($global->getUsageTracking()); ?>>
        <label for="usage_tracking"><?php esc_html_e("Enable usage tracking", 'duplicator-pro'); ?> </label>
        <i
            class="fa-solid fa-question-circle fa-sm dark-gray-color"
            data-tooltip-title="<?php esc_attr_e("Usage Tracking", 'duplicator-pro'); ?>"
            data-tooltip="<?php echo esc_attr($tplMng->render('admin_pages/settings/general/usage_tracking_tooltip', [], false)); ?>"
            data-tooltip-width="600">
        </i>
    <?php } ?>
</div>

<label class="lbl-larger">
    <?php esc_html_e("Hide Announcements", 'duplicator-pro'); ?>
</label>
<div class="margin-bottom-1">
    <input
        type="checkbox"
        name="dup_am_notices"
        id="dup_am_notices"
        value="1"
        class="margin-0"
        <?php checked(!$global->isAmNoticesEnabled()); ?>>
    <label for="dup_am_notices">
        <?php esc_html_e("Check this option to hide plugin announcements and update details.", 'duplicator-pro'); ?>
    </label>
</div>