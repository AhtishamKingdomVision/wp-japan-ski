<?php

/**
 * Duplicator Start Time template
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

$start_hour   = $tplMng->getDataValueIntRequired('start_hour');
$start_minute = $tplMng->getDataValueIntRequired('start_minute');
$mins         = 0;
?>
<label class="lbl-larger" id="start-time-label">
    <?php esc_html_e('Start Time', 'duplicator-pro'); ?>
</label>
<div class="margin-bottom-1" id="start-time-content">
    <select name="_start_time" class="width-small inline-display margin-0">
        <?php
        // Add setting to use 24 hour vs AM/PM the interval for hours is '1'
        for ($hours = 0; $hours < 24; $hours++) {
            ?>
            <option <?php selected($hours, $start_hour); ?> value="<?php echo (int) $hours; ?>">
                <?php echo esc_html(sprintf('%02d:%02d', $hours, $mins)); ?>
            </option>
        <?php } ?>
    </select>

    <i class="dupli-edit-info">
        <?php esc_html_e("Current Server Time Stamp is", 'duplicator-pro'); ?>&nbsp;
        <?php echo esc_html(date_i18n('Y-m-d H:i:s')); ?>
    </i>
</div>

<div class="margin-bottom-1">
    <p class="description width-xlarge">
        <?php
        printf(
            esc_html_x(
                '%1$sNote:%2$s Schedules require web site traffic in order to start a build. 
                If you set a start time of 06:00 daily but do not get any traffic till 10:00 then the build will not start until 10:00. 
                If you have low traffic consider setting up a cron job to periodically hit your site.',
                '%1$s and %2$s represent opening and closing bold tags',
                'duplicator-pro'
            ),
            '<b>',
            '</b>'
        );
        ?>
    </p>
</div>