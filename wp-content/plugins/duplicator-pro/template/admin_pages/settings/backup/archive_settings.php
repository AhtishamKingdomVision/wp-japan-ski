<?php

/**
 * @package Duplicator
 */

use Duplicator\Libs\Shell\ShellZipUtils;
use Duplicator\Models\GlobalEntity;
use Duplicator\Package\Archive\PackageArchive;
use Duplicator\Utils\ZipArchiveExtended;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

$global                = GlobalEntity::getInstance();
$isZipArchiveAvailable = ZipArchiveExtended::isPhpZipAvailable();
$isShellZipAvailable   = (ShellZipUtils::getShellExecZipPath() != null);
?>

<h3 class="title">
    <?php esc_html_e("Archive", 'duplicator-pro') ?>
</h3>
<hr size="1" />

<label class="lbl-larger">
    <?php esc_html_e("Compression", 'duplicator-pro'); ?>
</label>
<div class="margin-bottom-1">
    <input
        type="radio"
        name="archive_compression"
        id="archive_compression_off"
        value="0"
        class="margin-0"
        <?php checked($global->archive_compression, false); ?>>
    <label for="archive_compression_off">
        <?php esc_html_e("Off", 'duplicator-pro'); ?>
    </label> &nbsp;
    <input
        type="radio"
        name="archive_compression"
        id="archive_compression_on"
        value="1"
        class="margin-0"
        <?php checked($global->archive_compression); ?>>
    <label for="archive_compression_on">
        <?php esc_html_e("On", 'duplicator-pro'); ?>
    </label>
    <?php $tipContent = __(
        'This setting controls archive compression. The setting apply to all Archive Engine formats.
        For ZipArchive this setting only works on PHP 7.0 or higher.',
        'duplicator-pro'
    ); ?>&nbsp;
    <i style="margin-right:7px;" class="fa-solid fa-question-circle fa-sm dark-gray-color"
        data-tooltip-title="<?php esc_attr_e("Archive Compression", 'duplicator-pro'); ?>"
        data-tooltip="<?php echo esc_attr($tipContent); ?>">
    </i>
</div>

<label class="lbl-larger">
    <?php esc_html_e("Archive Engine", 'duplicator-pro'); ?>
</label>
<div class="margin-bottom-1">
    <div class="engine-radio">
        <input
            onclick="DupliJs.UI.SetArchiveOptionStates();"
            type="radio"
            name="archive_build_mode" id="archive_build_mode3"
            class="margin-0"
            value="<?php echo (int) PackageArchive::BUILD_MODE_DUP_ARCHIVE; ?>"
            <?php checked($global->getBuildMode() == PackageArchive::BUILD_MODE_DUP_ARCHIVE); ?>
            <?php disabled(!$global->isBuildModeAvailable(PackageArchive::BUILD_MODE_DUP_ARCHIVE)) ?>>
        <label for="archive_build_mode3"><?php esc_html_e("DupArchive", 'duplicator-pro'); ?></label> &nbsp; &nbsp;
    </div>
    <div class="engine-radio <?php echo ($isShellZipAvailable) ? '' : 'engine-radio-disabled'; ?>">
        <input
            onclick="DupliJs.UI.SetArchiveOptionStates();"
            type="radio"
            name="archive_build_mode"
            id="archive_build_mode1"
            class="margin-0"
            value="<?php echo (int) PackageArchive::BUILD_MODE_SHELL_EXEC; ?>"
            <?php checked($global->getBuildMode() == PackageArchive::BUILD_MODE_SHELL_EXEC); ?>
            <?php disabled(!$global->isBuildModeAvailable(PackageArchive::BUILD_MODE_SHELL_EXEC)) ?>>
        <label for="archive_build_mode1"><?php esc_html_e("Shell Zip", 'duplicator-pro'); ?></label>
    </div>
    <div class="engine-radio">
        <input
            onclick="DupliJs.UI.SetArchiveOptionStates();"
            type="radio"
            name="archive_build_mode"
            id="archive_build_mode2"
            class="margin-0"
            value="<?php echo (int) PackageArchive::BUILD_MODE_ZIP_ARCHIVE; ?>"
            <?php checked($global->getBuildMode() == PackageArchive::BUILD_MODE_ZIP_ARCHIVE); ?>
            <?php disabled(!$global->isBuildModeAvailable(PackageArchive::BUILD_MODE_ZIP_ARCHIVE)) ?>>
        <label for="archive_build_mode2"><?php esc_html_e("ZipArchive", 'duplicator-pro'); ?></label>
    </div>

    <br style="clear:both" />

    <!-- DUPARCHIVE -->
    <div class="engine-sub-opts" id="engine-details-3" style="display:none">
        <?php
        esc_html_e('This option creates a custom Duplicator Archive Format (.daf) archive file.', 'duplicator-pro');
        echo '<br/>  ';
        esc_html_e('This option is fully multi-threaded and recommended for large sites or throttled servers.', 'duplicator-pro');
        echo '<br/>  ';
        printf(
            '%s <a href="' . esc_url(DUPLICATOR_DUPLICATOR_DOCS_URL . 'how-to-work-with-daf-files-and-the-duparchive-extraction-tool')
                . '" target="_blank">%s</a> ',
            esc_html__('For details on how to use and manually extract the DAF format please see the ', 'duplicator-pro'),
            esc_html__('online documentation.', 'duplicator-pro')
        );
        ?>
    </div>

    <!-- SHELL EXEC  -->
    <div class="engine-sub-opts" id="engine-details-1" style="display:none">
        <?php
        $tplMng->render(
            'parts/settings/shellZipMessage',
            ['hasShellZip' => $isShellZipAvailable]
        );
        ?>
    </div>

    <!-- ZIP ARCHIVE -->
    <div class="engine-sub-opts" id="engine-details-2" style="display:none;">
        <div class="margin-bottom-1">
            <span><?php esc_html_e("Process Mode", 'duplicator-pro'); ?></span>&nbsp;
            <select name="ziparchive_mode" id="ziparchive_mode" onchange="DupliJs.UI.setZipArchiveMode();" class="inline-display width-medium margin-0">
                <option <?php selected($global->ziparchive_mode, PackageArchive::ZIP_MODE_MULTI_THREAD); ?>
                    value="<?php echo (int) PackageArchive::ZIP_MODE_MULTI_THREAD ?>">
                    <?php esc_html_e("Multi-Threaded", 'duplicator-pro'); ?>
                </option>
                <option <?php selected($global->ziparchive_mode == PackageArchive::ZIP_MODE_SINGLE_THREAD); ?>
                    value="<?php echo (int) PackageArchive::ZIP_MODE_SINGLE_THREAD ?>">
                    <?php esc_html_e("Single-Threaded", 'duplicator-pro'); ?>
                </option>
            </select>&nbsp;
            <i style="margin-right:7px;" class="fa-solid fa-question-circle fa-sm dark-gray-color"
                data-tooltip-title="<?php esc_attr_e("PHP ZipArchive Mode", 'duplicator-pro'); ?>"
                data-tooltip="<?php
                                esc_attr_e(
                                    'Single-Threaded mode attempts to create the entire archive in one request.  
                Multi-Threaded mode allows the archive to be chunked over multiple requests. 
                Multi-Threaded mode is typically slower but much more reliable especially for larger sites.',
                                    'duplicator-pro'
                                );
                                ?>"></i>
        </div>

        <div id="dupli-ziparchive-mode-st">
            <input type="checkbox" id="ziparchive_validation" name="ziparchive_validation" class="margin-0"
                <?php checked($global->ziparchive_validation); ?>>
            <label for="ziparchive_validation">Enable file validation</label>
        </div>

        <div id="dupli-ziparchive-mode-mt">
            <span><?php esc_html_e("Buffer Size", 'duplicator-pro'); ?></span>&nbsp;
            <input
                maxlength="4"
                class="inline-display width-small margin-0"
                data-parsley-required data-parsley-errors-container="#ziparchive_chunk_size_error_container"
                data-parsley-min="5" data-parsley-type="number"
                type="text" name="ziparchive_chunk_size_in_mb" id="ziparchive_chunk_size_in_mb"
                value="<?php echo (int) $global->ziparchive_chunk_size_in_mb; ?>">
            <?php esc_html_e('MB', 'duplicator-pro'); ?>
            <?php
            $toolTipContent = __(
                'Buffer size only applies to multi-threaded requests and indicates how large an archive will get before a close is registered. 
            Higher values are faster but can be more unstable based on the hosts max_execution_time.',
                'duplicator-pro'
            );
            ?>
            <i style="margin-right:7px" class="fa-solid fa-question-circle fa-sm dark-gray-color"
                data-tooltip-title="<?php esc_attr_e("PHP ZipArchive Buffer", 'duplicator-pro'); ?>"
                data-tooltip="<?php echo esc_attr($toolTipContent); ?>">
            </i>
            <div id="ziparchive_chunk_size_error_container" class="duplicator-error-container"></div>
        </div>
    </div>
</div>