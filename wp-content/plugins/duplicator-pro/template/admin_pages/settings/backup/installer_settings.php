<?php

/**
 * @package Duplicator
 */

use Duplicator\Models\GlobalEntity;
use Duplicator\Package\Create\PackInstaller;

defined("ABSPATH") or die("");


/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

$global            = GlobalEntity::getInstance();
$installerNameMode = $global->installer_name_mode;
?>

<h3 class="title">
    <?php esc_html_e("Installer Settings", 'duplicator-pro'); ?>
</h3>
<hr size="1" />

<label class="lbl-larger">
    <?php esc_html_e("Name", 'duplicator-pro'); ?>
</label>
<div class="margin-bottom-1">
    <b><?php esc_html_e("Default 'Save as' name:", 'duplicator-pro'); ?></b> <br />
    <label>
        <i class='fas fa-lock lock-info fa-fw'></i>&nbsp;
        <input
            type="radio"
            name="installer_name_mode"
            class="margin-0"
            value="<?php echo esc_attr(GlobalEntity::INSTALLER_NAME_MODE_WITH_HASH); ?>"
            <?php checked($installerNameMode === GlobalEntity::INSTALLER_NAME_MODE_WITH_HASH); ?>>&nbsp;
        [name]_[hash]_[date]_installer.php <i>(<?php esc_html_e("recommended", 'duplicator-pro'); ?>)</i>
    </label><br>
    <label>
        <i class='fas fa-lock-open lock-info fa-fw'></i>&nbsp;
        <input
            type="radio"
            name="installer_name_mode"
            class="margin-0"
            value="<?php echo esc_attr(GlobalEntity::INSTALLER_NAME_MODE_SIMPLE); ?>"
            <?php checked($installerNameMode === GlobalEntity::INSTALLER_NAME_MODE_SIMPLE); ?>>&nbsp;
        <?php echo esc_html(PackInstaller::DEFAULT_INSTALLER_FILE_NAME_WITHOUT_HASH); ?>
    </label>
    <p class="description">
        <?php
        printf(
            esc_html_x(
                'To understand the importance and usage of the installer name, please %1$sread this section%2$s.',
                '1 and 2 are opening and closing anchor or link tags',
                'duplicator-pro'
            ),
            '<a href="javascript:void(0)" onclick="jQuery(\'#dupli-inst-mode-details\').toggle()">',
            '</a>'
        );
        ?>
    </p>
    <div id="dupli-inst-mode-details">
        <p>
            <i>
                <?php esc_html_e(
                    'Using the full hashed format provides a higher level of security by helping to prevent the discovery of the installer file.',
                    'duplicator-pro'
                ); ?>
            </i> <br />
            <b><?php esc_html_e('Hashed example', 'duplicator-pro'); ?>:</b> my-name_64fc6df76c17f2023225_19990101010101_installer.php
        </p>
        <p>
            <?php
            esc_html_e(
                'The Installer \'Name\' setting specifies the name of the installer used at download-time.
                It\'s recommended you choose the hashed name to better protect the installer file.
                Independent of the value of this setting, you can always change the name in the \'Save as\' file dialog at download-time. 
                If you choose to use a custom name, use a filename that is known only to you. Installer filenames	must end in \'.php\'.',
                'duplicator-pro'
            );
            ?>
        </p>
        <p>
            <?php
            esc_html_e(
                'It\'s important not to leave the installer files on the destination server longer than necessary. 
                After installing the migrated or restored site, just logon as a WordPress administrator and 
                follow the prompts to have the plugin remove the files. 
                Alternatively, you can remove them manually.',
                'duplicator-pro'
            );
            ?>
        </p>
        <p>
            <i class="fas fa-info-circle"></i>
            <?php
            esc_html_e(
                'Tip: Each row on the Backups screen includes a copy button that copies the installer name to the clipboard. 
                After clicking this button, paste the installer name into the URL you\'re using to install the destination site. 
                This feature is handy when using the hashed installer name.',
                'duplicator-pro'
            );
            ?>
        </p>
    </div>
</div>

<h3 class="title">
    <?php esc_html_e("Installer Cleanup", 'duplicator-pro') ?>
</h3>
<hr size="1" />

<label class="lbl-larger">
    <?php esc_html_e("Mode", 'duplicator-pro'); ?>
</label>
<div class="margin-bottom-1">
    <input
        type="radio"
        name="cleanup_mode"
        id="cleanup_mode_Cleanup_Off"
        class="margin-0"
        value="<?php echo (int) GlobalEntity::CLEANUP_MODE_OFF; ?>"
        <?php checked($global->cleanup_mode, GlobalEntity::CLEANUP_MODE_OFF); ?>>
    <label for="cleanup_mode_Cleanup_Off"><?php esc_html_e("Off", 'duplicator-pro'); ?></label> &nbsp;
    <input
        type="radio"
        name="cleanup_mode"
        id="cleanup_mode_Email_Notice"
        class="margin-0"
        value="<?php echo (int) GlobalEntity::CLEANUP_MODE_MAIL; ?>"
        <?php checked($global->cleanup_mode, GlobalEntity::CLEANUP_MODE_MAIL); ?>>
    <label for="cleanup_mode_Email_Notice"><?php esc_html_e("Email Notice", 'duplicator-pro'); ?></label> &nbsp;
    <input
        type="radio"
        name="cleanup_mode"
        id="cleanup_mode_Auto_Cleanup"
        class="margin-0"
        value="<?php echo (int) GlobalEntity::CLEANUP_MODE_AUTO; ?>"
        <?php checked($global->cleanup_mode, GlobalEntity::CLEANUP_MODE_AUTO); ?>>
    <label for="cleanup_mode_Auto_Cleanup"><?php esc_html_e("Auto Cleanup", 'duplicator-pro'); ?></label> &nbsp;
    <p class="description">
        <?php esc_html_e("Email Notice: An email will be sent daily until the installer files are removed.", 'duplicator-pro'); ?>
    </p>
    <p class="description">
        <?php esc_html_e("Auto Cleanup: Installer files will be cleaned up automatically based on setting below.", 'duplicator-pro'); ?>
    </p>
</div>

<label class="lbl-larger">
    <?php esc_html_e("Auto Cleanup", 'duplicator-pro'); ?>
</label>
<div class="margin-bottom-1">
    <input
        data-parsley-required
        data-parsley-errors-container="#auto_cleanup_hours_error_container"
        data-parsley-min="1"
        data-parsley-type="number"
        class="inline-display width-small margin-0"
        type="text"
        name="auto_cleanup_hours" id="auto_cleanup_hours"
        value="<?php echo (int) $global->auto_cleanup_hours; ?>"
        size="7" />
    <?php esc_html_e('Hours', 'duplicator-pro'); ?>
    <div id="auto_cleanup_hours_error_container" class="duplicator-error-container"></div>
    <p class="description"> <?php esc_html_e('Auto cleanup will run every N hours based on value above.', 'duplicator-pro'); ?> </p>
</div>