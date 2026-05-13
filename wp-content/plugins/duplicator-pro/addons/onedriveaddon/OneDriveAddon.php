<?php

namespace Duplicator\Addons\OneDriveAddon;

use Duplicator\Addons\OneDriveAddon\Models\OneDriveStorage;
use Duplicator\Core\Addons\AbstractAddonCore;
use Duplicator\Models\Storages\AbstractStorageEntity;

class OneDriveAddon extends AbstractAddonCore
{
    const ADDON_PATH = __DIR__;

    /**
     * @return void
     */
    public function init(): void
    {
        add_action('duplicator_register_storage_types', [$this, 'registerStorages']);
        add_filter('duplicator_template_file', [self::class, 'getTemplateFile'], 10, 2);
        add_filter('duplicator_usage_stats_storages_infos', [self::class, 'getStorageUsageStats'], 10);
    }

    /**
     * Register storages
     *
     * @return void
     *
     * @throws \Exception
     */
    public function registerStorages(): void
    {
        OneDriveStorage::registerType();
    }

    /**
     * Return template file path
     *
     * @param string $path    path to the template file
     * @param string $slugTpl slug of the template
     *
     * @return string
     */
    public static function getTemplateFile($path, $slugTpl)
    {
        if (strpos($slugTpl, 'onedriveaddon/') === 0) {
            return self::getAddonPath() . '/template/' . $slugTpl . '.php';
        }
        return $path;
    }

    /**
     * Get storage usage stats
     *
     * @param array<string,int> $storageNums Storages num
     *
     * @return array<string,int>
     */
    public static function getStorageUsageStats($storageNums)
    {
        if (($storages = AbstractStorageEntity::getAll()) === false) {
            $storages = [];
        }

        $storageNums['storages_onedrive_count'] = 0;

        foreach ($storages as $storage) {
            if ($storage->getSType() === OneDriveStorage::getSType()) {
                $storageNums['storages_onedrive_count']++;
            }
        }

        return $storageNums;
    }

    /**
     * @return string
     */
    public static function getAddonPath()
    {
        return static::ADDON_PATH;
    }

    /**
     * @return string
     */
    public static function getAddonFile(): string
    {
        return __FILE__;
    }
}
