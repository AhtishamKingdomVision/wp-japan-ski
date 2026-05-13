<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Models\Storages;

use Duplicator\Utils\Logging\DupLog;
use Duplicator\Package\Storage\UploadInfo;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Models\Storages\Local\DefaultLocalStorage;
use Duplicator\Models\Storages\Local\LocalStorage;
use Duplicator\Package\AbstractPackage;
use Exception;

class StoragesUtil
{
    /** @var AbstractStorageEntity[] */
    protected static $storagesForCryptUpdate = [];

    /**
     * Init Default storage.
     * Create default storage if not exists.
     *
     * @return bool true if success false otherwise
     */
    public static function initDefaultStorage(): bool
    {
        $storage = self::getDefaultStorage();
        if ($storage->save() === false) {
            DupLog::trace("Error saving default storage");
            return false;
        }
        if ($storage->initStorageDirectory() === false) {
            DupLog::trace("Error init default storage directory");
            return false;
        }
        return true;
    }

    /**
     * Get default local storage, if don't exists create it
     *
     * @return DefaultLocalStorage
     */
    public static function getDefaultStorage()
    {
        static $defaultStorage = null;

        if ($defaultStorage === null) {
            if (($storages = AbstractStorageEntity::getAll()) !== false) {
                foreach ($storages as $storage) {
                    if ($storage->getSType() !== DefaultLocalStorage::getSType()) {
                        continue;
                    }
                    /** @var DefaultLocalStorage */
                    $defaultStorage = $storage;
                    break;
                }
            }

            if (is_null($defaultStorage)) {
                $defaultStorage = new DefaultLocalStorage();
                $defaultStorage->save();
            }
        }

        return $defaultStorage;
    }

    /**
     * Get default local storage id
     *
     * @return int
     */
    public static function getDefaultStorageId(): int
    {
        return self::getDefaultStorage()->getId();
    }

    /**
     * Get default new storage
     *
     * @return LocalStorage
     */
    public static function getDefaultNewStorage(): LocalStorage
    {
        return new LocalStorage();
    }

    /**
     * Process the Backup
     *
     * @param AbstractPackage $package     The Backup to process
     * @param UploadInfo      $upload_info The upload info
     *
     * @return void
     */
    public static function processPackage(AbstractPackage $package, UploadInfo $upload_info): void
    {
        $package->active_storage_id = $upload_info->getStorageId();
        $storage                    = $upload_info->getStorage();
        if ($storage->isValid() === false) {
            DupLog::infoTrace("Storage id {$package->active_storage_id} is invalid or not found for Backup {$package->getId()}");
            // Record the failure and persist it so the MAX_STORAGE_RETRIES threshold
            // can stop the cron from retrying an invalid storage indefinitely.
            $upload_info->increaseFailureCount();
            $package->update();
            return;
        }

        DupLog::infoTrace('** ' . strtoupper($storage->getStypeName()) . " [Name: {$storage->getName()}] [ID: {$package->active_storage_id}] **");

        if (!$upload_info->isDownloadFromRemote()) {
            $storage->copyFromDefault($package, $upload_info);
        } else {
            $storage->copyToDefault($package, $upload_info);

            if (!$upload_info->hasCompleted(true) || $package->isCancelPending()) {
                return;
            }

            $defaultStorage = StoragesUtil::getDefaultStorage();
            $defaultStorage->purgeOldPackages([$package->Archive->getFileName()]);

            $defaultLocalUploadInfo                   = new UploadInfo($defaultStorage->getId());
            $defaultLocalUploadInfo->copied_installer = true;
            $defaultLocalUploadInfo->copied_archive   = true;

            foreach ($package->upload_infos as $k => $uploadInfo) {
                if ($uploadInfo->getStorageId() == $defaultStorage->getId()) {
                    $package->upload_infos[$k] = $defaultLocalUploadInfo;
                    $package->update();
                    return;
                }
            }

            //insert at beginning
            array_unshift($package->upload_infos, $defaultLocalUploadInfo);
            $package->update();
        }
    }

    /**
     * Sort storages with default first other by id
     *
     * @param AbstractStorageEntity $a Storage a
     * @param AbstractStorageEntity $b Storage b
     *
     * @return int
     */
    public static function sortDefaultFirst(AbstractStorageEntity $a, AbstractStorageEntity $b): int
    {
        if ($a->getId() == $b->getId()) {
            return 0;
        }
        if ($a->getSType() == DefaultLocalStorage::getSType()) {
            return -1;
        }
        if ($b->getSType() == DefaultLocalStorage::getSType()) {
            return 1;
        }
        return ($a->getId() < $b->getId()) ? -1 : 1;
    }

    /**
     * Sort storages by priority, type and id
     *
     * @param AbstractStorageEntity $a Storage a
     * @param AbstractStorageEntity $b Storage b
     *
     * @return int
     */
    public static function sortByPriority(AbstractStorageEntity $a, AbstractStorageEntity $b): int
    {
        $aPriority = $a->getPriority();
        $bPriority = $b->getPriority();

        if ($aPriority == $bPriority) {
            if ($a->getSType() == $b->getSType()) {
                return $a->getId() <=> $b->getId();
            } else {
                return ($a->getSType() < $b->getSType()) ? -1 : 1;
            }
        }

        return ($aPriority < $bPriority) ? -1 : 1;
    }

    /**
     * Get local storages paths
     *
     * @return string[]
     */
    public static function getLocalStoragesPaths()
    {
        static $paths = null;
        if (!is_null($paths)) {
            return $paths;
        }

        $paths = [];
        if (($storages = AbstractStorageEntity::getAll()) !== false) {
            foreach ($storages as $storage) {
                if (!$storage->isLocal()) {
                    continue;
                }
                $paths[] = $storage->getLocationString();
            }
        }
        return $paths;
    }

    /**
     * Is local storage child path
     *
     * @param string $path Path to check
     *
     * @return bool
     */
    public static function isLocalStorageChildPath($path): bool
    {
        foreach (self::getLocalStoragesPaths() as $storagePath) {
            if (SnapIO::isChildPath($path, $storagePath)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Register all storages
     *
     * @return void
     */
    public static function registerTypes(): void
    {
        UnknownStorage::registerType();
        LocalStorage::registerType();
        DefaultLocalStorage::registerType();
        /** @todo move types on hook action */
        do_action('duplicator_register_storage_types');

        add_action('duplicator_before_update_crypt_setting', [self::class, 'beforeCryptUpdateSettings']);
        add_action('duplicator_after_update_crypt_setting', [self::class, 'afterCryptUpdateSettings']);
    }

    /**
     * Render storages global options
     *
     * @return void
     */
    public static function renderGlobalOptions(): void
    {
        foreach (AbstractStorageEntity::getResisteredTypes() as $type) {
            $class = AbstractStorageEntity::getSTypePHPClass($type);
            if (!class_exists($class)) {
                continue;
            }
            call_user_func([$class, 'renderGlobalOptions']);
        }
    }

    /**
     * Before crypt update settings
     *
     * @return void
     */
    public static function beforeCryptUpdateSettings(): void
    {
        self::$storagesForCryptUpdate = AbstractStorageEntity::getAll();
    }

    /**
     * After crypt update settings
     *
     * @return void
     */
    public static function afterCryptUpdateSettings(): void
    {
        foreach (self::$storagesForCryptUpdate as $storage) {
            $storage->save();
        }
        self::$storagesForCryptUpdate = [];
    }

    /**
     * Removed double default storages
     *
     * @return int[] Ids of removed storages
     */
    public static function removeDoubleDefaultStorages()
    {
        global $wpdb;

        try {
            $doubleStorageIds = [];
            $defaultStorageId = self::getDefaultStorageId();
            foreach (AbstractStorageEntity::getAll() as $storage) {
                if (!$storage->isDefault() || $storage->getId() === $defaultStorageId) {
                    continue;
                }

                $doubleStorageIds[] = $storage->getId();
            }

            if (count($doubleStorageIds) > 0) {
                $query = "DELETE FROM " . AbstractStorageEntity::getTableName() . " WHERE id IN (" . implode(',', $doubleStorageIds) . ")";
                if ($wpdb->query($query) === false) {
                    throw new Exception("Error executing query to remove double default storages");
                }
            }
        } catch (Exception $e) {
            DupLog::trace("Error removing double default storages: " . $e->getMessage());
            return [];
        }

        return $doubleStorageIds;
    }


    /**
     * Check storages ids
     *
     * @param int[] $ids   Array of storage IDs to check
     * @param bool  $all   If true the function return true only if all storages are valid
     * @param bool  $force If true force remote storages check
     *
     * @return bool True if schedule has at least one valid storage, false otherwise
     */
    public static function hasValidStorage(array $ids, $all = false, $force = false): bool
    {
        if (count($ids) == 0) {
            return false;
        }

        // Check each assigned storage
        foreach ($ids as $id) {
            $storage = AbstractStorageEntity::getById($id);
            if ($storage !== false && $storage->isSupported() && $storage->isValid($force)) {
                if ($all == false) {
                    return true;
                }
            } else {
                if ($storage instanceof AbstractStorageEntity) {
                    DupLog::trace(
                        'STORAGE CHECK FAIL ON STORAGE ' . $storage->getName() .
                        ' ID: ' . $storage->getId() .  " TYPE:" . $storage->getStypeName()
                    );
                } else {
                    DupLog::trace('STORAGE CHECK UNKNOWN STORAGE ID: ' . $id);
                }
                if ($all == true) {
                    return false;
                }
            }
        }
        return ($all ? true : false);
    }
}
