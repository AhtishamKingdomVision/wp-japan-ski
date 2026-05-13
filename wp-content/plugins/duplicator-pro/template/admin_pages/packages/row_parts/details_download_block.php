<?php

/**
 * Duplicator Backup row in table Backups list
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Controllers\ImportPageController;
use Duplicator\Controllers\SettingsPageController;
use Duplicator\Controllers\StoragePageController;
use Duplicator\Core\CapMng;
use Duplicator\Models\GlobalEntity;
use Duplicator\Package\AbstractPackage;
use Duplicator\Package\DupPackage;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var ?DupPackage $package
 */

$package              = $tplMng->getDataValueObjRequired('package', AbstractPackage::class);
$archive_exists       = ($package->getLocalPackageFilePath(AbstractPackage::FILE_TYPE_ARCHIVE) != false);
$installer_exists     = ($package->getLocalPackageFilePath(AbstractPackage::FILE_TYPE_INSTALLER) != false);
$archiveDownloadURL   = $package->getLocalPackageFileURL(AbstractPackage::FILE_TYPE_ARCHIVE);
$installerDownloadURL = $package->getLocalPackageFileURL(AbstractPackage::FILE_TYPE_INSTALLER);
$defaultStorageUrl    = StoragePageController::getEditDefaultUrl();
$global               = GlobalEntity::getInstance();

$txt_RequiresRemote = sprintf(
    _x(
        'This option requires the Backup to use the built-in default %1$sstorage location %2$s%3$s',
        '1 and 3 are opening and closing anchor/link tags, 2 is an icon',
        'duplicator-pro'
    ),
    '<a href="' . esc_url($defaultStorageUrl) . '" target="_blank">',
    '<i class="far fa-hdd fa-fw fa-sm"></i>',
    '</a>'
);

?>
<div class="dup-ovr-ctrls-hdrs clearfix">
    <h3 class="font-bold margin-bottom-0">
        <i class="fas fa-link fa-fw"></i> <?php esc_html_e('Install Resources', 'duplicator-pro'); ?>
    </h3>
    <small class="xsmall dark-gray-color">
        <i><?php esc_html_e('Links are sensitive. Keep them safe!', 'duplicator-pro'); ?></i>
    </small>

    <?php if (CapMng::can(CapMng::CAP_STORAGE, false)) { ?>
        <small class="float-right">
            <a
                class="dup-ovr-ref-links-more"
                href="javascript:void(0)"
                onclick="DupliJs.Pack.ShowRemote(<?php echo (int) $package->getId(); ?>, '<?php echo esc_js($package->getNameHash()); ?>');">
                <i class="fas fa-server fa-xs"></i> <?php esc_html_e('Storages', 'duplicator-pro'); ?>
            </a>
        </small>
    <?php } ?>
</div>


<!-- =======================
ARCHIVE FILE: -->
<div class="dup-ovr-copy-flex-box">
    <div class="flex-item">
        <i class="far fa-file-archive fa-fw"></i>
        <b class="black-color">
            <?php esc_html_e('Backup File', 'duplicator-pro'); ?>
        </b>
        <sup>
            <?php
            $archiveFileToolTipTitle = sprintf(
                esc_html_x(
                    'This link is used with the %1$sImport Link Install%2$s feature. 
                    Use the Copy Link button to copy this URL Backup File link to import on another WordPress site.',
                    '1 and 2 are opening and closing anchor tags',
                    'duplicator-pro'
                ),
                '<a href="' . esc_url(ImportPageController::getInstance()->getMenuLink()) . '">',
                '</a>'
            );
            ?>
            <i class="fas fa-question-circle fa-xs fa-fw dup-archive-help"
                data-tooltip-title="<?php esc_attr_e("Backup File", 'duplicator-pro'); ?>"
                data-tooltip="<?php echo esc_attr($archiveFileToolTipTitle); ?>"></i>
        </sup>
    </div>
    <div class="flex-item"></div>
</div>
<div class="dup-ovr-copy-flex-box dup-box-file">
    <?php if ($archive_exists) : ?>
        <div class="flex-item">
            <input type="text" class="dup-ovr-ref-links margin-bottom-0" readonly="readonly"
                value="<?php echo esc_attr($archiveDownloadURL); ?>"
                title="<?php echo esc_attr($archiveDownloadURL); ?>"
                onfocus="jQuery(this).select();" />
            <span class="fas fa-arrow-alt-circle-down dup-ovr-ref-links-icon"
                title="<?php esc_attr_e('Archive Import Link (URL)', 'duplicator-pro'); ?>"></span>
        </div>
        <div class="flex-item">
            <span onclick="jQuery(this).parent().parent().find('.dup-ovr-ref-links').select();">
                <span data-dup-copy-value="<?php echo esc_attr($archiveDownloadURL); ?>"
                    class="button hollow small gray dup-ovr-ref-copy no-select">
                    <i class='far fa-copy dup-cursor-pointer'></i>
                    <?php esc_html_e('Copy Link', 'duplicator-pro'); ?>
                </span>
            </span>
            <span class="dup-ovr-ref-dwnld button hollow small gray "
                aria-label="<?php esc_html_e("Download Archive", 'duplicator-pro') ?>"
                onclick="DupliJs.Pack.DownloadFile('<?php echo esc_attr($archiveDownloadURL); ?>',
                '<?php echo esc_attr($package->getArchiveFilename()); ?>');">
                <i class="fas fa-download"></i> <?php esc_html_e('Download', 'duplicator-pro'); ?>
            </span>
        </div>
    <?php else : ?>
        <div class="flex-item maroon">
            <?php
            echo wp_kses(
                $txt_RequiresRemote,
                [
                    'a' => [
                        'href'   => [],
                        'target' => [],
                    ],
                    'i' => [
                        'class' => [],
                    ],
                ]
            );
            ?>.
        </div>
    <?php endif; ?>
</div>
<!-- =======================
ARCHIVE INSTALLER: -->
<?php
switch ($global->installer_name_mode) {
    case GlobalEntity::INSTALLER_NAME_MODE_SIMPLE:
        $settingsPackageUrl    = SettingsPageController::getInstance()->getMenuLink(SettingsPageController::L2_SLUG_PACKAGE);
        $lockIcon              = 'fa-lock-open';
        $installerToolTipTitle = sprintf(
            __(
                'Using standard installer name. To improve security, switch to hashed change in %1$sSettings%2$s',
                'duplicator-pro'
            ),
            '<a href="' . esc_url($settingsPackageUrl) . '" >',
            '</a>'
        );
        break;

    case GlobalEntity::INSTALLER_NAME_MODE_WITH_HASH:
    default:
        $lockIcon              = 'fa-lock';
        $installerToolTipTitle = __('Using more secure, hashed installer name.', 'duplicator-pro');
        break;
}
$installerName = $package->Installer->getDownloadName();
?>

<i class="fas fa-bolt fa-fw"></i>
<b class="black-color">
    <?php esc_html_e('Backup Installer', 'duplicator-pro'); ?>
</b>
<sup>
    <i class="fas <?php echo esc_attr($lockIcon); ?> dup-cursor-pointer fa-fw fa-xs dup-installer-help"
        style="padding-left:3px"
        data-tooltip="<?php echo esc_html($installerToolTipTitle); ?>"></i>
</sup>
<div class="dup-ovr-copy-flex-box dup-box-installer">
    <?php if ($installer_exists) : ?>
        <div class="flex-item">
            <input type="text" class="dup-ovr-ref-links margin-bottom-0" readonly="readonly"
                value="<?php echo esc_attr($installerName); ?>"
                title="<?php echo esc_attr($installerName); ?>"
                onfocus="jQuery(this).select();">
            <small class="dup-info-msg01 xsmall dark-gray-color">
                <i><?php esc_html_e('These links contain highly sensitive data. Share with extra caution!', 'duplicator-pro'); ?></i>
            </small>
        </div>
        <div class="flex-item">
            <span onclick="jQuery(this).parent().parent().find('.dup-ovr-ref-links').select();">
                <span data-dup-copy-value="<?php echo esc_attr($installerName); ?>"
                    class="dup-ovr-ref-copy no-select button hollow small gray">
                    <i class='far fa-copy dup-cursor-pointer'></i>
                    <?php esc_html_e('Copy Name', 'duplicator-pro'); ?>
                </span>
            </span>
            <span class="dup-ovr-ref-dwnld button hollow small gray "
                aria-label="<?php esc_html_e("Download Installer", 'duplicator-pro') ?>"
                onclick="DupliJs.Pack.DownloadFile('<?php echo esc_attr($installerDownloadURL); ?>');">
                <i class="fas fa-download"></i> <?php esc_html_e('Download', 'duplicator-pro'); ?>
            </span>
        </div>
    <?php else : ?>
        <div class="flex-item maroon">
            <?php
            echo wp_kses(
                $txt_RequiresRemote,
                [
                    'a' => [
                        'href'   => [],
                        'target' => [],
                    ],
                    'i' => [
                        'class' => [],
                    ],
                ]
            );
            ?>.
        </div>
    <?php endif; ?>
</div>