<?php

namespace Duplicator\Addons\DropboxAddon;

use Duplicator\Addons\DropboxAddon\Models\DropboxStorage;
use Duplicator\Addons\DropboxAddon\Utils\Autoloader;
use Duplicator\Core\Addons\AbstractAddonCore;
use Duplicator\Models\Storages\AbstractStorageEntity;

class DropboxAddon extends AbstractAddonCore
{
    const ADDON_PATH = __DIR__;

    /**
     * @return void
     */
    public function init(): void
    {
        Autoloader::register();

        add_action('duplicator_register_storage_types', [$this, 'registerStorages']);
        add_filter('duplicator_template_file', [self::class, 'getTemplateFile'], 10, 2);
        add_filter('duplicator_usage_stats_storages_infos', [self::class, 'getStorageUsageStats'], 10);
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
        if (strpos($slugTpl, 'dropboxaddon/') === 0) {
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

        $storageNums['storages_dropbox_count'] = 0;

        foreach ($storages as $storage) {
            if ($storage->getSType() === DropboxStorage::getSType()) {
                $storageNums['storages_dropbox_count']++;
            }
        }

        return $storageNums;
    }

    /**
     * Register storages
     *
     * @return void
     */
    public function registerStorages(): void
    {
        DropboxStorage::registerType();
    }

    /**
     *
     * @return string
     */
    public static function getAddonPath(): string
    {
        return self::ADDON_PATH;
    }

    /**
     *
     * @return string
     */
    public static function getAddonFile(): string
    {
        return __FILE__;
    }
}
