<?php

/**
 * @package Duplicator
 */

use Duplicator\Addons\ProBase\License\License;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */
$daysLeft = $tplData['schedule_disalbe_days_left'];

if ($daysLeft === false) {
    return;
}

if ($daysLeft >= 0) {
    ?>
    <u>
        <?php
        if (License::can(License::CAPABILITY_SCHEDULE)) {
            $message  = sprintf(
                _n(
                    'Scheduled Backups are going to be disabled <b><em>in %d day</em></b>.',
                    'Scheduled Backups are going to be disabled <b><em>in %d days</em></b>.',
                    $daysLeft,
                    'duplicator-pro'
                ),
                $daysLeft
            );
            $message .= __(' Please renew your license to assure your backups are not interrupted.', 'duplicator-pro');
            echo wp_kses(
                $message,
                [
                    'b'  => [],
                    'em' => [],
                ]
            );
        } else {
            esc_html_e(
                'All automatic backups have been disabeld. Please renew your license to re-enable them.',
                'duplicator-pro'
            );
        }
        ?>
    </u>
    <?php
} else {
    esc_html_e(
        'Scheduled Backups.',
        'duplicator-pro'
    );
}
