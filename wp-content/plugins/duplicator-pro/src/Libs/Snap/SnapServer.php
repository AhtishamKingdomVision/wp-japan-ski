<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Libs\Snap;

class SnapServer
{
    const DEFAULT_WINDOWS_MAXPATH = 260;
    const DEFAULT_LINUX_MAXPATH   = 4096;

    /**
     * Return true if current SO is windows
     *
     * @return boolean
     */
    public static function isWindows()
    {
        static $isWindows = null;
        if (is_null($isWindows)) {
            $isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
        }
        return $isWindows;
    }

    /**
     * Return true if current SO is OSX
     *
     * @return boolean
     */
    public static function isOSX()
    {
        static $isOSX = null;
        if (is_null($isOSX)) {
            $isOSX = (strtoupper(substr(PHP_OS, 0, 6)) === 'DARWIN');
        }
        return $isOSX;
    }

    /**
     * Is URL Fopen enabled
     *
     * @return bool
     */
    public static function isURLFopenEnabled(): bool
    {
        return SnapUtil::phpIniGet('allow_url_fopen', false, 'bool');
    }

    /**
     *  Gets the name of the owner of the current PHP script
     *
     * @return string The name of the owner of the current PHP script
     */
    public static function getPHPUser(): string
    {
        $unreadable = 'Undetectable';
        if (function_exists('get_current_user')) {
            $user = get_current_user();
            return strlen($user) ? $user : $unreadable;
        }
        return $unreadable;
    }

    /**
     * Get PHP memory usage
     *
     * @param bool $peak If true, returns peak memory usage
     *
     * @return string Returns human readable memory usage.
     */
    public static function getPHPMemory(bool $peak = false): string
    {
        if ($peak) {
            $result = 'Unable to read PHP peak memory usage';
            if (function_exists('memory_get_peak_usage')) {
                $result = SnapString::byteSize(memory_get_peak_usage(true));
            }
        } else {
            $result = 'Unable to read PHP memory usage';
            if (function_exists('memory_get_usage')) {
                $result = SnapString::byteSize(memory_get_usage(true));
            }
        }
        return $result;
    }

    /**
     * Return true if memory_limit is >= $memoryLimit, otherwise false
     *
     * @param string $memoryLimit Memory limit to check
     *
     * @return bool
     */
    public static function memoryLimitCheck(string $memoryLimit): bool
    {
        // In case we can't get the ini value, assume it's ok
        if (($memory_limit = @ini_get('memory_limit')) === false || $memory_limit <= 0) {
            return true;
        }

        if (SnapUtil::convertToBytes($memory_limit) >= SnapUtil::convertToBytes(DUPLICATOR_MIN_MEMORY_LIMIT)) {
            return true;
        }

        return false;
    }
}
