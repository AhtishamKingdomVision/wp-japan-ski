<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Core\Upgrade;

use Duplicator\Addons\ProBase\Models\LicenseData;
use Duplicator\Core\CapMng;
use Duplicator\Core\Models\AbstractEntity;
use Duplicator\Core\UniqueId;
use Duplicator\Models\ActivityLog\AbstractLogEvent;
use Duplicator\Models\BrandEntity;
use Duplicator\Models\DynamicGlobalEntity;
use Duplicator\Models\GlobalEntity;
use Duplicator\Models\ScheduleEntity;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\Storages\StoragesUtil;
use Duplicator\Models\SystemGlobalEntity;
use Duplicator\Models\TemplateEntity;
use Duplicator\Package\AbstractPackage;
use Duplicator\Utils\Crypt\CryptBlowfish;
use Duplicator\Utils\Logging\DupLog;
use Throwable;

/**
 * Utility class managing when the plugin is updated
 */
class UpgradeFunctions
{
    /**
     * Initialize upgrade hooks
     *
     * Priority scheme:
     * - 10: initTables (creates database tables)
     * - 20: initCapabilities
     * - 50: initEntities (initializes default entities - plugin semi-initialized after this)
     * - 100: initSecureKey (new installations only)
     * - 200: initUniqueId
     * - 1000: updateOptionVersion
     * - 1001: setInstallInfo
     * - 10000: resaveAllEntities (always last)
     *
     * @return void
     */
    public static function init(): void
    {
        add_action('duplicator_upgrade', [self::class, 'initTables'], 10, 2);
        add_action('duplicator_upgrade', [self::class, 'initCapabilities'], 20, 2);
        add_action('duplicator_upgrade', [self::class, 'initEntities'], 50, 2);
        add_action('duplicator_upgrade', [self::class, 'initSecureKey'], 100, 2);
        add_action('duplicator_upgrade', [self::class, 'initUniqueId'], 200, 2);
        add_action('duplicator_upgrade', [self::class, 'updateOptionVersion'], 1000, 2);
        add_action('duplicator_upgrade', [self::class, 'setInstallInfo'], 1001, 2);
        add_action('duplicator_upgrade', [self::class, 'resaveAllEntities'], 10000, 2);
    }

    /**
     * Initialize capabilities manager
     *
     * Must run AFTER migrateCapabilities (priority 3) from ProLegacy addon
     * which migrates old capability names to new ones.
     *
     * @param false|string $currentVersion current Duplicator version
     * @param string       $newVersion     new Duplicator version
     *
     * @return void
     */
    public static function initCapabilities($currentVersion, $newVersion): void
    {
        CapMng::getInstance();
    }

    /**
     * Initialize database tables.
     *
     * Creates/updates database table structure.
     *
     * @param false|string $currentVersion current Duplicator version
     * @param string       $newVersion     new Duplicator version
     *
     * @return void
     */
    public static function initTables($currentVersion = false, $newVersion = ''): void
    {
        AbstractEntity::initTable();
        AbstractLogEvent::initTable();
        AbstractPackage::initTable();
    }

    /**
     * Initialize default entities and Default storage.
     *
     * Creates default entities if they don't exist.
     *
     * @param false|string $currentVersion current Duplicator version
     * @param string       $newVersion     new Duplicator version
     *
     * @return void
     */
    public static function initEntities($currentVersion = false, $newVersion = ''): void
    {
        GlobalEntity::getInstance()->save();
        DynamicGlobalEntity::getInstance()->save();
        StoragesUtil::initDefaultStorage();
        TemplateEntity::createDefault();
        TemplateEntity::getManualTemplate(); // If not exists, create it

        // Log upgrade info (safe to call now that tables exist)
        if ($currentVersion === false) {
            DupLog::trace("PLUGIN INSTALLED: VERSION " . $newVersion);
        } else {
            DupLog::trace("PLUGIN UPGRADED FROM VERSION: " . $currentVersion . " TO " . $newVersion);
        }
    }

    /**
     * Initialize DUP SECURE KEY if not exists
     *
     * @param false|string $currentVersion current Duplicator version
     * @param string       $newVersion     new Duplicator version
     *
     * @return void
     */
    public static function initSecureKey($currentVersion, $newVersion): void
    {
        if ($currentVersion === false) {
            // New installation - create key without forcing
            CryptBlowfish::createWpConfigSecureKey(false, false);
        }
    }

    /**
     * Init Unique identifier
     *
     * @param false|string $currentVersion current Duplicator version
     * @param string       $newVersion     new Duplicator version
     *
     * @return void
     */
    public static function initUniqueId($currentVersion, $newVersion): void
    {
        // Initialize the identifier
        UniqueId::getInstance();
    }

    /**
     * Update option version.
     *
     * @param false|string $currentVersion current Duplicator version
     * @param string       $newVersion     new Duplicator version
     *
     * @return void
     */
    public static function updateOptionVersion($currentVersion, $newVersion): void
    {
        if (update_option(UpgradePlugin::DUP_VERSION_OPT_KEY, DUPLICATOR_VERSION, true) === false) {
            DupLog::trace("Couldn't update " . UpgradePlugin::DUP_VERSION_OPT_KEY . " so deleting it.");

            delete_option(UpgradePlugin::DUP_VERSION_OPT_KEY);

            if (update_option(UpgradePlugin::DUP_VERSION_OPT_KEY, DUPLICATOR_VERSION, true) === false) { // @phpstan-ignore-line
                DupLog::trace("Still couldn\'t update the option!");
            } else { // @phpstan-ignore-line
                DupLog::trace("Option updated.");
            }
        }
    }

    /**
     * Update install info.
     *
     * @param false|string $currentVersion current Duplicator version
     * @param string       $newVersion     new Duplicator version
     *
     * @return void
     */
    public static function setInstallInfo($currentVersion, $newVersion): void
    {
        $installInfo = UpgradePlugin::getStoredInstallInfo();

        if (empty($currentVersion) || $installInfo === false) {
            // If is new installation or install info is not set generate new install info
            $installInfo = [
                'version'    => DUPLICATOR_VERSION,
                'time'       => time(),
                'updateTime' => time(),
            ];
        } else {
            $installInfo['updateTime'] = time();
        }

        delete_option(UpgradePlugin::DUP_INSTALL_INFO_OPT_KEY);
        update_option(UpgradePlugin::DUP_INSTALL_INFO_OPT_KEY, $installInfo, false);
    }

    /**
     * Re-save all entities to update serialization format and version entity
     *
     * @param false|string $currentVersion current Duplicator version
     * @param string       $newVersion     new Duplicator version
     *
     * @return void
     */
    public static function resaveAllEntities($currentVersion, $newVersion): void
    {
        $savedCount = 0;
        $errors     = [];

        // Singleton entity classes - use ::class for lazy resolution
        $singletonClasses = [
            GlobalEntity::class,
            DynamicGlobalEntity::class,
            SystemGlobalEntity::class,
            LicenseData::class,
        ];

        foreach ($singletonClasses as $class) {
            try {
                $class::getInstance()->save();
                $savedCount++;
            } catch (Throwable $e) {
                $errors[] = "{$class}: " . $e->getMessage();
            }
        }

        // List entity classes - use ::class for lazy resolution
        $listEntityClasses = [
            TemplateEntity::class,
            ScheduleEntity::class,
            AbstractStorageEntity::class,
            BrandEntity::class,
        ];

        foreach ($listEntityClasses as $class) {
            try {
                foreach ($class::getAll() as $entity) {
                    $entity->save();
                    $savedCount++;
                }
            } catch (Throwable $e) {
                $errors[] = "{$class}: " . $e->getMessage();
            }
        }

        // Log any errors that occurred
        if (!empty($errors)) {
            DupLog::trace("UPGRADE: Errors during entity resave: " . implode('; ', $errors));
        }

        DupLog::trace("UPGRADE: Resaved {$savedCount} entities");
    }
}
