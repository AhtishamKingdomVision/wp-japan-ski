<?php

/**
 * Duplicator messages sections
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Addons\ProBase\License\License;
use Duplicator\Models\ScheduleEntity;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 */

$repeatType = $tplMng->getDataValueIntRequired('repeat_type');

$min_frequency = 0;
$max_frequency = (
    License::can(License::CAPABILITY_SHEDULE_HOURLY) ?
    ScheduleEntity::REPEAT_HOURLY :
    ScheduleEntity::REPEAT_MONTHLY
);

$frequencyUpgradMsg = sprintf(
    __(
        'Hourly frequency isn\'t available at the <b>%1$s</b> license level.',
        'duplicator-pro'
    ),
    License::getLicenseToString()
) .
    ' <b>' .
    sprintf(
        _x(
            'To enable this option %1$supgrade%2$s the License.',
            '%1$s and %2$s represents the opening and closing HTML tags for an anchor or link',
            'duplicator-pro'
        ),
        '<a href="' . esc_url(License::getUpsellURL()) . '" target="_blank">',
        '</a>'
    ) .
    '</b>';

$frequency_note = __(
    'If you have a large site, it\'s recommended you schedule backups during lower traffic periods. 
    If you\'re on a shared host then be aware that running multiple schedules too close together 
    (i.e. every 10 minutes) may alert your host to a spike in system resource usage.  
    Be sure that your schedules do not overlap and give them plenty of time to run.',
    'duplicator-pro'
);
?>

<div class="dup-settings-wrapper margin-bottom-1">
    <label class="lbl-larger"><?php esc_html_e("Repeats", 'duplicator-pro'); ?></label>
    <div class="margin-bottom-1">
        <select
            id="change-mode"
            name="repeat_type"
            class="width-small margin-0"
            onchange="DupliJs.Schedule.ChangeMode()"
            data-parsley-range='<?php printf('[%1$s, %2$s]', (int) $min_frequency, (int) $max_frequency); ?>'
            data-parsley-error-message="<?php echo esc_attr($frequencyUpgradMsg); ?>">
            <option
                value="<?php echo (int) ScheduleEntity::REPEAT_HOURLY; ?>"
                <?php selected($repeatType, ScheduleEntity::REPEAT_HOURLY) ?>>
                <?php esc_html_e("Hourly", 'duplicator-pro'); ?>
            </option>
            <option
                value="<?php echo (int) ScheduleEntity::REPEAT_DAILY; ?>"
                <?php selected($repeatType, ScheduleEntity::REPEAT_DAILY) ?>>
                <?php esc_html_e("Daily", 'duplicator-pro'); ?>
            </option>
            <option
                value="<?php echo (int) ScheduleEntity::REPEAT_WEEKLY; ?>"
                <?php selected($repeatType, ScheduleEntity::REPEAT_WEEKLY) ?>>
                <?php esc_html_e("Weekly", 'duplicator-pro'); ?>
            </option>
            <option
                value="<?php echo (int) ScheduleEntity::REPEAT_MONTHLY; ?>"
                <?php selected($repeatType, ScheduleEntity::REPEAT_MONTHLY) ?>>
                <?php esc_html_e("Monthly", 'duplicator-pro'); ?>
            </option>
        </select>
    </div>

    <!-- Repeat Options -->
    <div id="repeat-hourly-area" class="repeater-area margin-bottom-1">
        <?php $tplMng->render('admin_pages/schedules/parts/repeat_hourly', ['frequency_note' => $frequency_note]); ?>
    </div>

    <div id="repeat-daily-area" class="repeater-area margin-bottom-1">
        <?php $tplMng->render('admin_pages/schedules/parts/repeat_daily', ['frequency_note' => $frequency_note]); ?>
    </div>

    <div id="repeat-weekly-area" class="repeater-area margin-bottom-1">
        <?php $tplMng->render('admin_pages/schedules/parts/repeat_weekly'); ?>
    </div>

    <div id="repeat-monthly-area" class="repeater-area margin-bottom-1">
        <?php $tplMng->render('admin_pages/schedules/parts/repeat_monthly'); ?>
    </div>

    <!-- Start Time -->
    <?php $tplMng->render('admin_pages/schedules/parts/start_time'); ?>
</div>

<script>
    jQuery(document).ready(function($) {
        DupliJs.Schedule.ChangeMode = function() {
            var mode = $("#change-mode option:selected").val();
            var animate = 400;
            $('#repeat-hourly-area, #repeat-daily-area, #repeat-weekly-area, #repeat-monthly-area').hide();
            n = $("#repeat-weekly-area input:checked").length;

            if (n == 0) {
                // Hack so parsely will ignore weekly if it isnt selected
                $('#repeat-weekly-mon').prop("checked", true);
            }

            switch (mode) {
                case "0":
                    $('#repeat-daily-area').show(animate);
                    $('#start-time-label, #start-time-content').show(animate);
                    break;
                case "1":
                    $('#repeat-weekly-area').show(animate);
                    $('#start-time-label, #start-time-content').show(animate);
                    break;
                case "2":
                    $('#repeat-monthly-area').show(animate);
                    $('#start-time-label, #start-time-content').show(animate);
                    break;
                case "3":
                    $('#repeat-hourly-area').show(animate);
                    $('#start-time-label, #start-time-content').hide(animate);
                    break;
            }
        }

        DupliJs.Schedule.ChangeMode();
    });
</script>