<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Addons\AmazonS3Addon\Models;

class DigitalOceanStorage extends AmazonS3CompatibleStorage
{
    /**
     * Return the storage type
     *
     * @return int
     */
    public static function getSType(): int
    {
        return 14;
    }

    /**
     * Returns the storage type name.
     *
     * @return string
     */
    public static function getStypeName(): string
    {
        return __('Digital Ocean Spaces', 'duplicator-pro');
    }

    /**
     * Return true if the region is generated automatically
     *
     * @return bool
     */
    public function isAutofillRegion(): bool
    {
        return true;
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
     * Returns the storage type icon URL
     *
     * @return string Returns the storage icon URL
     */
    public static function getStypeIconURL(): string
    {
        return DUPLICATOR_IMG_URL . '/digital-ocean.svg';
    }

    /**
     * Get storage location string
     *
     * @return string
     */
    public function getLocationString(): string
    {
        return 'https://cloud.digitalocean.com/spaces/' . $this->getBucketPath();
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
                'label' => __('Spaces Object Storage', 'duplicator-pro'),
                'url'   => 'https://docs.digitalocean.com/products/spaces/',
            ],
            [
                'label' => __('Spaces API', 'duplicator-pro'),
                'url'   => 'https://docs.digitalocean.com/reference/api/spaces-api/',
            ],
        ];
    }
}
