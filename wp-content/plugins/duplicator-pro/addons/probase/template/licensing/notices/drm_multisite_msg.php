<?php

/**
 * @package Duplicator
 */

use Duplicator\Addons\ProBase\License\License;
use Duplicator\Core\CapMng;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

$upgradeUrl = License::getUpsellURL();
?>
<span class='dashicons dashicons-warning'></span>
<div class="dup-sub-content">
    <h3>
        <?php esc_html_e('Your license does not support multisite functionality', 'duplicator-pro'); ?>
    </h3>
    <p>
        <?php echo esc_html(
            sprintf(
                _x(
                    'By upgrading to the %1$s or %2$s plans you will unlock the ability to create 
                    backups and do advanced migrations on multi-site installations!',
                    '1: name of pro plan, 2: name of elite plan',
                    'duplicator-pro'
                ),
                License::getLicenseToString(License::TYPE_PRO),
                License::getLicenseToString(License::TYPE_ELITE)
            )
        ); ?>
    </p>
    <br>
    <?php if (CapMng::can(CapMng::CAP_LICENSE, false)) { ?>
        <a class="button primary small margin-bottom-0" target="_blank" href="<?php echo esc_url($upgradeUrl); ?>">
            <?php esc_html_e('Upgrade Now!', 'duplicator-pro'); ?>
        </a>
    <?php } else { ?>
        <?php
        echo '<b>' . esc_html__(
            'Please contact the Duplicator license manager to update it.',
            'duplicator-pro'
        ) . '</b>';
        ?>
    <?php } ?>
</div>