<?php

/**
 * @package Duplicator
 */

use Duplicator\Package\Recovery\RecoveryPackage;
use Duplicator\Views\ViewHelper;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 * @var ?Duplicator\Package\Recovery\RecoveryPackage $recoverPackage
 */

$recoverPackage = $tplData['recoverPackage'];
$installerLink  = ($recoverPackage instanceof RecoveryPackage) ? $recoverPackage->getInstallLink() : '';
$disabledClass  = empty($installerLink) ? 'disabled' : '';

if ($tplData['displayCopyLink']) {
    $toolTipContent  = __(
        'The recovery point URL is the link to the recovery point Backup installer. 
        The link will run the installer wizard used to re-install and recover the site. 
        Copy this link and keep it in a safe location to easily restore this site.',
        'duplicator-pro'
    );
    $toolTipContent .= '<br><br><b>';
    $toolTipContent .= __('This URL is valid until another recovery point is set.', 'duplicator-pro');
    $toolTipContent .= '</b>';
    ?>
    <label>
        <i class="fa-solid fa-question-circle fa-sm dark-gray-color"
            data-tooltip-title="<?php esc_attr_e("Recovery Point URL", 'duplicator-pro'); ?>"
            data-tooltip="<?php echo esc_attr($toolTipContent); ?>"
        >
        </i> 
        <b><?php _e('Step 2 ', 'duplicator-pro'); ?>:</b> <i><?php _e('Copy Recovery URL &amp; Store in Safe Place', 'duplicator-pro'); ?></i>
    </label>
    <div class="copy-link <?php echo $disabledClass; ?>"
         data-dup-copy-value="<?php echo esc_url($installerLink); ?>"
         data-dup-copy-title="<?php _e("Copy Recovery URL to clipboard", 'duplicator-pro'); ?>"
         data-dup-copied-title="<?php _e("Recovery URL copied to clipboard", 'duplicator-pro'); ?>" >
        <div class="content" >
            <?php echo empty($installerLink) ? __('Please set the Recovery Point to generate the Recovery URL', 'duplicator-pro') : $installerLink; ?>
        </div>
        <i class="far fa-copy copy-icon"></i>
    </div>
<?php } ?>
<div class="dupli-recovery-buttons" >
    <?php
    if ($tplData['displayLaunch']) { ?>
        <a href="<?php echo esc_url($installerLink); ?>"
           class="button primary hollow dupli-launch small <?php echo $disabledClass; ?>" target="_blank"
           title="<?php _e('Initiates system recovery using the Recovery Point URL.', 'duplicator-pro'); ?>" 
        >
            <?php ViewHelper::restoreIcon(); ?>&nbsp;<?php _e('Restore Backup', 'duplicator-pro'); ?>
        </a>
        <?php
    }
    if ($tplData['displayDownload']) {
        $title = __(
            'This button downloads a recovery launcher that allows you to perform the recovery with a simple click of the downloaded file.',
            'duplicator-pro'
        );
        ?>
        <button 
            type="button" 
            class="button primary hollow small dupli-recovery-download-launcher <?php echo $disabledClass; ?>" 
            title="<?php echo esc_attr($title); ?>"
        >
            <i class="fa fa-rocket" ></i>&nbsp;<?php _e('Download Launcher', 'duplicator-pro'); ?>
        </button>
        <?php
    }
    if ($tplData['displayCopyButton']) {
        ?>
        <button type="button" class="button primary small hollow dupli-recovery-copy-url <?php echo $disabledClass; ?>" 
                data-dup-copy-value="<?php echo $installerLink; ?>"
                data-dup-copy-title="<?php _e("Copy Recovery URL to clipboard", 'duplicator-pro'); ?>"
                data-dup-copied-title="<?php _e("Recovery URL copied to clipboard", 'duplicator-pro'); ?>" >
            <i class="far fa-copy copy-icon"></i>&nbsp;<?php _e('Copy LINK', 'duplicator-pro'); ?>
        </button>
        <?php
    }
    ?>
</div>
