<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Controllers\ToolsPageController;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\TemplateEntity;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var AbstractStorageEntity $storage
 */
$blur = $tplData['blur'];
/** @var TemplateEntity */
$template = $tplData['template'];

if (($templateList = TemplateEntity::getAll()) === false) {
    $templateList = [];
}
$tplOptGrouped = [
    'general'  => [
        'label'   => __("General Templates", 'duplicator-pro'),
        'options' => [],
    ],
    'existing' => [
        'label'   => __("Existing Templates", 'duplicator-pro'),
        'options' => [],
    ],
];
$countCopyList = 0;
foreach ($templateList as $copyTemplate) {
    if ($copyTemplate->getId() == $template->getId()) {
        continue;
    }
    if ($copyTemplate->is_manual) {
        $tplOptGrouped['general']['options'][$copyTemplate->getId()] = __("Active Build Settings", 'duplicator-pro');
    } elseif ($copyTemplate->is_default) {
        $tplOptGrouped['general']['options'][$copyTemplate->getId()] = $copyTemplate->name;
    } else {
        $tplOptGrouped['existing']['options'][$copyTemplate->getId()] = $copyTemplate->name;
    }
    $countCopyList++;
}

$templateListURL = ControllersManager::getMenuLink(
    ControllersManager::TOOLS_SUBMENU_SLUG,
    ToolsPageController::L2_SLUG_TEMPLATE
);

$edit_template_url =  ControllersManager::getMenuLink(
    ControllersManager::TOOLS_SUBMENU_SLUG,
    ToolsPageController::L2_SLUG_TEMPLATE,
    null,
    ['inner_page' => 'edit']
);

$template = $tplData['template'];
?>
<form id="dupli-template-toolbar-form" class="dup-monitored-form" action="<?php echo esc_url($edit_template_url); ?>" method="post">
    <?php $tplData['actions'][ToolsPageController::ACTION_COPY_TEMPLATE]->getActionNonceFileds(); ?>
    <input type="hidden" name="package_template_id" value="<?php echo intval($template->getId()); ?>">
    <div class="dup-toolbar <?php echo ($blur ? 'dup-mock-blur' : ''); ?>">
        <label for="dupli-source-template-id" class="screen-reader-text">Copy Template action</label>
        <select
            id="dupli-source-template-id"
            name="dupli-source-template-id"
            class="small"
            <?php disabled($countCopyList, 0); ?>>
            <option value="-1" selected="selected" disabled="true">
                <?php esc_html_e('Copy From', 'duplicator-pro'); ?>
            </option>
            <?php foreach ($tplOptGrouped as $groupKey => $group) {
                if (empty($group['options'])) {
                    continue;
                }
                asort($group['options']);
                ?>
                <optgroup label="<?php echo esc_attr($group['label']); ?>">
                    <?php foreach ($group['options'] as $id => $val) { ?>
                        <option value="<?php echo (int) $id; ?>"><?php echo esc_html($val); ?></option>
                    <?php } ?>
                </optgroup>
            <?php } ?>
        </select>
        <input type="submit" class="button hollow secondary small action" value="<?php esc_html_e("Apply", 'duplicator-pro') ?>">
        <span class="separator"></span>
        <a
            href="<?php echo esc_url($templateListURL); ?>"
            class="button hollow secondary small dup-goto-templates-btn"
            title="<?php esc_attr_e('Back to Template list.', 'duplicator-pro'); ?>">
            <i class="far fa-clone"></i> <?php esc_html_e('Templates', 'duplicator-pro'); ?>
        </a>
    </div>
</form>
<hr class="dup-toolbar-divider" />