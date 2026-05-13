<?php

use Duplicator\Controllers\ToolsPageController;
use Duplicator\Core\Views\TplMng;
use Duplicator\Models\ScheduleEntity;
use Duplicator\Models\TemplateEntity;
use Duplicator\Package\Recovery\RecoveryStatus;
use Duplicator\Views\ViewHelper;

defined('ABSPATH') || defined('DUPXABSPATH') || exit;


/**
 * Variables
 *
 * @var array<string, mixed> $tplData
 * @var RecoveryStatus $recoveryStatus
 */
$recoveryStatus = $tplData['recoveryStatus'];

if ($recoveryStatus->getType() == RecoveryStatus::TYPE_SCHEDULE) {
    /** @var ScheduleEntity */
    $schedule = $recoveryStatus->getObject();
    if (($template       = $schedule->getTemplate()) === false) {
        $template       = new TemplateEntity();
        $template->name = __('Template not found', 'duplicator-pro');
    }
    $tooltipContent = esc_attr__(
        'A Schedule is not required to have a recovery point. For example if a schedule is backing up
        only a database then the recovery will always be disabled and may be desirable.',
        'duplicator-pro'
    );
} else {
    $schedule = null;
    /** @var TemplateEntity */
    $template       = $recoveryStatus->getObject();
    $tooltipContent = __(
        'A Template is not required to have a recovery point. For example if backing up only a database 
        then the recovery will always be disabled and may be desirable.',
        'duplicator-pro'
    );
}
?>
<div class="dup-recover-dlg-title">
    <b><?php ViewHelper::disasterIcon() ?>&nbsp;<?php esc_html_e('Status', 'duplicator-pro'); ?>: </b>
    <?php esc_html_e('Disabled', 'duplicator-pro'); ?>
    <sup>
        <i class="fas fa-question-circle fa-xs"
            data-tooltip-title="<?php esc_html_e('Recovery Status', 'duplicator-pro'); ?>"
            data-tooltip="<?php echo esc_attr($tooltipContent); ?>">
        </i>
    </sup>
</div>

<div class="dup-recover-dlg-subinfo">
    <table>
        <?php if ($recoveryStatus->getType() == RecoveryStatus::TYPE_SCHEDULE) { ?>
            <tr>
                <td><b><?php esc_html_e("Schedule", 'duplicator-pro'); ?>:</b></td>
                <td> <?php echo esc_html($schedule->name); ?></td>
            </tr>
            <tr>
                <td> <b><?php esc_html_e("Template", 'duplicator-pro'); ?>:</b></td>
                <td>
                    <a href="<?php echo esc_url(ToolsPageController::getTemplateEditURL($template->getId())); ?>">
                        <?php echo esc_html($template->name); ?>
                    </a>
                </td>
            </tr>
        <?php } else { ?>
            <tr>
                <td> <b><?php esc_html_e("Template", 'duplicator-pro'); ?>:</b> </td>
                <td><?php echo esc_html($template->name); ?></td>
            </tr>
            <tr>
                <td><b><?php esc_html_e('Notes', 'duplicator-pro'); ?>:</b>&nbsp; </td>
                <td><?php echo (strlen($template->notes))  ? esc_html($template->notes) : esc_html__("- no notes -", 'duplicator-pro'); ?></td>
            </tr>
        <?php } ?>
    </table>
</div>
<?php
TplMng::getInstance()->render(
    'parts/recovery/exclude_data_box',
    ['recoverStatus' => $recoveryStatus]
);
