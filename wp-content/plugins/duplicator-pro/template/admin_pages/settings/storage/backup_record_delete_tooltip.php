<?php

/**
 * @package Duplicator
 */

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */
?>

<p>
<?php esc_html_e("This option determines the logic of backup record handling (in the \"Backups\" screen)
in case the archive file is deleted from a storage due to the \"Max Backups\" limit being reached.", 'duplicator-pro'); ?>
</p>
<ul>
    <li>
        <?php esc_html_e("The first option will delete the record after the backup archive is removed from ALL storages.", 'duplicator-pro'); ?>
    </li>
    <li>
        <?php esc_html_e(
            "The second option will delete the record when the backup archive is removed from the Default Local Storage 
            even if it still exists in other storages.",
            'duplicator-pro'
        ); ?>
    </li>
    <li>
        <?php esc_html_e("The third option will never delete the record.", 'duplicator-pro'); ?>
    </li>
</ul>
