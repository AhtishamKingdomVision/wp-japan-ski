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
 * @var \Duplicator\Package\DupPackage $package
 */
$package = $tplData['package'];

$timeDiff = sprintf(
    _x('%s ago', '%s represents the time diff, eg. 2 days', 'duplicator-pro'),
    $package->getPackageLife('human')
);

?>
<table>
    <tr>
        <td><b><?php esc_html_e('Backup', 'duplicator-pro'); ?>:</b></td>
        <td><?php echo esc_html($package->getName()); ?></td>
    </tr>
    <tr>
        <td><b><?php esc_html_e('Created', 'duplicator-pro'); ?>:</b>&nbsp; </td>
        <td>
            <?php echo esc_html($package->getCreated()); ?>&nbsp;-&nbsp;<i><?php echo esc_html($timeDiff); ?></i>
        </td>
    </tr>
</table>