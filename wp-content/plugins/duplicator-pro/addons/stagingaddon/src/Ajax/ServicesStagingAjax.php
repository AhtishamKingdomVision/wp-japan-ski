<?php

/**
 * Staging AJAX services
 */

declare(strict_types=1);

namespace Duplicator\Addons\StagingAddon\Ajax;

use Duplicator\Addons\StagingAddon\Controllers\StagingPageController;
use Duplicator\Addons\StagingAddon\Models\StagingEntity;
use Duplicator\Addons\StagingAddon\Package\StagingPackage;
use Duplicator\Addons\StagingAddon\StagingAddon;
use Duplicator\Addons\StagingAddon\StagingSiteHandler;
use Duplicator\Ajax\AbstractAjaxService;
use Duplicator\Ajax\AjaxWrapper;
use Duplicator\Core\CapMng;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Package\AbstractPackage;
use Duplicator\Package\DupPackage;
use Duplicator\Package\Recovery\RecoveryStatus;
use Exception;

/**
 * AJAX handler for staging operations
 */
class ServicesStagingAjax extends AbstractAjaxService
{
    /**
     * Disk space multiplier for staging validation.
     * Requires 2x archive size to account for extraction plus intermediate files.
     */
    const DISK_SPACE_MULTIPLIER = 2;

    /**
     * Init ajax calls
     *
     * @return void
     */
    public function init(): void
    {
        $this->addAjaxCall('wp_ajax_duplicator_staging_create', 'createStaging');
        $this->addAjaxCall('wp_ajax_duplicator_staging_delete', 'deleteStaging');
        $this->addAjaxCall('wp_ajax_duplicator_staging_validate', 'validateBackup');
        $this->addAjaxCall('wp_ajax_duplicator_staging_check_complete', 'checkComplete');
    }

    /**
     * Create staging site callback
     *
     * @return array<string, mixed>
     */
    public static function createStagingCallback(): array
    {
        if (is_multisite()) {
            throw new Exception(__('Staging is not available on multisite installations.', 'duplicator-pro'));
        }

        $backupId    = SnapUtil::sanitizeIntInput(INPUT_POST, 'backup_id', 0);
        $title       = SnapUtil::sanitizeTextInput(INPUT_POST, 'title', '');
        $colorScheme = StagingSiteHandler::sanitizeColorScheme(
            SnapUtil::sanitizeTextInput(INPUT_POST, 'color_scheme', 'fresh')
        );

        $package = DupPackage::getById($backupId);
        if ($package === false) {
            throw new Exception(__('Backup not found', 'duplicator-pro'));
        }

        $archivePath = $package->getLocalPackageFilePath(AbstractPackage::FILE_TYPE_ARCHIVE);

        if ($archivePath === false || !file_exists($archivePath)) {
            throw new Exception(__('Backup archive not found locally', 'duplicator-pro'));
        }

        $staging = StagingEntity::create(
            $backupId,
            $package->getName(),
            $package->getCreated(),
            $title,
            $colorScheme
        );

        // Save to get ID
        if (!$staging->save()) {
            throw new Exception(__('Failed to create staging entity', 'duplicator-pro'));
        }

        // Generate DB prefix after we have an ID
        $staging->generateDbPrefix();

        $stagingPackage = new StagingPackage($archivePath, $staging, $package);

        if (!$stagingPackage->loadInfo()) {
            $staging->delete();
            throw new Exception(__('Failed to load backup information', 'duplicator-pro'));
        }

        try {
            $installerLink = $stagingPackage->prepareToInstall();
        } catch (Exception $e) {
            $staging->delete();
            throw new Exception(
                sprintf(__('Failed to prepare staging: %s', 'duplicator-pro'), $e->getMessage())
            );
        }

        if ($installerLink === false) {
            $staging->delete();
            throw new Exception(__('Failed to generate installer link', 'duplicator-pro'));
        }

        $staging->setWpVersion($stagingPackage->getWPVersion());
        $staging->setDupVersion($stagingPackage->getDupVersion());
        $staging->setInstallerLink($installerLink);
        $staging->setStatus(StagingEntity::STATUS_PENDING_INSTALL);
        $staging->save();

        return [
            'stagingId'        => $staging->getId(),
            'identifier'       => $staging->getIdentifier(),
            'installerLink'    => $installerLink,
            'installerPageUrl' => StagingPageController::getInstallerPageLink($staging->getId()),
            'stagingUrl'       => $staging->getUrl(),
            'adminUrl'         => $staging->getAdminUrl(),
        ];
    }

    /**
     * Create staging site action
     *
     * @return void
     */
    public function createStaging(): void
    {
        AjaxWrapper::json(
            [
                self::class,
                'createStagingCallback',
            ],
            'duplicator_staging_create',
            SnapUtil::sanitizeTextInput(INPUT_POST, 'nonce'),
            CapMng::CAP_STAGING
        );
    }

    /**
     * Delete staging site callback
     *
     * @return array<string, mixed>
     */
    public static function deleteStagingCallback(): array
    {
        $inputData  = filter_input_array(INPUT_POST, [
            'staging_ids' => [
                'filter' => FILTER_VALIDATE_INT,
                'flags'  => FILTER_REQUIRE_ARRAY,
            ],
        ]);
        $stagingIds = is_array($inputData['staging_ids']) ? array_filter($inputData['staging_ids']) : [];

        if (empty($stagingIds)) {
            throw new Exception(__('No staging sites selected', 'duplicator-pro'));
        }

        // Batch load all staging entities in a single query
        $stagingSites = StagingEntity::getByIds($stagingIds);

        if (empty($stagingSites)) {
            throw new Exception(__('No staging sites found', 'duplicator-pro'));
        }

        $deleted = 0;
        $errors  = [];

        foreach ($stagingSites as $staging) {
            if ($staging->delete()) {
                $deleted++;
            } else {
                $errors[] = sprintf(__('Failed to delete staging site #%d', 'duplicator-pro'), $staging->getId());
            }
        }

        if ($deleted === 0 && !empty($errors)) {
            throw new Exception(implode(', ', $errors));
        }

        return [
            'success' => true,
            'deleted' => $deleted,
            'message' => sprintf(
                _n(
                    '%d staging site deleted successfully',
                    '%d staging sites deleted successfully',
                    $deleted,
                    'duplicator-pro'
                ),
                $deleted
            ),
        ];
    }

    /**
     * Delete staging site action
     *
     * @return void
     */
    public function deleteStaging(): void
    {
        AjaxWrapper::json(
            [
                self::class,
                'deleteStagingCallback',
            ],
            'duplicator_staging_delete',
            SnapUtil::sanitizeTextInput(INPUT_POST, 'nonce'),
            CapMng::CAP_STAGING
        );
    }

    /**
     * Checks archive availability, type, and disk space requirements
     *
     * @return array<string, mixed>
     */
    public static function validateBackupCallback(): array
    {
        $backupId = SnapUtil::sanitizeIntInput(INPUT_POST, 'backup_id', 0);
        $package  = DupPackage::getById($backupId);
        if ($package === false) {
            throw new Exception(__('Backup not found', 'duplicator-pro'));
        }

        $archivePath = $package->getLocalPackageFilePath(AbstractPackage::FILE_TYPE_ARCHIVE);
        $issues      = [];

        if ($archivePath === false || !file_exists($archivePath)) {
            $issues[] = __('Backup archive is not available locally. Download it first.', 'duplicator-pro');
        }

        $recoveryStatus = new RecoveryStatus($package);
        $reasons        = [];
        if (!$recoveryStatus->meetsRecoveryRequirements($reasons)) {
            $issues = array_merge($issues, RecoveryStatus::reasonsToMessages($reasons));
        }

        if ($archivePath !== false && file_exists($archivePath)) {
            $archiveSize    = filesize($archivePath);
            $availableSpace = SnapIO::diskFreeSpace(dirname(StagingAddon::getStagingBasePath()));

            if ($availableSpace > 0 && $archiveSize !== false) {
                $requiredSpace = $archiveSize * self::DISK_SPACE_MULTIPLIER;
                if ($availableSpace < $requiredSpace) {
                    $issues[] = sprintf(
                        __('Insufficient disk space. Required: %1$s, Available: %2$s', 'duplicator-pro'),
                        size_format($requiredSpace),
                        size_format($availableSpace)
                    );
                }
            }
        }

        return [
            'valid'       => empty($issues),
            'issues'      => $issues,
            'packageName' => $package->getName(),
            'packageDate' => $package->getCreated(),
        ];
    }

    /**
     * Validate backup for staging action
     *
     * @return void
     */
    public function validateBackup(): void
    {
        AjaxWrapper::json(
            [
                self::class,
                'validateBackupCallback',
            ],
            'duplicator_staging_validate',
            SnapUtil::sanitizeTextInput(INPUT_POST, 'nonce'),
            CapMng::CAP_STAGING
        );
    }

    /**
     * Polls staging database to detect when installer finishes
     *
     * @return array<string, mixed>
     */
    public static function checkCompleteCallback(): array
    {
        $stagingId = SnapUtil::sanitizeIntInput(INPUT_POST, 'staging_id', 0);

        if ($stagingId <= 0) {
            throw new Exception(__('Invalid staging ID', 'duplicator-pro'));
        }

        $staging = StagingEntity::getById($stagingId);
        if ($staging === false) {
            return [
                'complete' => false,
                'notFound' => true,
            ];
        }

        $isComplete = $staging->isReady();

        // If entity shows "pending_install", check if installer has actually completed
        if (!$isComplete && $staging->isPendingInstall()) {
            $isComplete = $staging->isInstallComplete();
            if ($isComplete) {
                // Update the entity to reflect actual status
                $staging->setStatus(StagingEntity::STATUS_READY);
                $staging->save();
            }
        }

        return [
            'complete'   => $isComplete,
            'notFound'   => false,
            'status'     => $staging->getStatus(),
            'stagingUrl' => $staging->getUrl(),
            'adminUrl'   => $staging->getAdminUrl(),
        ];
    }

    /**
     * Check if staging installation is complete action
     *
     * @return void
     */
    public function checkComplete(): void
    {
        AjaxWrapper::json(
            [
                self::class,
                'checkCompleteCallback',
            ],
            'duplicator_staging_check_complete',
            SnapUtil::sanitizeTextInput(INPUT_POST, 'nonce'),
            CapMng::CAP_STAGING
        );
    }
}
