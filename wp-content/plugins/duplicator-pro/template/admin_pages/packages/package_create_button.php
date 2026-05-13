<?php

/**
 * Duplicator Backup row in table Backups list
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Controllers\PackagesPageController;
use Duplicator\Core\CapMng;
use Duplicator\Package\DupPackage;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 */

if ($tplMng->getDataValueBoolRequired('blur')) {
    return;
}

$disableCreate = DupPackage::isPackageRunning() || DupPackage::isPackageCancelling();

if (CapMng::can(CapMng::CAP_CREATE, false)) {
    $tipContent = __(
        'Create a new backup. If a backup is currently running then this button will be disabled.',
        'duplicator-pro'
    );
    ?>
    <span
        class="dup-new-package-wrapper"
        data-tooltip="<?php echo esc_attr($tipContent); ?>">
        <a
            href="<?php echo esc_url(PackagesPageController::getInstance()->getPackageBuildS1Url()); ?>"
            id="dupli-create-new"
            class="button primary tiny font-bold margin-bottom-0 <?php echo $disableCreate ? 'disabled' : ''; ?>">
            <b><?php esc_html_e('Add New', 'duplicator-pro'); ?></b>
        </a>
    </span>
    <?php
}
do_action('duplicator_backups_page_header_after');
