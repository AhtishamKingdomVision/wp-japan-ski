<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

defined("ABSPATH") or die("");

use Duplicator\Core\Views\TplMng;

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 */
?>
<div class="details-title">
    <i class="fas fa-tasks fa-sm fa-fw"></i> <?php esc_html_e("Setup", 'duplicator-pro'); ?>
    <div class="dup-more-details">
        <a href="site-health.php" target="_blank" title="<?php esc_attr_e('Site Health', 'duplicator-pro'); ?>">
            <i class="fas fa-file-medical-alt"></i>
        </a>
    </div>
</div>
<?php
TplMng::getInstance()->render('admin_pages/packages/scan/items/setup/system');
TplMng::getInstance()->render('admin_pages/packages/scan/items/setup/wordpress');
TplMng::getInstance()->render('admin_pages/packages/scan/items/setup/restore');
