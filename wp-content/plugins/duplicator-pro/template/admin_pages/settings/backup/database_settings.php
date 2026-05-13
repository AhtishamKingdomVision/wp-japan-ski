<?php

/**
 * @package Duplicator
 */

use Duplicator\Models\GlobalEntity;
use Duplicator\Controllers\SettingsPageController;
use Duplicator\Core\Constants;
use Duplicator\Libs\Shell\Shell;
use Duplicator\Libs\WpUtils\WpDbUtils;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

$global          = GlobalEntity::getInstance();
$is_shellexec_on = Shell::test();
$mysqlDumpPath   = WpDbUtils::getMySqlDumpPath();
$mysqlDumpFound  = (bool) $mysqlDumpPath;
?>

<h3 class="title">
    <?php esc_html_e("Database", 'duplicator-pro') ?>
</h3>
<hr size="1" />

<label class="lbl-larger">
    <?php esc_html_e("SQL Mode", 'duplicator-pro'); ?>
</label>
<div class="margin-bottom-1">
    <div class="margin-bottom-1">
        <div class="engine-radio <?php echo ($is_shellexec_on) ? '' : 'engine-radio-disabled'; ?> inline-display">
            <input
                type="radio"
                name="_package_dbmode"
                value="mysql"
                id="package_mysqldump"
                class="margin-0"
                <?php checked($global->package_mysqldump); ?> onclick="DupliJs.UI.SetDBEngineMode();">
            <label for="package_mysqldump"><?php esc_html_e("Mysqldump", 'duplicator-pro'); ?> </label> &nbsp; &nbsp; &nbsp;
        </div>

        <div class="engine-radio inline-display">
            <input
                type="radio"
                name="_package_dbmode"
                id="package_phpdump"
                value="php"
                class="margin-0"
                <?php checked(!$global->package_mysqldump); ?> onclick="DupliJs.UI.SetDBEngineMode();">
            <label for="package_phpdump"><?php esc_html_e("PHP Code", 'duplicator-pro'); ?></label>
        </div>
    </div>

    <!-- SHELL EXEC  -->
    <div class="engine-sub-opts" id="dbengine-details-1" style="display:none">
        <!-- MYSQLDUMP IN-ACTIVE -->
        <?php if (!$is_shellexec_on) :
            ?>
            <div class="dup-feature-notfound">
                <?php
                esc_html_e(
                    'In order to use Mysqldump, the PHP function popen/pclose must be enabled.',
                    'duplicator-pro'
                );
                echo ' ';
                esc_html_e('Please contact your host or server admin to enable this function.', 'duplicator-pro');
                echo ' ';
                printf(
                    esc_html_x(
                        'For a list of approved providers that support this function, %1$sclick here%2$s.',
                        '%1$s and %2$s are the opening and closing tags of a link.',
                        'duplicator-pro'
                    ),
                    '<a href="' . esc_url(DUPLICATOR_DUPLICATOR_DOCS_URL . 'what-host-providers-are-recommended-for-duplicator/')
                        . '" target="_blank">',
                    '</a>'
                );
                echo ' ';
                esc_html_e('The "PHP Code" setting will be used until this issue is resolved by your hosting provider.', 'duplicator-pro');
                ?>
                <p>
                    <?php
                    esc_html_e('Below is a list of possible functions to activate to solve the problem.', 'duplicator-pro');
                    echo ' ';
                    esc_html_e('If the problem persists, look at the log for a more thorough analysis.', 'duplicator-pro');
                    ?>
                </p>
                <br />
                <b><?php esc_html_e('Disabled Functions:', 'duplicator-pro'); ?></b>
                <code class="display-block margin-bottom-1">
                    <?php
                    foreach (['escapeshellarg', 'escapeshellcmd', 'extension_loaded', 'popen', 'pclose'] as $func) {
                        if (Shell::hasDisabledFunctions($func)) {
                            echo esc_html($func);
                            echo '<br>';
                        }
                    }
                    ?>
                </code>
                <?php
                printf(
                    esc_html_x(
                        'FAQ: %1$sHow to enable disabled PHP functions.%2$s',
                        '%1$s and %2$s are the opening and closing tags of a link.',
                        'duplicator-pro'
                    ),
                    '<a href="' . esc_url(DUPLICATOR_DUPLICATOR_DOCS_URL . 'how-to-resolve-dependency-checks') . '" target="_blank">',
                    '</a>'
                );
                ?>
            </div>
            <!-- MYSQLDUMP ACTIVE -->
            <?php
        else :
            $tipContent =  esc_attr__(
                'Add a custom path if the path to mysqldump is not properly detected.   
                For all paths use a forward slash as the path seperator.  
                On Linux systems use mysqldump for Windows systems use mysqldump.exe.
                If the path tried does not work please contact your hosting provider for details on the correct path.',
                'duplicator-pro'
            );
            ?>
            <span><?php esc_html_e("Current Path:", 'duplicator-pro'); ?></span>&nbsp;
            <?php
            SettingsPageController::getMySQLDumpMessage(
                $mysqlDumpFound,
                (!empty($mysqlDumpPath) ? $mysqlDumpPath : $global->package_mysqldump_path)
            ); ?><br><br>
            <span><?php esc_html_e("Custom Path:", 'duplicator-pro'); ?></span>&nbsp;
            <input
                class="width-large inline-display"
                type="text"
                name="_package_mysqldump_path"
                id="_package_mysqldump_path"
                value="<?php echo esc_attr($global->package_mysqldump_path); ?>"
                placeholder="<?php esc_attr_e("/usr/bin/mypath/mysqldump", 'duplicator-pro'); ?>">&nbsp;
            <i class="fa-solid fa-question-circle fa-sm dark-gray-color"
                data-tooltip-title="<?php esc_attr_e("mysqldump", 'duplicator-pro'); ?>"
                data-tooltip="<?php echo esc_attr($tipContent); ?>">
            </i><br>

            <label><?php esc_html_e("Switch Options:", 'duplicator-pro'); ?></label>
            <div class="dup-group-option-wrapper">
                <?php
                $mysqldumpOptions = $global->getMysqldumpOptions();
                foreach ($mysqldumpOptions as $key => $option) {
                    ?>
                    <div class="dup-group-option-item">
                        <input
                            type="checkbox"
                            name="<?php echo esc_attr($option->getInputName()); ?>"
                            id="<?php echo esc_attr($option->getInputName()); ?>"
                            class="margin-0"
                            <?php checked($option->getEnabled()); ?>>
                        --<?php echo esc_html($option->getOptionName()); ?>
                    </div>
                <?php } ?>
            </div>
            <?php
        endif; ?>
    </div>

    <!-- PHP OPTION -->
    <div class="engine-sub-opts" id="dbengine-details-2" style="display:none; line-height: 35px; margin-top:-5px">
        <span><?php esc_html_e("Process Mode", 'duplicator-pro'); ?></span>&nbsp;
        <select name="_phpdump_mode" class="width-medium inline-display margin-0">
            <option
                <?php selected($global->package_phpdump_mode, WpDbUtils::PHPDUMP_MODE_MULTI); ?>
                value="<?php echo (int) WpDbUtils::PHPDUMP_MODE_MULTI; ?>">
                <?php esc_html_e("Multi-Threaded", 'duplicator-pro'); ?>
            </option>
            <option
                <?php selected($global->package_phpdump_mode, WpDbUtils::PHPDUMP_MODE_SINGLE); ?>
                value="<?php echo (int) WpDbUtils::PHPDUMP_MODE_SINGLE; ?>">
                <?php esc_html_e("Single-Threaded", 'duplicator-pro'); ?>
            </option>
        </select>&nbsp;
        <i style="margin-right:7px;" class="fa-solid fa-question-circle fa-sm dark-gray-color"
            data-tooltip-title="<?php esc_attr_e("PHP Code Mode", 'duplicator-pro'); ?>"
            data-tooltip="<?php
                            esc_attr_e(
                                'Single-Threaded mode attempts to create the entire database script in one request. 
                Multi-Threaded mode allows the database script to be chunked over multiple requests.
                Multi-Threaded mode is typically slower but much more reliable especially for larger databases.',
                                'duplicator-pro'
                            );
                            ?>"></i>
    </div>
</div>

<label class="lbl-larger" for="_package_mysqldump_qrylimit">
    <?php esc_html_e("Query Size", 'duplicator-pro'); ?>
</label>
<div class="margin-bottom-1">
    <select name="_package_mysqldump_qrylimit" id="_package_mysqldump_qrylimit" class="width-small inline-display margin-0">
        <?php
        foreach (Constants::MYSQL_DUMP_CHUNK_SIZES as $value => $label) {
            echo '<option ' . selected($global->package_mysqldump_qrylimit, $value, false) . ' value="' . (int) $value . '">'
                . esc_html($label) . '</option>';
        }
        ?>
    </select>&nbsp;
    <?php $tipContent = __(
        'A higher limit size will speed up the database build time, however it will use more memory.
        If your host has memory caps start off low.',
        'duplicator-pro'
    ); ?>
    <i style="margin-right:7px" class="fa-solid fa-question-circle fa-sm dark-gray-color"
        data-tooltip-title="<?php esc_attr_e("MYSQL Query Limit Size", 'duplicator-pro'); ?>"
        data-tooltip="<?php echo esc_attr($tipContent); ?>">
    </i>
</div>