<?php

/**
 * @package Duplicator
 */

use Duplicator\Controllers\PackagesPageController;
use Duplicator\Core\CapMng;
use Duplicator\Package\AbstractPackage;
use Duplicator\Package\DupPackage;
use Duplicator\Package\Recovery\RecoveryPackage;
use Duplicator\Views\ViewHelper;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 * @var ?DupPackage $package
 */

$package = $tplData['package'];

$pack_dbonly       = $package->isDBOnly();
$pack_format       = strtolower($package->Archive->Format);
$packageDetailsURL = PackagesPageController::getInstance()->getPackageDetailsURL($package->getId());
$txt_DBOnly        = __('DB Only', 'duplicator-pro');
$archive_exists    = ($package->getLocalPackageFilePath(AbstractPackage::FILE_TYPE_ARCHIVE) != false);
$isRecoverable     = RecoveryPackage::isPackageIdRecoverable($package->getId());

?>
<td colspan="11">
    <div class="dup-package-row-details-wrapper">
        <div class="dup-ovr-hdr">
            <h3 class="font-bold">
                <i class="fas fa-archive"></i>
                <?php esc_html_e('Backup Overview', 'duplicator-pro'); ?>
            </h3>
        </div>

        <div class="dup-ovr-bar-flex-box">
            <div>
                <label><?php esc_html_e('WordPress', 'duplicator-pro'); ?></label>
                <?php echo esc_html($package->VersionWP); ?> &nbsp;
            </div>
            <div>
                <label><?php esc_html_e('Duplicator', 'duplicator-pro'); ?></label>
                <?php echo esc_html($package->getVersion()); ?> &nbsp;
            </div>
            <div>
                <label><?php esc_html_e('Format', 'duplicator-pro'); ?></label>
                <?php echo esc_html(strtoupper($pack_format)); ?>
            </div>
            <div>
                <label><?php esc_html_e('Files', 'duplicator-pro'); ?></label>
                <?php echo ($pack_dbonly)
                    ? '<i>' . esc_html($txt_DBOnly) . '</i>'
                    : number_format($package->Archive->FileCount); ?>
            </div>
            <div>
                <label><?php esc_html_e('Folders', 'duplicator-pro'); ?></label>
                <?php echo ($pack_dbonly)
                    ? '<i>' . esc_html($txt_DBOnly) . '</i>'
                    :  number_format($package->Archive->DirCount) ?>
            </div>
            <div>
                <label><?php esc_html_e('Tables', 'duplicator-pro'); ?></label>
                <?php
                printf(
                    esc_html_x(
                        '%1$d of %2$d',
                        'Example: 7 of 10',
                        'duplicator-pro'
                    ),
                    (int) $package->Database->info->tablesFinalCount,
                    (int) $package->Database->info->tablesBaseCount
                );
                ?>
            </div>
        </div>

        <div class="dup-ovr-ctrls-flex-box">
            <div class="flex-item">
                <?php
                if (CapMng::can(CapMng::CAP_EXPORT, false)) {
                    $tplMng->render('admin_pages/packages/row_parts/details_download_block');
                }
                ?>
            </div>

            <div class="flex-item dup-ovr-opts">
                <div class="dup-ovr-ctrls-hdrs">
                    <h3 class="font-bold margin-bottom-0">
                        <?php esc_html_e('Options', 'duplicator-pro'); ?>
                    </h3>
                    <small class="xsmall dark-gray-color">
                        <i><?php esc_html_e('Backup actions.', 'duplicator-pro'); ?></i>
                    </small>
                </div>
                <ul class="no-bullet">
                    <li>
                        <a
                            aria-label="<?php esc_attr_e("Go to Backup details screen", 'duplicator-pro') ?>"
                            class="button hollow secondary expanded dup-details"
                            href="<?php echo esc_url($packageDetailsURL); ?>">
                            <span><i class="fas fa-search"></i> <?php esc_html_e("View Details", 'duplicator-pro') ?></span>
                        </a>
                    </li>
                    <?php if (CapMng::can(CapMng::CAP_STORAGE, false)) { ?>
                        <li>
                            <?php if ($archive_exists) : ?>
                                <button class="button hollow secondary expanded dup-transfer"
                                    aria-label="<?php esc_attr_e('Go to Backup transfer screen', 'duplicator-pro') ?>"
                                    onclick="DupliJs.Pack.OpenPackTransfer(<?php echo (int) $package->getId(); ?>); return false;">
                                    <span><i class="fa fa-exchange-alt fa-fw"></i> <?php esc_html_e("Transfer Backup", 'duplicator-pro') ?></span>
                                </button>
                            <?php else : ?>
                                <span title="<?php esc_attr_e('Transfer Backups requires the use of built-in default storage!', 'duplicator-pro') ?>">
                                    <button class="button hollow secondary expanded disabled">
                                        <span><i class="fa fa-exchange-alt fa-fw"></i> <?php esc_html_e("Transfer Backup", 'duplicator-pro') ?></span>
                                    </button>
                                </span>
                            <?php endif; ?>
                        </li>
                    <?php } ?>

                    <?php if (CapMng::can(CapMng::CAP_BACKUP_RESTORE, false)) { ?>
                        <li>
                            <button
                                aria-label="<?php esc_attr_e("Recover this Backup", 'duplicator-pro') ?>"
                                class="button hollow secondary expanded dupli-btn-open-recovery-box <?php echo ($isRecoverable) ? '' : 'maroon' ?>"
                                data-package-id="<?php echo (int) $package->getId(); ?>">
                                <?php ViewHelper::disasterIcon(true); ?>
                                <?php esc_html_e("Disaster Recovery", 'duplicator-pro'); ?>
                            </button>
                        </li>
                    <?php } ?>
                </ul>
            </div>
        </div>
</td>
