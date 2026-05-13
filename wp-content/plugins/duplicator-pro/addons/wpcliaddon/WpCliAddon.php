<?php

/**
 * Add wp cli commands
 *
 * Name: WP-CLI Addon
 * Version: 1
 * Author: Duplicator
 * Author URI: http://snapcreek.com
 *
 * PHP version 7.4
 *
 * @category  Duplicator
 * @package   Plugin
 * @author    Duplicator
 * @copyright 2011-2024  Snapcreek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @version   GIT: $Id$
 * @link      https://duplicator.com/
 */

namespace Duplicator\Addons\WpCliAddon;

/**
 * Version Pro Base addon class
 *
 * @category Duplicator
 * @package  Plugin
 * @author   Snapcreek <admin@snapcreek.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     http://snapcreek.com
 */
class WpCLiAddon extends \Duplicator\Core\Addons\AbstractAddonCore
{
    /**
     * @return void
     */
    public function init(): void
    {
        $cli = new DuplicatorCli();
    }

    /**
     *
     * @return string
     */
    public static function getAddonPath(): string
    {
        return __DIR__;
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
