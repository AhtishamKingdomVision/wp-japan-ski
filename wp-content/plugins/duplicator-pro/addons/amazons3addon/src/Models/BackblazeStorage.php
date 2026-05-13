<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Addons\AmazonS3Addon\Models;

class BackblazeStorage extends AmazonS3CompatibleStorage
{
    /**
     * Get default config
     *
     * @return array<string,scalar>
     */
    protected static function getDefaultConfig(): array
    {
        $config                     = parent::getDefaultConfig();
        $config['ACL_full_control'] = false;
        return $config;
    }

    /**
     * Return the storage type
     *
     * @return int
     */
    public static function getSType(): int
    {
        return 9;
    }

    /**
     * Get storage location string
     *
     * @return string
     */
    public function getLocationString(): string
    {
        return 'https://secure.backblaze.com/b2_buckets.htm';
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
     * Returns the storage type name.
     *
     * @return string
     */
    public static function getStypeName(): string
    {
        return __('Backblaze B2', 'duplicator-pro');
    }

    /**
     * Get priority, used to sort storages.
     * 100 is neutral value, 0 is the highest priority
     *
     * @return int
     */
    public static function getPriority(): int
    {
        return 700;
    }

    /**
     * Returns the storage type icon URL
     *
     * @return string Returns the storage icon URL
     */
    public static function getStypeIconURL(): string
    {
        return DUPLICATOR_IMG_URL . '/backblaze.svg';
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
                'url'   => 'https://www.backblaze.com/b2/docs/',
            ],
            [
                'label' => __('S3 Compatible API', 'duplicator-pro'),
                'url'   => 'https://www.backblze.com/b2/docs/s3_compatible_api.html',
            ],
        ];
    }

    /**
     * Return the field label
     *
     * @param string $field Field name
     *
     * @return string
     */
    public static function getFieldLabel(string $field): string
    {
        switch ($field) {
            case 'accessKey':
                return __('Key ID', 'duplicator-pro');
            case 'secretKey':
                return __('Application Key', 'duplicator-pro');
        }
        return parent::getFieldLabel($field);
    }

    /**
     * Return true if ACL is supported
     *
     * @return bool
     */
    protected function isACLSupported(): bool
    {
        return false;
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
     * Register storage type
     *
     * @return void
     */
    public static function registerType(): void
    {
        parent::registerType();
        add_filter('duplicator_storage_type_class', function ($class, $type, $data) {
            if ($type == AmazonS3Storage::getSType()) {
                $isLegacy = (!isset($data['legacyEntity']) || $data['legacyEntity'] === true);
                $provider = ($data['s3_provider'] ?? '');
                if ($isLegacy && $provider == 'backblaze') {
                    $class = self::class;
                }
            }
            return $class;
        }, 10, 3);
    }
}
