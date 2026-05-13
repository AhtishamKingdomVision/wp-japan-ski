<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

defined("ABSPATH") or die("");

use Duplicator\Controllers\RecoveryController;
use Duplicator\Libs\Snap\SnapWP;
use Duplicator\Package\Recovery\RecoveryPackage;

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 */

$postTypeCount = SnapWP::getPostTypesCount();
if (
    ($recoverPackage = RecoveryPackage::getRecoverPackage()) == false ||
    $recoverPackage->isOutToDate()
) {
    $badgeClass =  'badge-warn';
    $badgeLabel = 'Notice';
} else {
    $badgeClass =  'badge-pass';
    $badgeLabel = 'Good';
}
?>
<div class="dupli-import-header" >
    <h2 class="title">
        <b>
            <?php printf(esc_html__("Step %s of 2: Confirmation", 'duplicator-pro'), '<span class="red">2</span>'); ?>
        </b>
    </h2>
</div>
<div class="dupli-recovery-details-max-width-wrapper" >
    <div class="dupli-import-box closable opened" >
        <div class="box-title" >
            <?php esc_html_e('Disaster Recovery', 'duplicator-pro'); ?>
            <div class="badge <?php echo esc_attr($badgeClass); ?> margin-right-1">
                <?php echo esc_html($badgeLabel); ?>
            </div>
        </div>
        <div class="box-content">
            <div  id="dupli-recovery-details-select-entry" class="dupli-recovery-info-set" >
                <?php
                RecoveryController::renderRecoveryWidged([
                    'selector'   => true,
                    'subtitle'   => '',
                    'copyLink'   => true,
                    'copyButton' => true,
                    'launch'     => false,
                    'download'   => true,
                    'info'       => true,
                ]);
                ?>
            </div>
            <hr>

            <div class="dupli-recovery-not-required">
                <i class="far fa-arrow-alt-circle-right"></i>
                <?php
                esc_html_e(
                    'The Recovery Point is not mandatory to perform an import. 
                    However, it can assist in restoring this site if there is a problem during install. 
                    If you have no need to recover this site then you can continue without creating the Recovery Point.',
                    'duplicator-pro'
                );
                ?>
            </div>

        </div>
    </div><br/>

    <div class="dupli-import-box closable opened" >
        <div class="box-title" >
            <?php esc_html_e('System Overview', 'duplicator-pro'); ?>
        </div>
        <div class="box-content">
            <div id="dupli-recovery-details-overview" >
                <div>
                    <?php esc_html_e("This site currently contains", 'duplicator-pro'); ?>:
                </div>

                <table class="margin-left-2" >
                    <?php foreach ($postTypeCount as $label => $count) { ?>
                        <tr>
                            <td><?php echo esc_html($label); ?></td>
                            <td class="text-right"><?php echo (int) $count; ?></td>
                        </tr>
                    <?php } ?>
                </table>
                <p>
                    <?php esc_html_e("This process will:", 'duplicator-pro') ?>
                </p>
                <ul>
                    <li>
                        <i class="far fa-check-circle"></i>&nbsp;
                        <?php esc_html_e("Launch the interactive installer wizard to install this new Backup.", 'duplicator-pro'); ?>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="dupli-import-confirm-buttons">
        <input 
            id="dupli-import-launch-installer-cancel" 
            type="button" class="button secondary hollow small recovery-reset" 
            value="<?php esc_attr_e('Cancel', 'duplicator-pro'); ?>"
        >&nbsp;
        <button 
            id="dupli-import-launch-installer-confirm" 
            type="button" 
            class="button primary small" 
            onclick="DupliJs.ImportManager.confirmLaunchInstaller();"
        >
            <i class="fa fa-bolt fa-sm"></i> <?php esc_html_e('Launch Installer', 'duplicator-pro'); ?>
        </button>
    </div>
</div>
