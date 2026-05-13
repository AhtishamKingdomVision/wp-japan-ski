<?php

/**
 * @package Duplicator
 */

defined("ABSPATH") or die("");

use Duplicator\Addons\ProBase\License\License;
use Duplicator\Controllers\SettingsPageController;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Models\BrandEntity;
use Duplicator\Views\UI\UiDialog;

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

$brand_list_url = ControllersManager::getCurrentLink([ControllersManager::QUERY_STRING_INNER_PAGE => SettingsPageController::BRAND_INNER_PAGE_LIST]);
$brand_edit_url = ControllersManager::getCurrentLink([ControllersManager::QUERY_STRING_INNER_PAGE => SettingsPageController::BRAND_INNER_PAGE_EDIT]);
$brands         = BrandEntity::getAllWithDefault();
$brand_count    = count($brands);
?>

<h3 class="title">
    <?php esc_html_e('Installer Branding', 'duplicator-pro'); ?>
</h3>
<hr>

<?php
if (!License::can(License::CAPABILITY_BRAND)) {
    $tplMng->render('admin_pages/settings/brand/no_capability_list');
    return;
}
?>

<div class="dup-toolbar">
    <label for="bulk_action" class="screen-reader-text">Select bulk action</label>
    <select id="bulk_action" class="small">
        <option value="-1" selected="selected">
            <?php esc_html_e('Bulk Actions', 'duplicator-pro'); ?>
        </option>
        <option value="delete" title="<?php esc_attr_e('Delete selected brand endpoint(s)', 'duplicator-pro'); ?>">
            <?php esc_html_e('Delete', 'duplicator-pro'); ?>
        </option>
    </select>
    <input
        type="button"
        class="button hollow secondary small action"
        value="<?php esc_html_e("Apply", 'duplicator-pro') ?>"
        onclick="DupliJs.Settings.Brand.BulkAction()">
    <span class="separator"></span>
    <?php $tplMng->render('admin_pages/settings/brand/brand_create_button'); ?>
</div>

<form id="dup-brand-form" action="<?php echo esc_attr($brand_list_url); ?>" method="post">
    <?php $tplData['actions'][SettingsPageController::ACTION_BRAND_DELETE]->getActionNonceFileds(); ?>
    <table class="widefat brand-tbl dup-table-list valign-top">
        <thead>
            <tr>
                <th style='width:10px;'>
                    <input type="checkbox" id="dupli-chk-all" title="Select all brand endpoints" onclick="DupliJs.Settings.Brand.SetAll(this)">
                </th>
                <th style='width:100%;'><?php esc_html_e('Name', 'duplicator-pro'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr id='main-view-<?php echo (int) $brands[0]->getId(); ?>' class="brand-row row">
                <td>
                    <input type="checkbox" disabled="disabled" />
                </td>
                <td>
                    <a href="javascript:void(0);" onclick="DupliJs.Settings.Brand.Edit(0)"><b><?php esc_html_e('Default', 'duplicator-pro'); ?></b></a>
                    <div class="sub-menu">
                        <a href="javascript:void(0);" onclick="DupliJs.Settings.Brand.Edit(0)"><?php esc_html_e('View', 'duplicator-pro'); ?></a> |
                        <a href="javascript:void(0);" onclick="DupliJs.Settings.Brand.View('<?php echo (int) $brands[0]->getId(); ?>');">
                            <?php esc_html_e('Quick View', 'duplicator-pro'); ?>
                        </a>
                    </div>
                </td>
            </tr>
            <tr id="quick-view-<?php echo (int) $brands[0]->getId() ?>" class="brand-detail row-details">
                <td colspan="3">
                    <b><?php esc_html_e('QUICK VIEW', 'duplicator-pro') ?></b> <br />
                    <div>
                        <label><?php esc_html_e('Name', 'duplicator-pro') ?>:</label>
                        <?php echo esc_html($brands[0]->name) ?>
                    </div>
                    <div>
                        <label><?php esc_html_e('Notes', 'duplicator-pro') ?>:</label>
                        <?php echo (strlen($brands[0]->notes)) ? esc_html($brands[0]->notes) : esc_html__('(no notes)', 'duplicator-pro'); ?>
                    </div>
                    <div>
                        <label><?php esc_html_e('Logo', 'duplicator-pro') ?>:</label>
                        <?php
                        echo $brands[0]->logo  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        ?>
                    </div>
                    <button
                        type="button"
                        class="button hollow secondary small"
                        onclick="DupliJs.Settings.Brand.View('<?php echo (int) $brands[0]->getId(); ?>');">
                        <?php esc_html_e('Close', 'duplicator-pro') ?>
                    </button>
                </td>
            </tr>
            <?php
            $i = 0;
            foreach ($brands as $x => $brand) :
                if ($x === 0) {
                    continue; // remove default item in list because is defined out of loop below
                }
                $i++;

                //$brand_type = $brand->getModeText();
                ?>
                <tr id='main-view-<?php echo (int) $brand->getId() ?>' class="row brand-row<?php echo ($i % 2) ? ' alternate' : ''; ?>">
                    <td>
                        <?php if ($brand->editable) : ?>
                            <input name="selected_id[]" type="checkbox" value="<?php echo (int) $brand->getId(); ?>" class="item-chk" />
                        <?php else : ?>
                            <input type="checkbox" disabled="disabled" />
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="javascript:void(0);" onclick="DupliJs.Settings.Brand.Edit('<?php echo (int) $brand->getId(); ?>')">
                            <b><?php echo esc_html($brand->name); ?></b>
                        </a>
                        <?php if ($brand->editable) : ?>
                            <div class="sub-menu">
                                <a href="javascript:void(0);" onclick="DupliJs.Settings.Brand.Edit('<?php echo (int) $brand->getId(); ?>')">
                                    <?php esc_html_e('Edit', 'duplicator-pro') ?>
                                </a> |
                                <a href="javascript:void(0);" onclick="DupliJs.Settings.Brand.View('<?php echo (int) $brand->getId(); ?>');">
                                    <?php esc_html_e('Quick View', 'duplicator-pro') ?>
                                </a> |
                                <a href="javascript:void(0);" onclick="DupliJs.Settings.Brand.Delete('<?php echo (int) $brand->getId(); ?>');">
                                    <?php esc_html_e('Delete', 'duplicator-pro') ?>
                                </a>
                            </div>
                        <?php else : ?>
                            <div class="sub-menu">
                                <a href="javascript:void(0);" onclick="DupliJs.Settings.Brand.Edit(0)">
                                    <?php esc_html_e('View', 'duplicator-pro') ?>
                                </a> |
                                <a href="javascript:void(0);" onclick="DupliJs.Settings.Brand.View('<?php echo (int) $brand->getId(); ?>');">
                                    <?php esc_html_e('Quick View', 'duplicator-pro') ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr id='quick-view-<?php echo (int) $brand->getId() ?>' class='<?php echo ($i % 2) ? 'alternate ' : ''; ?>brand-detail row-details'>
                    <td colspan="3">
                        <b><?php esc_html_e('QUICK VIEW', 'duplicator-pro') ?></b> <br />
                        <div>
                            <label><?php esc_html_e('Name', 'duplicator-pro') ?>:</label>
                            <?php echo esc_html($brand->name); ?>
                        </div>
                        <div>
                            <label><?php esc_html_e('Notes', 'duplicator-pro') ?>:</label>
                            <?php echo (strlen($brand->notes)) ? esc_html($brand->notes) : esc_html__('(no notes)', 'duplicator-pro'); ?>
                        </div>
                        <div>
                            <label><?php esc_html_e('Logo', 'duplicator-pro') ?>:</label>
                            <?php
                            echo $brand->logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            ?>
                        </div>
                        <button
                            type="button"
                            class="button hollow secondary small"
                            onclick="DupliJs.Settings.Brand.View('<?php echo (int) $brand->getId(); ?>');">
                            <?php esc_html_e('Close', 'duplicator-pro') ?>
                        </button>
                    </td>
                </tr>
                <?php
            endforeach;
            ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="8" style="text-align:right; font-size:12px">
                    <?php echo esc_html__('Total', 'duplicator-pro') . ': ' . (int) $brand_count; ?>
                </th>
            </tr>
        </tfoot>
    </table>
</form>

<!-- ==========================================
THICK-BOX DIALOGS: -->
<?php
$alert1          = new UiDialog();
$alert1->title   = __('Bulk Action Required', 'duplicator-pro');
$alert1->message = __('Please select an action from the "Bulk Actions" drop down menu!', 'duplicator-pro');
$alert1->initAlert();

$alert2          = new UiDialog();
$alert2->title   = __('Selection Required', 'duplicator-pro');
$alert2->message = __('Please select at least one brand to delete!', 'duplicator-pro');
$alert2->initAlert();

$confirm1               = new UiDialog();
$confirm1->title        = __('Delete Brand?', 'duplicator-pro');
$confirm1->message      = __('Are you sure you want to delete the selected brand(s)?', 'duplicator-pro');
$confirm1->message     .= '<br/>';
$confirm1->message     .= '<small><i>' . __('Note: This action removes all brands.', 'duplicator-pro') . '</i></small>';
$confirm1->progressText = __('Removing Brands, Please Wait...', 'duplicator-pro');
$confirm1->jsCallback   = 'DupliJs.Settings.Brand.BulkDelete()';
$confirm1->initConfirm();

$confirm2               = new UiDialog();
$confirm2->title        = __('Delete Brand?', 'duplicator-pro');
$confirm2->message      = __('Are you sure you want to delete the selected brand(s)?', 'duplicator-pro');
$confirm2->progressText = __('Removing Brands, Please Wait...', 'duplicator-pro');
$confirm2->jsCallback   = 'DupliJs.Settings.Brand.DeleteThis(this)';
$confirm2->initConfirm();

$delete_nonce = wp_create_nonce('duplicator_brand_delete');
?>
<script>
    jQuery(document).ready(function($) {

        //Shows detail view
        DupliJs.Settings.Brand.AddNew = function() {
            document.location.href = <?php echo wp_json_encode("{$brand_edit_url}&action=new"); ?>;
        }

        DupliJs.Settings.Brand.Edit = function(id) {
            if (id == 0) {
                document.location.href = <?php echo wp_json_encode("{$brand_edit_url}&action=default&id="); ?> + id;
            } else {
                document.location.href = <?php echo wp_json_encode("{$brand_edit_url}&action=edit&id="); ?> + id;
            }
        }

        //Shows detail view
        DupliJs.Settings.Brand.View = function(id) {
            $('#quick-view-' + id).toggle();
            $('#main-view-' + id).toggle();
        }

        //Delets a single record
        DupliJs.Settings.Brand.Delete = function(id) {
            <?php $confirm2->showConfirm(); ?>
            $("#<?php echo esc_js($confirm2->getID()); ?>-confirm").attr('data-id', id);
        }

        DupliJs.Settings.Brand.DeleteThis = function(e) {
            var id = $(e).attr('data-id');
            $("input[name^='selected_id[]'][value='" + id + "']").prop('checked', true);
            $("#dup-brand-form").submit()
        }

        //  Creats a comma seperate list of all selected Backup ids
        DupliJs.Settings.Brand.DeleteList = function() {
            var arr = [];

            $("input[name^='selected_id[]']").each(function(i, index) {
                var $this = $(index);

                if ($this.is(':checked') == true) {
                    arr[i] = $this.val();
                }
            });

            return arr;
        }

        // Bulk delete
        DupliJs.Settings.Brand.BulkDelete = function() {
            var list = DupliJs.Settings.Brand.DeleteList();
            var pageCount = $('#current-page-selector').val();
            var pageItems = $("input[name^='selected_id[]']");

            $.ajax({
                type: "POST",
                url: ajaxurl,
                dataType: "json",
                data: {
                    action: 'duplicator_brand_delete',
                    brand_ids: list,
                    nonce: <?php echo wp_json_encode($delete_nonce); ?>
                },
            }).done(function(data) {
                $('#dup-brand-form').submit();
            });
        }

        // Confirm bulk action
        DupliJs.Settings.Brand.BulkAction = function() {
            var list = DupliJs.Settings.Brand.DeleteList();

            if (list.length == 0) {
                <?php $alert2->showAlert(); ?>
                return;
            }

            var action = $('#bulk_action').val();
            var checked = ($('.item-chk:checked').length > 0);

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

        //Sets all for deletion
        DupliJs.Settings.Brand.SetAll = function(chkbox) {
            $('.item-chk').each(function() {
                this.checked = chkbox.checked;
            });
        }
    });
</script>