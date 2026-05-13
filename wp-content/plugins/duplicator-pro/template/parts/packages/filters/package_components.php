<?php

/**
 * Duplicator Backup row in table Backups list
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Addons\ProBase\License\License;
use Duplicator\Libs\WpUtils\WpArchiveUtils;
use Duplicator\Package\Create\BuildComponents;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 */

$activeComponents        = $tplData['components'] ?? [];
$archiveFilterOn         = $tplData['archiveFilterOn'] ?? false;
$archiveFilterDirs       = $tplData['archiveFilterDirs'] ?? '';
$archiveFilterFiles      = $tplData['archiveFilterFiles'] ?? '';
$archiveFilterPaths      = strlen($archiveFilterDirs) > 0 && strlen($archiveFilterFiles) > 0
    ? $archiveFilterDirs  . ';' . $archiveFilterFiles : $archiveFilterDirs . $archiveFilterFiles;
$archiveFilterPaths      = str_replace(';', ";\n", $archiveFilterPaths);
$archiveFilterExtensions = $tplData['archiveFilterExtensions'] ?? '';
$licenseDisabledClass    = License::can(License::CAPABILITY_PACKAGE_COMPONENTS_PLUS) ? '' : 'disabled';

$pathFiltersTooltip     = __('File filters allow you to exclude files and folders from the Backup.', 'duplicator-pro') . ' ' .
    __('To enable path and extension filters check the checkbox.', 'duplicator-pro') . ' ' .
    __('Enter the full path of the files and folders you want to exclude from the backup as a semicolon (;) seperated list.', 'duplicator-pro');
$extensionFilterTooltip = __(
    "File extension filters allow you to exclude files with certain file extensions from the backup e.g. zip;rar;pdf etc.",
    'duplicator-pro'
) . ' ' . __("Enter the file extensions you want to exclude from the backup as a semicolon (;) seperated list.", 'duplicator-pro');

$presetsTooltip    = __(
    'Presets allow you to quickly include/exclude differents part of your WordPress installation in the backup.',
    'duplicator-pro'
);
$componentsTooltip = $tplMng->render('parts/packages/filters/package_components_tootip', [], false);
?>

<div class="dup-package-components">
    <div class="component-section">
        <label class="lbl-larger">
            <?php esc_html_e('Presets:', 'duplicator-pro'); ?>
            <i class="fa-solid fa-question-circle fa-sm dark-gray-color"
                title="<?php echo esc_attr($presetsTooltip); ?>"
                aria-expanded="false"></i>
        </label>
        <div class="components-shortcut-select">
            <div class="dup-radio-button-group-wrapper">
                <?php foreach (BuildComponents::COMPONENTS_ACTIONS as $action) {
                    $inputId = 'dup-component-shortcut-action-' . $action;
                    if (
                        in_array($action, BuildComponents::COMPONENTS_PLUS_ACTIONS) &&
                        !License::can(License::CAPABILITY_PACKAGE_COMPONENTS_PLUS)
                    ) {
                        $disabled = 'disabled';
                    } else {
                        $disabled = '';
                    }
                    ?>
                    <input
                        type="radio"
                        id="<?php echo esc_attr($inputId); ?>"
                        name="auto-select-components"
                        class="dup-components-shortcut-radio"
                        value="<?php echo esc_html($action); ?>"
                        <?php checked($action === BuildComponents::getActionFromComponents($activeComponents));  ?>>
                    <label
                        for="<?php echo esc_attr($inputId); ?>"
                        class="button hollow secondary small <?php echo esc_attr($disabled); ?>">
                        <?php
                        echo wp_kses(
                            BuildComponents::getActionLabel($action, true),
                            [
                                'i' => [
                                    'class' => [],
                                ],
                            ]
                        );
                        ?>
                    </label>
                <?php } ?>
            </div>
            <div class="dup-tabs-opts-help">
                <?php
                if (!License::can(License::CAPABILITY_PACKAGE_COMPONENTS_PLUS)) { ?>
                    <div id="dup-upgrade-license-info" class="margin-top-1">
                        <?php
                        printf(
                            esc_html_x(
                                'The %1$s and %2$s options are not included in your license plan.',
                                '%1$s and %2$s are the options not included in the license.',
                                'duplicator-pro'
                            ),
                            '<b>' . esc_html(BuildComponents::getActionLabel(BuildComponents::COMP_ACTION_MEDIA)) . '</b>',
                            '<b>' . esc_html(BuildComponents::getActionLabel(BuildComponents::COMP_ACTION_CUSTOM)) . '</b>'
                        );
                        ?>
                        <br>
                        <?php
                        printf(
                            esc_html_x(
                                'To enable advanced options please %1$supgrade your license%2$s.',
                                '%1$s and %2$s represents the opening and closing HTML tags for an anchor or link.',
                                'duplicator-pro'
                            ),
                            '<a href="' . esc_url(License::getUpsellURL()) . '" target="_blank">',
                            '</a>'
                        );
                        ?>
                    </div>
                <?php } ?>
            </div>
        </div>
        <hr>
        <label class="lbl-larger">
            <?php esc_html_e('Components:', 'duplicator-pro'); ?>&nbsp;
            <i class="fa-solid fa-question-circle fa-sm dark-gray-color"
                data-tooltip-title="<?php esc_attr_e('Backup Components', 'duplicator-pro'); ?>"
                data-tooltip="<?php echo esc_attr($componentsTooltip); ?>"
                aria-expanded="false"></i>
        </label>
        <div>
            <ul class="custom-components-select no-bullet">
                <?php foreach (BuildComponents::COMPONENTS as $component) { ?>
                    <li>
                        <label class="<?php echo in_array($component, BuildComponents::SUB_OPTIONS) ? 'secondary license-disabled' : 'license-disabled' ?>">
                            <input
                                type="checkbox"
                                <?php echo !in_array($component, BuildComponents::SUB_OPTIONS) ? 'data-parsley-multiple="package_components"' : '' ?>
                                name="<?php echo esc_attr($component) ?>"
                                id="<?php echo esc_attr($component) ?>"
                                class="dup-components-checkbox"
                                <?php checked(in_array($component, $activeComponents));  ?>>
                            <?php echo esc_html(BuildComponents::getLabel($component)); ?>
                        </label>
                    </li>
                <?php } ?>
            </ul>
            <span id="components_error_container" class="duplicator-error-container"></span>
        </div>
    </div>
    <div class="files-filter-section">
        <hr>
        <label class="lbl-larger">
            <?php esc_html_e('File Filters:', 'duplicator-pro'); ?>
            <i class="fa-solid fa-question-circle fa-sm dark-gray-color"
                title="<?php esc_attr_e('File filters allow you to exclude files and folders from the backup.', 'duplicator-pro'); ?>"
                aria-expanded="false"></i>
        </label>
        <div class="margin-bottom-1">
            <label>
                <input
                    id="files-filter-on"
                    name="filter-on"
                    type="checkbox" <?php checked($archiveFilterOn); ?>
                    class="margin-0">&nbsp;<?php esc_html_e('Enable', 'duplicator-pro'); ?>
            </label>
        </div>
        <div class="filters <?php echo ($archiveFilterOn ? 'no-display' : ''); ?>">
            <label class="lbl-larger">
                <?php
                esc_html_e('Filters:', 'duplicator-pro');
                $tooltip = __(
                    '- Use full path for directories or specific files.<br> 
                    - Use filenames without paths to filter same-named files across multiple directories.<br>
                    - Use semicolons to separate all items.<br>
                    - Use # to comment a line.',
                    'duplicator-pro'
                );
                ?>
                <i class="fa-solid fa-question-circle fa-sm dark-gray-color"
                    title="<?php echo esc_attr($tooltip); ?>"
                    aria-expanded="false"></i>
            </label>
            <div>
                <textarea
                    id="filter-paths"
                    name="filter-paths"
                    class="margin-0"
                    placeholder="/full_path/dir/;&#10;/full_path/file;"
                    readonly><?php echo esc_html($archiveFilterPaths); ?></textarea>
                <small class="filter-links margin-bottom-1">
                    <a href="#" data-filter-path="<?php echo esc_attr(SnapIO::trailingslashit(WpArchiveUtils::getOriginalPaths('home'))); ?>">
                        [<?php esc_html_e('root path', 'duplicator-pro') ?>]
                    </a>
                    <a href="#" data-filter-path="<?php echo esc_attr(SnapIO::trailingslashit(WpArchiveUtils::getOriginalPaths('wpcontent'))); ?>">
                        [wp-content]
                    </a>
                    <a href="#" data-filter-path="<?php echo esc_attr(SnapIO::trailingslashit(WpArchiveUtils::getOriginalPaths('uploads'))); ?>">
                        [wp-uploads]
                    </a>
                    <a href="#" data-filter-path="<?php echo esc_attr(SnapIO::trailingslashit(WpArchiveUtils::getOriginalPaths('wpcontent')) . 'cache/'); ?>">
                        [<?php esc_html_e('cache', 'duplicator-pro') ?>]
                    </a>
                    <a href="#" id="clear-path-filters">(<?php esc_html_e('clear', 'duplicator-pro') ?>)</a>
                </small>
            </div>

            <label class="lbl-larger">
                <?php esc_html_e('File Extensions', 'duplicator-pro'); ?>
                <i class="fa-solid fa-question-circle fa-sm dark-gray-color"
                    data-tooltip-title="<?php esc_attr_e('File Extensions', 'duplicator-pro'); ?>"
                    data-tooltip="<?php echo esc_attr($extensionFilterTooltip); ?>"
                    aria-expanded="false"></i>
            </label>
            <div>
                <textarea
                    id="filter-exts"
                    name="filter-exts"
                    class="margin-bottom-0"
                    placeholder="ext1;ext2;ext3;"
                    readonly><?php echo esc_html($archiveFilterExtensions); ?></textarea>
                <small class="filter-links">
                    <a href="#" data-filter-exts="avi;mov;mp4;mpeg;mpg;swf;wmv;aac;m3u;mp3;mpa;wav;wma">[media]</a>
                    <a href="#" data-filter-exts="zip;rar;tar;gz;bz2;7z">[archive]</a>
                    <a href="#" id="clear-extension-filters">(<?php esc_html_e('clear', 'duplicator-pro'); ?>)</a>
                </small>
            </div>
        </div>
    </div>
</div>
<script>
    jQuery(document).ready(function($) {
        $('#package_component_db').attr('data-parsley-required', 'true');
        $('#package_component_db').attr('data-parsley-mincheck', '1');
        $('#package_component_db').attr('data-parsley-errors-container', '#components_error_container');
        $('#package_component_db')
            .attr('data-parsley-required-message', '<?php echo esc_js(__("Please select at least one component.", "duplicator-pro")); ?>');

        $('.dup-package-components .component-section input[type=checkbox]').on('change', function() {
            $('#package_component_db').parsley().validate();
        });

        DupliJs.Pack.ToggleFileFilters = function() {
            if ($("#files-filter-on").is(':checked')) {
                $('#dup-archive-filter-file-icon').show();
                $('.files-filter-section .filters').removeClass('no-display');
                $('#filter-exts, #filter-paths').prop('readonly', false);
            } else {
                $('#dup-archive-filter-file-icon').hide();
                $('.files-filter-section .filters').addClass('no-display');
                $('#filter-exts, #filter-paths').prop('readonly', true);
            }
        };

        DupliJs.Pack.ToggleDBExcluded = function() {
            if (!$('#package_component_db').is(":checked")) {
                $('.filter-db-tab-content').hide();
            } else {
                $('.filter-db-tab-content').show();
            }
        }

        DupliJs.Pack.ToggleDBOnly = function() {
            let allComponentCheckboxes = $('.dup-package-components .component-section input[type=checkbox]');
            if (allComponentCheckboxes.filter(':checked').length === 1 && $('#package_component_db').is(":checked")) {
                $('#dup-archive-filter-file-icon').hide();
                $("#dup-archive-db-only-icon").show();
                $('.files-filter-section').hide();
                $('.db-only-message').show();
            } else {
                if ($('#files-filter-on').is(':checked')) {
                    $('#dup-archive-filter-file-icon').show();
                }
                $("#dup-archive-db-only-icon").hide();
                $('.files-filter-section').show();
                $('.db-only-message').hide();
            }
        }

        DupliJs.Pack.ToggleMediaOnly = function() {
            let allComponentCheckboxes = $('.dup-package-components .component-section input[type=checkbox]');
            if (allComponentCheckboxes.filter(':checked').length === 1 && $('#package_component_uploads').is(":checked")) {
                $("#dup-archive-media-only-icon").show();
            } else {
                $("#dup-archive-media-only-icon").hide();
            }
        }

        DupliJs.Pack.SetDBOnly = function() {
            $('.dup-components-checkbox').prop('checked', false);
            $('.custom-components-select label').addClass('disabled');
            $('#package_component_db').prop('checked', true);
        }

        DupliJs.Pack.SetMediaOnly = function() {
            $('.dup-components-checkbox').prop('checked', false);
            $('.custom-components-select label').addClass('disabled');
            $('#package_component_uploads').prop('checked', true);
        }

        DupliJs.Pack.SetDefault = function() {
            $('.dup-components-checkbox').prop('checked', false);
            $('label:not(.secondary) .dup-components-checkbox').prop('checked', true);
            $('.custom-components-select label').addClass('disabled');
        }

        DupliJs.Pack.SetCustom = function() {
            $('.custom-components-select label').removeClass('disabled');
        }

        DupliJs.Pack.ToggleComponentsSelect = function() {
            let checkedSelect = $('.dup-components-shortcut-radio:checked').val();
            console.log(checkedSelect);
            switch (checkedSelect) {
                case 'all':
                    DupliJs.Pack.SetDefault();
                    break;
                case 'database':
                    DupliJs.Pack.SetDBOnly();
                    break;
                case 'media':
                    DupliJs.Pack.SetMediaOnly();
                    break;
                case 'custom':
                    DupliJs.Pack.SetCustom();
                    break;
            }

            $('.dup-components-checkbox').trigger('change');
        }


        DupliJs.Pack.SetComponentsSelect = function() {
            let allComponentCheckboxes = $('.dup-components-checkbox');
            let checkedComponentsCount = allComponentCheckboxes.filter(':checked').length;
            let activeOnlyDisabled = (!$('#package_component_plugins_active').is(":checked") && !$('#package_component_themes_active').is(":checked"));

            if (checkedComponentsCount === (allComponentCheckboxes.length - 2) && activeOnlyDisabled) {
                $('#dup-component-shortcut-action-all').prop('checked', true);
            } else if (checkedComponentsCount === 1 && $('#package_component_db').is(":checked")) {
                $('#dup-component-shortcut-action-database').prop('checked', true);
            } else if (checkedComponentsCount === 1 && $('#package_component_uploads').is(":checked")) {
                $('#dup-component-shortcut-action-media').prop('checked', true);
            } else {
                $('#dup-component-shortcut-action-custom').prop('checked', true);
            }

            DupliJs.Pack.ToggleComponentsSelect();
        }

        DupliJs.Pack.ToggleActivePlugins = function() {
            let activePluginsInput = $('#package_component_plugins_active');

            if (activePluginsInput.parent().hasClass('disabled')) {
                activePluginsInput.prop('disabled', true);
                return;
            }

            if (!$('#package_component_plugins').is(':checked')) {
                activePluginsInput.prop('disabled', true);
                activePluginsInput.prop('checked', false);
            } else {
                activePluginsInput.prop('disabled', false);
            }
        }

        DupliJs.Pack.ToggleActiveThemes = function() {
            let activeThemesInput = $('#package_component_themes_active');

            if (activeThemesInput.parent().hasClass('disabled')) {
                activeThemesInput.prop('disabled', true);
                return;
            }

            if (!$('#package_component_themes').is(':checked')) {
                activeThemesInput.prop('disabled', true);
                activeThemesInput.prop('checked', false);
            } else {
                activeThemesInput.prop('disabled', false);
            }
        }

        DupliJs.Pack.ToggleFileFilters();
        DupliJs.Pack.ToggleActiveThemes();
        DupliJs.Pack.ToggleActivePlugins();
        DupliJs.Pack.ToggleDBExcluded();
        DupliJs.Pack.ToggleDBOnly();
        DupliJs.Pack.ToggleMediaOnly();
        DupliJs.Pack.SetComponentsSelect();

        $('a[data-filter-path]').click(function(e) {
            e.preventDefault()

            if ($('#filter-paths').is("[readonly]")) {
                return;
            }

            let currentVal = $('#filter-paths').val()
            let newVal = currentVal.length > 0 ? currentVal + ";\n" + $(this).data('filter-path') : $(this).data('filter-path')

            $('#filter-paths').val(newVal)
        })

        $('#clear-path-filters').click(function(e) {
            e.preventDefault()

            if ($('#filter-paths').is("[readonly]")) {
                return;
            }

            $('#filter-paths').val('')
        })

        $('a[data-filter-exts]').click(function(e) {
            e.preventDefault()

            if ($('#filter-exts').is("[readonly]")) {
                return;
            }

            let currentVal = $('#filter-exts').val()
            let newVal = currentVal.length > 0 ? currentVal + ";" + $(this).data('filter-exts') : $(this).data('filter-exts')

            $('#filter-exts').val(newVal)
        })

        $('#clear-extension-filters').click(function(e) {
            e.preventDefault()

            if ($('#filter-exts').is("[readonly]")) {
                return;
            }

            $('#filter-exts').val('')
        })

        $('#files-filter-on').change(DupliJs.Pack.ToggleFileFilters)

        $('#package_component_db').change(function() {
            DupliJs.Pack.ToggleDBExcluded();
        })

        $('.dup-components-shortcut-radio').change(function() {
            DupliJs.Pack.ToggleComponentsSelect();
        })

        $('.dup-components-checkbox').change(function() {
            DupliJs.Pack.ToggleDBOnly()
            DupliJs.Pack.ToggleMediaOnly()
        })

        $('#package_component_plugins').change(function() {
            DupliJs.Pack.ToggleActivePlugins();
        });

        $('#package_component_themes').change(function() {
            DupliJs.Pack.ToggleActiveThemes();
        });

    });
</script>