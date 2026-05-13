<?php

/**
 * Duplicator Backup row in table Backups list
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Controllers\ImportPageController;
use Duplicator\Controllers\SettingsPageController;
use Duplicator\Controllers\ToolsPageController;
use Duplicator\Core\CapMng;
use Duplicator\Package\Recovery\RecoveryPackage;
use Duplicator\Views\ViewHelper;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

$settingsUrl = esc_url($ctrlMng->getMenuLink($ctrlMng::SETTINGS_SUBMENU_SLUG, SettingsPageController::L2_SLUG_PACKAGE));
$templateUrl = esc_url($ctrlMng->getMenuLink($ctrlMng::TOOLS_SUBMENU_SLUG, ToolsPageController::L2_SLUG_TEMPLATE));
$recoveryUrl = esc_url($ctrlMng->getMenuLink($ctrlMng::TOOLS_SUBMENU_SLUG, ToolsPageController::L2_SLUG_RECOVERY));
?>
<div class="dup-toolbar">
    <label for="dup-pack-bulk-actions" class="screen-reader-text">Select bulk action</label>
    <select id="dup-pack-bulk-actions" class="small" >
        <option value="-1" selected="selected">
            <?php esc_html_e("Bulk Actions", 'duplicator-pro') ?>
        </option>
        <?php if (CapMng::can(CapMng::CAP_CREATE, false)) { ?>
        <option value="delete" title="<?php esc_attr_e("Delete selected Backup(s)", 'duplicator-pro') ?>">
            <?php esc_html_e("Delete", 'duplicator-pro') ?>
        </option>
        <?php } ?>
    </select>
    <input 
        type="button"
        id="dup-pack-bulk-apply" 
        class="button hollow secondary small"
        value="<?php esc_attr_e("Apply", 'duplicator-pro') ?>"
        onclick="DupliJs.Pack.ConfirmDelete()" 
    >

    <span class="separator"></span>

    <?php if (CapMng::can(CapMng::CAP_SETTINGS, false)) { ?>
    <a href="<?php echo esc_url($settingsUrl); ?>"
        class="button hollow secondary small dupli-toolbar-settings"
        data-tooltip-title="<?php esc_attr_e("Backup Settings", 'duplicator-pro'); ?>" 
        data-tooltip="<?php esc_attr_e("Advanced settings for backups.", 'duplicator-pro'); ?>"
    >
        <i class="fas fa-sliders-h fa-fw"></i>
    </a>
    <?php } ?>
    <?php if (CapMng::can(CapMng::CAP_CREATE, false)) { ?>
    <a href="<?php echo esc_url($templateUrl); ?>" 
        class="button hollow secondary small dupli-toolbar-templates"
        data-tooltip-title="<?php esc_attr_e("Templates", 'duplicator-pro'); ?>" 
        data-tooltip="<?php esc_attr_e("Create Backup Templates with Preset Configurations.", 'duplicator-pro'); ?>"
    >
        <i class="far fa-clone fa-fw"></i>
    </a>
    <?php } ?>

    <?php if (CapMng::can(CapMng::CAP_IMPORT, false)) { ?>
    <a href="<?php echo esc_url(ImportPageController::getImportPageLink()); ?>" 
        id="btn-logs-dialog"
        class="button hollow secondary small dupli-toolbar-import" 
        data-tooltip-title="<?php esc_attr_e("Import", 'duplicator-pro'); ?>" 
        data-tooltip="<?php esc_attr_e("Import a backups from a file or link.", 'duplicator-pro'); ?>"
    >
        <i class="fas fa-arrow-alt-circle-down fa-fw"></i>
    </a>
    <?php } ?>
    <?php if (CapMng::can(CapMng::CAP_BACKUP_RESTORE, false)) { ?>
    <span 
        class="dupli-toolbar-recovery-info 
        button hollow secondary small <?php echo (RecoveryPackage::getRecoverPackageId() === false ? 'dup-recovery-unset' : ''); ?>"
        data-tooltip-title="<?php esc_attr_e("Disaster Recovery", 'duplicator-pro') ?>" 
        data-tooltip="<?php esc_attr_e("Quickly restore this site to a specific point in time.", 'duplicator-pro') ?>"  
    >
        <?php ViewHelper::disasterIcon(); ?> 
    </span>
    <?php } ?>
</div>
