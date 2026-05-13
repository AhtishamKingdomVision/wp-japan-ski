<?php

/**
 * Duplicator messages sections
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Controllers\StoragePageController;
use Duplicator\Core\CapMng;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\Storages\StoragesUtil;
use Duplicator\Utils\Logging\DupLog;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 */
$storageList        = AbstractStorageEntity::getAll(0, 0, [StoragesUtil::class, 'sortByPriority']);
$filteredStorageIds = $tplData['filteredStorageIds'] ?? [];
$selectedStorageIds = $tplData['selectedStorageIds'] ?? [];
$showAddNew         = $tplData['showAddNew'] ?? true;
$minCheck           = $tplData['minCheck'] ?? true;
$recoveryPointMsg   = $tplData['recoveryPointMsg'] ?? false;
$newStorageUrl      = StoragePageController::getEditUrl();
$hasInvalidStorage  = false;

?>
<table class="widefat dup-table-list storage-select-list small striped">
    <thead>
        <tr>
            <th></th>
            <th><?php esc_html_e('Type', 'duplicator-pro') ?></th>
            <th><?php esc_html_e('Name', 'duplicator-pro') ?></th>
            <th><?php esc_html_e('Location', 'duplicator-pro') ?></th>
        </tr>
    </thead>
    <tbody>
        <?php
        foreach ($storageList as $storage) {
            $rowClasses = [
                'package-row',
                'storage-row',
            ];

            try {
                $invalidErrorMsg = __('Invalid storage configuration', 'duplicator-pro');
                $isValid         = $storage->isValid($invalidErrorMsg);
                if ($storage->isSupported() && ! $storage->isHidden() && ! $isValid) {
                    $hasInvalidStorage = true;
                }

                if (
                    !$storage->isSupported()
                    || $storage->isHidden()
                    || in_array($storage->getId(), $filteredStorageIds)
                ) {
                    continue;
                }

                $storageId      = (int) $storage->getId();
                $isChecked      = in_array($storage->getId(), $selectedStorageIds) && $isValid;
                $storageEditUrl = esc_url(StoragePageController::getEditUrl($storage));

                if (!$isValid) {
                    $rowClasses[] = 'invalid';
                }

                ?>
                <tr class="<?php echo esc_attr(implode(' ', $rowClasses)); ?>">
                    <td class="storage-checkbox">
                        <?php
                        // Build parsley attributes
                        $parsleyAttrs = [];
                        if ($minCheck) {
                            $parsleyAttrs['data-parsley-mincheck'] = '1';
                            $parsleyAttrs['data-parsley-required'] = 'true';
                        }
                        ?>
                        <input
                            type="checkbox"
                            id="dup-chkbox-<?php echo esc_attr((string) $storageId); ?>"
                            name="_storage_ids[]"
                            class="dupli-storage-input margin-bottom-0"
                            data-parsley-errors-container="#storage_error_container"
                            <?php
                            foreach ($parsleyAttrs as $name => $value) {
                                printf(
                                    '%s="%s" ',
                                    esc_attr($name),
                                    esc_attr($value)
                                );
                            }
                            ?>
                            value="<?php echo esc_attr((string) $storageId); ?>"
                            <?php disabled(!$isValid); ?>
                            <?php checked($isChecked); ?>>
                    </td>
                    <td class="storage-type">
                        <label for="dup-chkbox-<?php echo esc_attr((string) $storageId); ?>" class="dup-store-lbl">
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
                            );
                            echo '&nbsp;' . esc_html($storage->getStypeName());
                            if ($recoveryPointMsg && $storage->isLocal()) {
                                ?>
                                <sup title="<?php esc_attr_e('Recovery Point Capable', 'duplicator-pro'); ?>">
                                    <i class="fas fa-house-fire fa-fw fa-sm"></i>
                                </sup>
                                <?php
                            }
                            ?>
                        </label>
                    </td>
                    <td class="storage-name">
                        <a href="<?php echo esc_attr($storageEditUrl); ?>" target="_blank">
                            <?php echo esc_html($storage->getName()); ?>
                        </a>
                        <?php if (!$isValid) : ?>
                            &nbsp;<i class="fas fa-exclamation-triangle alert-color"
                                title="<?php echo esc_attr($invalidErrorMsg); ?>"
                            ></i>
                        <?php endif; ?>
                    </td>
                    <td class="storage-location">
                        <?php
                        echo wp_kses(
                            $storage->getHtmlLocationLink(),
                            [
                                'a' => [
                                    'href'   => [],
                                    'target' => [],
                                ],
                            ]
                        );
                        ?>
                    </td>
                </tr>
                <?php
            } catch (Exception $e) {
                $rowClasses[] = 'invalid';
                ?>
                <tr class="<?php echo esc_attr(implode(' ', $rowClasses)); ?>">
                    <td class="storage-checkbox">
                        <input type="checkbox" <?php disabled(true); ?> <?php checked(false); ?>>
                    </td>
                    <td colspan='4'>
                        <i class="fas fa-exclamation-triangle alert-color" ></i>
                        <?php esc_html_e('Unable to load storage type. Please validate the setup.', 'duplicator-pro'); ?>
                        <strong><?php esc_html_e('Error:', 'duplicator-pro'); ?></strong>
                        <?php echo esc_html($e->getMessage()); ?>
                    </td>
                </tr>
                <?php
            }
        }
        ?>
    </tbody>
</table>
<div id="storage_error_container" class="duplicator-error-container"></div>
<?php if ($hasInvalidStorage) : ?>
    <div class="notice notice-warning storage-warning-notice inline padding-half margin-top-1">
        <i class="fas fa-exclamation-triangle alert-color"></i>
        <?php esc_html_e(
            'Some storage locations are invalid and cannot be used. Please update their configurations to make them available for backups.',
            'duplicator-pro'
        ); ?>
    </div>
<?php endif; ?>
<?php if ($showAddNew) : ?>
    <div class="text-right">
        <?php if (CapMng::can(CapMng::CAP_STORAGE, false)) : ?>
            <a href="<?php echo esc_url($newStorageUrl); ?>" target="_blank">
                [<?php esc_html_e('Add Storage', 'duplicator-pro') ?>]
            </a>
        <?php else : ?>
            &nbsp;
        <?php endif; ?>
    </div>
<?php endif; ?>
