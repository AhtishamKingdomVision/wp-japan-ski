<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Addons\AmazonS3Addon\Models;

class VultrStorage extends AmazonS3CompatibleStorage
{
    /**
     * Return the storage type
     *
     * @return int
     */
    public static function getSType(): int
    {
        return 12;
    }

    /**
     * Returns the storage type name.
     *
     * @return string
     */
    public static function getStypeName(): string
    {
        return __('Vultr', 'duplicator-pro');
    }

    /**
     * Returns the storage type icon URL
     *
     * @return string Returns the storage icon URL
     */
    public static function getStypeIconURL(): string
    {
        return DUPLICATOR_IMG_URL . '/vultr.svg';
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
     * Get storage location string
     *
     * @return string
     */
    public function getLocationString(): string
    {
        return 'https://my.vultr.com/objectstorage/';
    }

    /**
     * Returns the storage location label.
     *
     * @return string The storage location label
     */
    protected function getLocationLabel(): string
    {
        return __('Bucket List', 'duplicator-pro');
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
                'label' => __('Vultr Object Storage', 'duplicator-pro'),
                'url'   => 'https://www.vultr.com/docs/vultr-object-storage/',
            ],
        ];
    }
}
