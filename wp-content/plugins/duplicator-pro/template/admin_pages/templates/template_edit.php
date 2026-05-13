<?php

/**
 * @package Duplicator
 */

defined("ABSPATH") or die("");

use Duplicator\Models\GlobalEntity;
use Duplicator\Controllers\ToolsPageController;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Addons\ProBase\License\License;
use Duplicator\Controllers\SettingsPageController;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\WpUtils\WpArchiveUtils;
use Duplicator\Models\TemplateEntity;
use Duplicator\Package\Archive\PackageArchive;
use Duplicator\Views\UI\UiDialog;
use Duplicator\Views\UI\UiViewState;

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 */
$blur     = $tplMng->getDataValueBool('blur');
$template = $tplMng->getDataValueObj('template', TemplateEntity::class);

$templates_tab_url = ControllersManager::getMenuLink(
    ControllersManager::TOOLS_SUBMENU_SLUG,
    ToolsPageController::L2_SLUG_TEMPLATE
);
$edit_template_url =  ControllersManager::getMenuLink(
    ControllersManager::TOOLS_SUBMENU_SLUG,
    ToolsPageController::L2_SLUG_TEMPLATE,
    null,
    ['inner_page' => 'edit']
);

$bandListUrl = ControllersManager::getMenuLink(
    ControllersManager::SETTINGS_SUBMENU_SLUG,
    SettingsPageController::L2_SLUG_PACKAGE_BRAND
);

$brandDefaultEditUrl = ControllersManager::getMenuLink(
    ControllersManager::SETTINGS_SUBMENU_SLUG,
    SettingsPageController::L2_SLUG_PACKAGE_BRAND,
    null,
    [
        ControllersManager::QUERY_STRING_INNER_PAGE => SettingsPageController::BRAND_INNER_PAGE_EDIT,
        'action'                                    => 'default',
    ]
);

$brandBaseEditUrl = ControllersManager::getMenuLink(
    ControllersManager::SETTINGS_SUBMENU_SLUG,
    SettingsPageController::L2_SLUG_PACKAGE_BRAND,
    null,
    [
        ControllersManager::QUERY_STRING_INNER_PAGE => SettingsPageController::BRAND_INNER_PAGE_EDIT,
        'action'                                    => 'edit',
    ]
);

$global = GlobalEntity::getInstance();

if (($package_templates = TemplateEntity::getAll()) === false) {
    $package_templates = [];
}
$package_template_count = count($package_templates);

// For now not including in filters since don't want to encourage use
// with schedules since filtering creates incomplete multisite
$displayMultisiteTab = (is_multisite() && License::can(License::CAPABILITY_MULTISITE_PLUS));

$view_state     = UiViewState::getArray();
$ui_css_archive = (UiViewState::getValue('dup-template-archive-panel') ? 'display:block' : 'display:none');
$ui_css_install = (UiViewState::getValue('dup-template-install-panel') ? 'display:block' : 'display:none');

$installer_cpnldbaction = $template->installer_opts_cpnl_db_action;
$upload_dir             = WpArchiveUtils::getArchiveListPaths('uploads');
$content_path           = WpArchiveUtils::getArchiveListPaths('wpcontent');
$archive_format         = ($global->getBuildMode() == PackageArchive::BUILD_MODE_DUP_ARCHIVE ? 'daf' : 'zip');
?>

<?php $tplMng->render('admin_pages/templates/parts/template_edit_toolbar'); ?>
<form
    id="dupli-template-form"
    class="dup-monitored-form <?php echo ($blur ? 'dup-mock-blur' : ''); ?>"
    data-parsley-validate data-parsley-ui-enabled="true"
    action="<?php echo esc_url($edit_template_url); ?>"
    method="post">
    <?php $tplMng->getAction(ToolsPageController::ACTION_SAVE_TEMPLATE)->getActionNonceFileds(); ?>
    <input type="hidden" name="package_template_id" value="<?php echo intval($template->getId()); ?>">

    <!-- ====================
    SUB-TABS -->
    <div class="dupli-template-general">

        <div>
            <label class="inline-display">
                <?php esc_html_e("Recovery Status", 'duplicator-pro'); ?>:
            </label>&nbsp;
            <?php $template->recoveableHtmlInfo(); ?> <br /><br />
        </div>

        <label class="lbl-larger" for="template-name">
            <?php esc_html_e("Template Name", 'duplicator-pro'); ?>:
        </label>
        <input type="text" id="template-name" name="name" data-parsley-errors-container="#template_name_error_container"
            data-parsley-required="true" value="<?php echo esc_attr($template->name); ?>" autocomplete="off" maxlength="125">
        <div id="template_name_error_container" class="duplicator-error-container"></div>

        <?php
        TplMng::getInstance()->render(
            'admin_pages/packages/setup/name-format-controls',
            [
                'nameFormat' => $template->package_name_format,
                'notes'      => $template->notes,
            ]
        );
        ?>
    </div>

    <?php
    $tplMng->render(
        'parts/packages/filters/section_filters',
        [
            'isTemplateEdit' => true,
            'template'       => $template,
        ]
    );

    $tplMng->render(
        'parts/packages/filters/section_installer',
        [
            'activeBrandId' => $template->installer_opts_brand,
            'dbHost'        => $template->installer_opts_db_host,
            'dbName'        => $template->installer_opts_db_name,
            'dbUser'        => $template->installer_opts_db_user,
            'cpnlEnable'    => $template->installer_opts_cpnl_enable,
            'cpnlHost'      => $template->installer_opts_cpnl_host,
            'cpnlUser'      => $template->installer_opts_cpnl_user,
            'cpnlDbAction'  => $template->installer_opts_cpnl_db_action,
            'cpnlDbHost'    => $template->installer_opts_cpnl_db_host,
            'cpnlDbName'    => $template->installer_opts_cpnl_db_name,
            'cpnlDbUser'    => $template->installer_opts_cpnl_db_user,
        ]
    );

    ?>



    <button
        class="button primary small dup-save-template-btn"
        type="submit">
        <?php esc_html_e('Save Template', 'duplicator-pro'); ?>
    </button>
</form>




<?php
$alert1          = new UiDialog();
$alert1->title   = __('Transfer Error', 'duplicator-pro');
$alert1->message = __('You can\'t exclude all sites!', 'duplicator-pro');
$alert1->initAlert();
?>

<script>
    jQuery(document).ready(function($) {

        var usedPackageFormats = {};

        /* When installer brand changes preview button is updated */
        DupliJs.Template.BrandChange = function() {
            var $brand = $("#installer_opts_brand");
            var $id = $brand.val();
            var $url = new Array();

            $url = [
                <?php echo wp_json_encode($brandDefaultEditUrl); ?>,
                <?php echo wp_json_encode($brandBaseEditUrl); ?> + '&id=' + $id
            ];

            $("#brand-preview").attr('href', $url[$id > 0 ? 1 : 0]);
        };

        /* Enables strike through on excluded DB table */
        DupliJs.Template.ExcludeTable = function(check) {
            var $cb = $(check);
            if ($cb.is(":checked")) {
                $cb.closest("label").css('textDecoration', 'line-through');
            } else {
                $cb.closest("label").css('textDecoration', 'none');
            }
        }

        //INIT
        $('#template-name').focus().select();
        // $('#_archive_filter_files').val($('#_archive_filter_files').val().trim());
        //Default to cPanel tab if used
        $('#cpnl-enable').is(":checked") ? $('#dupli-cpnl-tab-lbl').trigger("click") : null;
        DupliJs.EnableInstallerPassword();
        DupliJs.Template.BrandChange();

        //MU-Transfer buttons
        $('#mu-include-btn').click(function() {
            return !$('#mu-exclude option:selected').remove().appendTo('#mu-include');
        });

        $('#mu-exclude-btn').click(function() {
            var include_all_count = $('#mu-include option').length;
            var include_selected_count = $('#mu-include option:selected').length;

            if (include_all_count > include_selected_count) {
                return !$('#mu-include option:selected').remove().appendTo('#mu-exclude');
            } else {
                <?php $alert1->showAlert(); ?>
            }
        });

        $('#dupli-template-form').submit(function() {
            DupliJs.Pack.FillExcludeTablesList();
        });

        //Defaults to Installer cPanel tab if 'Auto Select cPanel' is checked
        $('#installer_opts_cpnl_enable').is(":checked") ? $('#dupli-cpnl-tab-lbl').trigger("click") : null;
    });
</script>