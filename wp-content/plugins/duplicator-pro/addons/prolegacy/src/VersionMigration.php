<?php

/**
 * Version options migration for backward compatibility
 *
 * @package   Duplicator\Addons\ProLegacy
 * @copyright (c) 2025, Snap Creek LLC
 */

declare(strict_types=1);

namespace Duplicator\Addons\ProLegacy;

/**
 * Handles migration of version-related options from old to new prefix
 *
 * This class provides fallback filters so the core can read old option values
 * during the transition period before migration runs.
 */
class VersionMigration
{
    const OLD_VERSION_OPT_KEY      = 'duplicator_pro_plugin_version';
    const OLD_INSTALL_INFO_OPT_KEY = 'duplicator_pro_install_info';
    const OLD_INSTALL_TIME_OPT_KEY = 'duplicator_pro_install_time';

    /**
     * Initialize fallback filters and migration hooks
     *
     * Must be called early in plugin boot, before upgrade checks run.
     *
     * @return void
     */
    public static function init(): void
    {
        add_filter('duplicator_stored_version', [__CLASS__, 'fallbackVersion']);
        add_filter('duplicator_stored_install_info', [__CLASS__, 'fallbackInstallInfo']);

        // Priority 3: run very early on duplicator_upgrade, leaving room for future additions
        add_action('duplicator_upgrade', [__CLASS__, 'migrate'], 3, 2);
    }

    /**
     * Fallback filter for version option
     *
     * If new option doesn't exist, return value from old option.
     *
     * @param string|false $version Version from new option or false
     *
     * @return string|false
     */
    public static function fallbackVersion($version)
    {
        if ($version === false) {
            return get_option(self::OLD_VERSION_OPT_KEY, false);
        }
        return $version;
    }

    /**
     * Fallback filter for install info option
     *
     * If new option doesn't exist, return value from old option.
     *
     * @param array<string,mixed>|false $installInfo Install info from new option or false
     *
     * @return array<string,mixed>|false
     */
    public static function fallbackInstallInfo($installInfo)
    {
        if ($installInfo === false) {
            return get_option(self::OLD_INSTALL_INFO_OPT_KEY, false);
        }
        return $installInfo;
    }

    /**
     * Migrate old version options to new names
     *
     * Called during upgrade process. Deletes old options after migration.
     * Idempotent - safe to run multiple times.
     *
     * @param false|string $currentVersion current Duplicator version
     * @param string       $newVersion     new Duplicator version
     *
     * @return void
     */
    public static function migrate($currentVersion = false, $newVersion = ''): void
    {
        // Version option: just delete old, new will be written by updateOptionVersion()
        delete_option(self::OLD_VERSION_OPT_KEY);

        // Install info: migrate value if needed
        $oldInstallInfo = get_option(self::OLD_INSTALL_INFO_OPT_KEY, false);
        $newInstallInfo = get_option(\Duplicator\Core\Upgrade\UpgradePlugin::DUP_INSTALL_INFO_OPT_KEY, false);

        if ($oldInstallInfo !== false && $newInstallInfo === false) {
            update_option(
                \Duplicator\Core\Upgrade\UpgradePlugin::DUP_INSTALL_INFO_OPT_KEY,
                $oldInstallInfo,
                false
            );
        }
        delete_option(self::OLD_INSTALL_INFO_OPT_KEY);

        // Cleanup legacy install time option
        delete_option(self::OLD_INSTALL_TIME_OPT_KEY);
    }
}
