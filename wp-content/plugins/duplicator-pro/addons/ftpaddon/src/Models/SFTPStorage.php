<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Addons\FtpAddon\Models;

use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Models\DynamicGlobalEntity;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Exception;

/** @property SFTPStorageAdapter $adapter */
class SFTPStorage extends AbstractStorageEntity
{
    const MIN_DOWNLOAD_CHUNK_SIZE_IN_MB     = 2;
    const DEFAULT_DOWNLOAD_CHUNK_SIZE_IN_MB = 10;
    const MAX_DOWNLOAD_CHUNK_SIZE_IN_MB     = 9999;

    /**
     * Get stoage adapter
     *
     * @return SFTPStorageAdapter
     */
    protected function getAdapter(): SFTPStorageAdapter
    {
        if ($this->adapter !== null) {
            return $this->adapter;
        }

        $this->adapter = new SFTPStorageAdapter(
            $this->config['server'],
            $this->config['port'],
            $this->config['username'],
            $this->config['password'],
            $this->config['storage_folder'],
            $this->config['private_key'],
            $this->config['private_key_password'],
            $this->config['timeout_in_secs']
        );

        return $this->adapter;
    }

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
                'server'               => '',
                'port'                 => 22,
                'username'             => '',
                'password'             => '',
                'private_key'          => '',
                'private_key_password' => '',
                'timeout_in_secs'      => 15,
            ]
        );
    }

    /**
     * Return the storage type
     *
     * @return int
     */
    public static function getSType(): int
    {
        return 5;
    }

    /**
     * Returns the storage type icon URL
     *
     * @return string Returns the storage icon URL
     */
    public static function getStypeIconURL(): string
    {
        return DUPLICATOR_IMG_URL . '/network-wired-secure.svg';
    }

    /**
     * Returns the storage type name.
     *
     * @return string
     */
    public static function getStypeName(): string
    {
        return __('SFTP', 'duplicator-pro');
    }

    /**
     * Get storage location string
     *
     * @return string
     */
    public function getLocationString(): string
    {
        return $this->config['server'] . ":" . $this->config['port'];
    }

    /**
     * Get priority, used to sort storages.
     * 100 is neutral value, 0 is the highest priority
     *
     * @return int
     */
    public static function getPriority(): int
    {
        return 1010;
    }

    /**
     * Check if storage is supported
     *
     * @return bool
     */
    public static function isSupported(): bool
    {
        return extension_loaded('gmp');
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

        return sprintf(
            _x(
                'SFTP requires the %1$sgmp extension%2$s. Please contact your hosting provider to install.',
                '1: <a> tag, 2: </a> tag',
                'duplicator-pro'
            ),
            '<a href="http://php.net/manual/en/book.gmp.php" target="_blank">',
            '</a>'
        );
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
     * Get action key text
     *
     * @param string $key Key name (action, pending, failed, cancelled, success)
     *
     * @return string
     */
    protected function getUploadActionKeyText(string $key): string
    {
        switch ($key) {
            case 'action':
                return sprintf(
                    __('Transferring to SFTP server %1$s in folder:<br/> <i>%2$s</i>', "duplicator-pro"),
                    $this->config['server'],
                    $this->getStorageFolder()
                );
            case 'pending':
                return sprintf(
                    __('Transfer to SFTP server %1$s in folder %2$s is pending', "duplicator-pro"),
                    $this->config['server'],
                    $this->getStorageFolder()
                );
            case 'failed':
                return sprintf(
                    __('Failed to transfer to SFTP server %1$s in folder %2$s', "duplicator-pro"),
                    $this->config['server'],
                    $this->getStorageFolder()
                );
            case 'cancelled':
                return sprintf(
                    __('Cancelled before could transfer to SFTP server:<br/>%1$s in folder %2$s', "duplicator-pro"),
                    $this->config['server'],
                    $this->getStorageFolder()
                );
            case 'success':
                return sprintf(
                    __('Transferred Backup to SFTP server:<br/>%1$s in folder %2$s', "duplicator-pro"),
                    $this->config['server'],
                    $this->getStorageFolder()
                );
            default:
                throw new Exception('Invalid key');
        }
    }

    /**
     * Get action key text
     *
     * @param string $key Key name (action, pending, failed, cancelled, success)
     *
     * @return string
     */
    protected function getDownloadActionKeyText(string $key): string
    {
        switch ($key) {
            case 'action':
                return sprintf(
                    __('Downloading from SFTP server %1$s from folder:<br/> <i>%2$s</i>', "duplicator-pro"),
                    $this->config['server'],
                    $this->getStorageFolder()
                );
            case 'pending':
                return sprintf(
                    __('Download from SFTP server %1$s from folder %2$s is pending', "duplicator-pro"),
                    $this->config['server'],
                    $this->getStorageFolder()
                );
            case 'failed':
                return sprintf(
                    __('Failed to download from SFTP server %1$s from folder %2$s', "duplicator-pro"),
                    $this->config['server'],
                    $this->getStorageFolder()
                );
            case 'cancelled':
                return sprintf(
                    __('Cancelled before could download from SFTP server:<br/>%1$s from folder %2$s', "duplicator-pro"),
                    $this->config['server'],
                    $this->getStorageFolder()
                );
            case 'success':
                return sprintf(
                    __('Downloaded Backup from SFTP server:<br/>%1$s from folder %2$s', "duplicator-pro"),
                    $this->config['server'],
                    $this->getStorageFolder()
                );
            default:
                throw new Exception('Invalid key');
        }
    }

    /**
     * List quick view
     *
     * @param bool $echo Echo or return
     *
     * @return string
     */
    public function getListQuickView(bool $echo = true): string
    {
        ob_start();
        ?>
        <div>
            <label><?php esc_html_e('Server', 'duplicator-pro'); ?>:</label>
            <?php echo esc_html($this->config['server']); ?>: <?php echo intval($this->config['port']);  ?> <br />
            <label><?php esc_html_e('Location', 'duplicator-pro') ?>:</label>
            <?php
            echo wp_kses(
                $this->getHtmlLocationLink(),
                [
                    'a' => [
                        'href'   => [],
                        'target' => [],
                    ],
                ]
            );
            ?>
        </div>
        <?php
        if ($echo) {
            ob_end_flush();
            return '';
        } else {
            return ob_get_clean();
        }
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
            'storage'       => $this,
            'server'        => $this->config['server'],
            'port'          => $this->config['port'],
            'username'      => $this->config['username'],
            'password'      => $this->config['password'],
            'privateKey'    => $this->config['private_key'],
            'privateKeyPwd' => $this->config['private_key_password'],
            'storageFolder' => $this->config['storage_folder'],
            'maxPackages'   => $this->config['max_packages'],
            'timeout'       => $this->config['timeout_in_secs'],
        ];
    }

    /**
     * Returns the config fields template path
     *
     * @return string
     */
    protected function getConfigFieldsTemplatePath(): string
    {
        return 'ftpaddon/configs/sftp';
    }

    /**
     * Get upload chunk size in bytes
     *
     * @return int bytes
     */
    public function getUploadChunkSize(): int
    {
        return -1;
    }

    /**
     * Get upload chunk size in bytes
     *
     * @return int bytes
     */
    public function getDownloadChunkSize(): int
    {
        $dGlobal = DynamicGlobalEntity::getInstance();
        return $dGlobal->getValInt(
            'sftp_download_chunksize_in_mb',
            self::DEFAULT_DOWNLOAD_CHUNK_SIZE_IN_MB
        ) * MB_IN_BYTES;
    }

    /**
     * Get upload chunk timeout in seconds
     *
     * @return int timeout in microseconds, 0 unlimited
     */
    public function getUploadChunkTimeout(): int
    {
        return (int) ($this->config['timeout_in_secs'] <= 0 ? 0 :  $this->config['timeout_in_secs'] * SECONDS_IN_MICROSECONDS);
    }

    /**
     * Get default settings
     *
     * @return array<string, scalar>
     */
    protected static function getDefaultSettings(): array
    {
        return ['sftp_download_chunksize_in_mb' => self::DEFAULT_DOWNLOAD_CHUNK_SIZE_IN_MB];
    }

    /**
     * Render the settings page for this storage.
     *
     * @return void
     */
    public static function renderGlobalOptions(): void
    {
        $dGlobal = DynamicGlobalEntity::getInstance();
        TplMng::getInstance()->render(
            'ftpaddon/configs/sftp_global_options',
            [
                'downloadChunkSize' => $dGlobal->getVal('sftp_download_chunksize_in_mb', self::DEFAULT_DOWNLOAD_CHUNK_SIZE_IN_MB),
            ]
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

        $password  = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'sftp_password', '');
        $password2 = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'sftp_password2', '');

        if (strlen($password) > 0) {
            if ($password !== $password2) {
                $message = __('Passwords do not match', 'duplicator-pro');
                return false;
            }
        }

        $this->config['max_packages']    = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'sftp_max_files', 10);
        $this->config['server']          = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'sftp_server', '');
        $this->config['port']            = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'sftp_port', 10);
        $this->config['username']        = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'sftp_username', '');
        $this->config['private_key']     = SnapUtil::sanitizeDefaultInput(SnapUtil::INPUT_REQUEST, 'sftp_private_key', '');
        $this->config['storage_folder']  = self::getSanitizedInputFolder('_sftp_storage_folder', 'add');
        $this->config['timeout_in_secs'] = max(10, SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'sftp_timeout_in_secs', 15));

        if (strlen($password) > 0) {
            if (strlen($this->config['private_key']) > 0) {
                $this->config['private_key_password'] = $password;
                $this->config['password']             = '';
            } else {
                $this->config['private_key_password'] = '';
                $this->config['password']             = $password;
            }
        }

        if (strlen($this->config['server']) === 0) {
            $message = __('SFTP Server is required.', 'duplicator-pro');
            return false;
        }

        if (strlen($this->config['username']) === 0) {
            $message = __('SFTP Username is required.', 'duplicator-pro');
            return false;
        }

        $errorMsg = '';
        if ($this->getAdapter()->initialize($errorMsg) === false) {
            $message = sprintf(
                __('Failed to connect to SFTP server with message: %1$s', 'duplicator-pro'),
                $errorMsg
            );
            return false;
        }

        $message = sprintf(
            __('SFTP Storage Updated - Server %1$s, Folder %2$s was created.', 'duplicator-pro'),
            $this->config['server'],
            $this->getStorageFolder()
        );
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

        add_action('duplicator_update_global_storage_settings', function (): void {
            $dGlobal = DynamicGlobalEntity::getInstance();

            foreach (static::getDefaultSettings() as $key => $default) {
                $value = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, $key, $default);
                $dGlobal->setValInt($key, $value);
            }
            $dGlobal->save();
        });
    }
}
