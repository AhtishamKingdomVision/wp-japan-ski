<?php

declare(strict_types=1);

namespace Duplicator\Addons\StagingAddon;

use WP_Admin_Bar;
use Duplicator\Core\CapMng;
use Duplicator\Core\Views\TplMng;
use Duplicator\Installer\Models\MigrateData;

/**
 * Handles post-installation logic for staging sites
 */
class StagingSiteHandler
{
    /** @var string[] Capabilities disabled on staging sites */
    const DISABLED_CAPS = [
        CapMng::CAP_IMPORT,
        CapMng::CAP_STORAGE,
        CapMng::CAP_SCHEDULE,
    ];

    /**
     * Initialize staging site restrictions and UI elements
     *
     * @return void
     */
    public function init(): void
    {
        // Disable capabilities not needed on staging sites
        add_filter('duplicator_cap_enabled', [$this, 'filterCapabilities'], 10, 2);

        // Block all emails
        add_filter('wp_mail', [$this, 'blockEmails'], 999);

        // Add admin bar indicator
        add_action('admin_bar_menu', [$this, 'addAdminBarIcon'], 100);

        // Add admin notice
        add_action('admin_notices', [$this, 'showNotice']);

        // Enqueue staging styles for admin bar icon
        add_action('admin_enqueue_scripts', [$this, 'enqueueStyles']);

        // Enqueue staging styles for front-end admin bar icon
        add_action('wp_enqueue_scripts', [$this, 'enqueueStyles']);
    }

    /**
     * Configure staging site after installation
     *
     * Sets up staging metadata, disables search indexing, applies color scheme
     *
     * @param MigrateData $migrationData Migration data
     *
     * @return void
     */
    public function configure(MigrateData $migrationData): void
    {
        $staging = $migrationData->staging;
        if (empty($staging['enabled'])) {
            return;
        }

        // Store staging metadata in options
        $stagingData = [
            'mainSiteUrl'    => $staging['mainSiteUrl'] ?? '',
            'stagingPageUrl' => $staging['pageUrl'] ?? '',
            'createdAt'      => current_time('mysql'),
            'identifier'     => $staging['identifier'] ?? '',
            'colorScheme'    => $staging['colorScheme'] ?? 'fresh',
            'stagingTitle'   => $staging['title'] ?? '',
        ];

        update_option(StagingAddon::STAGING_OPTION_KEY, $stagingData);
        update_option(StagingAddon::STAGING_MODE_OPTION, true);

        // Discourage search engines
        update_option('blog_public', '0');

        // Apply staging title as site name
        if (!empty($staging['title'])) {
            update_option('blogname', $staging['title']);
        }

        // Apply color scheme to all admin users
        $this->applyColorScheme($staging['colorScheme'] ?? 'fresh');
    }

    /**
     * Apply color scheme to all admin users on staging site
     *
     * @param string $colorScheme WordPress admin color scheme
     *
     * @return void
     */
    protected function applyColorScheme(string $colorScheme): void
    {
        $colorScheme = self::sanitizeColorScheme($colorScheme);

        $adminUsers = get_users([
            'role__in' => ['administrator'],
            'fields'   => 'ID',
        ]);

        foreach ($adminUsers as $userId) {
            update_user_meta($userId, 'admin_color', $colorScheme);
        }
    }

    /**
     * Get valid WordPress admin color schemes
     *
     * Uses WordPress global $_wp_admin_css_colors which contains all registered schemes.
     *
     * @return string[]
     */
    public static function getValidColorSchemes(): array
    {
        global $_wp_admin_css_colors;

        return is_array($_wp_admin_css_colors) ? array_keys($_wp_admin_css_colors) : [];
    }

    /**
     * Validate and sanitize a color scheme value
     *
     * @param string $colorScheme Color scheme to validate
     *
     * @return string Valid color scheme (defaults to 'fresh' if invalid)
     */
    public static function sanitizeColorScheme(string $colorScheme): string
    {
        return in_array($colorScheme, self::getValidColorSchemes(), true) ? $colorScheme : 'fresh';
    }

    /**
     * Filter capabilities to disable pages not needed on staging sites
     *
     * @param bool   $enabled Whether the capability is enabled
     * @param string $cap     The capability being checked
     *
     * @return bool
     */
    public function filterCapabilities(bool $enabled, string $cap): bool
    {
        if (in_array($cap, self::DISABLED_CAPS, true)) {
            return false;
        }

        return $enabled;
    }

    /**
     * Block emails on staging site
     *
     * @param array<string, mixed> $args Email arguments
     *
     * @return array<string, mixed>
     */
    public function blockEmails(array $args): array
    {
        // Return empty 'to' to prevent email from being sent
        $args['to'] = '';
        return $args;
    }

    /**
     * Add staging indicator to admin bar
     *
     * @param WP_Admin_Bar $adminBar Admin bar instance
     *
     * @return void
     */
    public function addAdminBarIcon(WP_Admin_Bar $adminBar): void
    {
        $stagingData = StagingAddon::getStagingData();

        TplMng::getInstance()->render('stagingaddon/staging_adminbar', [
            'adminBar'       => $adminBar,
            'mainSiteUrl'    => $stagingData['mainSiteUrl'] ?? '',
            'identifier'     => $stagingData['identifier'] ?? '',
            'stagingPageUrl' => $stagingData['stagingPageUrl'] ?? '',
            'createdAt'      => $stagingData['createdAt'] ?? '',
        ]);
    }

    /**
     * Show staging site admin notice
     *
     * @return void
     */
    public function showNotice(): void
    {
        $stagingData = StagingAddon::getStagingData();

        TplMng::getInstance()->render('stagingaddon/staging_notice', [
            'mainSiteUrl' => $stagingData['mainSiteUrl'] ?? '',
        ]);
    }

    /**
     * Enqueue styles for staging site admin bar
     *
     * @return void
     */
    public function enqueueStyles(): void
    {
        wp_enqueue_style(
            'dupli-addon-staging-site',
            StagingAddon::getAddonUrl() . '/assets/css/staging-site.css',
            [],
            DUPLICATOR_VERSION
        );
    }
}
