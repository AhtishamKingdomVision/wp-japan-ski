<?php

/**
 * @package Duplicator
 */

defined("ABSPATH") or die("");

use Duplicator\Controllers\ToolsPageController;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Models\ScheduleEntity;
use Duplicator\Models\TemplateEntity;
use Duplicator\Views\UI\UiDialog;

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 * @var bool $blur
 */

$blur = $tplData['blur'];

$nonce_action = 'dupli-template-list';
$display_edit = false;

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

if (($package_templates = TemplateEntity::getAllWithoutManualMode()) === false) {
    $package_templates = [];
}
$package_template_count = count($package_templates);
?>
<form
    id="dup-package-form"
    class="<?php echo ($blur ? 'dup-mock-blur' : ''); ?>"
    action="<?php echo esc_url($templates_tab_url); ?>"
    method="post">
    <?php $tplData['actions'][ToolsPageController::ACTION_DELETE_TEMPLATE]->getActionNonceFileds(); ?>

    <h2><?php esc_html_e('Templates', 'duplicator-pro'); ?></h2>
    <p>
        <?php esc_html_e('Create Backup Templates with Preset Configurations.', 'duplicator-pro'); ?>
    </p>
    <hr>

    <div class="dup-toolbar <?php echo ($blur ? 'dup-mock-blur' : ''); ?>">
        <label for="bulk_action" class="screen-reader-text">Select bulk action</label>
        <select id="bulk_action" class="small">
            <option value="-1" selected>
                <?php esc_html_e("Bulk Actions", 'duplicator-pro') ?>
            </option>
            <option value="delete" title="Delete selected Backup(s)">
                <?php esc_html_e("Delete", 'duplicator-pro'); ?>
            </option>
        </select>
        <input
            type="button"
            id="dup-pack-bulk-apply"
            class="button hollow secondary small"
            value="<?php esc_attr_e("Apply", 'duplicator-pro') ?>"
            onclick="DupliJs.Template.BulkAction()">
        <span class="separator"></span>
        <?php $tplMng->render('admin_pages/templates/template_create_button'); ?>
    </div>


    <table class="widefat dupli-template-list-tbl dup-table-list valign-top">
        <thead>
            <tr>
                <th class="col-check"><input type="checkbox" id="dupli-chk-all" title="Select all Templates" onclick="DupliJs.Template.SetDeleteAll(this)"></th>
                <th class="col-name"><?php esc_html_e('Name', 'duplicator-pro'); ?></th>
                <th class="col-recover"><?php esc_html_e('Recovery', 'duplicator-pro'); ?></th>
                <th class="col-empty"></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $i = 0;
            foreach ($package_templates as $package_template) :
                $i++;

                $schedules      = ScheduleEntity::getByTemplateId($package_template->getId());
                $schedule_count = count($schedules);
                ?>
                <tr class="package-row <?php echo ($i % 2) ? 'alternate' : ''; ?>">
                    <td class="col-check">
                        <?php if ($package_template->is_default == false) : ?>
                            <input name="selected_id[]" type="checkbox" value="<?php echo intval($package_template->getId()); ?>" class="item-chk" />
                        <?php else : ?>
                            <input type="checkbox" disabled />
                        <?php endif; ?>
                    </td>
                    <td class="col-name">
                        <a
                            href="javascript:void(0);"
                            onclick="DupliJs.Template.Edit(<?php echo intval($package_template->getId()); ?>);"
                            class="name"
                            data-template-id="<?php echo intval($package_template->getId()); ?>">
                            <?php echo esc_html($package_template->name); ?>
                        </a>
                        <div class="sub-menu">
                            <a
                                class="dup-edit-template-btn"
                                href="javascript:void(0);"
                                onclick="DupliJs.Template.Edit(<?php echo (int) $package_template->getId(); ?>);">
                                <?php esc_html_e('Edit', 'duplicator-pro'); ?>
                            </a> |
                            <?php $actionCopyUrl = $tplData['actions'][ToolsPageController::ACTION_COPY_TEMPLATE]
                                ->getUrl([
                                    'dupli-source-template-id' => $package_template->getId(),
                                    'tab'                      => ToolsPageController::L2_SLUG_TEMPLATE,
                                    'inner_page'               => ToolsPageController::TEMPLATE_INNER_PAGE_EDIT,
                                ]); ?>
                            <a
                                class="dup-copy-template-btn"
                                href="<?php echo esc_url($actionCopyUrl); ?>">
                                <?php esc_html_e('Copy', 'duplicator-pro'); ?>
                            </a>
                            <?php if ($package_template->is_default == false) : ?>
                                | <a
                                    class="dup-delete-template-btn"
                                    href="javascript:void(0);"
                                    onclick="DupliJs.Template.Delete(<?php echo (int) $package_template->getId() ?>, <?php echo (int) $schedule_count; ?>);">
                                    <?php esc_html_e('Delete', 'duplicator-pro'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="col-recover">
                        <?php $package_template->recoveableHtmlInfo(true); ?>
                    </td>
                    <td>&nbsp;</td>
                </tr>

            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="8" style="text-align:right; font-size:12px">
                    <?php echo esc_html__('Total', 'duplicator-pro') . ': ' . (int) $package_template_count; ?>
                </th>
            </tr>
        </tfoot>
    </table>
</form>
<?php
$alert1          = new UiDialog();
$alert1->title   = __('Bulk Action Required', 'duplicator-pro');
$alert1->message = __('Please select an action from the "Bulk Actions" drop down menu!', 'duplicator-pro');
$alert1->initAlert();

$alert2          = new UiDialog();
$alert2->title   = __('Selection Required', 'duplicator-pro');
$alert2->message = __('Please select at least one template to delete!', 'duplicator-pro');
$alert2->initAlert();

$confirm1                      = new UiDialog();
$confirm1->wrapperClassButtons = 'dup-delete-template-dialog-bulk';
$confirm1->title               = __('Delete the selected templates?', 'duplicator-pro');
$confirm1->message             = __('All schedules using this template will be reassigned to the "Default" Template.', 'duplicator-pro');
$confirm1->message            .= '<br/><br/>';
$confirm1->message            .= '<small><i>' . __('Note: This action removes all selected custom templates.', 'duplicator-pro') . '</i></small>';
$confirm1->progressText        = __('Removing Templates, Please Wait...', 'duplicator-pro');
$confirm1->jsCallback          = 'DupliJs.Storage.BulkDelete()';
$confirm1->initConfirm();

$confirm2                      = new UiDialog();
$confirm2->wrapperClassButtons = 'dup-delete-template-dialog-single';
$confirm2->title               = __('Are you sure you want to delete this template?', 'duplicator-pro');
$confirm2->message             = __('All schedules using this template will be reassigned to the "Default" Template.', 'duplicator-pro');
$confirm2->progressText        = $confirm1->progressText;
$confirm2->jsCallback          = 'DupliJs.Template.DeleteThis(this)';
$confirm2->initConfirm();
?>
<script>
    jQuery(document).ready(function($) {

        //Shows detail view
        DupliJs.Template.View = function(id) {
            $('#' + id).toggle();
        }

        // Edit template
        DupliJs.Template.Edit = function(id) {
            document.location.href = <?php echo wp_json_encode("$edit_template_url&package_template_id="); ?> + id;
        };

        //Delets a single record
        DupliJs.Template.Delete = function(id, schedule_count) {
            var message = "";
            <?php $confirm2->showConfirm(); ?>
            if (schedule_count > 0) {
                message += "<?php esc_html_e('There currently are', 'duplicator-pro') ?>" + " ";
                message += schedule_count + " " + "<?php esc_html_e('schedule(s) using this template.', 'duplicator-pro'); ?>" + "  ";
                message += "<?php esc_html_e('All schedules using this template will be reassigned to the \"Default\" template.', 'duplicator-pro') ?>" + " ";
                $("#<?php echo esc_js($confirm2->getID()); ?>_message").html(message);
            }
            $("#<?php echo esc_js($confirm2->getID()); ?>-confirm").attr('data-id', id);
        }

        DupliJs.Template.DeleteThis = function(e) {
            var id = $(e).attr('data-id');
            $("input[name^='selected_id[]'][value='" + id + "']").prop('checked', true);
            $("#dup-package-form").submit();
        }

        //  Creats a comma seperate list of all selected Backup ids
        DupliJs.Template.DeleteList = function() {
            var arr = [];

            $("input[name^='selected_id[]']").each(function(i, index) {
                var $this = $(index);

                if ($this.is(':checked') == true) {
                    arr[i] = $this.val();
                }
            });

            return arr.join(',');
        }

        // Bulk Action
        DupliJs.Template.BulkAction = function() {
            var list = DupliJs.Template.DeleteList();

            if (list.length == 0) {
                <?php $alert2->showAlert(); ?>
                return;
            }

            var action = $('#bulk_action').val(),
                checked = ($('.item-chk:checked').length > 0);

            if (action != "delete") {
                <?php $alert1->showAlert(); ?>
                return;
            }

            if (checked) {
                switch (action) {
                    default:
                        <?php $alert2->showAlert(); ?>
                        break;
                    case 'delete':
                        <?php $confirm1->showConfirm(); ?>
                        break;
                }
            }
        }

        DupliJs.Storage.BulkDelete = function() {
            $("#dup-package-form").submit();
        }

        //Sets all for deletion
        DupliJs.Template.SetDeleteAll = function(chkbox) {
            $('.item-chk').each(function() {
                this.checked = chkbox.checked;
            });
        }
    });
</script>