<?php

/**
 * Auloader calsses
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

 namespace Duplicator\Addons\AmazonS3Addon\Utils;

use Duplicator\Addons\AmazonS3Addon\AmazonS3Addon;
use Duplicator\Utils\AbstractAutoloader;

/**
 * Autoloader calss, dont user Duplicator library here
 */
final class Autoloader extends AbstractAutoloader
{
    const VENDOR_PATH = AmazonS3Addon::ADDON_PATH . '/vendor-prefixed/';

    /**
     * Register autoloader function
     *
     * @return void
     */
    public static function register(): void
    {
        spl_autoload_register([self::class, 'load']);

        self::loadFiles();
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

    /**
     * Load necessary files
     *
     * @return void
     */
    private static function loadFiles(): void
    {
        $files = [
            '/mtdowling/jmespath.php/src/JmesPath.php',
            '/aws/aws-sdk-php/src/functions.php',
        ];

        foreach ($files as $file) {
            require_once self::VENDOR_PATH . $file;
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
            self::ROOT_VENDOR . 'JmesPath' => AmazonS3Addon::getAddonPath() . '/vendor-prefixed/mtdowling/jmespath.php/src',
            self::ROOT_VENDOR . 'Aws'      => AmazonS3Addon::getAddonPath() . '/vendor-prefixed/aws/aws-sdk-php/src',
        ];
    }
}
