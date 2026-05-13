<?php

/**
 * Activity Log integration upgrade notice template
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
 * @var array<string, mixed> $tplData
 */
?>
<div>
    <p><b><?php esc_html_e('Activity Log Integration Update', 'duplicator-pro'); ?></b></p>
    <p>
        <?php esc_html_e('Failed backups are no longer shown in the main backup list.', 'duplicator-pro'); ?>
        <?php
        echo esc_html(
            sprintf(
                _n(
                    '%d failed backup has been moved to the Activity Log.',
                    '%d failed backups have been moved to the Activity Log.',
                    $tplData['count'],
                    'duplicator-pro'
                ),
                $tplData['count']
            )
        );
        ?>
        <?php
        printf(
            esc_html__(
                'You can view them in the %1$sActivity Log%2$s.',
                'duplicator-pro'
            ),
            '<a href="' . esc_url($tplData['activityLogUrl']) . '">',
            '</a>'
        );
        ?>
    </p>
</div>
