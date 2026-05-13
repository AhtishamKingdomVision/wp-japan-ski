<?php

/**
 * Duplicator Backup row in table Backups list
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
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
<?php echo esc_html(
    sprintf(
        _x(
            'There are currently (%1$s) orphaned Backup files taking up %2$s of space. 
            These Backup files are no longer visible in the backups list below and are safe to remove.',
            '%1$s is the number of orphaned packages, %2$s is the total size of orphaned packages',
            'duplicator-pro'
        ),
        $tplData['count'],
        $tplData['size']
    )
); ?>
<br>
<?php esc_html_e(
    'Go to: Tools > General > Information > Stored Data > look for the [Delete Backups Orphans] button for more details.',
    'duplicator-pro'
); ?>
<br>
<a href="<?php echo esc_url($tplData['url']); ?>">
    <?php esc_html_e('Take me there now!', 'duplicator-pro'); ?>
</a>
