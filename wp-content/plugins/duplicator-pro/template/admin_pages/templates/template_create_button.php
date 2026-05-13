<?php

/**
 * Duplicator Backup row in table Backups list
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Controllers\ToolsPageController;
use Duplicator\Core\CapMng;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 */

if (CapMng::can(CapMng::CAP_CREATE, false)) {
    $edit_template_url =  $ctrlMng::getMenuLink(
        $ctrlMng::TOOLS_SUBMENU_SLUG,
        ToolsPageController::L2_SLUG_TEMPLATE,
        null,
        ['inner_page' => 'edit']
    );

    $tipContent = __(
        'Create a new Template.',
        'duplicator-pro'
    );
    ?>  
    <span
        data-tooltip="<?php echo esc_attr($tipContent); ?>"
    >
        <a  
            href="<?php echo esc_url($edit_template_url); ?>"
            id="dupli-create-new" 
            class="button primary small font-bold margin-0"
        >
            <?php esc_html_e('Add New', 'duplicator-pro'); ?>
        </a>
    </span>
    <?php
}