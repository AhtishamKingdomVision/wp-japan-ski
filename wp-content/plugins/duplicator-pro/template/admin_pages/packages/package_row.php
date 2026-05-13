<?php

/**
 * @package Duplicator
 */

use Duplicator\Package\AbstractPackage;
use Duplicator\Package\DupPackage;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 */
$package          = $tplMng->getDataValueObjRequired('package', DupPackage::class);
$pendingCancelIds = $tplMng->getDataValueArray('pending_cancelled_package_ids');

// If its in the pending cancels consider it stopped
if (in_array($package->getId(), $pendingCancelIds)) {
    $status = AbstractPackage::STATUS_PENDING_CANCEL;
} else {
    $status = $package->getStatus();
}

if ($package->getStatus() >= AbstractPackage::STATUS_COMPLETE) {
    $tplMng->render('admin_pages/packages/package_row_complete', ['status' => $status]);
} else {
    $tplMng->render('admin_pages/packages/package_row_incomplete', ['status' => $status]);
}
$tplMng->render('admin_pages/packages/package_row_building', ['status' => $status]);
