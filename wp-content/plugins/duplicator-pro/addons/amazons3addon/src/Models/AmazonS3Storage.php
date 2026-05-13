<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Addons\AmazonS3Addon\Models;

use Duplicator\Models\GlobalEntity;
use Duplicator\Utils\Logging\DupLog;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Models\DynamicGlobalEntity;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Exception;

/** @property S3StorageAdapter $adapter */
class AmazonS3Storage extends AbstractStorageEntity
{
    /** @var int */
    const DEFAULT_DOWNLOAD_CHUNK_SIZE_IN_KB = 10 * 1024;
    /** @var int */
    const DOWNLOAD_CHUNK_MIN_SIZE_IN_KB = 5 * 1024;
    /** @var int */
    const DOWNLOAD_CHUNK_MAX_SIZE_IN_KB = 5 * 1024 * 1024;
    /** @var int */
    const DEFAULT_UPLOAD_CHUNK_SIZE_IN_KB = 6000;
    /** @var int */
    const UPLOAD_CHUNK_MIN_SIZE_IN_KB = 5 * 1024;
    /** @var int */
    const UPLOAD_CHUNK_MAX_SIZE_IN_KB = 5 * 1024 * 1024;

    /**
     * Get default config
     *
     * @return array<string,scalar>
     */
    protected static function getDefaultConfig(): array
    {
        $config = parent::getDefaultConfig();
        return array_merge(
            $config,
            [
                'access_key'       => '',
                'bucket'           => '',
                'region'           => '',
                'endpoint'         => '',
                'secret_key'       => '',
                'storage_class'    => 'STANDARD',
                'ACL_full_control' => true,
            ]
        );
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
                return __('Access Key', 'duplicator-pro');
            case 'secretKey':
                return __('Secret Key', 'duplicator-pro');
            case 'region':
                return __('Region', 'duplicator-pro');
            case 'endpoint':
                return __('Endpoint', 'duplicator-pro');
            case 'bucket':
                return __('Bucket', 'duplicator-pro');
            case 'aclFullControl':
                return __('Additional Settings', 'duplicator-pro');
            default:
                throw new Exception("Unknown field: $field");
        }
    }

    /**
     * Return the storage type
     *
     * @return int
     */
    public static function getSType(): int
    {
        return 4;
    }

    /**
     * Returns the storage type icon URL
     *
     * @return string Returns the storage icon URL
     */
    public static function getStypeIconURL(): string
    {
        return DUPLICATOR_IMG_URL . '/aws.svg';
    }


    /**
     * Returns the storage type name.
     *
     * @return string
     */
    public static function getStypeName(): string
    {
        return __('Amazon S3', 'duplicator-pro');
    }

    /**
     * Get priority, used to sort storages.
     * 100 is neutral value, 0 is the highest priority
     *
     * @return int
     */
    public static function getPriority(): int
    {
        return 500;
    }

    /**
     * Get storage location string
     *
     * @return string
     */
    public function getLocationString(): string
    {
        $params = [
            'region' => $this->config['region'],
            'bucket' => $this->config['bucket'],
            'prefix' => $this->getStorageFolder(),
        ];

        return 'https://console.aws.amazon.com/s3/home?' . http_build_query($params);
    }

    /**
     * Returns an html anchor tag of location
     *
     * @return string Returns an html anchor tag with the storage location as a hyperlink.
     *
     * @example
     * OneDrive Example return
     * <a target="_blank" href="https://1drv.ms/f/sAFrQtasdrewasyghg">https://1drv.ms/f/sAFrQtasdrewasyghg</a>
     */
    public function getHtmlLocationLink(): string
    {
        if ($this->isValid()) {
            return '<a href="' . esc_url($this->getLocationString()) . '" target="_blank" >' . esc_html($this->getLocationLabel()) . '</a>';
        } else {
            return '<span>' . esc_html($this->getLocationLabel()) . '</span>';
        }
    }

    /**
     * Returns the storage location label.
     *
     * @return string The storage location label
     */
    protected function getLocationLabel(): string
    {
        return 's3://' . $this->config['bucket'] . $this->getStorageFolder();
    }

    /**
     * Check if storage is supported
     *
     * @return bool
     */
    public static function isSupported(): bool
    {
        return SnapUtil::isCurlEnabled(true);
    }

    /**
     * Get supported notice, displayed if storage isn't supported
     *
     * @return string html string or empty if storage is supported
     */
    public static function getNotSupportedNotice(): string
    {
        if (static::isSupported()) {
            return '';
        }

        if (!SnapUtil::isCurlEnabled()) {
            $result = sprintf(
                __(
                    "The Storage %s requires the PHP cURL extension and related functions to be enabled.",
                    'duplicator-pro'
                ),
                static::getStypeName()
            );
        } elseif (!SnapUtil::isCurlEnabled(true)) {
            $result = sprintf(
                __(
                    "The Storage %s requires 'curl_multi_' type functions to be enabled. One or more are disabled on your server.",
                    'duplicator-pro'
                ),
                static::getStypeName()
            );
        } else {
            $result = sprintf(
                __(
                    'The Storage %s is not supported on this server.',
                    'duplicator-pro'
                ),
                static::getStypeName()
            );
        }

        return esc_html($result);
    }

    /**
     * Check if storage is valid
     *
     * @param ?string $errorMsg Reference to store error message
     * @param bool    $force    Force the storage to be revalidated
     *
     * @return bool Return true if storage is valid and ready to use, false otherwise
     */
    protected function realIsValid(?string &$errorMsg = '', bool $force = false): bool
    {
        return $this->getAdapter()->isValid($errorMsg, $force);
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
            'storage'        => $this,
            'maxPackages'    => $this->config['max_packages'],
            'storageFolder'  => $this->config['storage_folder'],
            'accessKey'      => $this->config['access_key'],
            'bucket'         => $this->config['bucket'],
            'region'         => $this->config['region'],
            'endpoint'       => $this->config['endpoint'],
            'secretKey'      => $this->config['secret_key'],
            'storageClass'   => $this->config['storage_class'],
            'aclFullControl' => $this->config['ACL_full_control'],
            'regionOptions'  => self::regionOptions(),
        ];
    }

    /**
     * Returns the config fields template path
     *
     * @return string
     */
    protected function getConfigFieldsTemplatePath(): string
    {
        return 'amazons3addon/configs/amazon_s3';
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

        $this->config['max_packages']   = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 's3_max_files', 10);
        $this->config['storage_folder'] = self::getSanitizedInputFolder('_s3_storage_folder');

        $this->config['access_key'] = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 's3_access_key');
        $secretKey                  = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 's3_secret_key');
        if (strlen($secretKey) > 0) {
            $this->config['secret_key'] = $secretKey;
        }
        $this->config['region']        = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 's3_region');
        $this->config['storage_class'] = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 's3_storage_class');
        $this->config['bucket']        = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 's3_bucket');

        if (strlen($this->config['access_key']) === 0) {
            $message = sprintf(
                __('The %s field is required.', 'duplicator-pro'),
                self::getFieldLabel('accessKey')
            );
            return false;
        }

        if (strlen($this->config['region']) === 0) {
            $message = sprintf(
                __('The %s field is required.', 'duplicator-pro'),
                self::getFieldLabel('region')
            );
            return false;
        }

        if (strlen($this->config['bucket']) === 0) {
            $message = sprintf(
                __('The %s field is required.', 'duplicator-pro'),
                self::getFieldLabel('bucket')
            );
            return false;
        }

        $message = __('Storage Updated.', 'duplicator-pro');

        return true;
    }

    /**
     * Get full s3 client
     *
     * @return S3StorageAdapter
     */
    protected function getAdapter(): S3StorageAdapter
    {
        if ($this->adapter !== null) {
            return $this->adapter;
        }

        try {
            $global        = GlobalEntity::getInstance();
            $this->adapter = new S3StorageAdapter(
                $this->config['access_key'],
                $this->config['secret_key'],
                $this->config['region'],
                $this->config['bucket'],
                $this->config['storage_folder'],
                $this->config['endpoint'],
                $this->config['storage_class'],
                $global->ipv4_only,
                !$global->ssl_disableverify,
                ($global->ssl_useservercerts ? '' : DUPLICATOR_CERT_PATH),
                $this->config['ACL_full_control']
            );
        } catch (Exception $e) {
            DupLog::infoTraceException($e, "Error creating S3 adapter: ");
            throw new Exception("Failed to create S3 adapter: " . $e->getMessage());
        }

        return $this->adapter;
    }

    /**
     * Returns value => label pairs for region drop-down options for S3 Amazon Direct storage type
     *
     * @return string[]
     */
    protected static function regionOptions(): array
    {
        return [
            "us-east-1"      => __("US East (N. Virginia)", 'duplicator-pro'),
            "us-east-2"      => __("US East (Ohio)", 'duplicator-pro'),
            "us-west-1"      => __("US West (N. California)", 'duplicator-pro'),
            "us-west-2"      => __("US West (Oregon)", 'duplicator-pro'),
            "af-south-1"     => __("Africa (Cape Town)", 'duplicator-pro'),
            "ap-east-1"      => __("Asia Pacific (Hong Kong)", 'duplicator-pro'),
            "ap-south-1"     => __("Asia Pacific (Mumbai)", 'duplicator-pro'),
            "ap-northeast-1" => __("Asia Pacific (Tokyo)", 'duplicator-pro'),
            "ap-northeast-2" => __("Asia Pacific (Seoul)", 'duplicator-pro'),
            "ap-northeast-3" => __("Asia Pacific (Osaka)", 'duplicator-pro'),
            "ap-southeast-1" => __("Asia Pacific (Singapore)", 'duplicator-pro'),
            "ap-southeast-2" => __("Asia Pacific (Sydney)", 'duplicator-pro'),
            "ap-southeast-3" => __("Asia Pacific (Jakarta)", 'duplicator-pro'),
            "ca-central-1"   => __("Canada (Central)", 'duplicator-pro'),
            "cn-north-1"     => __("China (Beijing)", 'duplicator-pro'),
            "cn-northwest-1" => __("China (Ningxia)", 'duplicator-pro'),
            "eu-central-1"   => __("EU (Frankfurt)", 'duplicator-pro'),
            "eu-west-1"      => __("EU (Ireland)", 'duplicator-pro'),
            "eu-west-2"      => __("EU (London)", 'duplicator-pro'),
            "eu-west-3"      => __("EU (Paris)", 'duplicator-pro'),
            "eu-south-1"     => __("Europe (Milan)", 'duplicator-pro'),
            "eu-north-1"     => __("Europe (Stockholm)", 'duplicator-pro'),
            "me-south-1"     => __("Middle East (Bahrain)", 'duplicator-pro'),
            "sa-east-1"      => __("South America (Sao Paulo)", 'duplicator-pro'),
        ];
    }

    /**
     * Register storage type
     *
     * @return void
     */
    public static function registerType(): void
    {
        parent::registerType();

        add_action('duplicator_update_global_storage_settings', function (): void {
            $dGlobal = DynamicGlobalEntity::getInstance();

            foreach (static::getDefaultSettings() as $key => $default) {
                $value = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, $key, $default);
                $dGlobal->setValInt($key, $value);
            }
        });
    }

    /**
     * Get upload chunk size in bytes
     *
     * @return int bytes
     */
    public function getUploadChunkSize(): int
    {
        $dGlobal = DynamicGlobalEntity::getInstance();
        return $dGlobal->getValInt(
            's3_upload_part_size_in_kb',
            self::DEFAULT_UPLOAD_CHUNK_SIZE_IN_KB
        ) * KB_IN_BYTES;
    }

    /**
     * Get download chunk size in bytes
     *
     * @return int bytes
     */
    public function getDownloadChunkSize(): int
    {
        $dGlobal = DynamicGlobalEntity::getInstance();
        return $dGlobal->getValInt(
            's3_download_part_size_in_kb',
            self::DEFAULT_DOWNLOAD_CHUNK_SIZE_IN_KB
        ) * KB_IN_BYTES;
    }

    /**
     * Get upload chunk timeout in seconds
     *
     * @return int timeout in microseconds, 0 unlimited
     */
    public function getUploadChunkTimeout(): int
    {
        $global = GlobalEntity::getInstance();
        return (int) ($global->php_max_worker_time_in_sec <= 0 ? 0 :  $global->php_max_worker_time_in_sec * SECONDS_IN_MICROSECONDS);
    }

    /**
     * Get default settings
     *
     * @return array<string, scalar>
     */
    protected static function getDefaultSettings(): array
    {
        return [
            's3_upload_part_size_in_kb'   => self::DEFAULT_UPLOAD_CHUNK_SIZE_IN_KB,
            's3_download_part_size_in_kb' => self::DEFAULT_DOWNLOAD_CHUNK_SIZE_IN_KB,
        ];
    }

    /**
     * @return void
     */
    public static function renderGlobalOptions(): void
    {
        if (static::class !== self::class) {
            return;
        }

        $dGlobal = DynamicGlobalEntity::getInstance();
        TplMng::getInstance()->render(
            'amazons3addon/configs/global_options',
            [
                'uploadPartSizeInKb'   => $dGlobal->getValInt('s3_upload_part_size_in_kb', self::DEFAULT_UPLOAD_CHUNK_SIZE_IN_KB),
                'downloadPartSizeInKb' => $dGlobal->getValInt('s3_download_part_size_in_kb', self::DEFAULT_DOWNLOAD_CHUNK_SIZE_IN_KB),
            ]
        );
    }

    /**
     * Purge old multipart uploads
     *
     * @return void
     */
    public function purgeMultipartUpload(): void
    {
        $this->getAdapter()->abortMultipartUploads(2);
    }
}
