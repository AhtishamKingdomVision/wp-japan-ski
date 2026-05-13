<?php

/**
 * @package Duplicator
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
<span class="dup-archive-filters-icons">
    <span id="dup-archive-filter-file-icon" title="<?php esc_attr_e('Folder/File Filters Enabled', 'duplicator-pro'); ?>">
        <i class="fa-solid fa-folder-minus fa-sm primary-color"></i>
    </span>
    <span id="dup-archive-filter-db-icon" title="<?php esc_attr_e('Database Filters Enabled', 'duplicator-pro'); ?>">
        <i class="fa-solid fa-table fa-sm primary-color"></i>
    </span>
    <span id="dup-archive-db-only-icon" title="<?php esc_attr_e('Backup Only the Database', 'duplicator-pro'); ?>">
        <i class="fa-solid fa-database fa-sm primary-color"></i>
    </span>
    <span id="dup-archive-media-only-icon" title="<?php esc_attr_e('Backup Only Media files', 'duplicator-pro'); ?>">
        <i class="fa-solid fa-file-image fa-sm primary-color"></i>
    </span>
    <span id="dupli-install-secure-lock-icon" title="<?php esc_attr_e('Backup Password Protection is On', 'duplicator-pro'); ?>" >
        <i class="fa-solid fa-lock fa-sm primary-color"></i>
    </span>
</span>