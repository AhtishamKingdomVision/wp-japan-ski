<?php

/**
 * Duplicator Weekly repeat template
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

$weekdays = $tplMng->getDataValueArrayRequired('weekdays');
?>
<label class="lbl-larger">
    <?php esc_html_e('Every', 'duplicator-pro'); ?>
</label>
<div>
    <!-- RSR Cron does not support counting by week - just days and months so removing (for now?)-->
    <div class="weekday-div">
        <input
            <?php checked($weekdays['mon']); ?>
            value="mon" name="weekday[]"
            type="checkbox"
            id="repeat-weekly-mon"
            data-parsley-group="weekly" required data-parsley-class-handler="#repeat-weekly-area"
            data-parsley-error-message="<?php esc_attr_e('At least one day must be checked.', 'duplicator-pro'); ?>"
            data-parsley-no-focus data-parsley-errors-container="#weekday-errors">
        <label for="repeat-weekly-mon"><?php esc_html_e('Monday', 'duplicator-pro'); ?></label>
    </div>
    <div class="weekday-div">
        <input
            <?php checked($weekdays['tue']); ?>
            value="tue" name="weekday[]"
            type="checkbox"
            id="repeat-weekly-tue">
        <label for="repeat-weekly-tue"><?php esc_html_e('Tuesday', 'duplicator-pro'); ?></label>
    </div>
    <div class="weekday-div">
        <input
            <?php checked($weekdays['wed']); ?>
            value="wed" name="weekday[]"
            type="checkbox"
            id="repeat-weekly-wed">
        <label for="repeat-weekly-wed"><?php esc_html_e('Wednesday', 'duplicator-pro'); ?></label>
    </div>
    <div class="weekday-div">
        <input
            <?php checked($weekdays['thu']); ?>
            value="thu" name="weekday[]"
            type="checkbox"
            id="repeat-weekly-thu">
        <label for="repeat-weekly-thu"><?php esc_html_e('Thursday', 'duplicator-pro'); ?></label>
    </div>
    <div class="weekday-div" style="clear:both">
        <input
            <?php checked($weekdays['fri']); ?>
            value="fri" name="weekday[]"
            type="checkbox"
            id="repeat-weekly-fri">
        <label for="repeat-weekly-fri"><?php esc_html_e('Friday', 'duplicator-pro'); ?></label>
    </div>
    <div class="weekday-div">
        <input
            <?php checked($weekdays['sat']); ?>
            value="sat" name="weekday[]"
            type="checkbox"
            id="repeat-weekly-sat">
        <label for="repeat-weekly-sat"><?php esc_html_e('Saturday', 'duplicator-pro'); ?></label>
    </div>
    <div class="weekday-div">
        <input
            <?php checked($weekdays['sun']); ?>
            value="sun" name="weekday[]"
            type="checkbox"
            id="repeat-weekly-sun">
        <label for="repeat-weekly-sun"><?php esc_html_e('Sunday', 'duplicator-pro'); ?></label>
    </div>
    <div style="padding-top:3px; clear:both;" id="weekday-errors"></div>
</div>