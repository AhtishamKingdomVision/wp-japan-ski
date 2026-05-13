<?php

/**
 * Duplicator Backup row in table Backups list
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Addons\ProBase\License\License;
use Duplicator\Controllers\PackagesPageController;
use Duplicator\Core\CapMng;
use Duplicator\Package\DupPackage;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 */

$tooltipTitle   = esc_attr__('Backup creation', 'duplicator-pro');
$tooltipContent = esc_attr__(
    'This will create a new Backup. If a Backup is currently running then this button will be disabled.',
    'duplicator-pro'
);
$disableCreate  = DupPackage::isPackageRunning() || DupPackage::isPackageCancelling();
?>
<div class="dup-section-package-create dup-flex-content">
    <span>
        <?php esc_html_e('Last backup:', 'duplicator-pro'); ?>
        <span class="dup-last-backup-info">
            <?php
            echo wp_kses(
                $tplData['lastBackupString'],
                [
                    'b'    => [],
                    'span' => [
                        'class' => [],
                    ],
                ]
            );
            ?>
        </span>
    </span>
    <?php if (CapMng::can(CapMng::CAP_CREATE, false) && (!is_multisite() || License::can(License::CAPABILITY_MULTISITE))) { ?>
        <span
            class="dup-new-package-wrapper"
            data-tooltip-title="<?php echo esc_attr($tooltipTitle); ?>"
            data-tooltip="<?php echo esc_attr($tooltipContent); ?>">
            <a
                id="dupli-create-new"
                class="button button-primary <?php echo $disableCreate ? 'disabled' : ''; ?>"
                href="<?php echo esc_url(PackagesPageController::getInstance()->getPackageBuildS1Url()); ?>">
                <?php esc_html_e('Create New', 'duplicator-pro'); ?>
            </a>
        </span>
    <?php } ?>
</div>