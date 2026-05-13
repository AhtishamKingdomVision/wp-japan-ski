<?php

/**
 * Duplicator Backup row in table Backups list
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Controllers\RecoveryController;
use Duplicator\Controllers\StoragePageController;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Core\Views\TplMng;
use Duplicator\Package\Create\BuildComponents;
use Duplicator\Package\Recovery\RecoveryStatus;
use Duplicator\Views\ViewHelper;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var ControllersManager $ctrlMng
 * @var TplMng $tplMng
 * @var array<string, mixed> $tplData
 * @var RecoveryStatus $recoverStatus
 */

$recoverStatus = $tplData['recoverStatus'];
$filteredData  = $recoverStatus->getFilteredData();

$activeType  = $recoverStatus->getType();
$activeLabel = strtolower($recoverStatus->getTypeLabel());

$isLocalStorageSafe  = $recoverStatus->isLocalStorageEnabled();
$isWordPressCoreSafe = $recoverStatus->isWordPressCoreComplete();
$isDatabaseSafe      = $recoverStatus->isDatabaseComplete();
$isMultisiteComplete = $recoverStatus->isMultisiteComplete();

$editDefaultStorageURL = StoragePageController::getEditDefaultUrl();

//echo '<pre>';var_export($isWordPressCoreSafe); echo '</pre>';
//echo '<pre>';var_export($recoverStatus->activeTemplate); echo '</pre>';

/**
 * @var wpdb $wpdb
*/
global $wpdb;
?>
<div class="dup-recover-dlg-notice-box">
    <div class="title-area">
        <div class="title">
            <?php esc_html_e("REQUIREMENTS", 'duplicator-pro'); ?>:
        </div>
    </div>

    <!--  ===============
    LOCAL SERVER STORAGE -->
    <div class="req-data">
        <?php
        if ($activeType == $recoverStatus::TYPE_TEMPLATE) {
            echo '<i class="far fa-question-circle fa-fw pass"></i>';
        } else {
            echo $isLocalStorageSafe
                ? '<i class="far fa-check-circle fa-fw pass"></i>'
                : '<i class="far fa-times-circle fa-fw fail"></i>';
        }
        ?>
        <a class="req-title" href="javascript:void(0)" onclick="jQuery(this).parent().children('div.req-info').toggle();">
            <?php esc_html_e("Local Server Storage", 'duplicator-pro'); ?>
        </a>
        
        <div class="req-info">

            <i class="fas fa-server  fa-fw fa-lg"></i>
            <?php esc_html_e("Recovery points require one of the following 'Local Server' storage types:", 'duplicator-pro'); ?>
            <ul class="req-info-list">
                <li>
                    <?php
                        echo sprintf(
                            "<i class='far fa-hdd fa-fw'></i><sup>"
                            . wp_kses(ViewHelper::disasterIcon(false), ['i' => ['class' => []]])
                            . "</sup>&nbsp; "
                            . "<b><a href='" . esc_url($editDefaultStorageURL) . "' target='_blank'>%s</a></b> %s",
                            esc_html__('[Local Default]', 'duplicator-pro'),
                            esc_html__('This is the default built-in local storage type.', 'duplicator-pro')
                        );
                        ?>
                </li>
                <li>
                    <?php
                        echo sprintf(
                            "<i class='fas fa-hdd'></i><sup>"
                            . wp_kses(ViewHelper::disasterIcon(false), ['i' => ['class' => []]])
                            . "</sup>&nbsp; <b>%s</b> %s",
                            esc_html__('[Local Non-Default]', 'duplicator-pro'),
                            esc_html__('This is a custom directory on this server.', 'duplicator-pro')
                        );
                        ?>
                </li>
            </ul>

            <div class="req-status">
                <b><?php esc_html_e("STATUS", 'duplicator-pro'); ?>:</b><br/>
                <?php
                if ($activeType == $recoverStatus::TYPE_TEMPLATE) {
                    esc_html_e(
                        "Templates do not control storage locations, only schedules and new backup creation control this process.",
                        'duplicator-pro'
                    );
                    echo ' ';
                    esc_html_e('No changes can be made to affect this test.', 'duplicator-pro');
                } elseif ($isLocalStorageSafe) {
                    echo '<span class="darkgreen">';
                    echo esc_html__("At least one local server storage is associated with this ", 'duplicator-pro') . esc_html($activeLabel) . '.';
                    echo '</span>';
                } else {
                    echo '<span class="maroon">';
                    echo esc_html__("No local server storage found for this ", 'duplicator-pro') . esc_html($activeLabel) . '.';
                    echo '</span>';
                }
                ?>
            </div>
       </div>
    </div>

    <!--  ===============
    WordPress CORE -->
    <div class="req-data">
        <?php
           echo $isWordPressCoreSafe
                ? '<i class="far fa-check-circle fa-fw pass"></i>'
                : '<i class="far fa-times-circle fa-fw fail"></i>';
        ?>
        <a class="req-title" href="javascript:void(0)" onclick="jQuery(this).parent().children('div.req-info').toggle();">
            <?php esc_html_e("WordPress Core Folders", 'duplicator-pro'); ?>
        </a>
        <div class="req-info">
             <i class="fab fa-wordpress-simple fa-fw fa-lg"></i>
             <?php esc_html_e(
                 "A recovery point needs all WordPress core folders included in the backup (wp-admin, wp-content &amp; wp-includes).",
                 'duplicator-pro'
             ); ?>

             <div class="req-status">
                <b><?php esc_html_e("STATUS", 'duplicator-pro'); ?>:</b><br/>
                <?php if (($filteredData['dbonly'])) : ?>
                    <span class="maroon">
                        <?php esc_html_e(
                            "Backup is setup as a database only configuration, the core WordPress folders have been excluded automatically.",
                            'duplicator-pro'
                        ); ?>
                    </span>
                <?php elseif (count($filteredData['filterDirs']) > 0) : ?>
                    <span class="maroon">
                        <?php esc_html_e("Filtered out WordPress core folders.", 'duplicator-pro'); ?>
                        <?php foreach ($filteredData['filterDirs'] as $path) { ?>
                            <small class="req-paths-data"><?php echo esc_html($path); ?></small>
                        <?php } ?>
                    </span>
                <?php else : ?>
                    <span class="darkgreen">
                        <?php esc_html_e("No WordPress core folder filters set", 'duplicator-pro'); ?>.
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (is_multisite()) { ?>
        <!--  ===============
        Multisite complete-->
        <div class="req-data">
            <?php
            echo $isMultisiteComplete
                    ? '<i class="far fa-check-circle fa-fw pass"></i>'
                    : '<i class="far fa-times-circle fa-fw fail"></i>';
            ?>
            <a class="req-title" href="javascript:void(0)" onclick="jQuery(this).parent().children('div.req-info').toggle();">
                <?php esc_html_e("Multisite", 'duplicator-pro'); ?>
            </a>
            <div class="req-info">
                <?php esc_html_e(
                    'Some subsites are filterd.',
                    'duplicator-pro'
                ); ?>
            </div>
        </div>
    <?php } ?>

    <!--  ===============
    DATABASE TABLES -->
    <div class="req-data">
       <?php
          echo $isDatabaseSafe
               ? '<i class="far fa-check-circle fa-fw pass"></i>'
               : '<i class="far fa-times-circle fa-fw fail"></i>';
        ?>
       <a class="req-title" href="javascript:void(0)" onclick="jQuery(this).parent().children('div.req-info').toggle();">
           <?php esc_html_e("Primary Database Tables", 'duplicator-pro'); ?>
       </a>
       <div class="req-info">
           <i class="fas fa-database fa-fw fa-lg"></i>
            <?php
                printf(
                    esc_html_x(
                        'All database tables with the prefix %1$s must be included in the %2$s for this to be an eligible recovery point.',
                        '%1$s representes the database prefix and %2$s is one of the following: backup, schedule or template',
                        'duplicator-pro'
                    ),
                    '<i>' . esc_html($wpdb->prefix) . '</i>',
                    esc_html($activeLabel)
                );
                ?>
            <div class="req-status">
                <b><?php esc_html_e("STATUS", 'duplicator-pro'); ?>:</b><br/>
                <?php if (count($filteredData['filterTables']) > 0) : ?>
                    <?php esc_html_e("Filtered table list", 'duplicator-pro'); ?>:
                    <?php
                    foreach ($filteredData['filterTables'] as $table) {
                        if (strpos($table, $wpdb->prefix) !== false) {
                               echo '<small class="req-paths-data maroon">' . esc_html($table) . '</small>';
                        } else {
                                echo '<small class="req-paths-data darkgreen">' . esc_html($table) . '</small>';
                        }
                    }
                    ?>
                <?php else : ?>
                    <span class="darkgreen">
                        <?php esc_html_e("No table filters set on this backup.", 'duplicator-pro'); ?>
                    </span>
                <?php endif; ?>
            </div>
       </div>
    </div>

    <!--  ===============
    PACKAGE COMPONENTS-->
    <div class="req-data">
        <?php if ($recoverStatus->hasRequiredComponents()) { ?>
            <i class="far fa-check-circle fa-fw pass"></i>
        <?php } else { ?>
            <i class="far fa-times-circle fa-fw fail"></i>
        <?php } ?>
        <a class="req-title" href="javascript:void(0)" onclick="jQuery(this).parent().children('div.req-info').toggle();">
            <?php esc_html_e("Backup Components", 'duplicator-pro'); ?>
        </a>
        <div class="req-info">
            <b><?php esc_html_e('Required components:', 'duplicator-pro'); ?>:</b>   
            <ul class="dup-recovery-package-components-required">            
            <?php foreach (RecoveryStatus::COMPONENTS_REQUIRED as $component) { ?>
                <li>
                    <span class="label"><?php echo esc_html(BuildComponents::getLabel($component)); ?></span>
                    <span class="value">
                            <?php if ($recoverStatus->hasComponent($component)) { ?>
                                <i class="fas fa-check-circle green"></i> <?php  esc_html_e('included', 'duplicator-pro'); ?>
                            <?php } else { ?>
                                <i class="fas fa-times-circle maroon"></i> <?php  esc_html_e('excluded', 'duplicator-pro'); ?>
                            <?php } ?>
                    </span>
                </li>
            <?php } ?>
            </ul>
        </div>
    </div><br/>

    <div class="title-area">
        <div class="title">
            <?php esc_html_e("NOTES", 'duplicator-pro'); ?>:
        </div>
    </div>

    <div class="req-notes">
        <?php
        switch ($recoverStatus->getType()) {
            case $recoverStatus::TYPE_PACKAGE:
                esc_html_e(
                    'To create a recovery-point enabled backup change the conditions of the backup build or template to meet the requirements listed above.',
                    'duplicator-pro'
                );
                echo ' ';
                printf(
                    esc_html_x(
                        'Then use either the %1$sRecovery Point%2$s tool or the Recovery Point button to set which backup you would like as the active recovery-point.', // phpcs:ignore Generic.Files.LineLength
                        '%1$s and %2$s represents the opening and closing HTML tags for an anchor or link',
                        'duplicator-pro'
                    ),
                    '<a href="' . esc_url(RecoveryController::getRecoverPageLink()) . '" target="_blank" >',
                    '</a>'
                );
                break;

            case $recoverStatus::TYPE_SCHEDULE:
                esc_html_e(
                    'To change the recovery status visit the template link above and make sure that it passes the recovery status test.',
                    'duplicator-pro'
                );
                esc_html_e(
                    'If the local storage test does not pass check the schedule storage types and make sure the local server storage type is selected.',
                    'duplicator-pro'
                );
                esc_html_e(
                    'These steps are optional and only required if you want to enable this schedule as an active recovery point.',
                    'duplicator-pro'
                );
                break;

            case $recoverStatus::TYPE_TEMPLATE:
                esc_html_e(
                    'To change a template recovery point status to enabled, edit the template and make sure that it passes the recovery status test.',
                    'duplicator-pro'
                );
                break;
        }
        ?>
    </div>
</div>