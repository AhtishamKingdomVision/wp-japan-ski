<?php

/**
 * Handler for DupCloud storage tokens via license integration
 *
 * @package   Duplicator\Addons\DupCloudAddon
 * @copyright (c) 2024, Snap Creek LLC
 */

declare(strict_types=1);

namespace Duplicator\Addons\DupCloudAddon\Models;

use Duplicator\Addons\ProBase\Models\LicenseData;
use Duplicator\Utils\Logging\DupLog;

/**
 * Handles storage tokens for quick connect from duplicator pro license
 */
class QuickConnect
{
    /** @var array<string,mixed> */
    private static $tokens = [];

    /**
     * Initialize hooks
     *
     * @return void
     */
    public static function init(): void
    {
        add_action('duplicator_license_check_remote_data_success', [self::class, 'handleLicenseDataSuccess'], 10, 2);
    }

    /**
     * Update storage tokens
     *
     * @param LicenseData $license    The license data instance
     * @param object      $remoteData The remote data from license check
     *
     * @return void
     */
    public static function handleLicenseDataSuccess(LicenseData $license, $remoteData): void
    {
        if (
            !isset($remoteData->storage_tokens) ||
            !is_array($remoteData->storage_tokens)
        ) {
            return;
        }

        foreach ($remoteData->storage_tokens as $tokenObj) {
            self::$tokens[] =  [
                'license_key'  => $tokenObj->license_key,
                'token'        => $tokenObj->token,
                'product_name' => $tokenObj->product_name,
                'price_name'   => $tokenObj->price_name,
                'expiration'   => $tokenObj->expiration,
                'is_lifetime'  => $tokenObj->is_lifetime,
            ];
        }
    }

    /**
     * Get storage tokens by requesting fresh license data
     *
     * @return array<string,mixed>
     */
    public static function getStorageTokens(): array
    {
        $licenseData = LicenseData::getInstance();

        // Tokens are set with funciton handleLicenseDataSuccess inside getLicenseData using hooks
        add_filter('duplicator_license_request_params', [self::class, 'addStorageTokensRequest']);
        $licenseData->getLicenseData(true);
        remove_filter('duplicator_license_request_params', [self::class, 'addStorageTokensRequest']);

        return self::$tokens;
    }

    /**
     * Add storage tokens request parameter to license request
     *
     * @param array<string,mixed> $params Request parameters
     *
     * @return array<string,mixed>
     */
    public static function addStorageTokensRequest($params)
    {
        $params['request_storage_tokens'] = 'true';
        return $params;
    }

    /**
     * Check if license-based connection is available
     *
     * @return bool
     */
    public static function isLicenseConnectionAvailable(): bool
    {
        return class_exists(LicenseData:: class);
    }
}
