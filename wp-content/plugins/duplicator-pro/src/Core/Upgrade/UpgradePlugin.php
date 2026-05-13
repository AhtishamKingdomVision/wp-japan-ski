<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Core\Upgrade;

use Duplicator\Utils\Logging\DupLog;

/**
 * Upgrade logic of plugin resides here
 */
class UpgradePlugin
{
    const DUP_VERSION_OPT_KEY      = 'dupli_opt_version';
    const DUP_INSTALL_INFO_OPT_KEY = 'dupli_opt_install_info';

    /**
     * Get stored plugin version with fallback filter for legacy support.
     *
     * ProLegacy addon hooks into 'duplicator_stored_version' filter to provide
     * fallback to old option name during transition.
     *
     * @return string|false
     */
    public static function getStoredVersion()
    {
        $version = get_option(self::DUP_VERSION_OPT_KEY, false);
        return apply_filters('duplicator_stored_version', $version);
    }

    /**
     * Get stored install info with fallback filter for legacy support.
     *
     * @return array{version:string,time:int,updateTime:int}|false
     */
    public static function getStoredInstallInfo()
    {
        $installInfo = get_option(self::DUP_INSTALL_INFO_OPT_KEY, false);
        return apply_filters('duplicator_stored_install_info', $installInfo);
    }

    /**
     * Perform activation action.
     *
     * @return void
     */
    public static function onActivationAction(): void
    {
        // Register upgrade hooks before performing upgrade
        UpgradeFunctions::init();

        $currentVersion = self::getStoredVersion();
        $newVersion     = DUPLICATOR_VERSION;

        // NOTE: DupLog::trace() cannot be called before updateDatabase() runs at priority 9
        // because TraceLogMng requires DynamicGlobalEntity which needs the database tables.
        // Upgrade logging is done in UpgradeFunctions::updateDatabase() after tables are created.

        do_action('duplicator_upgrade', $currentVersion, $newVersion);

        DupLog::trace("PLUGIN UPGRADED TO VERSION: " . $newVersion);

        do_action('duplicator_after_activation', $currentVersion, $newVersion);
    }

    /**
     * Update install info.
     *
     * @param false|string $oldVersion The last/previous installed version, is empty for new installs
     *
     * @return array{version:string,time:int,updateTime:int}
     */
    public static function setInstallInfo($oldVersion = ''): array
    {
        UpgradeFunctions::setInstallInfo($oldVersion, DUPLICATOR_VERSION);
        /** @var array{version:string,time:int,updateTime:int} */
        return self::getStoredInstallInfo();
    }

    /**
     * Get install info.
     *
     * @return array{version:string,time:int,updateTime:int}
     */
    public static function getInstallInfo()
    {
        $installInfo = self::getStoredInstallInfo();
        if ($installInfo === false) {
            $installInfo = self::setInstallInfo();
        }
        return $installInfo;
    }
}
