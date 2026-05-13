<?php

/**
 * Duplicator Hourly repeat template
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 */

$runEvery       = $tplMng->getDataValueIntRequired('run_every');
$frequency_note = $tplMng->getDataValueString('frequency_note', '');
$hour_intervals = [
    1,
    2,
    4,
    6,
    12,
];
$tipContent     = __('Backup will build every x hours starting at 00:00.', 'duplicator-pro') . '<br/><br/>' . $frequency_note;
?>
<label class="lbl-larger">
    <?php esc_html_e('Every', 'duplicator-pro'); ?>
</label>
<div>
    <select name="_run_every_hours" class="width-tiny inline-block margin-0" data-parsley-ui-enabled="false">
        <?php foreach ($hour_intervals as $hour_interval) { ?>
            <option <?php selected($hour_interval, $runEvery); ?> value="<?php echo (int) $hour_interval; ?>">
                <?php echo (int) $hour_interval; ?>
            </option>
        <?php } ?>
    </select>
    <?php
    esc_html_e('hours', 'duplicator-pro');
    ?>
    <i
        class="fa-solid fa-question-circle fa-sm dark-gray-color"
        data-tooltip-title="<?php esc_attr_e("Frequency Note", 'duplicator-pro'); ?>"
        data-tooltip="<?php echo esc_attr($tipContent); ?>">
    </i>
</div>