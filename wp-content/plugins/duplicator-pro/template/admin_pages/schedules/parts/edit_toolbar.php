<?php

/**
 * Duplicator messages sections
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Controllers\SchedulePageController;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Models\ScheduleEntity;
use Duplicator\Models\Storages\AbstractStorageEntity;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var AbstractStorageEntity $storage
 */
$blur                = $tplData['blur'];
$schedule            = $tplData['schedule'];
$copyScheduleList    = ScheduleEntity::getAll(
    0,
    0,
    null,
    fn(ScheduleEntity $s): bool => $s->getId() != $schedule->getId()
);
$schedulesListURL    = ControllersManager::getMenuLink(
    ControllersManager::SCHEDULES_SUBMENU_SLUG
);
$scheduleCopyBaseURL = SchedulePageController::getInstance()->getCopyActionUrl($schedule->getId());

$countList = count($copyScheduleList);
?>
<div class="dup-toolbar <?php echo ($blur ? 'dup-mock-blur' : ''); ?>">
    <label for="dup-copy-source-id-select" class="screen-reader-text">Copy storage action</label>
    <select
        id="dup-schedule-copy-select"
        name="dupli-source-schedule-id"
        class="small"
        <?php disabled($countList, 0); ?>>
        <option value="-1" selected="selected" disabled="true">
            <?php esc_html_e('Copy From', 'duplicator-pro'); ?>
        </option>
        <?php foreach ($copyScheduleList as $copy_schedule) { ?>
            <option value="<?php echo (int) $copy_schedule->getId(); ?>">
                <?php echo esc_html($copy_schedule->name); ?>
            </option>
        <?php } ?>
    </select>
    <input
        id="dup-schedule-copy-btn"
        type="button"
        class="button hollow secondary small  action"
        value="<?php esc_html_e("Apply", 'duplicator-pro') ?>"
        disabled>
    <span class="separator"></span>
    <a
        href="<?php echo esc_url($schedulesListURL); ?>"
        class="button hollow secondary small "
        title="<?php esc_attr_e('Back to Schedules list.', 'duplicator-pro'); ?>">
        <i class="far fa-clock fa-sm"></i> <?php esc_html_e('Schedules', 'duplicator-pro'); ?>
    </a>
</div>
<script>
    jQuery(document).ready(function($) {
        $('#dup-schedule-copy-select').on('change', function(e) {
            let copyId = parseInt($(this).val());
            $('#dup-schedule-copy-btn').prop('disabled', (copyId <= 0));
        });

        /*$('#dup-schedule-copy-select').change(function (evente) {
            event.preventDefault();
            alert('changed val ' + $(this).val());
            $('#dup-schedule-copy-btn').prop('disabled', ($(this).val() > 0));
        });*/

        $('#dup-schedule-copy-btn').click(function(event) {
            event.preventDefault();
            let copyId = $('#dup-schedule-copy-select').val();
            document.location.href = <?php echo json_encode($scheduleCopyBaseURL); ?> + '&dupli-source-schedule-id=' + copyId;
        });
    });
</script>

<hr class="dup-toolbar-divider" />