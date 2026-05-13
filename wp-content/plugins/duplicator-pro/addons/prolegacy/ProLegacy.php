<?php

/**
 * ProLegacy addon - Unified legacy compatibility system
 *
 * Name: Duplicator PRO Legacy Support
 * Version: 1.0.0
 * Description: Provides backward compatibility for deprecated prefixes (hooks, options, temp files)
 * Author: Duplicator
 * Author URI: https://duplicator.com
 * Requires PHP: 7.4
 * Requires Duplicator min version: 4.5.24
 *
 * PHP version 7.4
 *
 * @category  Duplicator
 * @package   Addons\ProLegacy
 * @author    Duplicator <support@duplicator.com>
 * @copyright 2011-2025 Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link      https://duplicator.com/
 */

declare(strict_types=1);

namespace Duplicator\Addons\ProLegacy;

/**
 * ProLegacy Addon Class
 *
 * Provides unified legacy compatibility system:
 * - Hook forwarding: old → new hook names (40 public hooks)
 * - Options & user meta migration during upgrade
 * - Temporary files cleanup (dup_pro_* prefixes)
 * - Upgrade coordination and logging
 *
 * @category Duplicator
 * @package  Addons\ProLegacy
 * @author   Duplicator <support@duplicator.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://duplicator.com/
 */
class ProLegacy extends \Duplicator\Core\Addons\AbstractAddonCore
{
    /**
     * Initialize addon
     *
     * @return void
     */
    public function init(): void
    {
        VersionMigration::init();
        BackupDirMigration::init();
        LegacyUpgrade::init();
        HookForwarding::init();
        LegacyFiles::init();
    }

    /**
     * Get addon directory path
     *
     * @return string
     */
    public static function getAddonPath(): string
    {
        return __DIR__;
    }

    /**
     * Get addon main file path
     *
     * @return string
     */
    public static function getAddonFile(): string
    {
        return __FILE__;
    }
}
