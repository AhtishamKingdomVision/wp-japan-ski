<?php

/**
 * Staging page controller
 */

declare(strict_types=1);

namespace Duplicator\Addons\StagingAddon\Controllers;

use Duplicator\Addons\ProBase\License\License;
use Duplicator\Addons\StagingAddon\Models\StagingEntity;
use Duplicator\Addons\StagingAddon\StagingAddon;
use Duplicator\Core\CapMng;
use Duplicator\Core\Controllers\AbstractMenuPageController;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Core\Controllers\PageAction;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Libs\Snap\SnapWP;
use Duplicator\Package\DupPackage;
use Duplicator\Package\Recovery\RecoveryStatus;

/**
 * Staging page controller class
 */
class StagingPageController extends AbstractMenuPageController
{
    const STAGING_SUBMENU_SLUG = ControllersManager::MAIN_MENU_SLUG . '-staging';

    /**
     * Inner page constants
     */
    const INNER_PAGE_LIST      = 'list';
    const INNER_PAGE_INSTALLER = 'installer';

    /**
     * Action constants
     */
    const ACTION_PREPARE_DELETE = 'prepare_delete';

    /**
     * Class constructor
     */
    protected function __construct()
    {
        $this->parentSlug   = ControllersManager::MAIN_MENU_SLUG;
        $this->pageSlug     = self::STAGING_SUBMENU_SLUG;
        $this->pageTitle    = __('Staging Sites', 'duplicator-pro');
        $this->menuLabel    = __('Staging', 'duplicator-pro');
        $this->capatibility = CapMng::CAP_STAGING;
        $this->menuPos      = 21; // After Import (20)

        add_filter('duplicator_page_template_data_' . $this->pageSlug, [$this, 'templateData']);
        add_action('duplicator_render_page_content_' . $this->pageSlug, [$this, 'renderContent'], 10, 2);
        add_action('duplicator_before_render_page_' . $this->pageSlug, [$this, 'beforeRenderPage'], 10, 2);
        add_filter('duplicator_page_actions_' . $this->pageSlug, [$this, 'pageActions']);
    }

    /**
     * Return true if current page is enabled
     *
     * @return boolean
     */
    public function isEnabled(): bool
    {
        return !StagingAddon::isStagingSite();
    }

    /**
     * Return actions for current page
     *
     * @param PageAction[] $actions actions list
     *
     * @return PageAction[]
     */
    public function pageActions(array $actions): array
    {
        $actions[] = new PageAction(
            self::ACTION_PREPARE_DELETE,
            [
                $this,
                'prepareDelete',
            ],
            [$this->pageSlug]
        );

        return $actions;
    }

    /**
     * Prepare delete action - validates staging identifier and sets up delete confirmation
     *
     * @return array<string, mixed>
     */
    public function prepareDelete(): array
    {
        $requestStagingId = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'staging_id', '');

        if (empty($requestStagingId)) {
            return ['errorMessage' => __('No staging site specified.', 'duplicator-pro')];
        }

        // Verify the staging entity exists with this identifier (identifier includes hash for security)
        $staging = StagingEntity::getByIdentifier($requestStagingId);
        if ($staging === false) {
            return ['errorMessage' => __('Staging site not found.', 'duplicator-pro')];
        }

        return [
            'deleteAction'    => true,
            'deleteStagingId' => $staging->getId(),
        ];
    }

    /**
     * Before render page - set blur for non-Pro licenses, handle redirects
     *
     * @param string[] $currentLevelSlugs Current menu slugs
     * @param string   $innerPage         Current inner page
     *
     * @return void
     */
    public function beforeRenderPage($currentLevelSlugs, $innerPage): void
    {
        TplMng::getInstance()->setGlobalValue('blur', !License::can(License::CAPABILITY_STAGING));

        // Redirect from installer page if staging site is already ready
        if ($innerPage === self::INNER_PAGE_INSTALLER) {
            $stagingId = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'staging_id', 0);
            if ($stagingId > 0) {
                $staging = StagingEntity::getById($stagingId);
                if ($staging !== false && $staging->isReady()) {
                    wp_safe_redirect(self::getStagingPageLink());
                    exit;
                }
            }
        }
    }

    /**
     * Template data
     *
     * @param array<string, mixed> $data Template data
     *
     * @return array<string, mixed>
     */
    public function templateData(array $data): array
    {
        $data['templateSecondaryPart'] = 'stagingaddon/staging_create_button';

        // Check for multisite incompatibility
        $data['isMultisite'] = is_multisite();

        $blur = TplMng::getInstance()->getGlobalValue('blur');

        // Only load data if feature is available
        if (!$data['isMultisite']) {
            // Clean up orphan entities and get staging sites
            $data['stagingSites'] = StagingEntity::getAllWithCleanup(true);
        } else {
            $data['stagingSites'] = [];
        }

        // Get available backups grouped by date
        $data['backupsByDate'] = [];
        if (!$blur && !$data['isMultisite']) {
            $data['backupsByDate'] = $this->getCompatibleBackupsByDate();
        }

        $data['defaultStagingTitle'] = $this->getDefaultStagingTitle($data['stagingSites']);
        $data['pendingStagingIds']   = $this->getPendingStagingIds($data['stagingSites']);

        // Delete action data - can come from PageAction result or cross-site link parameter
        // The actual delete operation uses AJAX with proper nonce verification
        if (!isset($data['deleteAction'])) {
            $data['deleteAction'] = false;
        }
        if (!isset($data['deleteStagingId'])) {
            $data['deleteStagingId'] = 0;
        }

        // Handle cross-site delete link from staging site admin bar
        $crossSiteDeleteId = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'delete_staging_id', '');
        if (!empty($crossSiteDeleteId) && !$data['deleteAction']) {
            $staging = StagingEntity::getByIdentifier($crossSiteDeleteId);
            if ($staging !== false) {
                $data['deleteAction']    = true;
                $data['deleteStagingId'] = $staging->getId();
            }
        }

        return $data;
    }

    /**
     * Get compatible backups grouped by date
     *
     * @return array<string, DupPackage[]>
     */
    protected function getCompatibleBackupsByDate(): array
    {
        $backupsByDate = [];

        /** @var DupPackage[] $packages */
        $packages = DupPackage::getPackagesByStatus(
            [
                [
                    'op'     => '>=',
                    'status' => DupPackage::STATUS_COMPLETE,
                ],
            ],
            0,
            0,
            '`id` DESC'
        );

        // Filter out non-recoverable backups and older incompatible versions, then group by date
        foreach ($packages as $pkg) {
            if (!StagingAddon::isBackupVersionCompatible($pkg->getVersion())) {
                continue;
            }
            $recoveryStatus = new RecoveryStatus($pkg);
            if (!$recoveryStatus->meetsRecoveryRequirements()) {
                continue;
            }
            $dateKey = date("Y/m/d", strtotime($pkg->getCreated()));
            if (!isset($backupsByDate[$dateKey])) {
                $backupsByDate[$dateKey] = [];
            }
            $backupsByDate[$dateKey][] = $pkg;
        }

        return $backupsByDate;
    }

    /**
     * Get default staging title with incremental counter for same-date duplicates
     *
     * @param StagingEntity[] $stagingSites Existing staging sites
     *
     * @return string
     */
    protected function getDefaultStagingTitle(array $stagingSites): string
    {
        $todayDate = date_i18n(get_option('date_format'));
        $baseTitle = 'Staging - ' . $todayDate;

        $count = 0;
        foreach ($stagingSites as $staging) {
            $title = $staging->getTitle();
            if ($title === $baseTitle || preg_match('/^' . preg_quote($baseTitle, '/') . ' \(\d+\)$/', $title)) {
                $count++;
            }
        }

        return $count > 0 ? $baseTitle . ' (' . $count . ')' : $baseTitle;
    }

    /**
     * Get IDs of staging sites that are not in a final state (pending install or creating)
     *
     * @param StagingEntity[] $stagingSites Existing staging sites
     *
     * @return int[]
     */
    protected function getPendingStagingIds(array $stagingSites): array
    {
        $ids = [];
        foreach ($stagingSites as $staging) {
            if (!$staging->isReady() && $staging->getStatus() !== StagingEntity::STATUS_FAILED) {
                $ids[] = $staging->getId();
            }
        }

        return $ids;
    }

    /**
     * Render page content
     *
     * @param string[] $currentLevelSlugs Current menu slugs
     * @param string   $innerPage         Current inner page
     *
     * @return void
     */
    public function renderContent($currentLevelSlugs, $innerPage): void
    {
        switch ($innerPage) {
            case self::INNER_PAGE_INSTALLER:
                $stagingId = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'staging_id', 0);
                $this->renderInstallerView($stagingId);
                break;
            case self::INNER_PAGE_LIST:
            default:
                TplMng::getInstance()->render('stagingaddon/staging_page');
                break;
        }
    }

    /**
     * Render installer view
     *
     * @param int $stagingId Staging entity ID
     *
     * @return void
     */
    protected function renderInstallerView(int $stagingId): void
    {
        $tplData = [
            'installerUrl' => '',
            'stagingId'    => $stagingId,
            'stagingTitle' => '',
            'error'        => '',
        ];

        if ($stagingId <= 0) {
            $tplData['error'] = __('Invalid staging ID.', 'duplicator-pro');
        } else {
            $staging = StagingEntity::getById($stagingId);
            if ($staging === false) {
                $tplData['error'] = __('Staging site not found.', 'duplicator-pro');
            } elseif (!$staging->folderExists()) {
                $tplData['error'] = __('Staging site folder not found. The staging creation may have failed.', 'duplicator-pro');
            } else {
                $tplData['stagingTitle'] = $staging->getTitle();

                // Use stored installer link - required for proper installer access
                $installerLink = $staging->getInstallerLink();
                if (empty($installerLink)) {
                    $tplData['error'] = __('Installer link not available. The staging site may need to be recreated.', 'duplicator-pro');
                } else {
                    $tplData['installerUrl'] = $installerLink;
                }
            }
        }

        TplMng::getInstance()->render('stagingaddon/staging_installer', $tplData);
    }

    /**
     * Get staging page link
     *
     * @return string
     */
    public static function getStagingPageLink(): string
    {
        return SnapWP::adminUrl('admin.php', ['page' => self::STAGING_SUBMENU_SLUG]);
    }

    /**
     * Get staging page link with delete action
     *
     * @param string $identifier Staging identifier
     *
     * @return string
     */
    public function getDeleteActionLink(string $identifier): string
    {
        $action = $this->getActionByKey(self::ACTION_PREPARE_DELETE);
        if ($action === null) {
            return self::getStagingPageLink();
        }

        return $action->getUrl(['staging_id' => $identifier]);
    }

    /**
     * Get staging installer page link
     *
     * @param int $stagingId Staging entity ID
     *
     * @return string
     */
    public static function getInstallerPageLink(int $stagingId): string
    {
        return ControllersManager::getMenuLink(
            self::STAGING_SUBMENU_SLUG,
            null,
            null,
            [
                ControllersManager::QUERY_STRING_INNER_PAGE => self::INNER_PAGE_INSTALLER,
                'staging_id'                                => $stagingId,
            ]
        );
    }

    /**
     * Get status display info (label and icon) for a staging entity
     *
     * @param string $status Status constant from StagingEntity
     *
     * @return array{label: string, icon: string}
     */
    public static function getStatusDisplay(string $status): array
    {
        switch ($status) {
            case StagingEntity::STATUS_READY:
                return [
                    'label' => __('Ready', 'duplicator-pro'),
                    'icon'  => '<i class="fas fa-check-circle dupli-staging-status-ready"></i>',
                ];
            case StagingEntity::STATUS_CREATING:
                return [
                    'label' => __('Creating...', 'duplicator-pro'),
                    'icon'  => '<i class="fas fa-circle-notch fa-spin"></i>',
                ];
            case StagingEntity::STATUS_PENDING_INSTALL:
                return [
                    'label' => __('Install Pending', 'duplicator-pro'),
                    'icon'  => '<i class="fas fa-clock"></i>',
                ];
            case StagingEntity::STATUS_FAILED:
                return [
                    'label' => __('Failed', 'duplicator-pro'),
                    'icon'  => '<i class="fas fa-exclamation-triangle alert-color"></i>',
                ];
            default:
                return [
                    'label' => ucfirst($status),
                    'icon'  => '',
                ];
        }
    }
}
