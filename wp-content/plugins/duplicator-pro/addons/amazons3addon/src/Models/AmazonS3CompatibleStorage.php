<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Addons\AmazonS3Addon\Models;

use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapUtil;

class AmazonS3CompatibleStorage extends AmazonS3Storage
{
    /**
     * Get default config
     *
     * @return array<string,scalar>
     */
    protected static function getDefaultConfig(): array
    {
        $config = parent::getDefaultConfig();
        return array_merge($config, ['ACL_full_control' => false]);
    }
    /**
     * Return the storage type
     *
     * @return int
     */
    public static function getSType(): int
    {
        return 8;
    }

    /**
     * Returns the storage type name.
     *
     * @return string
     */
    public static function getStypeName(): string
    {
        return __('S3 Compatible', 'duplicator-pro');
    }

    /**
     * Returns the storage type icon URL
     *
     * @return string Returns the storage icon URL
     */
    public static function getStypeIconURL(): string
    {
        return DUPLICATOR_IMG_URL . '/aws-compatible.svg';
    }

    /**
     * Returns an html anchor tag of location or a string
     *
     * @return string Returns an html anchor tag with the storage location as a hyperlink or just a plain string
     */
    public function getHtmlLocationLink(): string
    {
        if ($this->isValid()) {
            return '<a href="' . esc_url($this->getLocationString()) . '" target="_blank" >' . esc_html($this->getLocationLabel()) . '</a>';
        } else {
            return '<span>' . esc_html($this->getLocationString()) . '</span>';
        }
    }

    /**
     * Get storage location string
     *
     * @return string
     */
    public function getLocationString(): string
    {
        return '/' . $this->getBucketPath();
    }

    /**
     * Returns the bucket path with optional storage folder
     *
     * @return string The bucket path
     */
    protected function getBucketPath(): string
    {
        $folder = $this->getStorageFolder();

        $suffix = ($folder !== '') ? '/' . ltrim($folder, '/') : '';
        return $this->config['bucket'] . $suffix;
    }

    /**
     * Returns the storage location label.
     *
     * @return string The storage location label
     */
    protected function getLocationLabel(): string
    {
        return '/' . $this->getBucketPath();
    }

    /**
     * Returns a list of S3 compatible providers
     *
     * @return string[]
     */
    public static function getCompatibleProviders(): array
    {
        return [
            'Aruba',
            'Cloudian',
            'Cloudn',
            'Connectria',
            'Constant',
            'Exoscal',
            'Eucalyptus',
            'Nifty',
            'Nimbula',
            'Minio',
        ];
    }

    /**
     * Get priority, used to sort storages.
     * 100 is neutral value, 0 is the highest priority
     *
     * @return int
     */
    public static function getPriority(): int
    {
        return 510;
    }

    /**
     * Returns the config fields template data
     *
     * @return array<string, mixed>
     */
    protected function getConfigFieldsData(): array
    {
        return $this->getDefaultConfigFieldsData();
    }

    /**
     * Returns the default config fields template data
     *
     * @return array<string, mixed>
     */
    protected function getDefaultConfigFieldsData(): array
    {
        return [
            'storage'            => $this,
            'maxPackages'        => $this->config['max_packages'],
            'storageFolder'      => $this->config['storage_folder'],
            'accessKey'          => $this->config['access_key'],
            'bucket'             => $this->config['bucket'],
            'region'             => $this->config['region'],
            'endpoint'           => $this->config['endpoint'],
            'secretKey'          => $this->config['secret_key'],
            'storageClass'       => $this->config['storage_class'],
            'aclFullControl'     => $this->config['ACL_full_control'],
            'isAutofillEndpoint' => $this->isAutofillEndpoint(),
            'isAutofillRegion'   => $this->isAutofillRegion(),
            'isAclSupported'     => $this->isACLSupported(),
            'aclDescription'     => $this->getACLDescription(),
            'documentationLinks' => static::getDocumentationLinks(),
        ];
    }

    /**
     * Returns the config fields template path
     *
     * @return string
     */
    protected function getConfigFieldsTemplatePath(): string
    {
        return 'amazons3addon/configs/all_s3_compatible';
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
                'label' => __('S3 Compatibility API', 'duplicator-pro'),
                'url'   => 'https://docs.aws.amazon.com/AmazonS3/latest/API/Welcome.html',
            ],
        ];
    }

    /**
     * Return true if the endpoint is generated automatically
     *
     * @return bool
     */
    protected function isAutofillEndpoint(): bool
    {
        return false;
    }

    /**
     * Return true if the region is generated automatically
     *
     * @return bool
     */
    protected function isAutofillRegion(): bool
    {
        return false;
    }

    /**
     * Return true if the ACL is supported
     *
     * @return bool
     */
    protected function isACLSupported(): bool
    {
        return true;
    }

    /**
     * Get ACL description
     *
     * @return string
     */
    protected function getACLDescription()
    {
        return __(
            "This option only works if the storage provider supports the 'bucket-owner-full-control' object-level canned ACL.",
            'duplicator-pro'
        );
    }

    /**
     * Update data from http request, this method don't save data, just update object properties
     *
     * @param string $message Message
     *
     * @return bool True if success and all data is valid, false otherwise
     */
    public function updateFromHttpRequest(&$message = ''): bool
    {
        if ((parent::updateFromHttpRequest($message) === false)) {
            return false;
        }

        $this->config['endpoint']         = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 's3_endpoint');
        $this->config['ACL_full_control'] = $this->isACLSupported() && SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 's3_ACL_full_control');

        if (strlen($this->config['endpoint']) === 0) {
            $message = sprintf(
                __('The %s field is required.', 'duplicator-pro'),
                self::getFieldLabel('endpoint')
            );
            return false;
        }

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

        if (self::class === static::class) {
            // only add filter for current storage and not inherited
            add_filter('duplicator_storage_type_class', function ($class, $type, $data) {
                if ($type == AmazonS3Storage::getSType()) {
                    $isLegacy = (!isset($data['legacyEntity']) || $data['legacyEntity'] === true);
                    $provider = ($data['s3_provider'] ?? '');
                    if ($isLegacy && $provider == 'other') {
                        $class = self::class;
                    }
                }
                return $class;
            }, 10, 3);
        }
    }
}
