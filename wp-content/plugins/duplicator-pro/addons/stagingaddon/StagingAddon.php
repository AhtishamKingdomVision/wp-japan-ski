<?php

declare(strict_types=1);

namespace Duplicator\Addons\StagingAddon;

use Duplicator\Addons\ProBase\Models\LicenseData;
use Duplicator\Addons\StagingAddon\Ajax\ServicesStagingAjax;
use Duplicator\Addons\StagingAddon\Controllers\StagingPageController;
use Duplicator\Addons\StagingAddon\Models\StagingEntity;
use Duplicator\Core\Addons\AbstractAddonCore;
use Duplicator\Core\Bootstrap;
use Duplicator\Core\Controllers\AbstractMenuPageController;
use Duplicator\Core\MigrationMng;
use Duplicator\Libs\Snap\SnapUtil;

/**
 * Staging addon class
 */
class StagingAddon extends AbstractAddonCore
{
    const ADDON_PATH = __DIR__;

    const STAGING_FOLDER_NAME = 'dup_staging';
    const STAGING_OPTION_KEY  = 'dupli_opt_staging_data';
    const STAGING_MODE_OPTION = 'dupli_opt_is_staging_site';
    const MIN_BACKUP_VERSION  = '4.5.25-beta11';

    /** @var ?StagingSiteHandler */
    private ?StagingSiteHandler $siteHandler = null;

    /**
     * Initialize the addon
     *
     * @return void
     */
    public function init(): void
    {
        add_filter('duplicator_template_file', [self::class, 'getTemplateFile'], 10, 2);
        add_filter('duplicator_menu_pages', [$this, 'addMenuPage']);
        add_filter('duplicator_database_tables_list', [self::class, 'filterStagingTables']);

        add_action('admin_init', [self::class, 'registerJsCss']);

        $this->siteHandler = new StagingSiteHandler();

        // First login after install hook for staging configuration
        add_action(MigrationMng::HOOK_FIRST_LOGIN_AFTER_INSTALL, [$this->siteHandler, 'configure'], 5);

        // If this is a staging site, apply restrictions
        if (self::isStagingSite()) {
            $this->siteHandler->init();
        }

        add_action('duplicator_before_staging_delete', function (StagingEntity $stagingEntity): void {
            if ($stagingEntity->getStatus() !== StagingEntity::STATUS_READY) {
                return;
            }
            LicenseData::getInstance()->deactivate($stagingEntity->getUrl());
        });

        (new ServicesStagingAjax())->init();
    }

    /**
     * Add menu page
     *
     * @param AbstractMenuPageController[] $pages Menu pages
     *
     * @return AbstractMenuPageController[]
     */
    public function addMenuPage(array $pages): array
    {
        // Don't show staging menu on staging sites
        if (self::isStagingSite()) {
            return $pages;
        }

        $pages[]  = StagingPageController::getInstance();
        $pageSlug = StagingPageController::getInstance()->getMenuHookSuffix();
        add_action('admin_print_scripts-' . $pageSlug, [Bootstrap::class, 'enqueueScripts']);
        add_action('admin_print_scripts-' . $pageSlug, [self::class, 'enqueueScripts']);
        add_action('admin_print_styles-' . $pageSlug, [Bootstrap::class, 'enqueueStyles']);
        add_action('admin_print_styles-' . $pageSlug, [self::class, 'enqueueStyles']);

        return $pages;
    }

    /**
     * Check if current site is a staging site
     *
     * @return bool
     */
    public static function isStagingSite(): bool
    {
        return (bool) get_option(self::STAGING_MODE_OPTION, false);
    }

    /**
     * Check if a backup version is compatible with staging
     *
     * @param string $version Backup version to check
     *
     * @return bool
     */
    public static function isBackupVersionCompatible(string $version): bool
    {
        return SnapUtil::versionCompare($version, self::MIN_BACKUP_VERSION, '>=');
    }

    /**
     * Get staging data for current site (if it's a staging site)
     *
     * @return array<string, mixed>
     */
    public static function getStagingData(): array
    {
        return get_option(self::STAGING_OPTION_KEY, []);
    }

    /**
     * Get staging sites base path
     *
     * @return string
     */
    public static function getStagingBasePath(): string
    {
        return DUPLICATOR_SSDIR_PATH . '/' . self::STAGING_FOLDER_NAME;
    }

    /**
     * Get staging sites base URL
     *
     * @return string
     */
    public static function getStagingBaseUrl(): string
    {
        return DUPLICATOR_SSDIR_URL . '/' . self::STAGING_FOLDER_NAME;
    }

    /**
     * Return template file path
     *
     * @param string $path    Path to the template file
     * @param string $slugTpl Slug of the template
     *
     * @return string
     */
    public static function getTemplateFile(string $path, string $slugTpl): string
    {
        if (strpos($slugTpl, 'stagingaddon/') === 0) {
            return self::getAddonPath() . '/template/' . $slugTpl . '.php';
        }
        return $path;
    }

    /**
     * Register styles and scripts
     *
     * @return void
     */
    public static function registerJsCss(): void
    {
        if (wp_doing_ajax()) {
            return;
        }

        wp_register_style(
            'dupli-addon-staging',
            self::getAddonUrl() . "/assets/css/staging.css",
            ['dup-plugin-global-style'],
            DUPLICATOR_VERSION
        );

        wp_register_script(
            'dupli-addon-staging',
            self::getAddonUrl() . "/assets/js/staging.js",
            [
                'jquery',
                'dupli-vendor-bundle',
            ],
            DUPLICATOR_VERSION,
            true
        );
    }

    /**
     * Enqueue CSS Styles
     *
     * @return void
     */
    public static function enqueueStyles(): void
    {
        wp_enqueue_style('dupli-addon-staging');
    }

    /**
     * Enqueue JavaScript
     *
     * @return void
     */
    public static function enqueueScripts(): void
    {
        wp_enqueue_script('dupli-addon-staging');
    }

    /**
     * Get addon path
     *
     * @return string
     */
    public static function getAddonPath(): string
    {
        return __DIR__;
    }

    /**
     * Get addon file
     *
     * @return string
     */
    public static function getAddonFile(): string
    {
        return __FILE__;
    }

    /**
     * Filter out staging tables from database tables list
     *
     * Staging tables are always excluded from backups as they are temporary
     * installations and cannot be restored via the installer.
     *
     * @param string[] $tables List of database table names
     *
     * @return string[] Filtered list without staging tables
     */
    public static function filterStagingTables(array $tables): array
    {
        return array_filter($tables, function ($table): bool {
            return !preg_match(StagingEntity::STAGING_TABLE_PREFIX_PATTERN, $table);
        });
    }
}
