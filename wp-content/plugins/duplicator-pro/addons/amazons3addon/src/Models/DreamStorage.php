<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Addons\AmazonS3Addon\Models;

class DreamStorage extends AmazonS3CompatibleStorage
{
    /**
     * Return the storage type
     *
     * @return int
     */
    public static function getSType(): int
    {
        return 13;
    }

    /**
     * Returns the storage type name.
     *
     * @return string
     */
    public static function getStypeName(): string
    {
        return __('Dream Objects', 'duplicator-pro');
    }

    /**
     * Returns the storage type icon URL
     *
     * @return string Returns the storage icon URL
     */
    public static function getStypeIconURL(): string
    {
        return DUPLICATOR_IMG_URL . '/dreamhost.svg';
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
     * Get storage location string
     *
     * @return string
     */
    public function getLocationString(): string
    {
        return 'https://panel.dreamhost.com/index.cgi?tree=cloud.objects';
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
                'label' => __('Overview', 'duplicator-pro'),
                'url'   => 'https://help.dreamhost.com/hc/en-us/articles/214823108-DreamObjects-overview',
            ],
            [
                'label' => __('S3 Compatible API', 'duplicator-pro'),
                'url'   => 'https://help.dreamhost.com/hc/en-us/articles/217590537-How-To-Use-DreamObjects-S3-compatible-API',
            ],
        ];
    }
}
