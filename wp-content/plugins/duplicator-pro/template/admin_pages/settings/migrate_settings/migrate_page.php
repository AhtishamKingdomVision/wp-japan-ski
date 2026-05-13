<?php

/**
 * @package Duplicator
 */

defined("ABSPATH") or die("");

use Duplicator\Addons\ProBase\License\License;

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

/* FOR PERSONAL LICENSE JUST SHOW MESSAGE */
if (!License::can(License::CAPABILITY_IMPORT_SETTINGS)) {
    $tplMng->render('admin_pages/settings/migrate_settings/no_capatibily');
    return;
}
?>

<?php $tplMng->render('admin_pages/settings/migrate_settings/export'); ?>
<hr size="1" />
<?php $tplMng->render('admin_pages/settings/migrate_settings/import'); ?>

<?php add_thickbox();

