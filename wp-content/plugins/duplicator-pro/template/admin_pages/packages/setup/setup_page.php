<?php

/**
 * @package Duplicator
 */

defined("ABSPATH") or die("");

use Duplicator\Controllers\PackagesPageController;
use Duplicator\Controllers\ToolsPageController;
use Duplicator\Libs\WpUtils\WpDbUtils;
use Duplicator\Package\PackageUtils;
use Duplicator\Models\TemplateEntity;
use Duplicator\Views\UI\UiDialog;

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string,mixed> $tplData
 * @var array<string,mixed> $requirements
 */
$requirements = $tplData['requirements'];
/** @var bool */
$blur = $tplData['blur'];

$manual_template = TemplateEntity::getManualTemplate();
$templates       = TemplateEntity::getAll();
$default_name1   = PackageUtils::getDefaultPackageName();
$default_name2   = PackageUtils::getDefaultPackageName(false);
$default_notes   = $manual_template->notes;
$dbbuild_mode    = WpDbUtils::getBuildMode();

$templatesUrl        = ToolsPageController::getInstance()->getMenuLink(ToolsPageController::L2_SLUG_TEMPLATE);
$templateEditBaseUrl = ToolsPageController::getTemplateEditURL();


$tplMng->render('admin_pages/packages/setup/section-requirements');
$form_action_url = PackagesPageController::getInstance()->getPackageBuildS2Url();
?>
<form
    id="dup-form-opts"
    class="<?php echo ($blur ? 'dup-mock-blur' : ''); ?>"
    method="post"
    action="<?php echo esc_attr($form_action_url); ?>"
    data-parsley-validate data-parsley-ui-enabled="true">
    <?php $tplMng->getAction(PackagesPageController::ACTION_UPDATE_TEMPLATE)->getActionNonceFileds(); ?>
    <div class="dupli-general-area">
        <?php
        $tplMng->render(
            'admin_pages/packages/setup/name-format-controls',
            [
                'nameFormat' => '',
                'notes'      => '',
            ]
        );
        ?>
        <div>
            <label for="template_id" class="lbl-larger large">
                <?php esc_html_e('Template', 'duplicator-pro') ?>:
            </label>&nbsp;
            <i class="fa-solid fa-question-circle fa-sm dark-gray-color margin-bottom-0"
                data-tooltip-title="<?php esc_attr_e("Apply Template", 'duplicator-pro'); ?>"
                data-tooltip="<?php
                                esc_attr_e(
                                    'An optional template configuration that can be applied to this backup setup. 
                        An [Unassigned] template will retain the settings from the last scan/build.',
                                    'duplicator-pro'
                                ); ?>">
            </i>

            <div class="float-right">
                <a
                    href="<?php echo esc_url($templatesUrl); ?>"
                    class="clear button gray xtiny margin-bottom-0"
                    title="<?php esc_attr_e("List All Templates", 'duplicator-pro') ?>">
                    <i class="fa-regular fa-clone"></i>
                </a>
                <button
                    type="button"
                    onclick="DupliJs.Pack.EditTemplate()"
                    class="clear button gray xtiny margin-bottom-0"
                    title="<?php esc_attr_e("Edit Selected Template", 'duplicator-pro') ?>">
                    <i class="fa-solid fa-pen-to-square"></i>
                </button>
            </div>
        </div>

        <div>
            <select
                data-parsley-ui-enabled="false"
                onChange="DupliJs.Pack.EnableTemplate();"
                name="template_id" id="template_id"
                aria-label="Prefill option with selected template">
                <option value="<?php echo intval($manual_template->getId()); ?>"><?php echo '[' . esc_html__('Unassigned', 'duplicator-pro') . ']' ?></option>
                <?php
                if (count($templates) == 0) {
                    ?>
                    <option value="-1">
                        <?php echo esc_html_e('No Templates', 'duplicator-pro'); ?>
                    </option>
                    <?php
                } else {
                    foreach ($templates as $template) {
                        if ($template->is_manual) {
                            continue;
                        }
                        ?>
                        <option value="<?php echo (int) $template->getId(); ?>">
                            <?php echo esc_html($template->name); ?>
                        </option>
                        <?php
                    }
                }
                ?>
            </select>
        </div>
    </div>

    <?php
    $tplMng->render('admin_pages/packages/setup/section_storages');
    $tplMng->render(
        'parts/packages/filters/section_filters',
        [
            'isTemplateEdit' => false,
            'template'       => null,
        ]
    );
    $tplMng->render('parts/packages/filters/section_installer');
    ?>

    <div class="dup-button-footer">
        <input
            type="button"
            value="<?php esc_attr_e("Reset", 'duplicator-pro') ?>"
            class="button hollow secondary small" <?php echo ($requirements['Success']) ? '' : 'disabled="disabled"'; ?>
            onClick="DupliJs.Pack.ResetSettings()">&nbsp;
        <input
            id="button-next"
            type="submit"
            value="<?php esc_attr_e("Next", 'duplicator-pro') ?> &#9654;"
            class="button primary small" <?php echo ($requirements['Success']) ? '' : 'disabled="disabled"'; ?>>
    </div>
</form>

<!-- CACHE PROTECTION: If the back-button is used from the scanner page then we need to
refresh page in-case any filters where set while on the scanner page -->
<form id="cache_detection">
    <input type="hidden" id="cache_state" name="cache_state" value="" />
</form>
<?php
$confirm1               = new UiDialog();
$confirm1->title        = __('Would you like to continue', 'duplicator-pro');
$confirm1->message      = __('This will clear all of the current backup settings.', 'duplicator-pro');
$confirm1->progressText = __('Please Wait...', 'duplicator-pro');
$confirm1->jsCallback   = 'DupliJs.Pack.ResetSettingsRun()';
$confirm1->initConfirm();
?>
<script>
    jQuery(function($) {
        var packageTemplates = <?php TemplateEntity::getTemplatesFrontendListData(); ?>

        DupliJs.Pack.BeforeSubmit = function(e) {
            $('#mu-exclude option').each(function() {
                $(this).prop('selected', true);
            });

            DupliJs.Pack.FillExcludeTablesList();

            return true;
        };

        $('#dup-form-opts').submit(function() {
            return DupliJs.Pack.BeforeSubmit();
        })

        // Template-specific Functions
        DupliJs.Pack.GetTemplateById = function(templateId) {
            for (var i = 0; i < packageTemplates.length; i++) {
                var currentTemplate = packageTemplates[i];
                if (currentTemplate.id == templateId) {
                    return currentTemplate;
                }
            }
            return null;
        };

        $.fn.selectOption = function(val) {
            this.val(val)
                .find('option')
                .prop('selected', false)
                .parent()
                .find('option[value="' + val + '"]')
                .prop('selected', true)
                .parent()
                .trigger('change');
            return this;
        };

        DupliJs.Pack.PopulateCurrentTemplate = function() {
            var selectedId = $('#template_id').val();
            var selectedTemplate = DupliJs.Pack.GetTemplateById(selectedId);
            if (selectedTemplate != null) {
                let formatInput = $('#package-name-format');
                formatInput.val(selectedTemplate.package_name_format)
                if (selectedTemplate.is_manual) {
                    name = '<?php echo esc_js(PackageUtils::getDefaultPackageName()); ?>';
                }

                $("#package-notes").val(selectedTemplate.notes);

                $("#files-filter-on").prop("checked", selectedTemplate.archive_filter_on);
                $("#filter-names").prop("checked", selectedTemplate.archive_filter_names);

                //Add trailing slash to directories to differentiate between files and directories
                let filterDirs = selectedTemplate.archive_filter_dirs.split(';').join(";\n");
                let filterFiles = selectedTemplate.archive_filter_files.split(';').join(";\n")
                let separator = filterDirs.length > 0 && filterFiles.length > 0 ? ";\n" : '';

                $("#filter-paths").val(filterDirs + separator + filterFiles);
                $("#filter-exts").val(selectedTemplate.archive_filter_exts);
                $("#dbfilter-on").prop("checked", selectedTemplate.database_filter_on);
                $("#db-prefix-filter").prop("checked", selectedTemplate.databasePrefixFilter);
                $("#db-prefix-sub-filter").prop("checked", selectedTemplate.databasePrefixSubFilter);

                $(".dup-components-checkbox").each(function(i, component) {
                    $(component).prop("checked", false);
                });

                selectedTemplate.components.forEach(function(component) {
                    $("#" + component).prop("checked", true);
                });

                DupliJs.Pack.ToggleDBOnly()
                DupliJs.Pack.SetComponentsSelect();

                if (typeof selectedTemplate.filter_sites != 'undefined' && selectedTemplate.filter_sites.length > 0) {
                    for (var i = 0; i < selectedTemplate.filter_sites.length; i++) {
                        var site_id = selectedTemplate.filter_sites[i];
                        var exclude_option = $('#mu-include').find("option[value=" + site_id + "]").first();
                        console.log(exclude_option.html());
                        $("#mu-exclude").append(exclude_option.clone());
                        exclude_option.remove();
                    }
                }

                //-- cPanel
                $("#cpnl-enable").prop("checked", selectedTemplate.installer_opts_cpnl_enable);
                $("#cpnl-host").val(selectedTemplate.installer_opts_cpnl_host);
                $("#cpnl-user").val(selectedTemplate.installer_opts_cpnl_user);

                $('.secure-on-input-wrapper input').prop("checked", false);
                $('.secure-on-input-wrapper input[value=' + selectedTemplate.installer_opts_secure_on + ']:enabled').prop("checked", true);
                $("#skipscan").prop("checked", selectedTemplate.installer_opts_skip_scan);
                $("#secure-pass").val(selectedTemplate.installerPassowrd);
                $("#cpnl-dbaction").selectOption(selectedTemplate.installer_opts_cpnl_db_action);
                $("#cpnl-dbhost").val(selectedTemplate.installer_opts_cpnl_db_host);
                $("#cpnl-dbname").val(selectedTemplate.installer_opts_cpnl_db_name);
                $("#cpnl-dbuser").val(selectedTemplate.installer_opts_cpnl_db_user);

                //-- Brand
                let installer_opts_brand = selectedTemplate.installer_opts_brand;
                installer_opts_brand_id = [];
                x = 0;

                // most tricky thing - setup proper brand on emplate change
                if (typeof selectedTemplate.installer_opts_brand != 'undefined') {
                    if (selectedTemplate.installer_opts_brand <= 0) {
                        installer_opts_brand = -1;
                    } else if (selectedTemplate.installer_opts_brand > 0) {
                        installer_opts_brand = Number(selectedTemplate.installer_opts_brand);
                    }
                }

                // find, fix deleted brands and setup default
                for (var i = 0; i < packageTemplates.length; i++) {
                    if (
                        typeof packageTemplates[i].installer_opts_brand != 'undefined' &&
                        null != packageTemplates[i].installer_opts_brand &&
                        packageTemplates[i].installer_opts_brand != 0
                    ) {
                        installer_opts_brand_id[x] = Number(packageTemplates[i].installer_opts_brand);
                        x++;
                    }
                }

                $("#brand").selectOption(installer_opts_brand);

                //-- Database
                if (selectedTemplate.database_filter_tables.length) {
                    let databaseFilterTables = selectedTemplate.database_filter_tables.split(",");
                    let tablesToExclude = $("#dup-db-tables-exclude");

                    tablesToExclude.find(".dup-pseudo-checkbox").each(function() {
                        let node = $(this);
                        if (databaseFilterTables.includes(node.data('value'))) {
                            node.addClass('checked');
                        } else {
                            node.removeClass('checked');
                        }
                    });
                }

                $("#dbhost").val(selectedTemplate.installer_opts_db_host);
                $("#dbname").val(selectedTemplate.installer_opts_db_name);
                $("#dbuser").val(selectedTemplate.installer_opts_db_user);

            } else {
                console.log("Template ID doesn't exist?? " + selectedId);
            }

            //Default to Installer cPanel tab if used
            $('#cpnl-enable').is(":checked") ? $('#dupli-cpnl-tab-lbl').trigger("click") : $('#dupli-bsc-tab-lbl').trigger("click");
        };


        DupliJs.Pack.ResetSettings = function() {
            <?php $confirm1->showConfirm(); ?>
        };

        DupliJs.Pack.ResetSettingsRun = function() {
            $('#dup-form-opts')[0].reset();
            setTimeout(function() {
                tb_remove();
            }, 800);
        }

        DupliJs.Pack.EditTemplate = function() {
            var manualTemplateID = <?php echo (int) $manual_template->getId(); ?>;
            var templateID = $('#template_id').val();
            var url;

            if (templateID <= 0 || templateID == manualTemplateID) {
                url = <?php echo json_encode($templatesUrl); ?>;
            } else {
                url = <?php echo json_encode($templateEditBaseUrl); ?> + '&package_template_id=' + templateID;
            }
            window.open(url, 'edit-template');
        };

    });

    //INIT
    jQuery(document).ready(function($) {
        DupliJs.Pack.checkPageCache = function() {
            var $state = $('#cache_state');
            if ($state.val() == "") {
                $state.val("fresh-load");
            } else {
                $state.val("cached");
                <?php $redirect = PackagesPageController::getInstance()->getPackageBuildS1Url(); ?>
                window.location.href = '<?php echo esc_js($redirect); ?>';
            }
        }

        DupliJs.Pack.EnableTemplate = function() {
            $('#dupli-template-specific-area').show(0);
            DupliJs.Pack.PopulateCurrentTemplate();
            //DupliJs.Pack.ToggleInstallerPassword();
            DupliJs.EnableInstallerPassword();
            DupliJs.Pack.ToggleFileFilters();
            DupliJs.Pack.ToggleDBFilters();
            DupliJs.Pack.ToggleActiveThemes();
            DupliJs.Pack.ToggleActivePlugins();
            DupliJs.Pack.ToggleDBExcluded();
            DupliJs.Pack.ToggleNoPrefixTables(false);
            DupliJs.Pack.ToggleNoSubsiteExistsTables(false);
        }

        DupliJs.Pack.checkPageCache();
        DupliJs.Pack.EnableTemplate();
    });
</script>