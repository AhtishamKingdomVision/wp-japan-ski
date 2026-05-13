<?php

/**
 * Legacy file compatibility for backward compatibility with old installers
 *
 * @package   Duplicator\Addons\ProLegacy
 * @copyright (c) 2025, Snap Creek LLC
 */

declare(strict_types=1);

namespace Duplicator\Addons\ProLegacy;

/**
 * Handles legacy file creation for backward compatibility
 *
 * Creates legacy-prefixed files alongside new files so that old installers
 * can still read the overwrite params files.
 */
class LegacyFiles
{
    const LEGACY_OVERWRITE_PARAMS_PREFIX = 'duplicator_pro_params_overwrite';

    /**
     * Initialize legacy file hooks
     *
     * @return void
     */
    public static function init(): void
    {
        add_action('duplicator_after_overwrite_params_created', [__CLASS__, 'createLegacyOverwriteParams'], 10, 3);
    }

    /**
     * Create legacy overwrite params file for backward compatibility with old installers
     *
     * When new plugin creates duplicator_params_overwrite_HASH.json,
     * this also creates duplicator_pro_params_overwrite_HASH.json so that
     * old installers (which look for the legacy prefix) can still find it.
     *
     * @param string               $filePath    Full path to the new overwrite params file
     * @param array<string, mixed> $params      Parameters written to the file
     * @param string               $packageHash Package hash used in filename
     *
     * @return void
     */
    public static function createLegacyOverwriteParams(string $filePath, array $params, string $packageHash): void
    {
        $directory      = dirname($filePath);
        $legacyFilePath = $directory . '/' . self::LEGACY_OVERWRITE_PARAMS_PREFIX . '_' . $packageHash . '.json';

        // Copy the new file to the legacy location
        if (file_exists($filePath)) {
            copy($filePath, $legacyFilePath);
        }
    }
}
