<?php

/**
 * Staging create button - displayed in page header
 */

use Duplicator\Addons\StagingAddon\StagingAddon;
use Duplicator\Core\CapMng;
use Duplicator\Package\DupPackage;
use Duplicator\Package\Recovery\RecoveryStatus;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

// Early exit checks - avoid running unnecessary code
if ($tplMng->getGlobalValue('blur')) {
    return;
}

if (!CapMng::can(CapMng::CAP_STAGING, false)) {
    return;
}

if (is_multisite()) {
    return;
}

// Check if there are any completed and version-compatible backups available
$hasCompatibleBackups = false;
/** @var \Duplicator\Package\DupPackage[] $packages */
$packages = DupPackage::getPackagesByStatus([
    [
        'op'     => '>=',
        'status' => DupPackage::STATUS_COMPLETE,
    ],
]);
foreach ($packages as $pkg) {
    if (!StagingAddon::isBackupVersionCompatible($pkg->getVersion())) {
        continue;
    }
    $recoveryStatus = new RecoveryStatus($pkg);
    if (!$recoveryStatus->meetsRecoveryRequirements()) {
        continue;
    }
    $hasCompatibleBackups = true;
    break;
}

if ($hasCompatibleBackups) {
    $tipContent = __(
        'Create a new staging site from an existing backup.',
        'duplicator-pro'
    );
    $isDisabled = false;
} else {
    // Show base version without beta suffix in user-facing message
    $displayVersion = preg_replace('/-.*$/', '', StagingAddon::MIN_BACKUP_VERSION);
    $tipContent     = sprintf(
        /* translators: %s: minimum required Duplicator version */
        __(
            'No compatible backups found. Please create a new backup with Duplicator %s or later to use the staging feature.',
            'duplicator-pro'
        ),
        $displayVersion
    );
    $isDisabled = true;
}
?>
<span
    class="dupli-new-staging-wrapper"
    data-tooltip="<?php echo esc_attr($tipContent); ?>">
    <button
        type="button"
        id="dupli-staging-create-new-btn"
        class="button primary tiny font-bold margin-bottom-0"
        <?php disabled($isDisabled); ?>>
        <b><?php esc_html_e('Add New', 'duplicator-pro'); ?></b>
    </button>
</span>
