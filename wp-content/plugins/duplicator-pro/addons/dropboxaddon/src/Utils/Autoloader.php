<?php

namespace Duplicator\Addons\DropboxAddon\Utils;

use Duplicator\Addons\DropboxAddon\DropboxAddon;
use Duplicator\Utils\AbstractAutoloader;

class Autoloader extends AbstractAutoloader
{
    const VENDOR_PATH = DropboxAddon::ADDON_PATH . '/vendor-prefixed/';
    /**
     * Register autoloader function
     *
     * @return void
     */
    public static function register(): void
    {
        spl_autoload_register([self::class, 'load']);
    }

    /**
     * Load class
     *
     * @param string $className class name
     *
     * @return void
     */
    public static function load($className): void
    {
        if (strpos($className, self::ROOT_VENDOR) === 0) {
            foreach (self::getNamespacesVendorMapping() as $namespace => $mappedPath) {
                if (strpos($className, (string) $namespace) !== 0) {
                    continue;
                }

                $filepath = self::getFilenameFromClass($className, $namespace, $mappedPath);
                if (file_exists($filepath)) {
                    include $filepath;
                    return;
                }
            }
        }
    }

    /**
     * Return namespace mapping
     *
     * @return string[]
     */
    protected static function getNamespacesVendorMapping(): array
    {
        return [
            self::ROOT_VENDOR . 'GrahamCampbell\GuzzleFactory' => self::VENDOR_PATH . 'graham-campbell/guzzle-factory/src',
            self::ROOT_VENDOR . 'Spatie\\Dropbox'              => self::VENDOR_PATH . 'spatie/dropbox-api/src',
        ];
    }
}
