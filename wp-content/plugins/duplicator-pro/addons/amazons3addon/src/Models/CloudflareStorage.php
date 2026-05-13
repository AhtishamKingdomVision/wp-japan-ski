<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Addons\AmazonS3Addon\Models;

class CloudflareStorage extends AmazonS3CompatibleStorage
{
    /**
     * Return the storage type
     *
     * @return int
     */
    public static function getSType(): int
    {
        return 11;
    }

    /**
     * Returns the storage type name.
     *
     * @return string
     */
    public static function getStypeName(): string
    {
        return __('Cloudflare R2', 'duplicator-pro');
    }

    /**
     * Get storage location string
     *
     * @return string
     */
    public function getLocationString(): string
    {
        return 'https://dash.cloudflare.com/';
    }

    /**
     * Returns the storage location label.
     *
     * @return string The storage location label
     */
    protected function getLocationLabel(): string
    {
        return __('Dashboard', 'duplicator-pro');
    }

    /**
     * Returns the storage type icon URL
     *
     * @return string Returns the storage icon URL
     */
    public static function getStypeIconURL(): string
    {
        return DUPLICATOR_IMG_URL . '/cloudflare.svg';
    }

    /**
     * Return true if the ACL is supported
     *
     * @return bool
     */
    protected function isACLSupported(): bool
    {
        return false;
    }

    /**
     * Get documentation links
     *
     * @return array<int,array<string,string>>
     */
    protected static function getDocumentationLinks(): array
    {
        return [
            [
                'label' => __('Overview', 'duplicator-pro'),
                'url'   => 'https://developers.cloudflare.com/r2/',
            ],
            [
                'label' => __('S3 Compatible API', 'duplicator-pro'),
                'url'   => 'https://developers.cloudflare.com/r2/api/s3/api/',
            ],
        ];
    }
}
