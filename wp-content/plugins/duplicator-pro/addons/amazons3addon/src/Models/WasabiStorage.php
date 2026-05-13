<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Addons\AmazonS3Addon\Models;

class WasabiStorage extends AmazonS3CompatibleStorage
{
    /**
     * Return the storage type
     *
     * @return int
     */
    public static function getSType(): int
    {
        return 10;
    }

    /**
     * Returns the storage type name.
     *
     * @return string
     */
    public static function getStypeName(): string
    {
        return __('Wasabi', 'duplicator-pro');
    }

    /**
     * Returns the storage type icon URL
     *
     * @return string Returns the storage icon URL
     */
    public static function getStypeIconURL(): string
    {
        return DUPLICATOR_IMG_URL . '/wasabi.svg';
    }

    /**
     * Return true if the endpoint is generated automatically
     *
     * @return bool
     */
    public function isAutofillEndpoint(): bool
    {
        return true;
    }

    /**
     * Get storage location string
     *
     * @return string
     */
    public function getLocationURL(): string
    {
        return 'https://console.wasabisys.com/file_manager/';
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
                'label' => __('Wasabi Academy', 'duplicator-pro'),
                'url'   => 'https://docs.wasabi.com/',
            ],
            [
                'label' => __('S3 Compatible API', 'duplicator-pro'),
                'url'   => 'https://docs.wasabi.com/docs/wasabi-api',
            ],
        ];
    }
}
