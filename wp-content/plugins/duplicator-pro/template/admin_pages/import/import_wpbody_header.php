<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Controllers\SettingsPageController;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Core\Controllers\SubMenuItem;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string,mixed> $tplData
 * @var string $pageTitle
 */
$pageTitle = $tplData['pageTitle'];
/** @var string */
$templateSecondaryPart = ($tplData['templateSecondaryPart'] ?? '');
/** @var array<string,mixed> */
$templateSecondaryArgs = ($tplData['templateSecondaryArgs'] ?? []);
/** @var bool $blur */
$blur = $tplData['blur'];

$importSettingsUrl = $ctrlMng->getMenuLink(
    ControllersManager::SETTINGS_SUBMENU_SLUG,
    SettingsPageController::L2_SLUG_IMPORT
);

$items            = [];
$item             = new SubMenuItem(
    'mode-upload-tab',
    __('Import File', 'duplicator-pro'),
    '',
    true
);
$item->link       = '';
$item->active     = true;
$item->attributes = ['data-tab-target' => "dupli-import-upload-file-tab"];
$items[]          = $item;

$item             = new SubMenuItem(
    'mode-remote-tab',
    __('Import Link', 'duplicator-pro'),
    '',
    true
);
$item->link       = '';
$item->active     = false;
$item->attributes = ['data-tab-target' => "dupli-import-remote-file-tab"];
$items[]          = $item;

$item         = new SubMenuItem(
    'import-settings',
    __('Settings', 'duplicator-pro'),
    '',
    true
);
$item->link   = $importSettingsUrl;
$item->active = false;
$items[]      = $item;
?>

<div class="dup-body-header">
    <h1><?php echo esc_html($pageTitle); ?></h1>
    <?php
    if (!$blur) {
        $tplMng->render('parts/tabs_menu_l2', ['menuItemsL2' => $items]);
    }
    if (strlen($templateSecondaryPart) > 0) {
        $tplMng->render($templateSecondaryPart, $templateSecondaryArgs);
    }
    ?>
</div>
<hr class="wp-header-end margin-top-0">
