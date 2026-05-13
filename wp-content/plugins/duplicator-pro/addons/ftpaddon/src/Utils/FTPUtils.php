<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snapcreek LLC
 */

namespace Duplicator\Addons\FtpAddon\Utils;

use DateTime;

class FTPUtils
{
    const SYS_TYPE_UNIX       = 'UNIX';
    const SYS_TYPE_WINDOWS_NT = 'WINDOWS_NT';

    /**
     * Parses a raw list item string into an array
     *
     * @param string $rawListString Raw list string
     * @param string $systemType    System type (UNIX or WINDOWS_NT)
     *
     * @return false|array{name: string, size: int, modified: int, created: int, isDir: bool}|false
     */
    public static function parseRawListString($rawListString, $systemType)
    {
        $rawListString = rtrim($rawListString);
        switch (strtoupper($systemType)) {
            case self::SYS_TYPE_UNIX:
                return self::parseUnixRawListString($rawListString);
            case self::SYS_TYPE_WINDOWS_NT:
                return self::parseWindowsRawListString($rawListString);
            default:
                return false;
        }
    }

    /**
     * Returns the info in the raw list item for Windows
     *
     * @param string $rawListString Raw list string
     *
     * @return false|array{name: string, size: int, modified: int, created: int, isDir: bool}|false
     */
    private static function parseWindowsRawListString($rawListString)
    {
        //Regex below gets info from raw string on Windows systems
        //Dir:     01-01-70  12:00AM       <DIR>          folder
        //File:    01-01-70  12:00AM       4096           file.txt
        //Groups:  1                       2              3
        $regex = '/^(\d{2}-\d{2}-\d{2,4}\s+\d{1,2}:\d{1,2}(?:AM|PM))\s+(<DIR>|\d+)\s+(.+)$/';
        if (preg_match($regex, $rawListString, $matches) !== 1) {
            return false;
        }

        $date = DateTime::createFromFormat('m-d-y h:ia', $matches[1]);

        $info             = [];
        $info['name']     = $matches[3];
        $info['isDir']    = strtoupper($matches[2]) === '<DIR>';
        $info['size']     = $info['isDir'] ? 0 : (int) $matches[2];
        $info['modified'] = $date !== false ? $date->getTimestamp() : 0;
        $info['created']  = $info['modified'];

        return $info;
    }

    /**
     * Returns the info in the raw list item for Unix
     *
     * @param string $rawListString Raw list string
     *
     * @return false|array{name: string, size: int, modified: int, created: int, isDir: bool}|false
     */
    private static function parseUnixRawListString($rawListString)
    {
        //Regex below gets info from raw string on UNIX systems
        //Example: drwxr-xr-x   2 user group 4096 Jan  1  1970 folder
        //Groups:  1            2 3    4     5    6            7
        $regex = '/^([drwx\-]{10})\s+(\d+)\s+(\S+)\s+(\S+)\s+(\d+)\s+(\w{3}\s+\d{1,2}\s+\d{1,2}:\d{1,2}|\w{3}\s+\d{1,2}\s+\d{4})\s+(.+)$/';
        if (preg_match($regex, $rawListString, $matches) !== 1) {
            return false;
        }

        $info             = [];
        $info['name']     = $matches[7];
        $info['isDir']    = $matches[1][0] === 'd';
        $info['size']     = $info['isDir'] ? 0 : (int) $matches[5];
        $info['modified'] = strtotime($matches[6]);
        $info['created']  = $info['modified'];

        return $info;
    }
}
