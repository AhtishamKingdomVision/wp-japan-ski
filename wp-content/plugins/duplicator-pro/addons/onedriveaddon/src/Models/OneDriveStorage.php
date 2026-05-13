<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Addons\OneDriveAddon\Models;

use Duplicator\Models\GlobalEntity;
use Duplicator\Utils\Logging\DupLog;
use Duplicator\Addons\OneDriveAddon\OnedriveAdapter;
use Duplicator\Addons\OneDriveAddon\OneDriveStoragePathInfo;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Models\DynamicGlobalEntity;
use Duplicator\Models\Storages\AbstractOAuthStorage;
use Duplicator\Utils\OAuth\TokenEntity;
use Duplicator\Utils\OAuth\TokenService;
use Exception;

/**
 * @property OneDriveAdapter $adapter
 */
class OneDriveStorage extends AbstractOAuthStorage
{
    const GRAPH_SCOPES = [
        'openid',
        'offline_access',
        'files.readwrite.appfolder',
    ];

    const CLIENT_ID                         = '15fa3a0d-b7ee-447c-8093-7bfcf30b0797';
    const LOGOUT_REDIRECT_URI               = 'https://snapcreek.com/misc/onedrive/redir3.php';
    const UPLOAD_CHUNK_MIN_SIZE_IN_KB       = 320;
    const UPLOAD_CHUNK_DEFAULT_SIZE_IN_KB   = 3200;
    const DEFAULT_DOWNLOAD_CHUNK_SIZE_IN_KB = 10 * 1024;
    const MIN_DOWNLOAD_CHUNK_SIZE_IN_KB     = 2 * 1024;



    /**
     * @var null|OneDriveAdapter Storage adapter
     */
    protected $adapter;

    /**
     * Get priority, used to sort storages.
     * 100 is neutral value, 0 is the highest priority
     *
     * @return int
     */
    public static function getPriority(): int
    {
        return 320;
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
                'endpoint_url'           => '',
                'resource_id'            => '',
                'access_token'           => '',
                'refresh_token'          => '',
                'token_obtained'         => 0,
                'expires_in'             => 3600,
                'storage_folder_id'      => '',
                'storage_folder_web_url' => '',
                'all_folders_perm'       => false,
                'authorized'             => false,
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
        return 7;
    }

    /**
     * Returns the storage type icon URL
     *
     * @return string Returns the storage icon URL
     */
    public static function getStypeIconURL(): string
    {
        return DUPLICATOR_IMG_URL . '/onedrive.svg';
    }

    /**
     * Returns the storage type name.
     *
     * @return string
     */
    public static function getStypeName(): string
    {
        return __('OneDrive', 'duplicator-pro');
    }

    /**
     * Get storage location string
     *
     * @return string
     */
    public function getLocationString(): string
    {
        if (!$this->isAuthorized()) {
            return __("Not Authenticated", "duplicator-pro");
        } else {
            return (string) $this->config['storage_folder_web_url'];
        }
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
        if ($this->isAuthorized()) {
            return '<a href="' . esc_url($this->getLocationString()) . '" target="_blank" >' . esc_html($this->getStorageFolder()) . '</a>';
        } else {
            return $this->getLocationString();
        }
    }

    /**
     * Check if storage is supported
     *
     * @return bool
     */
    public static function isSupported(): bool
    {
        return SnapUtil::isCurlEnabled();
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

        return esc_html__('OneDrive requires the PHP CURL extension enabled.', 'duplicator-pro');
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
        if (!$this->isAuthorized()) {
            $errorMsg = __('Not authenticated.', 'duplicator-pro');
            return false;
        }

        if (!$this->getToken()->isValid()) {
            $errorMsg = __('OneDrive token is invalid.', 'duplicator-pro');
            return false;
        }

        return true;
    }

    /**
     * Is autorized
     *
     * @return bool
     */
    public function isAuthorized(): bool
    {
        return (bool) ($this->config['authorized'] ?? false);
    }

    /**
     * Get the chunk size bytes
     *
     * @return int
     */
    public function getUploadChunkSize(): int
    {
        return DynamicGlobalEntity::getInstance()->getValInt(
            'onedrive_upload_chunksize_in_kb',
            self::UPLOAD_CHUNK_DEFAULT_SIZE_IN_KB
        ) * KB_IN_BYTES;
    }

    /**
     * Get the chunk size bytes
     *
     * @return int
     */
    public function getDownloadChunkSize(): int
    {
        return DynamicGlobalEntity::getInstance()->getValInt(
            'onedrive_download_chunksize_in_kb',
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
     * Authorized from HTTP request
     *
     * @param string $message Message
     *
     * @return bool True if authorized, false if failed
     */
    public function authorizeFromRequest(&$message = ''): bool
    {
        try {
            if (($refreshToken = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'auth_code')) === '') {
                throw new Exception(__('Authorization code is empty', 'duplicator-pro'));
            }

            $this->name                     = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'name', '');
            $this->notes                    = SnapUtil::sanitizeDefaultInput(SnapUtil::INPUT_REQUEST, 'notes', '');
            $this->config['max_packages']   = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'max_packages', 10);
            $this->config['storage_folder'] = self::getSanitizedInputFolder('storage_folder', 'remove');

            $this->revokeAuthorization();

            $token = (new TokenEntity(self::getSType(), ['refresh_token' => $refreshToken]));
            if (! $token->refresh(true)) {
                throw new Exception(__('Failed to fetch information from OneDrive. Make sure the token is valid.', 'duplicator-pro'));
            }

            $this->setTokenConfig($token);
            $this->config['endpoint_url'] = $this->config['resource_id'] = '';
            $this->config['authorized']   = true;


            DupLog::traceObject("OneDrive App folder: ", $storageFolder = $this->getOneDriveStorageFolder());

            if (! $storageFolder) {
                throw new Exception(__("Failed to fetch information from OneDrive. Make sure the token is valid.", "duplicator-pro"));
            }

            // Get the storage folder id
            $this->config['storage_folder_id']      = $storageFolder->id;
            $this->config['storage_folder_web_url'] = $storageFolder->webUrl;
        } catch (Exception $e) {
            DupLog::trace("Problem authorizing OneDrive access token msg: " . $e->getMessage());
            $message = $e->getMessage();
            return false;
        }

        $message = __('OneDrive is connected successfully and Storage Provider Updated.', 'duplicator-pro');
        return true;
    }

    /**
     * Revokes authorization
     *
     * @param string $message Message
     *
     * @return bool True if authorized, false if failed
     */
    public function revokeAuthorization(&$message = ''): bool
    {
        if (!$this->isAuthorized()) {
            $message = __('Onedrive isn\'t authorized.', 'duplicator-pro');
            return true;
        }

        $this->config['endpoint_url']           = '';
        $this->config['resource_id']            = '';
        $this->config['access_token']           = '';
        $this->config['refresh_token']          = '';
        $this->config['token_obtained']         = 0;
        $this->config['storage_folder_id']      = '';
        $this->config['storage_folder_web_url'] = '';
        $this->config['authorized']             = false;

        $message = __('Onedrive is disconnected successfully.', 'duplicator-pro');
        return true;
    }

    /**
     * Get external revoke url
     *
     * @return string
     */
    public function getExternalRevokeUrl(): string
    {
        $base_url   = "https://login.microsoftonline.com/common/oauth2/v2.0/logout";
        $fields_arr = [
            "client_id"                => self::CLIENT_ID,
            "post_logout_redirect_uri" => self::LOGOUT_REDIRECT_URI,
        ];
        $query      = http_build_query($fields_arr);

        return $base_url . "?$query";
    }

    /**
     * Get authorization URL
     *
     * @return string
     */
    public function getAuthorizationUrl(): string
    {
        return (new TokenService(static::getSType()))->getRedirectUri();
    }

    /**
     * Returns the config fields template data
     *
     * @return array<string, mixed>
     */
    protected function getConfigFieldsData(): array
    {
        $hasError = $this->isAuthorized() && !$this->getAdapter()->isValid();
        return array_merge($this->getDefaultConfigFieldsData(), [
            'accountInfo' => $this->getAccountInfo(),
            'hasError'    => $hasError,
        ]);
    }

    /**
     * Returns the default config fields template data
     *
     * @return array<string, mixed>
     */
    protected function getDefaultConfigFieldsData(): array
    {
        return [
            'storage'           => $this,
            'storageFolder'     => $this->config['storage_folder'],
            'maxPackages'       => $this->config['max_packages'],
            'allFolderPers'     => $this->config['all_folders_perm'],
            'accountInfo'       => false,
            'hasError'          => false,
            'externalRevokeUrl' => $this->getExternalRevokeUrl(),
        ];
    }

    /**
     * Returns the config fields template path
     *
     * @return string
     */
    protected function getConfigFieldsTemplatePath(): string
    {
        return 'onedriveaddon/configs/onedrive';
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

        $this->config['max_packages']     = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'onedrive_msgraph_max_files', 10);
        $oldFolder                        = $this->config['storage_folder'];
        $this->config['storage_folder']   = self::getSanitizedInputFolder('_onedrive_msgraph_storage_folder', 'remove');
        $this->config['all_folders_perm'] = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'onedrive_msgraph_all_folders_read_write_perm', false);

        if ($this->isAuthorized() && $oldFolder != $this->config['storage_folder']) {
            $this->config['storage_folder_id'] = '';
            // Create new folder
            $folder                            = $this->getOneDriveStorageFolder();
            $this->config['storage_folder_id'] = $folder->id;
            $this->config['storage_folder_web_url'] = $folder->webUrl;
            $this->save();
        }

        $message = sprintf(
            __('OneDrive Storage Updated.', 'duplicator-pro'),
            $this->getStorageFolder()
        );
        return true;
    }

    /**
     * Get account info
     *
     * @return false|object
     */
    protected function getAccountInfo()
    {
        if (! $this->isAuthorized()) {
            return false;
        }

        $storageFolder = $this->getOneDriveStorageFolder();

        if (!$storageFolder || empty($storageFolder->user)) {
            return false;
        }

        return (object) $storageFolder->user;
    }

    /**
     * Get onedrive storage folder
     *
     * @return OneDriveStoragePathInfo|false
     */
    protected function getOneDriveStorageFolder()
    {
        $adapter = $this->getAdapter();

        if (! $adapter->isValid()) {
            DupLog::trace("OneDrive adapter is not valid, can't get storage folder.");
            return false;
        }

        if (!$this->config['storage_folder_id']) {
            if (! $adapter->initialize($error)) {
                DupLog::trace("Failed to initialize OneDrive adapter: $error");
                return false;
            }
            $folder                            = $adapter->getPathInfo('/');
            $this->config['storage_folder_id'] = $folder->id;
            $this->save();
        } else {
            $folder = $adapter->getPathInfo('/');
        }

        return $folder;
    }

    /**
     * Get stoage adapter
     *
     * @return OnedriveAdapter
     */
    protected function getAdapter(): OneDriveAdapter
    {
        if (!$this->adapter) {
            $global        = GlobalEntity::getInstance();
            $token         = $this->getToken();
            $this->adapter = new OneDriveAdapter(
                $token,
                $this->config['storage_folder'],
                $this->config['storage_folder_id'],
                !$global->ssl_disableverify,
                ($global->ssl_useservercerts ? '' : DUPLICATOR_CERT_PATH)
            );
            if (! $this->adapter->initialize($error)) {
                DupLog::trace("Failed to initialize OneDrive adapter: $error");
            }
        }
        return $this->adapter;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getTokenConfig(): array
    {
        return [
            'created'       => $this->config['token_obtained'],
            'expires_in'    => $this->config['expires_in'] ?? 3600,
            'scope'         => self::GRAPH_SCOPES,
            'access_token'  => $this->config['access_token'],
            'refresh_token' => $this->config['refresh_token'],
        ];
    }

    /**
     * Persist the refreshed token data back to the storage config.
     *
     * @param TokenEntity $token The refreshed token entity
     *
     * @return void
     */
    protected function setTokenConfig(TokenEntity $token): void
    {
        $this->config['token_obtained'] = $token->getCreated();
        $this->config['refresh_token']  = $token->getRefreshToken();
        $this->config['access_token']   = $token->getAccessToken();
        $this->config['expires_in']     = $token->getExpiresIn();
    }

    /**
     * @return void
     * @throws Exception
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
     * Get default settings
     *
     * @return array<string, scalar>
     */
    protected static function getDefaultSettings(): array
    {
        return [
            'onedrive_upload_chunksize_in_kb'   => self::UPLOAD_CHUNK_DEFAULT_SIZE_IN_KB,
            'onedrive_download_chunksize_in_kb' => self::DEFAULT_DOWNLOAD_CHUNK_SIZE_IN_KB,
        ];
    }

    /**
     * @return void
     */
    public static function renderGlobalOptions(): void
    {
        $dGlobal = DynamicGlobalEntity::getInstance();

        TplMng::getInstance()->render(
            'onedriveaddon/configs/global_options',
            [
                'uploadChunkSize'   => $dGlobal->getValInt('onedrive_upload_chunksize_in_kb', self::UPLOAD_CHUNK_DEFAULT_SIZE_IN_KB),
                'downloadChunkSize' => $dGlobal->getValInt('onedrive_download_chunksize_in_kb', self::DEFAULT_DOWNLOAD_CHUNK_SIZE_IN_KB),
            ]
        );
    }
}
