<?php

/**
 * Duplicator Daily repeat template
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
?>
<label class="lbl-larger">
    <?php esc_html_e('Every', 'duplicator-pro'); ?>
</label>
<div>
    <select name="_run_every_days" class="width-tiny inline-block margin-0" data-parsley-ui-enabled="false">
        <?php for ($i = 1; $i < 30; $i++) { ?>
            <option <?php selected($i, $runEvery); ?> value="<?php echo (int) $i; ?>">
                <?php echo (int) $i; ?>
            </option>
        <?php } ?>
    </select>
    <?php esc_html_e('days', 'duplicator-pro'); ?>
    <i
        class="fa-solid fa-question-circle fa-sm dark-gray-color"
        data-tooltip-title="<?php esc_attr_e("Frequency Note", 'duplicator-pro'); ?>"
        data-tooltip="<?php echo esc_attr($frequency_note) ?>">
    </i>
</div>