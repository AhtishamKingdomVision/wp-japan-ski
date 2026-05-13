<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

defined('ABSPATH') || defined('DUPXABSPATH') || exit;

/**
 * Variables
 *
 *  @var string $paramView
 */

if (!isset($paramView)) {
    $paramView = '';
}

$archiveConfig = DUPX_ArchiveConfig::getInstance();
?>
<span class="dup-help-header-link">
    <?php DUPX_View_Funcs::helpLink($paramView, 'Help'); ?>
</span>
