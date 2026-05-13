<?php

/**
 * Storage authorization success message template
 */

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var string $storagePageUrl Storage page URL
 * @var string $storageName    Storage name
 * @var bool   $isSettingsPage Whether authorization was from Settings page
 */

$storagePageUrl = $tplMng->getDataValueString('storagePageUrl');
$storageName    = $tplMng->getDataValueString('storageName');
$isSettingsPage = $tplMng->getDataValueBool('isSettingsPage');

if ($isSettingsPage) {
    printf(
        esc_html__('Successfully connected to %1$s! You can manage/edit it from the %2$sStorage page%3$s.', 'duplicator-pro'),
        '<strong>' . esc_html($storageName) . '</strong>',
        '<a href="' . esc_url($storagePageUrl) . '">',
        '</a>'
    );
} else {
    printf(
        esc_html__('Successfully connected to %s!', 'duplicator-pro'),
        '<strong>' . esc_html($storageName) . '</strong>'
    );
}
