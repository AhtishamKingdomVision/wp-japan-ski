<?php

/**
 * @package Duplicator
 */

use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Models\TemplateEntity;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var bool $isTemplateEdit
 */
$isTemplateEdit = $tplData['isTemplateEdit'];
/** @var ?TemplateEntity */
$template = ($tplData['template'] ?? null);

if ($isTemplateEdit && $template != null) {
    $componentsParams = [
        'archiveFilterOn'         => $template->archive_filter_on,
        'archiveFilterDirs'       => $template->archive_filter_dirs,
        'archiveFilterFiles'      => $template->archive_filter_files,
        'archiveFilterExtensions' => $template->archive_filter_exts,
        'components'              => $template->components,
    ];
} else {
    $componentsParams = [
        'archiveFilterOn'         => 0,
        'archiveFilterDirs'       => '',
        'archiveFilterFiles'      => '',
        'archiveFilterExtensions' => '',
        'components'              => [],
    ];
}
?>
<div class="filter-files-tab-content">
    <?php $tplMng->render('parts/packages/filters/package_components', $componentsParams); ?>
    <?php $tplMng->render('parts/packages/filters/section_filters_subtab_filters_db'); ?>
</div>