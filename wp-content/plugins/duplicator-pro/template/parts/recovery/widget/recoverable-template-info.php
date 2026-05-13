<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

defined('ABSPATH') || defined('DUPXABSPATH') || exit;

use Duplicator\Models\ScheduleEntity;
use Duplicator\Models\TemplateEntity;
use Duplicator\Package\Recovery\RecoveryStatus;
use Duplicator\Views\UI\UiDialog;
use Duplicator\Views\ViewHelper;

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 */

$isList   = $tplMng->getDataValueBool('isList');
$template = $tplMng->getDataValueObj('template', TemplateEntity::class);
$schedule = $tplMng->getDataValueObj('schedule', ScheduleEntity::class);

if (isset($schedule)) {
    $recoveryStatus = new RecoveryStatus($schedule);
} else {
    $recoveryStatus = new RecoveryStatus($template);
}

$isRecoverable         = $recoveryStatus->isRecoverable();
$templareRecoveryAlter = new UiDialog();

if (!$isRecoverable) {
    $templareRecoveryAlter->title        = (
        isset($schedule) ?
        __('Schedule: Recovery Point', 'duplicator-pro') :
        __('Template: Recovery Point', 'duplicator-pro')
    );
    $templareRecoveryAlter->width        = 600;
    $templareRecoveryAlter->height       = 600;
    $templareRecoveryAlter->showButtons  = false;
    $templareRecoveryAlter->templatePath = 'parts/recovery/widget/template-filters-info';
    $templareRecoveryAlter->templateArgs = ['recoveryStatus' => $recoveryStatus];
    $templareRecoveryAlter->initAlert();
    ?>
    <script>
        jQuery(document).ready(function($) {
            $('#dup-template-recoveable-info-<?php echo (int) $templareRecoveryAlter->getUniqueIdCounter(); ?>').click(function() {
                <?php $templareRecoveryAlter->showAlert(); ?>
            });
        });
    </script>
    <?php
}
?>
<span class="dup-template-recoveable-info-wrapper">
    <?php
    if ($isRecoverable) {
        ?>
        <?php esc_html_e('Available', 'duplicator-pro'); ?>
        <sup><?php ViewHelper::disasterIcon(); ?></sup>
        <?php
    } else {
        ?>
        <a href="javascript:void(0)"
            id="dup-template-recoveable-info-<?php echo (int) $templareRecoveryAlter->getUniqueIdCounter(); ?>"
            class="dup-template-recoveable-info"><u><?php esc_html_e('Disabled', 'duplicator-pro'); ?></u></a>
        <?php
    }

    if (!$isList) {
        ?>
        &nbsp;
        <i class="fa-solid fa-question-circle fa-sm dark-gray-color"
            data-tooltip-title="<?php esc_attr_e("Recovery Status", 'duplicator-pro'); ?>"
            data-tooltip="<?php
            if (!isset($schedule)) {
                esc_html_e(
                    "The Recovery Status can be either 'Available' or 'Disabled'.
                    An 'Available' status allows the templates archive to be restored through the recovery point wizard.
                    A 'Disabled' status means the archive can still be used but just not ran as a rapid recovery point.",
                    'duplicator-pro'
                );
            } else {
                esc_html_e(
                    "The Recovery Status can be either 'Available' or 'Disabled'.
                    An 'Available' status allows the schedules archive to be restored through the recovery point wizard.
                    A 'Disabled' status means the archive can still be used but just not ran as a rapid recovery point.",
                    'duplicator-pro'
                );
            }
            ?>"></i>
    <?php } ?>
</span>
