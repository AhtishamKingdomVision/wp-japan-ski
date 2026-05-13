<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Addons\AmazonS3Addon\Models;

class GoogleCloudStorage extends AmazonS3CompatibleStorage
{
    /**
     * Return the storage type
     *
     * @return int
     */
    public static function getSType(): int
    {
        return 16;
    }

    /**
     * Returns the storage type name.
     *
     * @return string
     */
    public static function getStypeName(): string
    {
        return __('Google Cloud Storage', 'duplicator-pro');
    }

    /**
     * Get storage location string
     *
     * @return string
     */
    public function getLocationString(): string
    {
        return 'https://console.cloud.google.com/storage/browser/' . $this->getBucketPath();
    }

    /**
     * Returns the storage type icon URL
     *
     * @return string Returns the storage icon URL
     */
    public static function getStypeIconURL(): string
    {
        return DUPLICATOR_IMG_URL . '/google-cloud.svg';
    }

    /**
     * Get ACL description
     *
     * @return string
     */
    protected function getACLDescription(): string
    {
        return __(
            "Make sure to change the 'Access Control' to 'Fine Grained' for this setting to work.",
            'duplicator-pro'
        );
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
                'label' => __('Interoperability with S3 API', 'duplicator-pro'),
                'url'   => 'https://cloud.google.com/storage/docs/interoperability',
            ],
        ];
    }
}
