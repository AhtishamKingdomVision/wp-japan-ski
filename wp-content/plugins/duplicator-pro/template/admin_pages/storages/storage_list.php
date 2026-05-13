<?php

/**
 * @package Duplicator
 */

defined("ABSPATH") or die("");

use Duplicator\Ajax\ServicesStorage;
use Duplicator\Controllers\SettingsPageController;
use Duplicator\Controllers\StoragePageController;
use Duplicator\Core\CapMng;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Core\Views\TplMng;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\Storages\StoragesUtil;
use Duplicator\Views\UI\UiDialog;

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

$blur = $tplMng->getDataValueBool('blur');

$storages    = AbstractStorageEntity::getAll(0, 0, [StoragesUtil::class, 'sortDefaultFirst']);
$numStorages = ($storages === false) ? 0 : count($storages);

$edit_storage_url = ControllersManager::getMenuLink(
    ControllersManager::STORAGE_SUBMENU_SLUG,
    null,
    null,
    [
        ControllersManager::QUERY_STRING_INNER_PAGE => StoragePageController::INNER_PAGE_EDIT,
    ]
);
$storage_tab_url  = ControllersManager::getMenuLink(
    ControllersManager::STORAGE_SUBMENU_SLUG,
    SettingsPageController::L2_SLUG_STORAGE
);

$settingsStorageUrl = ControllersManager::getMenuLink(
    ControllersManager::SETTINGS_SUBMENU_SLUG,
    SettingsPageController::L2_SLUG_STORAGE
);

$baseCopyUrl = ControllersManager::getMenuLink(
    ControllersManager::STORAGE_SUBMENU_SLUG,
    SettingsPageController::L2_SLUG_STORAGE,
    null,
    [
        ControllersManager::QUERY_STRING_INNER_PAGE => 'edit',
        'action'                                    => $tplData['actions']['copy-storage']->getKey(),
        '_wpnonce'                                  => $tplData['actions']['copy-storage']->getNonce(),
    ]
);

?>
<div class="dup-toolbar <?php echo ($blur ? 'dup-mock-blur' : ''); ?>">
    <label for="bulk_action" class="screen-reader-text">Select bulk action</label>
    <select id="bulk_action" class="small">
        <option value="-1" selected>
            <?php esc_html_e("Bulk Actions", 'duplicator-pro') ?>
        </option>
        <?php if (CapMng::can(CapMng::CAP_STORAGE, false)) { ?>
            <option value="<?php echo (int) ServicesStorage::STORAGE_BULK_DELETE; ?>" title="Delete selected storage endpoint(s)">
                <?php esc_html_e('Delete', 'duplicator-pro'); ?>
            </option>
        <?php } ?>
    </select>
    <input
        type="button"
        id="dup-pack-bulk-apply"
        class="button hollow secondary small"
        value="<?php esc_attr_e("Apply", 'duplicator-pro') ?>"
        onclick="DupliJs.Storage.BulkAction()">
    <span class="separator"></span>
    <?php if (CapMng::can(CapMng::CAP_SETTINGS, false)) { ?>
        <a href="<?php echo esc_url($settingsStorageUrl); ?>"
            class="button hollow secondary small dupli-toolbar-settings"
            title="<?php esc_attr_e("Storage Settings", 'duplicator-pro'); ?>">
            <i class="fas fa-sliders-h fa-fw"></i>
        </a>
    <?php } ?>
</div>
<form
    id="dup-storage-form"
    action="<?php echo esc_url($storage_tab_url); ?>"
    method="post"
    class="<?php echo ($blur ? 'dup-mock-blur' : ''); ?>">
    <input type="hidden" id="dup-selected-storage" name="storage_id" value="null" />

    <!-- ====================
    LIST ALL STORAGE -->
    <table class="widefat storage-tbl dup-table-list valign-top dup-packtbl">
        <thead>
            <tr>
                <th style='width:10px;'>
                    <input
                        type="checkbox"
                        id="dupli-chk-all"
                        title="Select all storage endpoints" onclick="DupliJs.Storage.SetAll(this)"
                        class="margin-bottom-0">
                </th>
                <th style='width:275px;'>Name</th>
                <th><?php esc_html_e('Type', 'duplicator-pro'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($storages === false) {
                $tplMng->render('admin_pages/storages/storage_list_row_error');
            } else {
                foreach ($storages as $index => $storage) {
                    if ($storage->isHidden()) {
                        continue;
                    }
                    $tplMng->render('admin_pages/storages/storage_list_row', [
                        'storage' => $storage,
                        'index'   => $index,
                    ]);
                };
            }
            ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="8" style="text-align:right; font-size:12px">
                    <?php echo esc_html__('Total', 'duplicator-pro') . ': ' . (int) $numStorages; ?>
                </th>
            </tr>
        </tfoot>
    </table>

</form>
<?php
//Select Action Alert
$alert1          = new UiDialog();
$alert1->title   = __('Bulk Action Required', 'duplicator-pro');
$alert1->message = __('Please select an action from the "Bulk Actions" drop down menu!', 'duplicator-pro');
$alert1->initAlert();

//Select Storage Alert
$alert2          = new UiDialog();
$alert2->title   = __('Selection Required', 'duplicator-pro');
$alert2->message = __('Please select at least one storage to delete!', 'duplicator-pro');
$alert2->initAlert();

//Delete Dialog
$dlgDelete               = new UiDialog();
$dlgDelete->height       = 525;
$dlgDelete->title        = __('Delete Storage(s)?', 'duplicator-pro');
$dlgDelete->progressText = __('Removing Storages, Please Wait...', 'duplicator-pro');
$dlgDelete->jsCallback   = 'DupliJs.Storage.deleteAjax()';
$dlgDelete->initConfirm();
$storage_bulk_action_nonce = wp_create_nonce("duplicator_storage_bulk_actions");
?>
<script>
    jQuery(document).ready(function($) {
        //Shows detail view
        DupliJs.Storage.Edit = function(id) {
            document.location.href = <?php echo json_encode($edit_storage_url); ?> + '&storage_id=' + id;
        };

        //Copy and edit
        DupliJs.Storage.CopyEdit = function(id) {
            document.location.href = <?php echo json_encode($baseCopyUrl); ?> + '&dupli-source-storage-id=' + id;
        };

        //Shows detail view
        DupliJs.Storage.View = function(id) {
            $('#quick-view-' + id).toggle();
        };

        //Select all checked items
        DupliJs.Storage.SelectedList = function() {
            var arr = [];
            $("input[name^='selected_id[]']").each(function() {
                if ($(this).is(':checked')) {
                    arr.push($(this).val());
                }
            });
            return arr;
        };

        //Sets all for deletion
        DupliJs.Storage.SetAll = function(chkbox) {
            $('.item-chk').each(function() {
                this.checked = chkbox.checked;
            });
        };

        // Bulk action
        DupliJs.Storage.BulkAction = function() {
            var list = DupliJs.Storage.SelectedList();
            var action = $('#bulk_action').val();

            if (list.length === 0) {
                <?php $alert2->showAlert(); ?>
                return;
            }

            switch (action) {
                case '<?php echo (int) ServicesStorage::STORAGE_BULK_DELETE; ?>':
                    DupliJs.Storage.deleteConfirm(list);
                    break;
                default:
                    <?php $alert1->showAlert(); ?>
                    break;
            }
        };

        //Delete via the delete link
        DupliJs.Storage.deleteSingle = function(id) {
            $('#dup-selected-storage').val(id);
            DupliJs.Storage.deleteConfirm([id]);
        };

        //Load the delete confirm dialog
        DupliJs.Storage.deleteConfirm = function(idList) {
            var $rowData;
            var name, id, typeName, html;

            var storeCount = idList.length;
            var isSingle = (storeCount == 1) ? true : false;
            var dlgID = "<?php echo esc_js($dlgDelete->getID()); ?>";
            var $content = $(`#${dlgID}_message`);

            html = (isSingle) ?
                "<i><?php esc_html_e('Are you sure you want to delete this storage item?', 'duplicator-pro') ?></i>" :
                `<i><?php esc_html_e('Are you sure you want to delete these ${storeCount} storage items?', 'duplicator-pro') ?></i>`;

            // Build storage item html
            html += '<div class="store-items">';
            idList.forEach(v => {
                html += $('#main-view-' + v).data('delete-view');
            });
            html += '</div>';

            $content.html(html);
            <?php $dlgDelete->showConfirm(); ?>

            html = `<div class="schedule-area">
                    <b><?php esc_html_e('Linked Schedules', 'duplicator-pro') ?>:</b><br/>
                    <small><?php esc_html_e("Schedules linked to the storage items above", 'duplicator-pro');  ?>:</small>
                    <div class="schedule-progress" id="${dlgID}-schedule-progress">
                        <i class="fas fa-circle-notch fa-spin"></i>
                        <?php esc_html_e('Finding Schedule Links...  Please wait', 'duplicator-pro') ?>
                    </div>
                    <small>
                        <?php
                        esc_html_e("To remove storage items and unlink schedules click OK. ", 'duplicator-pro');
                        printf(
                            esc_html_x(
                                'Schedules with asterisk%1$s will be deactivated if storage is removed.',
                                '%1$s is an asterisk symbol',
                                'duplicator-pro'
                            ),
                            '<span class="maroon">*</span>'
                        );
                        ?>
                    </small>
                 </div>`;
            $content.append(html);

            function loadSchedules(idList, dlgID) {
                let result = DupliJs.Storage.getScheduleData(idList);
                (result != null) ?
                $(`#${dlgID}-schedule-progress`).html(result):
                    $(`#${dlgID}-schedule-progress`).html("<?php esc_html_e('- No linked schedules found -', 'duplicator-pro') ?>");
            }
            setTimeout(loadSchedules, 100, idList, dlgID);
        };

        //Get the linked schedule data
        DupliJs.Storage.getScheduleData = function(storageIDs) {

            var result = null;
            var html;

            $.ajax({
                    type: "POST",
                    url: ajaxurl,
                    async: false,
                    dataType: "json",
                    data: {
                        action: 'duplicator_storage_bulk_actions',
                        perform: <?php echo (int) ServicesStorage::STORAGE_GET_SCHEDULES; ?>,
                        storage_ids: storageIDs,
                        nonce: '<?php echo esc_js($storage_bulk_action_nonce); ?>'
                    }
                })
                .done(function(data) {
                    //__sleepFor(1000); //Test delays
                    if (data.schedules !== undefined && data.schedules.length > 0) {
                        html = '';
                        data.schedules.forEach(function(schedule) {
                            let name = $("<div/>").text(schedule.name).html();
                            let asterisk = schedule.hasOneStorage ? "*" : "";
                            html += `<div class="schedule-item">
                               <i class="far fa-clock"></i> <a href="${schedule.editURL}">${name}</a> <span class="maroon">${asterisk}</span>
                            </div>`;
                        });
                        result = html;
                    }
                })
                .fail(function() {
                    result = '<i class="fas fa-exclamation-triangle"></i> <?php esc_html_e('Unable to get schedule data.', 'duplicator-pro') ?>';
                });
            return result;
        };


        //Perform the delete via ajax
        DupliJs.Storage.deleteAjax = function() {

            var dlgID = "<?php echo esc_js($dlgDelete->getID()); ?>";
            var list = DupliJs.Storage.SelectedList();

            //Delete from the quick link
            if (list.length == 0) {
                var singleID = $('#dup-selected-storage').val();
                list = (singleID > 0) ? [singleID] : null;
            }

            $(`#${dlgID}_message`).hide();

            $.ajax({
                    type: "POST",
                    url: ajaxurl,
                    dataType: "json",
                    data: {
                        action: 'duplicator_storage_bulk_actions',
                        perform: <?php echo (int) ServicesStorage::STORAGE_BULK_DELETE; ?>,
                        storage_ids: list,
                        nonce: '<?php echo esc_js($storage_bulk_action_nonce); ?>'
                    }
                })
                .done(function() {
                    $('#dup-storage-form').submit()
                })
                .always(function() {
                    $('#dup-selected-storage').val(null)
                });
        };
    });

    //Used to test ajax delays
    function __sleepFor(sleepDuration) {
        var now = new Date().getTime();
        while (new Date().getTime() < now + sleepDuration) {
            /* Do nothing */
        }
    }
</script>