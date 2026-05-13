<?php

/**
 * Duplicator Backup row in table Backups list
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Controllers\PackagesPageController;
use Duplicator\Controllers\RecoveryController;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Core\Views\TplMng;
use Duplicator\Package\Recovery\RecoveryPackage;
use Duplicator\Views\ViewHelper;

/**
 * Variables
 *
 * @var ControllersManager $ctrlMng
 * @var TplMng $tplMng
 * @var array<string, mixed> $tplData
 * @var \Duplicator\Package\DupPackage $package
 */
$package        = $tplData['package'];
$isRecoverPoint = (RecoveryPackage::getRecoverPackageId() === $package->getId());

$colorClass = ($isRecoverPoint ? 'green' : '');
?>
<h3 class="dup-title margin-top-0">
    <?php ViewHelper::disasterIcon(true, $colorClass); ?>&nbsp;
    <?php
    if ($isRecoverPoint) {
        esc_html_e('Disaster Recovery - Is Set on this Backup', 'duplicator-pro');
    } else {
        esc_html_e('Disaster Recovery - Is Available for this Backup', 'duplicator-pro');
    }
    ?>
</h3>

<?php $tplMng->render('parts/recovery/package_info_mini'); ?>
<hr class="margin-top-1 margin-bottom-1">

<?php if ($isRecoverPoint) {
    RecoveryController::renderRecoveryWidged([
        'details'    => false,
        'selector'   => false,
        'subtitle'   => '',
        'copyLink'   => false,
        'copyButton' => true,
        'launch'     => false,
        'download'   => true,
        'info'       => true,
    ]);
} else { ?>
    <form method="post" action="<?php PackagesPageController::getInstance()->getPageUrl(); ?>">
        <?php PackagesPageController::getInstance()->getActionByKey(PackagesPageController::ACTION_SET_RECOVERY_POINT)->getActionNonceFileds(); ?>
        <input type="hidden" name="recovery_package" value="<?php echo (int) $package->getId(); ?>">
        <div class="dupli-recovery-widget-wrapper">
            <div class="dupli-recovery-point-actions">
                <div class="dupli-recovery-buttons">
                    <button
                        class="button primary dupli-btn-set-recovery margin-0"
                        type="submit">
                        <span><?php ViewHelper::disasterIcon(); ?>&nbsp;
                            <?php esc_html_e("Set Disaster Recovery", 'duplicator-pro'); ?></span>&nbsp;
                        <i
                            class="fa-solid fa-question-circle fa-sm dark-gray-color dup-base-color white"
                            data-tooltip-title="<?php esc_attr_e("Activate Recovery", 'duplicator-pro'); ?>"
                            data-tooltip="<?php esc_attr_e("This action will set this Backup as the active Disaster Recovery Backup.", 'duplicator-pro'); ?>"
                            aria-expanded="false">
                        </i>
                    </button>
                </div>
            </div>
        </div>
    </form>
<?php }
