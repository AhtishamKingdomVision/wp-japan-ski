<?php

/**
 * Duplicator Backup row in table Backups list
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Package\Recovery\RecoveryStatus;
use Duplicator\Views\ViewHelper;

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

$tooltipContent = __('A Backup Recovery Point status is not required to be enabled and in some cases is desirable.', 'duplicator-pro') . ' ' .
    __('For example you may want to backup only your database.', 'duplicator-pro') . ' ' .
    __(
        'In this case you can still run a database only install, however the ability to use the recovery point installer will be unavailable.',
        'duplicator-pro'
    );
?>

<h3 class="dup-title margin-top-0">
    <?php ViewHelper::disasterIcon(true, 'maroon'); ?>&nbsp;
    <?php esc_html_e('Disaster Recovery - Isn\'t Available for this Backup', 'duplicator-pro'); ?>
    <sup>
        <i class="fas fa-question-circle fa-xs"
            data-tooltip-title="<?php esc_attr_e('Recovery', 'duplicator-pro'); ?>"
            data-tooltip="<?php echo esc_attr($tooltipContent); ?>">
        </i>
    </sup>
</h3>

<?php $tplMng->render('parts/recovery/package_info_mini'); ?>
<hr class="margin-top-1 margin-bottom-1">

<?php
$recoverStatus = new RecoveryStatus($package);
$tplMng->render('parts/recovery/exclude_data_box', ['recoverStatus' => $recoverStatus]);
