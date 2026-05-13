<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2024, Snap Creek LLC
 */

namespace Duplicator\Libs\Snap;

/**
 * open_basedir utilities class
 */
class SnapOpenBasedir
{
    /**
     * Check if php.ini open_basedir is enabled
     *
     * @return bool true if open_basedir is set
     */
    public static function isEnabled(): bool
    {
        $iniVar = SnapUtil::phpIniGet("open_basedir", '');
        return (strlen($iniVar) > 0);
    }

    /**
     * Get open_basedir list paths
     *
     * @return string[] Paths contained in the open_basedir setting. Empty array if the setting is not enabled.
     */
    public static function getPaths(): array
    {
        $baseDirStr = SnapUtil::phpIniGet("open_basedir", '');
        if (strlen($baseDirStr) === 0) {
            return [];
        }
        return explode(PATH_SEPARATOR, $baseDirStr);
    }

    /**
     * Get open base dir root path of path
     *
     * @param string $path file path
     *
     * @return false|string Path to the base dir of $path if it exists, otherwise false
     */
    public static function getRootOfPath(string $path)
    {
        foreach (self::getPaths() as $allowedPath) {
            $allowedPath = $allowedPath !== "/" ? SnapIO::safePathUntrailingslashit($allowedPath) : "/";
            if (strpos($path, $allowedPath) === 0) {
                return $allowedPath;
            }
        }

        return false;
    }

    /**
     * Check if open_basedir is enabled and if the path is allowed
     *
     * @param string $path The path to check
     *
     * @return bool True if the path is allowed or open_basedir is not enabled
     */
    public static function isPathValid(string $path): bool
    {
        if (!self::isEnabled()) {
            return true;
        }

        $hadOpenBasedirError = false;

        // Use a custom error handler to catch warnings caused by open_basedir restrictions.
        // This allows us to detect when is_link fails due to these restrictions.
        set_error_handler(function ($errno, $errstr) use (&$hadOpenBasedirError): bool {
            if (strpos($errstr, 'open_basedir restriction in effect') !== false) {
                $hadOpenBasedirError = true;
                return true;
            }

            // For other errors.
            return false;
        });

        try {
            $isLink = is_link($path);
        } finally {
            restore_error_handler();
        }

        if ($hadOpenBasedirError) {
            return false;
        }

        if ($isLink) {
            $linkTarget = readlink($path);
            if ($linkTarget === false) {
                return false;
            }
            $path = $linkTarget;
        }

        return self::getRootOfPath($path) !== false;
    }
}
