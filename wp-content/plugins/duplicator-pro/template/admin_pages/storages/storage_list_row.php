<?php

/**
 * @package Duplicator
 */

defined("ABSPATH") or die("");

use Duplicator\Core\Views\TplMng;

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 * @var Duplicator\Models\Storages\AbstractStorageEntity $storage
 */
$storage = $tplData['storage'];
/** @var int */
$index = $tplData['index'];

$typeName        = $storage->getStypeName();
$typeId          = $storage->getSType();
$invalidErrorMsg = __('Invalid storage configuration', 'duplicator-pro');
$isValid         = $storage->isValid($invalidErrorMsg);
$isSupported     = $storage::isSupported();

$row_classes = [ 'storage-row' ];

if ($index % 2) {
    $row_classes[] = 'alternate';
}

if (!$isSupported) {
    $row_classes[]    = 'unsupported';
    $shortDescWarning = __('Storage Type Not Supported', 'duplicator-pro');
    $longDescWarning  = __(
        'This storage type is not supported on this server. Please remove this storage or contact your host to install the required extensions.',
        'duplicator-pro'
    );
} elseif (!$isValid) {
    $row_classes[]    = 'invalid';
    $shortDescWarning = $invalidErrorMsg;
    $longDescWarning  = __(
        'This storage has invalid configuration and cannot be used. Please edit and fix the configuration.',
        'duplicator-pro'
    );
} else {
    $shortDescWarning = '';
    $longDescWarning  = '';
}

?>
<tr id="main-view-<?php echo (int) $storage->getId() ?>"
    class="<?php echo esc_attr(implode(' ', $row_classes)); ?>"
    data-delete-view="<?php echo esc_attr($storage->getDeleteView(false)); ?>"
>
    <td class="storage-checkbox">
        <?php if ($storage->isDefault()) : ?>
            <input type="checkbox" disabled="disabled" />
        <?php else : ?>
            <input name="selected_id[]" type="checkbox" value="<?php echo (int) $storage->getId(); ?>" class="item-chk" />
        <?php endif; ?>
    </td>
    <td class="storage-name">
        <a href="javascript:void(0);" onclick="DupliJs.Storage.Edit('<?php echo (int) $storage->getId(); ?>')">
            <b><?php echo esc_html($storage->getName()); ?></b>
            <?php if (!$isSupported || !$isValid) : ?>
                &nbsp;<i class="fas fa-exclamation-triangle alert-color" title="<?php echo esc_attr($shortDescWarning); ?>"></i>
            <?php endif; ?>
        </a>
        <div class="sub-menu">
            <a href="javascript:void(0);" onclick="DupliJs.Storage.Edit('<?php echo (int) $storage->getId(); ?>')">
                <?php esc_html_e('Edit', 'duplicator-pro'); ?>
            </a>
            |
            <a href="javascript:void(0);" onclick="DupliJs.Storage.View('<?php echo (int) $storage->getId(); ?>');">
                <?php esc_html_e('Quick View', 'duplicator-pro'); ?>
            </a>
            <?php if (!$storage->isDefault()) : ?>
                <?php if (!$storage->isLocal()) : ?>
                |
                <a href="javascript:void(0);" onclick="DupliJs.Storage.CopyEdit('<?php echo (int) $storage->getId(); ?>');">
                    <?php esc_html_e('Copy', 'duplicator-pro'); ?>
                </a>
                <?php endif; ?>
                |
                <a href="javascript:void(0);" onclick="DupliJs.Storage.deleteSingle('<?php echo (int) $storage->getId(); ?>');">
                    <?php esc_html_e('Delete', 'duplicator-pro'); ?>
                </a>
            <?php endif; ?>
        </div>
    </td>
    <td class="storage-type">
        <?php
        echo wp_kses(
            $storage->getStypeIcon(),
            [
                'i'   => [
                    'class' => [],
                ],
                'img' => [
                    'src'   => [],
                    'class' => [],
                    'alt'   => [],
                ],
            ]
        ),
        '&nbsp;',
        esc_html($storage->getStypeName());
        ?>
    </td>
</tr>
<?php
ob_start();
try { ?>
    <tr id='quick-view-<?php echo (int) $storage->getId(); ?>'
        class='<?php echo ($index % 2) ? 'alternate' : ''; ?> storage-detail'>
        <td colspan="3">
            <b><?php esc_html_e('QUICK VIEW', 'duplicator-pro') ?></b> <br/>
            <div>
                <label><?php esc_html_e('Name', 'duplicator-pro') ?>:</label>
                <?php echo esc_html($storage->getName()); ?>
            </div>
            <div>
                <label><?php esc_html_e('Notes', 'duplicator-pro') ?>:</label>
                <?php echo (strlen($storage->getNotes())) ? esc_html($storage->getNotes()) : esc_html__('(no notes)', 'duplicator-pro'); ?>
            </div>
            <div>
                <label><?php esc_html_e('Type', 'duplicator-pro') ?>:</label>
                <?php echo esc_html($storage->getStypeName()); ?>
            </div>
            <?php $storage->getListQuickView(); ?>
            <?php if (!$isSupported || !$isValid) : ?>
            <div class="storage-status-warning">
                <p>
                    <i class="fas fa-exclamation-triangle alert-color"></i>&nbsp;
                    <strong><?php echo esc_html($shortDescWarning); ?></strong>
                </p>
                <?php echo esc_html($longDescWarning); ?>
            </div>
            <?php endif; ?>
            <button type="button" class="button secondary hollow tiny"
                    onclick="DupliJs.Storage.View('<?php echo (int) $storage->getId(); ?>');">
                <?php esc_html_e('Close', 'duplicator-pro') ?>
            </button>
        </td>
    </tr>
    <?php
} catch (Exception $e) {
    ob_clean(); ?>
    <tr id='quick-view-<?php echo intval($storage->getId()); ?>' class='<?php echo ($index % 2) ? 'alternate' : ''; ?>'>
        <td colspan="3">
            <?php TplMng::getInstance()->render(
                'admin_pages/storages/parts/storage_error',
                ['exception' => $e]
            ); ?>
            <br><br>
            <button type="button" class="button" onclick="DupliJs.Storage.View('<?php echo intval($storage->getId()); ?>');">
            <?php esc_html_e('Close', 'duplicator-pro') ?>
            </button>
        </td>
    </tr>
    <?php
}
ob_end_flush();
