<?php

/**
 * Failed backups notice template
 */

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 */
?>
<img src="<?php echo esc_url(plugins_url('duplicator-pro/assets/img/warning.png')); ?>"
    style="float:left; padding:0 10px 0 5px"
    alt="<?php esc_attr_e('Warning', 'duplicator-pro'); ?>" />
<div style="margin-left: 70px;">
    <p><b><?php esc_html_e('Failed Backups Detected', 'duplicator-pro'); ?></b></p>
    <p>
        <?php
        printf(
            esc_html__(
                'One or more backups have failed. To view failure details, check the %1$sActivity Log%2$s.',
                'duplicator-pro'
            ),
            '<a href="' . esc_url($tplData['activityLogUrl']) . '">',
            '</a>'
        );
        ?>
    </p>
</div>
