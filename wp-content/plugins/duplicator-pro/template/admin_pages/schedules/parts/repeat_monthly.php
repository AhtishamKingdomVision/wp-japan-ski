<?php

/**
 * Duplicator Monthly repeat template
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

$runEvery   = $tplMng->getDataValueIntRequired('run_every');
$dayOfMonth = $tplMng->getDataValueIntRequired('day_of_month');
?>
<label class="lbl-larger">
    <?php esc_html_e('Day', 'duplicator-pro'); ?>
</label>
<div>
    <select name="day_of_month" class="width-tiny inline-block margin-0">
        <?php for ($i = 1; $i <= 31; $i++) { ?>
            <option <?php selected($i, $dayOfMonth); ?> value="<?php echo (int) $i; ?>">
                <?php echo (int) $i; ?>
            </option>
        <?php } ?>
    </select>&nbsp;
    <?php esc_html_e('of every', 'duplicator-pro'); ?>&nbsp;
    <select name="_run_every_months" data-parsley-ui-enabled="false" class="width-tiny inline-block margin-0">
        <?php for ($i = 1; $i <= 12; $i++) { ?>
            <option <?php selected($i, $runEvery); ?> value="<?php echo (int) $i; ?>">
                <?php echo (int) $i; ?>
            </option>
        <?php } ?>
    </select>&nbsp;
    <?php esc_html_e('month(s)', 'duplicator-pro'); ?>
</div>