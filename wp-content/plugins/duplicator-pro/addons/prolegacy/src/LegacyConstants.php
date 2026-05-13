<?php

/**
 * Legacy constants mapping for backward compatibility
 *
 * This file maps old DUPLICATOR_PRO_* constants to new DUPLICATOR_* constants.
 * Users who have defined old constants in wp-config.php will still have them work.
 *
 * IMPORTANT: This file must be loaded BEFORE define.php
 *
 * @package   Duplicator\Addons\ProLegacy
 * @copyright (c) 2025, Snap Creek LLC
 */

namespace Duplicator\Addons\ProLegacy;

/**
 * Maps legacy DUPLICATOR_PRO_* constants to new DUPLICATOR_* constants
 *
 * Only includes constants that were overridable in wp-config.php (defined with if (!defined()) pattern)
 */
class LegacyConstants
{
    /**
     * Mapping of old constant names to new constant names
     *
     * Only constants that were user-overridable in the original define.php
     *
     * @var array<string,string>
     */
    private const CONSTANTS_MAP = [
        'DUPLICATOR_PRO_SSDIR_NAME'                    => 'DUPLICATOR_SSDIR_NAME',
        'DUPLICATOR_PRO_DEBUG_TPL_OUTPUT_INVALID'      => 'DUPLICATOR_DEBUG_TPL_OUTPUT_INVALID',
        'DUPLICATOR_PRO_DEBUG_TPL_DATA'                => 'DUPLICATOR_DEBUG_TPL_DATA',
        'DUPLICATOR_PRO_INDEX_INCLUDE_HASH'            => 'DUPLICATOR_INDEX_INCLUDE_HASH',
        'DUPLICATOR_PRO_INDEX_INCLUDE_INSTALLER_FILES' => 'DUPLICATOR_INDEX_INCLUDE_INSTALLER_FILES',
        'DUPLICATOR_PRO_DISALLOW_IMPORT'               => 'DUPLICATOR_DISALLOW_IMPORT',
        'DUPLICATOR_PRO_DISALLOW_RECOVERY'             => 'DUPLICATOR_DISALLOW_RECOVERY',
        'DUPLICATOR_PRO_PRIMARY_OAUTH_SERVER'          => 'DUPLICATOR_PRIMARY_OAUTH_SERVER',
        'DUPLICATOR_PRO_SECONDARY_OAUTH_SERVER'        => 'DUPLICATOR_SECONDARY_OAUTH_SERVER',
    ];

    /**
     * Apply legacy constant mappings
     *
     * For each legacy constant that is defined, define the new constant with the same value
     * (only if the new constant is not already defined)
     *
     * @return void
     */
    public static function apply(): void
    {
        foreach (self::CONSTANTS_MAP as $oldName => $newName) {
            if (\defined($oldName) && !\defined($newName)) {
                \define($newName, \constant($oldName));
            }
        }
    }
}
