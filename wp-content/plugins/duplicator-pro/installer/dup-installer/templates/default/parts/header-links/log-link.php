<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

defined('ABSPATH') || defined('DUPXABSPATH') || exit;

$archiveConfig = DUPX_ArchiveConfig::getInstance();
?>
<span class="dup-log-header-link">
    <?php DUPX_View_Funcs::installerLogLink(); ?>
</span>
