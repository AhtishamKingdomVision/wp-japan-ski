<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

defined('ABSPATH') || exit;

use Duplicator\Controllers\PackagesPageController;
use Duplicator\Core\Views\TplMng;
use Duplicator\Core\Views\Notifications;
use Duplicator\Models\GlobalEntity;
use Duplicator\Models\SystemGlobalEntity;
use Duplicator\Package\AbstractPackage;
use Duplicator\Package\DupPackage;
use Duplicator\Package\PackageUtils;
use Duplicator\Views\PackageListTable;

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var bool $blur
 */

$tplMng        = TplMng::getInstance();
$system_global = SystemGlobalEntity::getInstance();

// Filter out failed backups (status < 0) from the main backup list
$statusConditions = [
    [
        'op'     => '>=',
        'status' => 0,
    ],
];

$totalElements = PackageUtils::getNumPackages([DupPackage::getBackupType()], $statusConditions);
$statusActive  = DupPackage::isPackageRunning();
$activePackage = DupPackage::getNextActive();
$isTransfer    = $activePackage === null ? false : $activePackage->getStatus() == AbstractPackage::STATUS_STORAGE_PROCESSING;

$pager       = new PackageListTable();
$perPage     = $pager->get_per_page();
$currentPage = $statusActive && !$isTransfer ? 1 : $pager->get_pagenum();
$offset      = ($currentPage - 1) * $perPage;

$global = GlobalEntity::getInstance();

do_action('duplicator_before_packages_table_action');
?>

<form
    id="form-duplicator"
    method="post"
    class="<?php echo esc_attr($tplData['blur'] ? 'dup-mock-blur' : ''); ?>">
    <?php PackagesPageController::getInstance()
        ->getActionByKey(PackagesPageController::ACTION_STOP_BUILD)->getActionNonceFileds(); ?>
    <input type="hidden" id="stop-backup-id" name="stop-backup-id" />
    <?php $tplMng->render('admin_pages/packages/toolbar'); ?>

    <table class="widefat dup-table-list dup-packtbl striped" aria-label="Backup List">
        <?php
        $tplMng->render(
            'admin_pages/packages/packages_table_head',
            ['totalElements' => $totalElements]
        );

        if ($totalElements == 0) {
            $tplMng->render('admin_pages/packages/no_elements_row');
        } else {
            DupPackage::dbSelectByStatusCallback(
                function (DupPackage $package): void {
                    TplMng::getInstance()->render(
                        'admin_pages/packages/package_row',
                        ['package' => $package]
                    );
                },
                $statusConditions,
                $perPage,
                $offset,
                '`id` DESC',
                [
                    PackageUtils::DEFAULT_BACKUP_TYPE,
                ]
            );
        }
        $tplMng->render(
            'admin_pages/packages/packages_table_foot',
            ['totalElements' => $totalElements]
        ); ?>
    </table>
</form>

<?php if ($totalElements > $perPage) { ?>
    <form id="form-duplicator-nav" method="post">
        <div class="dup-paged-nav tablenav">
            <?php if ($statusActive > 0) : ?>
                <div id="dupli-paged-progress" style="padding-right: 10px">
                    <i class="fas fa-circle-notch fa-spin fa-lg fa-fw"></i>
                    <i><?php esc_html_e('Paging disabled during build...', 'duplicator-pro'); ?></i>
                </div>
            <?php else : ?>
                <div id="dupli-paged-buttons">
                    <?php $pager->display_pagination($totalElements, $perPage); ?>
                </div>
            <?php endif; ?>
        </div>
    </form>
<?php } else { ?>
    <div style="float:right; padding:10px 5px">
        <?php echo esc_html(sprintf(_n('%s item', '%s items', $totalElements, 'duplicator-pro'), $totalElements)); ?>
    </div>
    <?php
}

$tplMng->render(
    'admin_pages/packages/packages_scripts',
    [
        'perPage'          => $perPage,
        'offset'           => $offset,
        'currentPage'      => $currentPage,
        'stattiBackupType' => DupPackage::getBackupType(),
    ]
);
