<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

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
?>
<div class="dup-body-header">
    <h1><?php echo esc_html($pageTitle); ?></h1>
    <?php
    $tplMng->render('parts/tabs_menu_l2');

    if (strlen($templateSecondaryPart) > 0) {
        $tplMng->render($templateSecondaryPart, $templateSecondaryArgs);
    }
    ?>
</div>
<hr class="wp-header-end margin-top-0">
