<?php

/**
 * Duplicator Backup row in table Backups list
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Controllers\SettingsPageController;
use Duplicator\Core\Controllers\ControllersManager;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 */

$brandNewUrl = ControllersManager::getCurrentLink(
    [
        ControllersManager::QUERY_STRING_INNER_PAGE => SettingsPageController::BRAND_INNER_PAGE_EDIT,
        'action'                                    => 'new',
    ]
);

$tipContent = __(
    'Create a new Brand.',
    'duplicator-pro'
);
?>  
<span
    data-tooltip="<?php echo esc_attr($tipContent); ?>"
>
    <a  
        href="<?php echo esc_url($brandNewUrl); ?>"
        id="dupli-create-new" 
        class="button primary small font-bold margin-0"
    >
        <?php esc_html_e('Add New', 'duplicator-pro'); ?>
    </a>
</span>
