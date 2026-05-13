<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Controllers\ImportPageController;
use Duplicator\Package\Import\PackageImporter;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 */

switch ($tplData['viewMode']) {
    case ImportPageController::VIEW_MODE_ADVANCED:
        $viewModeClass = 'view-list-item';
        break;
    case ImportPageController::VIEW_MODE_BASIC:
    default:
        $viewModeClass = 'view-single-item';
        break;
}

?>
<div id="dupli-import-available-packages" class="<?php echo esc_attr($viewModeClass); ?>" >
    <table class="dup-import-avail-packs packages-list">
        <thead>
            <tr>
                <th class="name"><?php esc_html_e("Backups", 'duplicator-pro'); ?></th>
                <th class="size"><?php esc_html_e("Size", 'duplicator-pro'); ?></th>
                <th class="created"><?php esc_html_e("Created", 'duplicator-pro'); ?></th>
                <th class="funcs"><?php esc_html_e("Status", 'duplicator-pro'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $importObjs = PackageImporter::getArchiveObjects();
            if (count($importObjs) === 0) {
                $tplMng->render('admin_pages/import/step1/package-row-no-found');
            } else {
                foreach ($importObjs as $importObj) {
                    $tplMng->render(
                        'admin_pages/import/step1/package-row',
                        [
                            'importObj' => $importObj,
                            'idRow'     => '',
                        ]
                    );
                }
            }
            ?>
        </tbody>
    </table>
    <div class="no-display" >
        <table id="dupli-import-available-packages-templates">
            <?php
            $tplMng->render(
                'admin_pages/import/step1/package-row',
                [
                    'importObj' => null,
                    'idRow'     => 'dupli-import-row-template',
                ]
            );
            $tplMng->render('admin_pages/import/step1/package-row-no-found');
            ?>
        </table>
    </div>
</div>