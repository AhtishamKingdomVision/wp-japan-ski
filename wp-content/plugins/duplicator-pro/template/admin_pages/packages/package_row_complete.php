<?php

/**
 * @package Duplicator
 */

use Duplicator\Controllers\PackagesPageController;
use Duplicator\Libs\Snap\SnapString;
use Duplicator\Package\AbstractPackage;
use Duplicator\Package\PackageUtils;
use Duplicator\Package\Recovery\RecoveryPackage;
use Duplicator\Views\UserUIOptions;
use Duplicator\Models\GlobalEntity;
use Duplicator\Package\DupPackage;

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

/** @var int */
$status = $tplData['status'];

if ($status < AbstractPackage::STATUS_COMPLETE) {
    return;
}

$global            = GlobalEntity::getInstance();
$isRecoverable     = RecoveryPackage::isPackageIdRecoverable($package->getId());
$isRecoverPoint    = (RecoveryPackage::getRecoverPackageId() === $package->getId());
$pack_name         = $package->getName();
$pack_archive_size = $package->Archive->Size;
$pack_dbonly       = $package->isDBOnly();
$brand             = $package->Brand;

//Links
$uniqueid         = $package->getNameHash();
$archive_exists   = ($package->getLocalPackageFilePath(AbstractPackage::FILE_TYPE_ARCHIVE) != false);
$installer_exists = ($package->getLocalPackageFilePath(AbstractPackage::FILE_TYPE_INSTALLER) != false);
$progress_error   = '';

//ROW CSS
$rowClasses   = [''];
$rowClasses[] = 'dup-row';
$rowClasses[] = 'dup-row-complete';
$rowClasses[] = ($isRecoverPoint) ? 'dup-recovery-package' : '';
$rowCSS       = trim(implode(' ', $rowClasses));


//ArchiveInfo
$archive_name         = $package->Archive->getFileName();
$archiveDownloadURL   = $package->getLocalPackageFileURL(AbstractPackage::FILE_TYPE_ARCHIVE);
$installerDownloadURL = $package->getLocalPackageFileURL(AbstractPackage::FILE_TYPE_INSTALLER);
$installerFullName    = $package->Installer->getInstallerName();
$isBackupAvailable    = ($package->haveRemoteStorage() || $package->haveLocalStorage());

//Lang Values
$txt_DatabaseOnly       = __('Database Only', 'duplicator-pro');
$txt_BackupNotAvailable = __('Backup doesn\'t exist', 'duplicator-pro');

switch ($package->getExecutionType()) {
    case DupPackage::EXEC_TYPE_MANUAL:
        $package_type_string = __('Manual', 'duplicator-pro');
        break;
    case DupPackage::EXEC_TYPE_SCHEDULED:
        $package_type_string = __('Schedule', 'duplicator-pro');
        break;
    case DupPackage::EXEC_TYPE_RUN_NOW:
        $lang_schedule       = __('Schedule', 'duplicator-pro');
        $lang_title          = __('This Backup was started manually from the schedules page.', 'duplicator-pro');
        $package_type_string = "{$lang_schedule}<span><sup>&nbsp;<i class='fas fa-cog fa-sm pointer' title='{$lang_title}'></i>&nbsp;</sup><span>";
        break;
    default:
        $package_type_string = __('Unknown', 'duplicator-pro');
        break;
}

$packageDetailsURL = PackagesPageController::getInstance()->getPackageDetailsURL($package->getId());
$createdFormat     = UserUIOptions::getInstance()->get(UserUIOptions::VAL_CREATED_DATE_FORMAT);

?>
<tr
    id="dup-row-pack-id-<?php echo (int) $package->getId(); ?>"
    data-package-id="<?php echo (int) $package->getId(); ?>"
    class="<?php echo esc_attr($rowCSS); ?>">
    <td class="dup-check-column dup-cell-chk">
        <label for="<?php echo (int) $package->getId(); ?>">
            <input
                name="delete_confirm"
                type="checkbox"
                id="<?php echo (int) $package->getId(); ?>"
                data-archive-name="<?php echo esc_attr($archive_name); ?>"
                data-installer-name="<?php echo esc_attr($installerFullName); ?>" />
        </label>
    </td>
    <td class="dup-name-column dup-cell-name">
        <?php echo esc_html($pack_name); ?>
    </td>
    <td class="dup-note-column">
        <?php echo esc_html($package->notes); ?>
    </td>
    <td class="dup-storages-column">
    </td>
    <td class="dup-flags-column">
        <?php $tplMng->render('admin_pages/packages/row_parts/falgs_cell'); ?>
    </td>
    <td class="dup-size-column">
        <?php echo esc_html(SnapString::byteSize($pack_archive_size)); ?>
    </td>
    <td class="dup-created-column">
        <?php echo esc_html(PackageUtils::formatLocalDateTime($package->getCreated(), $createdFormat)); ?>
    </td>
    <td class="dup-age-column">
        <?php echo esc_html($package->getPackageLife('human')); ?>
    </td>
    <td class="dup-cell-btns dup-download-column" <?php echo $isBackupAvailable ? '' : 'data-tooltip="' . esc_attr($txt_BackupNotAvailable) . '"'; ?>>
        <?php $tplMng->render('admin_pages/packages/row_parts/download_buttons'); ?>
    </td>
    <td class="dup-cell-btns dup-restore-column" <?php echo $isBackupAvailable ? '' : 'data-tooltip="' . esc_attr($txt_BackupNotAvailable) . '"'; ?>>
        <?php $tplMng->render('admin_pages/packages/row_parts/restore_backup_button'); ?>
    </td>
    <td class="dup-cell-btns dup-cell-toggle-btn dup-toggle-details dup-details-column">
        <span class="full-cell-button link-style">
            <i class="fa-solid fa-plus"></i>
        </span>
    </td>
</tr>
<tr id="dup-row-pack-id-<?php echo (int) $package->getId(); ?>-details" class="dup-row-details no-display">
    <?php $tplMng->render('admin_pages/packages/row_parts/details_package'); ?>
</tr>
