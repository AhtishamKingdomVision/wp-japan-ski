<?php

/**
 * @package Duplicator
 */

use Duplicator\Controllers\PackagesPageController;
use Duplicator\Core\CapMng;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 * @var Duplicator\Package\Recovery\RecoveryPackage $recoverPackage
 */
$recoverPackage = $tplData['recoverPackage'];
$packagesURL    = PackagesPageController::getInstance()->getPageUrl();
?>
<div class="dupli-recovery-point-selector">
    <?php if (empty($tplData['recoverablePackages'])) { ?>
        <div class="dupli-notice-details">
            <div class="margin-bottom-1" >
                <b><?php _e('Would you like to create a Recovery Point before running this import?', 'duplicator-pro'); ?></b>
            </div>
            <b><?php _e('How to create:', 'duplicator-pro'); ?></b>
            <ol class="dupli-simple-style-list" >
                <li>
                    <?php
                        printf(
                            esc_html_x(
                                'Open the %1$sBackups screen%2$s and create a valid recovery Backup.',
                                '1 and 2 are opening and closing anchor or link tags',
                                'duplicator-pro'
                            ),
                            '<a href="' . esc_url($packagesURL) . '" target="_blank">',
                            '</a><i class="fas fa-external-link-alt fa-small" ></i>'
                        );
                    ?>
                </li>
                <li>
                    <?php _e('On the Backups screen click the Backup\'s Hamburger menu and select "Set Recovery Point".', 'duplicator-pro'); ?>
                </li>
                <li>
                    <?php
                    printf(
                        esc_html_x(
                            '%1$sRefresh%2$s this page to show and choose the recovery point.',
                            '1 and 2 are opening and closing span tags',
                            'duplicator-pro'
                        ),
                        '<span class="dupli-recovery-windget-refresh link-style">',
                        '</span>'
                    );
                    ?>
                </li>
            </ol>
        </div>
    <?php } else {
        $tooltipContent = __(
            'A Recovery Point allows one to quickly restore the site to a prior state. 
            To use this, mark a Backup as the Recovery Point, then copy and save off the associated URL. 
            Then, if a problem occurs, browse to the URL to launch a streamlined installer to quickly restore the site.',
            'duplicator-pro'
        );
        ?>
        <div class="dupli-recovery-point-selector-area-wrapper" >
            <?php if (CapMng::can(CapMng::CAP_CREATE, false)) { ?>
                <span class="dupli-opening-packages-windows" >
                    <a href="<?php echo esc_url($packagesURL); ?>" >[<?php _e('Create New', 'duplicator-pro'); ?>]</a>
                </span> 
            <?php } ?>
            <label>
                <i class="fa-solid fa-question-circle fa-sm dark-gray-color"
                    data-tooltip-title="<?php esc_attr_e("Choose Recovery Point Archive", 'duplicator-pro'); ?>"
                    data-tooltip="<?php echo esc_attr($tooltipContent); ?>">
                </i>
                <b><?php _e('Step 1 ', 'duplicator-pro'); ?>:</b> <i><?php _e('Choose Recovery Point Archive', 'duplicator-pro'); ?></i>
            </label>
            <div class="dupli-recovery-point-selector-area">
                <select class="recovery-select" name="recovery_package" >
                    <option value=""> -- <?php _e('Not selected', 'duplicator-pro'); ?> -- </option>
                    <?php
                    $currentDay = null;
                    foreach ($tplData['recoverablePackages'] as $package) {
                        $packageDay = date("Y/m/d", strtotime($package['created']));
                        if ($packageDay != $currentDay) {
                            if (!is_null($currentDay)) {
                                ?>
                                </optgroup>
                            <?php } ?>
                            <optgroup label="<?php echo esc_attr($packageDay); ?>">
                                <?php
                                $currentDay = $packageDay;
                        }
                        ?>
                            <option value="<?php echo $package['id']; ?>" <?php selected($tplData['recoverPackageId'], $package['id']) ?>>
                                <?php echo '[' . $package['created'] . '] ' . $package['name']; ?>
                            </option>
                    <?php } ?>
                    </optgroup>
                </select>             
                <button type="button" class="button secondary hollow small recovery-reset" ><?php echo _e('Reset', 'duplicator-pro'); ?></button> 
                <button type="button" class="button primary small recovery-set" ><?php echo _e('Set', 'duplicator-pro'); ?></button>
            </div>
        </div>
    <?php } ?>
</div>
