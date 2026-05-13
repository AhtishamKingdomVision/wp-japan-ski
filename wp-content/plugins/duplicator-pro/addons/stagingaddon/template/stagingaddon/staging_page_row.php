<?php

/**
 * Staging page table row partial
 */

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

use Duplicator\Addons\StagingAddon\Controllers\StagingPageController;
use Duplicator\Addons\StagingAddon\Models\StagingEntity;
use Duplicator\Controllers\PackagesPageController;
use Duplicator\Package\DupPackage;

/** @var \Duplicator\Addons\StagingAddon\Models\StagingEntity $staging */
$staging = $tplData['staging'];
/** @var int $index */
$index = $tplData['index'];

$rowClasses = ['staging-row'];
if ($index % 2) {
    $rowClasses[] = 'alternate';
}
if ($staging->getStatus() === StagingEntity::STATUS_FAILED) {
    $rowClasses[] = 'staging-failed';
}

$statusDisplay = StagingPageController::getStatusDisplay($staging->getStatus());
$backupExists  = DupPackage::getById($staging->getBackupId()) !== false;
?>
<tr id="dupli-staging-row-<?php echo (int) $staging->getId(); ?>"
    data-staging-id="<?php echo esc_attr((string) $staging->getId()); ?>"
    class="<?php echo esc_attr(implode(' ', $rowClasses)); ?>">
    <td class="dup-check-column dup-cell-chk">
        <label for="<?php echo (int) $staging->getId(); ?>">
            <input
                name="delete_confirm"
                type="checkbox"
                id="<?php echo (int) $staging->getId(); ?>"
                data-staging-title="<?php echo esc_attr($staging->getTitle()); ?>" />
        </label>
    </td>
    <td class="dup-name-column">
        <?php if ($staging->isReady()) : ?>
            <a href="<?php echo esc_url($staging->getUrl()); ?>" target="_blank">
                <strong><?php echo esc_html($staging->getTitle()); ?></strong>
            </a>
        <?php else : ?>
            <strong><?php echo esc_html($staging->getTitle()); ?></strong>
        <?php endif; ?>
    </td>
    <td>
        <?php
        echo wp_kses($statusDisplay['icon'], ['i' => ['class' => []]]);
        echo '&nbsp;';
        echo esc_html($statusDisplay['label']);
        ?>
    </td>
    <td>
        <?php if ($backupExists) :
            $backupUrl = PackagesPageController::getInstance()->getPackageDetailsUrl($staging->getBackupId());
            ?>
            <a href="<?php echo esc_url($backupUrl); ?>" title="<?php esc_attr_e('View backup details', 'duplicator-pro'); ?>">
                <?php echo esc_html($staging->getBackupName()); ?>
            </a>
        <?php else : ?>
            <span class="dupli-backup-deleted" title="<?php esc_attr_e('Backup no longer exists', 'duplicator-pro'); ?>">
                <?php echo esc_html($staging->getBackupName()); ?>
            </span>
        <?php endif; ?>
    </td>
    <td class="dupli-versions-column">
        <span title="<?php esc_attr_e('WordPress Version', 'duplicator-pro'); ?>">
            <i class="fab fa-wordpress fa-fw"></i>
            <?php echo esc_html($staging->getWpVersion() ?: '—'); ?>
        </span>
        <br>
        <span title="<?php esc_attr_e('Duplicator Version', 'duplicator-pro'); ?>">
            <i class="fas fa-clone fa-fw"></i>
            <?php echo esc_html($staging->getDupVersion() ?: '—'); ?>
        </span>
    </td>
    <td>
        <?php echo esc_html($staging->getCreatedFormatted()); ?>
    </td>
    <td class="dup-cell-btns dupli-open-admin-column">
        <?php if ($staging->isReady()) : ?>
            <a href="<?php echo esc_url($staging->getAdminUrl()); ?>"
               class="full-cell-button link-style"
               target="_blank"
               title="<?php esc_attr_e('Open Admin', 'duplicator-pro'); ?>">
                <i class="fas fa-external-link-alt fa-fw"></i> <?php esc_html_e('Open Admin', 'duplicator-pro'); ?>
            </a>
        <?php elseif ($staging->isPendingInstall()) : ?>
            <a href="<?php echo esc_url(StagingPageController::getInstallerPageLink($staging->getId())); ?>"
               class="full-cell-button link-style"
               target="_blank"
               title="<?php esc_attr_e('Finish Setup', 'duplicator-pro'); ?>">
                <i class="fas fa-play-circle fa-fw"></i> <?php esc_html_e('Finish Setup', 'duplicator-pro'); ?>
            </a>
        <?php endif; ?>
    </td>
    <td class="dup-cell-btns dupli-delete-column">
        <button type="button"
                class="full-cell-button link-style"
                onclick="DupliJs.Staging.deleteSingle(<?php echo (int) $staging->getId(); ?>);"
                title="<?php esc_attr_e('Delete', 'duplicator-pro'); ?>">
            <i class="fas fa-trash-alt fa-fw"></i> <?php esc_html_e('Delete', 'duplicator-pro'); ?>
        </button>
    </td>
</tr>
