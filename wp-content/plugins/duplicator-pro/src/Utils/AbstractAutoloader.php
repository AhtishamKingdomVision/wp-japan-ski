<?php

/**
 * Auloader calsses
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Utils;

/**
 * Autoloader calss, dont user Duplicator library here
 */
abstract class AbstractAutoloader
{
    const ROOT_NAMESPACE                 = 'Duplicator\\';
    const ROOT_INSTALLER_NAMESPACE       = self::ROOT_NAMESPACE . 'Installer\\';
    const ROOT_ADDON_NAMESPACE           = self::ROOT_NAMESPACE . 'Addons\\';
    const ROOT_ADDON_INSTALLER_NAMESPACE = self::ROOT_INSTALLER_NAMESPACE . 'Addons\\';
    const ROOT_VENDOR                    = 'VendorDuplicator\\';

    /**
     * Register autoloader function
     *
     * @return void
     */
    public static function register()
    {
        throw new \Exception('AbstractAutoloader::register() must be implemented in child class');
    }

    /**
     * Return PHP file full class from class name
     *
     * @param string $class      Name of class
     * @param string $namespace  Base namespace
     * @param string $mappedPath Base path
     *
     * @return string
     */
    protected static function getFilenameFromClass($class, $namespace, $mappedPath)
    {
        $subPath = str_replace('\\', '/', substr($class, strlen($namespace))) . '.php';
        $subPath = ltrim($subPath, '/');
        return rtrim($mappedPath, '\\/') . '/' . $subPath;
    }

    /**
     * Return addon file by class
     *
     * @param string $class class name
     *
     * @return false|string
     */
    protected static function getAddonFile($class)
    {
        $matches = null;
        if (preg_match('/^\\\\?Duplicator(?:\\\\Installer)?\\\\Addons\\\\(.+?)\\\\(.+)$/', $class, $matches) !== 1) {
            return false;
        }

        $addonName         = $matches[1];
        $subClass          = $matches[2];
        $basePath          = DUPLICATOR____PATH . '/addons/' . strtolower($addonName) . '/';
        $basePathSecondary = DUPLICATOR_SSDIR_PATH_ADDONS . '/' . strtolower($addonName) . '/';

        if (strpos($class, self::ROOT_ADDON_INSTALLER_NAMESPACE) === 0) {
            $basePath .= 'installer/' . strtolower($addonName) . '/';
        }

        if (self::endsWith($class, $addonName) === false) {
            $basePath .= 'src/';
        }
        $filePhp = $basePath . str_replace('\\', '/', $subClass) . '.php';
        if (file_exists($filePhp)) {
            return $filePhp;
        }
        if (self::endsWith($class, $addonName) === false) {
            $basePathSecondary .= 'src/';
        }
        $filePhp = $basePathSecondary . str_replace('\\', '/', $subClass) . '.php';
        if (file_exists($filePhp)) {
            return $filePhp;
        }
        return false;
    }

    /**
     * Returns true if the $haystack string end with the $needle, only for internal use
     *
     * @param string $haystack The full string to search in
     * @param string $needle   The string to for
     *
     * @return bool Returns true if the $haystack string starts with the $needle
     */
    protected static function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }

        return (substr($haystack, -$length) === $needle);
    }
}
