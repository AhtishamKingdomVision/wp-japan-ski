<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Libs\Snap\SnapString;
use Duplicator\Package\Import\PackageImporter;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 */

/** @var PackageImporter $importObj  */
$importObj = $tplData['importObj'];

if ($importObj instanceof PackageImporter) {
    $name             = $importObj->getName();
    $size             = $importObj->getSize();
    $created          = $importObj->getCreated();
    $archivePath      = $importObj->getFullPath();
    $installPakageUrl = $importObj->getInstallerPageLink();
    $isImportable     = $importObj->isImportable();
    $funcsEnalbed     = true;
} else {
    $name             = '';
    $size             = 0;
    $created          = '';
    $archivePath      = '';
    $installPakageUrl = '';
    $isImportable     = false;
    $funcsEnalbed     = false;
}

$rowClasses = ['dupli-import-package'];
if ($isImportable) {
    $rowClasses[] = 'is-importable';
}
?>
<tr <?php echo strlen($tplData['idRow']) ?  'id="' . esc_attr($tplData['idRow']) . '" ' : ''; ?>
    class="<?php echo esc_attr(implode(' ', $rowClasses)) ?>"
    data-path="<?php echo esc_attr($archivePath); ?>">
    <td class="name">
        <span class="text"><b><?php echo esc_html($name); ?></b></span>
        <div class="dupli-import-package-detail no-display">
            <?php
            if ($funcsEnalbed) {
                $importObj->getHtmlDetails();
            }
            ?>
        </div>
    </td>
    <td class="size">
        <span title="<?php printf(esc_attr__('Total %d bytes', 'duplicator-pro'), (int) $size); ?>">
            <?php echo esc_html(SnapString::byteSize($size)); ?>
        </span>
    </td>
    <td class="created">
        <?php echo esc_html($created); ?>
    </td>
    <td class="funcs">
        <div class="actions <?php echo $funcsEnalbed ? '' : 'no-display'; ?>">
            <button type="button" class="button secondary hollow margin-bottom-0 small dupli-import-action-package-detail-toggle">
                <i class="fa fa-caret-down"></i> <?php esc_html_e('Details', 'duplicator-pro'); ?>
            </button>
            <span class="separator"></span>
            <button type="button" class="dupli-import-action-remove button secondary hollow small margin-bottom-0 ">
                <i class="fa fa-ban"></i> <?php esc_html_e('Remove', 'duplicator-pro'); ?>
            </button>
            <span class="separator"></span>
            <button type="button" class="dupli-import-action-install button primary small margin-bottom-0"
                data-install-url="<?php echo esc_url($installPakageUrl); ?>"
                <?php echo $isImportable ? '' : 'disabled'; ?>>
                <i class="fa fa-bolt fa-sm"></i> <?php esc_html_e('Continue', 'duplicator-pro'); ?>
            </button>
        </div>
        <div class="invalid no-display">
            <?php esc_html_e('Backup Invalid', 'duplicator-pro'); ?>
        </div>
        <div class="dupli-loader no-display">
            <div class="dupli-meter-wrapper">
                <div class="dupli-meter green">
                    <span style="width: 0%"></span>
                </div>
                <span class="text">0%</span>
            </div>
            <a href="" class="dupli-import-action-cancel-upload button secondary hollow small margin-bottom-0 button-cancel">
                <i class="fa fa-ban"></i> <?php esc_html_e('Cancel', 'duplicator-pro'); ?>
            </a>
        </div>
    </td>
</tr>