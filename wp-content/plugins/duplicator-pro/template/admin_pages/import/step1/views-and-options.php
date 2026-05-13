<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Controllers\SettingsPageController;
use Duplicator\Core\CapMng;
use Duplicator\Core\Controllers\ControllersManager;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 */

$importSettingsUrl = $ctrlMng->getMenuLink(
    ControllersManager::SETTINGS_SUBMENU_SLUG,
    SettingsPageController::L2_SLUG_IMPORT
);

?>
<div id="dupli-import-vews-and-opt-wrapper" >
    <ul class="dupli-toggle-sub-menu">
        <li>
            <span class="dupli-toggle" >
                <span class="button" >
                    <i class="fas fa-bars fa-lg"></i><span class="screen-reader-text">Options</span>
                </span>
            </span>
            <ul>
                <li class="title"><?php esc_html_e('VIEWS', 'duplicator-pro'); ?></li>
                <li>
                    <span class="link-style no-decoration dupli-import-view-list <?php echo $tplData['viewMode'] == 'list' ? 'active' : ''; ?>">
                        <?php esc_html_e('Advanced mode', 'duplicator-pro'); ?>
                        <span class="description"><?php esc_html_e('View multiple Backups', 'duplicator-pro') ?></span>
                    </span>
                </li>
                <li>
                    <span class="link-style no-decoration dupli-import-view-single <?php echo $tplData['viewMode'] == 'single' ? 'active' : ''; ?>">
                        <?php esc_html_e('Basic mode', 'duplicator-pro') ?>
                        <span class="description"><?php esc_html_e('View last uploaded Backup', 'duplicator-pro'); ?></span>
                    </span>
                </li>
                <li class="title separator"><?php esc_html_e('TOOLS', 'duplicator-pro'); ?></li>
                <?php if (CapMng::can(CapMng::CAP_SETTINGS, false)) { ?>
                    <li>
                        <a class="no-decoration" href="<?php echo esc_url($importSettingsUrl); ?>" target="_blank">
                            <?php esc_html_e('Import Settings', 'duplicator-pro'); ?>
                        </a>&nbsp;
                        <i class="fas fa-external-link-alt fa-small" ></i>
                    </li>
                <?php } ?>
                <li>
                    <span class="link-style no-decoration dupli-open-help-link">
                        <a href="<?php echo esc_url(DUPLICATOR_DUPLICATOR_DOCS_URL . 'import-install'); ?>" target="_blank">
                            <?php esc_html_e('Quick Start', 'duplicator-pro'); ?>
                        </a>&nbsp;
                        <i class="fas fa-external-link-alt fa-small" ></i>
                    </span>
                </li>
            </ul>
        </li>
    </ul>
</div>
