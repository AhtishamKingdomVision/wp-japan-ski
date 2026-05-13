<?php

/**
 * @package Duplicator
 */

defined("ABSPATH") or die("");

use Duplicator\Controllers\SchedulePageController;
use Duplicator\Controllers\ToolsPageController;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Models\ScheduleEntity;
use Duplicator\Models\TemplateEntity;

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 */

$blur     = $tplMng->getDataValueBool('blur');
$schedule = $tplMng->getDataValueObjRequired('schedule', ScheduleEntity::class);

$templatesPageUrl = ToolsPageController::getInstance()->getMenuLink(ToolsPageController::L2_SLUG_TEMPLATE);
$editTemplateUrl  = ControllersManager::getMenuLink(
    ControllersManager::TOOLS_SUBMENU_SLUG,
    ToolsPageController::L2_SLUG_TEMPLATE,
    null,
    [ControllersManager::QUERY_STRING_INNER_PAGE => 'edit']
);

?>
<form
    id="dup-schedule-form"
    class="dup-monitored-form <?php echo ($blur ? 'dup-mock-blur' : ''); ?>"
    action="<?php echo esc_url(ControllersManager::getCurrentLink()); ?>"
    method="post"
    data-parsley-ui-enabled="true">
    <?php $tplMng->render('admin_pages/schedules/parts/edit_toolbar'); ?>
    <?php $tplMng->getAction(SchedulePageController::ACTION_EDIT_SAVE)->getActionNonceFileds(); ?>
    <input type="hidden" name="schedule_id" value="<?php echo (int) $schedule->getId(); ?>">

    <!-- ===============================
    SETTINGS -->
    <table class="form-table">
        <tr valign="top">
            <th scope="row"><label><?php esc_html_e('Schedule Name', 'duplicator-pro'); ?></label></th>
            <td>
                <input
                    type="text"
                    id="schedule-name"
                    class="width-medium"
                    name="name"
                    value="<?php echo esc_attr($schedule->name); ?>"
                    required data-parsley-group="standard"
                    autocomplete="off">
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <label><?php esc_html_e('Backup Template', 'duplicator-pro'); ?></label>
            </th>
            <td>
                <div class="schedule-template display-flex">
                    <div>
                        <select id="schedule-template-selector" name="template_id" class="width-medium margin-bottom-0" required>
                            <?php
                            $templates = TemplateEntity::getAllWithoutManualMode();
                            if (count($templates) == 0) {
                                $no_templates = __('No Templates Found', 'duplicator-pro');
                                printf('<option value="">%1$s</option>', esc_html($no_templates));
                            } else {
                                echo "<option value='' selected='true'>" . esc_html__("&lt;Choose A Template&gt;", 'duplicator-pro') . "</option>";
                                foreach ($templates as $template) {
                                    ?>
                                    <option
                                        <?php selected($schedule->template_id, $template->getId()); ?>
                                        value="<?php echo (int) $template->getId(); ?>">
                                        <?php echo esc_html($template->name); ?>
                                    </option>
                                    <?php
                                }
                            }
                            ?>
                        </select>
                        <div class="margin-bottom-1">
                            <small>
                                <a href="<?php echo esc_url($templatesPageUrl); ?>" target="edit-template">
                                    [<?php esc_attr_e("Show All Templates", 'duplicator-pro') ?>]
                                </a>
                            </small>
                        </div>
                    </div>
                    <div>
                        <a
                            id="schedule-template-edit-btn"
                            href="javascript:void(0)"
                            onclick="DupliJs.Schedule.EditTemplate()"
                            style="display:none"
                            class="pack-temp-btns button hollow secondary small margin-bottom-0"
                            title="<?php esc_attr_e("Edit Selected Template", 'duplicator-pro') ?>">
                            <i class="far fa-edit"></i>
                        </a>
                        <a
                            id="schedule-template-add-btn"
                            href="<?php echo esc_url($editTemplateUrl); ?>"
                            class="pack-temp-btns button hollow secondary small margin-bottom-0"
                            title="<?php esc_attr_e("Add New Template", 'duplicator-pro') ?>"
                            target="edit-template">
                            <i class="far fa-plus-square"></i>
                        </a>
                        <a
                            id="schedule-template-sync-btn"
                            href="javascript:window.location.reload()"
                            class="pack-temp-btns button hollow secondary small margin-bottom-0"
                            title="<?php esc_attr_e("Refresh Template List", 'duplicator-pro') ?>">
                            <i class="fas fa-sync-alt"></i>
                        </a>
                        &nbsp;
                        <i
                            class="fa-solid fa-question-circle fa-sm dark-gray-color"
                            data-tooltip-title="<?php esc_attr_e("Template Details", 'duplicator-pro'); ?>"
                            data-tooltip="<?php
                                            esc_attr_e(
                                                'The template specifies which files and database tables should be included in the archive.<br/><br/>
                                Choose from an existing template or create a new one by clicking the "Add New Template" button. 
                                To edit a template, select it and then click the "Edit Selected Template" button.',
                                                'duplicator-pro'
                                            );
                                            ?>">
                        </i>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <th scope="row"><label><?php esc_html_e('Storage', 'duplicator-pro'); ?></label></th>
            <td>
                <!-- ===============================
                STORAGE -->
                <?php $tplMng->render(
                    'parts/storage/select_list',
                    [
                        'selectedStorageIds' => $schedule->storage_ids,
                        'showAddNew'         => false,
                        'recoveryPointMsg'   => true,
                    ]
                ); ?>
            </td>
        </tr>
        <tr valign="top">
            <td colspan="2">
                <?php
                $tplMng->render(
                    'admin_pages/schedules/parts/repeats_options',
                    [
                        'repeat_type'  => $schedule->repeat_type,
                        'run_every'    => $schedule->run_every,
                        'day_of_month' => $schedule->day_of_month,
                        'weekdays'     => [
                            'mon' => $schedule->isDaySet('mon'),
                            'tue' => $schedule->isDaySet('tue'),
                            'wed' => $schedule->isDaySet('wed'),
                            'thu' => $schedule->isDaySet('thu'),
                            'fri' => $schedule->isDaySet('fri'),
                            'sat' => $schedule->isDaySet('sat'),
                            'sun' => $schedule->isDaySet('sun'),
                        ],
                        'start_hour'   => $schedule->getStartTimePiece(0),
                        'start_minute' => $schedule->getStartTimePiece(1),
                    ]
                );
                ?>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php esc_html_e('Recovery Status', 'duplicator-pro'); ?></label></th>
            <td class="dup-recovery-template">
                <div class="margin-bottom-1">
                    <?php
                    if (($template = $schedule->getTemplate()) !== false) {
                        $schedule->recoveableHtmlInfo();
                    } else {
                        esc_html_e('Unavailable', 'duplicator-pro');
                        ?>
                        <i class="fa-solid fa-question-circle fa-sm dark-gray-color"
                            data-tooltip-title="<?php esc_attr_e("Recovery Status", 'duplicator-pro'); ?>"
                            data-tooltip="<?php esc_attr_e('Status is unavailable. Please save the schedule to view recovery status', 'duplicator-pro');
                            ?>"></i>
                    <?php } ?>
                </div>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="schedule-active"><?php esc_html_e('Activated', 'duplicator-pro'); ?></label></th>
            <td>
                <input name="_active" id="schedule-active" type="checkbox" <?php checked($schedule->isActive()); ?> class="margin-0">
                <label for="schedule-active"><?php esc_html_e('Enable This Schedule', 'duplicator-pro'); ?></label><br />
                <i class="dupli-edit-info"> <?php esc_html_e('When checked this schedule will run', 'duplicator-pro'); ?></i>
            </td>
        </tr>
    </table><br />
    <button
        id="dupli-save-schedule"
        class="button primary small"
        type="submit">
        <?php esc_html_e('Save Schedule', 'duplicator-pro'); ?>
    </button>
</form>

<script>
    jQuery(document).ready(function($) {
        DupliJs.Schedule.EditTemplate = function() {
            var templateID = $('#schedule-template-selector').val();
            var url = <?php echo wp_json_encode($editTemplateUrl); ?> + '&package_template_id=' + templateID;
            window.open(url, 'edit-template');
        };

        DupliJs.Schedule.ToggleTemplateEditBtn = function() {
            $('#schedule-template-edit-btn, #schedule-template-add-btn, #schedule-template-sync-btn').hide();
            if ($("#schedule-template-selector").val() > 0) {
                $('#schedule-template-edit-btn').show();
            } else {
                $('#schedule-template-add-btn, #schedule-template-sync-btn').show();
            }
        }

        $('#dup-schedule-form').parsley({
            excluded: ':disabled'
        });

        jQuery('#schedule-name').focus().select();
        DupliJs.Schedule.ToggleTemplateEditBtn();

        $("#schedule-template-selector").change(function() {
            DupliJs.Schedule.ToggleTemplateEditBtn()
        });
    });
</script>