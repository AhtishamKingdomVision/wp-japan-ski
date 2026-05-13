<?php

namespace Duplicator\Addons\GDriveAddon\Utils;

use Duplicator\Addons\GDriveAddon\GDriveAddon;
use Duplicator\Utils\AbstractAutoloader;

class Autoloader extends AbstractAutoloader
{
    const VENDOR_PATH = GDriveAddon::ADDON_PATH . '/vendor-prefixed/';
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
        $googleNS = 'Google\\'; // This to avoid StringClassNameToClassConstantRector replace rules
        return [
            self::ROOT_VENDOR . 'Firebase\\JWT'       => self::VENDOR_PATH . 'firebase/php-jwt/src/',
            self::ROOT_VENDOR . $googleNS . 'Service' => self::VENDOR_PATH . 'google/apiclient-services/src',
            self::ROOT_VENDOR . $googleNS . 'Auth'    => self::VENDOR_PATH . 'google/auth/src',
            self::ROOT_VENDOR . 'Google'              => self::VENDOR_PATH . 'google/apiclient/src',
            self::ROOT_VENDOR . 'Monolog'             => self::VENDOR_PATH . 'monolog/monolog/src/Monolog',
        ];
    }
}
