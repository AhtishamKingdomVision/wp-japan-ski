<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Addons\DupCloudAddon\Utils;

use Duplicator\Utils\Logging\DupLog;
use Duplicator\Addons\DupCloudAddon\Exceptions\PresignedUrlExpiredException;
use Duplicator\Addons\DupCloudAddon\Utils\RemoteStorageInfo;
use Duplicator\Libs\Snap\SnapString;
use Duplicator\Models\Storages\StoragePathInfo;
use Error;
use Exception;
use VendorDuplicator\WpOrg\Requests\Requests;

class DupCloudClient
{
    /** @var int Number of part URLs to request */
    const MAX_PART_REQUEST_COUNT  = 50;
    const BACKUP_TYPE_STANDARD    = 'standard';
    const BACKUP_TYPE_INCREMENTAL = 'incremental';

    /**
     * @var string The API URL
     */
    const API_PATH = '/api/';

    /**
     * @var string The API URL
     */
    const AUTH_PATH = '/dashboard/websites/create/';

    /** @var string backup storage token */
    private string $storageToken = '';

    /**
     * Class constructor
     *
     * @param string $storageToken The storage token
     *
     * @return void
     */
    public function __construct(string $storageToken = '')
    {
        $this->storageToken = $storageToken;
    }

    /**
     * Get Manage license storage Url
     *
     * @return string
     */
    public static function getManageLicenseStorageUrl(): string
    {
        return DUPLICATOR_STORE_URL . '/my-account/storages/';
    }

    /**
     * Get the Duplicator Cloud register URL
     *
     * @return string
     */
    public static function getRegisterUrl(): string
    {
        return DUPLICATOR_CLOUD_HOST . '/dashboard/register';
    }

    /**
     * Get create new remote bucker URL
     *
     * @return string
     */
    public static function manageWebsitesUrl(): string
    {
        return DUPLICATOR_CLOUD_HOST . '/dashboard/websites';
    }

    /**
     * Set the storage token
     *
     * @param string $storageToken The storage token
     *
     * @return void
     */
    public function setStorageToken(string $storageToken): void
    {
        $this->storageToken = $storageToken;
    }

    /**
     * Send a request to the cloud
     *
     * @param string              $path    The API path
     * @param string              $token   The token
     * @param string              $type    The request type
     * @param array<string,mixed> $headers The headers
     * @param array<string,mixed> $data    The data
     * @param array<string,mixed> $options The options
     *
     * @return array{success:bool,httpCode:int,data:array<string,mixed>,message:string} The result
     */
    private static function request(
        string $path,
        string $token = '',
        string $type = Requests::GET,
        array $headers = [],
        array $data = [],
        array $options = []
    ): array {
        $url    = self::getApiUrl($path);
        $result = [
            'success'  => false,
            'httpCode' => -1,
            'data'     => [],
            'message'  => '',
        ];

        try {
            if (DupCloudRateLimitHandler::isBlocked($url)) {
                DupLog::infoTrace("DupCloud rate limit hit for $url. Not sending request.");
                return [
                    'success'  => false,
                    'httpCode' => 429,
                    'data'     => [],
                    'message'  => __('DupCloud rate limit hit for this URL. Please try again later.', 'duplicator-pro'),
                ];
            }

            $headers['Accept'] = 'application/json';
            if (strlen($token) > 0) {
                $headers['Authorization'] = 'Bearer ' . $token;
            }

            $request = [
                'url'     => $url,
                'type'    => $type,
                'headers' => $headers,
                'data'    => $data,
            ];

            $response           = Requests::request(self::getApiUrl($path), $headers, $data, $type, $options);
            $result['success']  = false;
            $result['httpCode'] = $response->status_code;
            $bodyDecoded        = !empty($response->body) ? $response->decode_body() : [];
            $result['data']     = ($bodyDecoded['data'] ?? []);

            if ($response->status_code === 429) {
                $retryAfter = isset($bodyDecoded['retry_after']) ? $bodyDecoded['retry_after'] : 60;
                do_action('duplicator_dup_cloud_rate_limit_error', $url, $retryAfter);
            }

            if (isset($bodyDecoded['message']) && strlen($bodyDecoded['message']) > 0) {
                $result['message'] = $bodyDecoded['message'];
            } else {
                $result['message'] = '';
            }

            if ($response->status_code < 200 || $response->status_code >= 300) {
                $result['success'] = false;
                $result['message'] = (strlen($result['message']) > 0 ?
                    $result['message'] :
                    sprintf(__('Remote server error code: %s', 'duplicator-pro'), $response->status_code)
                );
                DupLog::traceBacktrace('ERROR ON CLOUD HTTP REQUEST msg: ' . $result['message']);
                DupLog::traceObject('REQUEST:', $request);
                DupLog::traceObject('RESPONSE:', $bodyDecoded);
            } elseif (isset($bodyDecoded['success']) && $bodyDecoded['success'] === false) {
                $result['success'] = false;
                $result['message'] = (strlen($result['message']) > 0 ?
                    $result['message'] :
                    __('Remote server error', 'duplicator-pro')
                );
                DupLog::traceBacktrace('ERROR ON CLOUD REQUEST FUNCTION msg: ' . $result['message']);
                DupLog::traceObject('REQUEST:', $request);
                DupLog::traceObject('RESPONSE:', $bodyDecoded);
            } else {
                $result['success'] = true;
                $result['message'] = (strlen($result['message']) > 0) ? $result['message'] : __('Success', 'duplicator-pro');

                if (!isset($bodyDecoded['data'])) {
                    $result['data'] = [];
                    DupLog::traceObject('REQUEST:', $request);
                    DupLog::traceObject('RESPONSE DATA NOT FOUND IN BODY:', $bodyDecoded);
                }
            }
        } catch (Exception | Error $e) {
            $result['success']  = false;
            $result['httpCode'] = -1;
            $result['message']  = $e->getMessage();
            DupLog::traceException($e, 'ERROR EXCEPTION ON CLOUD REQUEST');
            DupLog::traceObject('REQUEST:', $request);
        }

        return $result;
    }


    /**
     * Checks if the Cloud is available and ready to use.
     * if success is false meant that request failed
     * if authorized is false means that the storage token is not valid or expired
     * if ready is false means that the storage is not ready to use, probably another upload is in progress
     *
     * Endpoint: website/verify-storage
     * Response: [
     *     'success' => bool,
     *     'message' => string,
     *     'data' => [
     *         'authorized' => bool,
     *         'user_name' => string,
     *         'user_email' => string,
     *         'ready_for_upload' => bool,
     *         'total_space' => int,
     *         'free_space' => int,
     *         'message' => string
     *     ]
     * ]
     *
     * @param string $message Reference for any error message.
     *
     * @return RemoteStorageInfo Returns RemoteStorageInfo instance with default values if request fails
     */
    public function remoteStorageInfo(string &$message = ''): RemoteStorageInfo
    {
        if (strlen($this->storageToken) === 0) {
            // If token is empty return success and authorized false
            return new RemoteStorageInfo(true, false);
        }

        $result  = self::request('website/verify-storage', $this->storageToken);
        $message = $result['message'];

        if (!$result['success']) {
            // Unauthorized 401 HTTP code is considered success
            $success = ($result['httpCode'] === 401);
            return new RemoteStorageInfo($success);
        }

        return new RemoteStorageInfo(
            true,
            (bool) $result['data']['authorized'],
            (bool) $result['data']['ready_for_upload'],
            (int) $result['data']['total_space'],
            (int) $result['data']['free_space'],
            (string) $result['data']['user_name'],
            (string) $result['data']['user_email'],
            (string) $result['data']['website_uuid']
        );
    }

    /**
     * Get user info
     *
     * @return array{name:string,email:string,email_verified_at:string,created_at:string}
     */
    public function getUserInfo()
    {
        $result = self::request('user', $this->storageToken);
        if (!$result['success']) {
            DupLog::traceObject('Failed to get user info', $result);
            throw new Exception($result['message']);
        }
        return $result['data'];
    }

    /**
     * Revokes authorization
     *
     * @return bool
     */
    public function revoke()
    {
        $result = self::request(
            'revoke',
            $this->storageToken,
            Requests::POST
        );

        return $result['success'];
    }

    /**
     * Start Upload
     *
     * @param array<stirng, mixed> $backupDetails The backup details
     * @param string               $backupType    The backup type, default is standard
     *
     * @return array{'uuid': string, 'url': string}
     */
    public function startUpload($backupDetails, $backupType = self::BACKUP_TYPE_STANDARD)
    {
        if (!isset($backupDetails['file_info']['backup_filename'])) {
            throw new Exception('Invalid backup details');
        }
        if (!preg_match(DUPLICATOR_ARCHIVE_REGEX_PATTERN, $backupDetails['file_info']['backup_filename'])) {
            throw new Exception('Invalid backup name');
        }

        $result = self::request(
            'website/upload',
            $this->storageToken,
            Requests::POST,
            [],
            [
                'backup_details' => $backupDetails,
                'backup_type'    => $backupType,
            ]
        );
        if (!$result['success']) {
            DupLog::traceObject('Failed to start upload to website', $result);
            throw new Exception($result['message']);
        }

        return $result['data'];
    }

    /**
     * Complete upload
     *
     * @param string $uuid       The uuid
     * @param string $etag       The etag
     * @param int    $maxBackups The max backups
     *
     * @return bool
     */
    public function completeUpload($uuid, $etag, $maxBackups)
    {
        $result = self::request(
            'website/upload/' . $uuid,
            $this->storageToken,
            Requests::POST,
            [],
            [
                'etag'        => $etag,
                'max_backups' => $maxBackups,
            ]
        );

        if (!$result['success']) {
            DupLog::traceObject('Failed to complete upload to website', $result);
            throw new Exception($result['message']);
        }

        return $result['success'];
    }

    /**
     * Mark an upload as failed on the remote server
     *
     * @param string $backupName The backup name to cancel
     *
     * @return bool True on success
     * @throws Exception If the request fails or backup name is invalid
     */
    public function failUploadByName(string $backupName): bool
    {
        if (empty($this->storageToken)) {
            throw new Exception('No storage token provided', 401);
        }

        if (empty($backupName)) {
            throw new Exception('Backup name is required');
        }

        $result = self::request(
            'website/backups/' . rawurlencode($backupName) . '/fail',
            $this->storageToken,
            Requests::POST
        );

        if (!$result['success']) {
            DupLog::traceObject('Failed to fail upload for backup ' . $backupName, $result);
            throw new Exception($result['message']);
        }

        return true;
    }

    /**
     * Mark an upload as canceled on the remote server
     *
     * @param string $backupName The backup name to cancel
     *
     * @return bool True on success
     * @throws Exception If the request fails or backup name is invalid
     */
    public function cancelUploadByName(string $backupName): bool
    {
        if (empty($this->storageToken)) {
            throw new Exception('No storage token provided', 401);
        }

        if (empty($backupName)) {
            throw new Exception('Backup name is required');
        }

        $result = self::request(
            'website/backups/' . rawurlencode($backupName) . '/cancel',
            $this->storageToken,
            Requests::POST
        );

        if (!$result['success']) {
            DupLog::traceObject('Failed to cancel upload for backup ' . $backupName, $result);
            throw new Exception($result['message']);
        }

        return true;
    }

    /**
     * Mark an upload as failed on the remote server
     *
     * @param string $uuid The backup upload UUID to cancel
     *
     * @return bool True on success
     * @throws Exception If the request fails or UUID/token invalid
     */
    public function failUpload(string $uuid): bool
    {
        if (empty($this->storageToken)) {
            throw new Exception('No storage token provided', 401);
        }

        if (empty($uuid)) {
            throw new Exception('Upload UUID is required');
        }

        $result = self::request(
            'website/upload/' . $uuid . '/fail',
            $this->storageToken,
            Requests::POST
        );

        if (!$result['success']) {
            DupLog::traceObject('Failed to fail upload ' . $uuid, $result);
            throw new Exception($result['message']);
        }

        return true;
    }

    /**
     * Cancel an ongoing upload on the remote server
     *
     * @param string $uuid The backup upload UUID to cancel
     *
     * @return bool True on success
     * @throws Exception If the request fails or UUID/token invalid
     */
    public function cancelUpload(string $uuid): bool
    {
        if (empty($this->storageToken)) {
            throw new Exception('No storage token provided', 401);
        }

        if (empty($uuid)) {
            throw new Exception('Upload UUID is required');
        }

        $result = self::request(
            'website/upload/' . $uuid . '/cancel',
            $this->storageToken,
            Requests::POST
        );

        if (!$result['success']) {
            DupLog::traceObject('Failed to cancel upload ' . $uuid, $result);
            throw new Exception($result['message']);
        }

        return true;
    }

    /**
     * Upload the contents of the file
     *
     * @param string $path     The path of the file
     * @param string $fileType The file type
     * @param string $saveAs   The save as name
     *
     * @return bool
     */
    public function directUpload($path, $fileType, $saveAs = '')
    {
        if (!file_exists($path) || !is_readable($path)) {
            throw new Exception("File does not exist or is not readable: $path");
        }

        $size = filesize($path);
        if ($size === false) {
            throw new Exception("File is empty: $path");
        }

        $fileName = strlen($saveAs) > 0 ? $saveAs : basename($path);
        if (!self::isAllowedFileName($fileName)) {
            throw new Exception('The provided file name is invalid: ' . $fileName);
        }

        DupLog::infoTrace("directUpload: file_name={$fileName} file_type={$fileType} size={$size}");

        $result = self::request(
            'website/direct_upload',
            $this->storageToken,
            Requests::POST,
            [],
            [
                'file_name' => $fileName,
                'file_type' => $fileType,
                'size'      => $size,
            ]
        );

        if (!$result['success']) {
            DupLog::infoTrace(
                "directUpload failed: httpCode={$result['httpCode']} message={$result['message']} data=" . print_r($result['data'], true)
            );
            DupLog::traceObject('Failed to get direct upload URL', $result);
            throw new Exception($result['message']);
        }

        if (!isset($result['data']['url'])) {
            DupLog::traceObject('No url returned', $result);
            throw new Exception('No url returned');
        }

        if (!isset($result['data']['headers'])) {
            DupLog::traceObject('No headers returned', $result);
            throw new Exception('No headers returned');
        }

        $url = $result['data']['url'];
        if (($content = file_get_contents($path)) === false) {
            throw new Exception("Can't read file: $path");
        }

        $response = Requests::request(
            $url,
            [],
            $content, // @phpstan-ignore-line
            Requests::PUT,
            ['timeout' => 300]
        );
        if (!$response->success) {
            DupLog::traceObject('Failed to upload file to pre-signed URL', $response);
            throw new Exception('Failed to upload file to pre-signed URL');
        }

        return $response->success;
    }

    /**
     * Upload the contents of the file
     *
     * @param string               $path          The path of the file
     * @param array<stirng, mixed> $backupDetails The backup details
     * @param int                  $maxBackups    Max backups
     *
     * @return bool
     */
    public function upload($path, $backupDetails, $maxBackups)
    {
        if (!file_exists($path) || !is_readable($path)) {
            throw new Exception("File does not exist or is not readable: $path");
        }

        if (($content = @file_get_contents($path)) === false) {
            throw new Exception("Could not read file $path");
        }

        $uploadData = $this->startUpload($backupDetails);
        if (empty($uploadData) || !isset($uploadData['url'])) {
            DupLog::traceObject('Failed to start upload', $uploadData);
            throw new Exception('Could not start upload');
        }

        $response = Requests::put(
            $uploadData['url'],
            [],
            $content,
            ['timeout' => 300]
        );

        if ($response->status_code !== 200) {
            DupLog::traceObject('Failed to upload file to pre-signed URL', $response);
            throw new Exception('Failed to upload file to pre-signed URL');
        }

        return $this->completeUpload($uploadData['uuid'], md5($content), $maxBackups);
    }

    /**
     * Start multipart upload
     *
     * @param array<string, mixed> $backupDetails The backup details
     * @param string               $backupType    The backup type, default is standard
     *
     * @return array{uuid: string, urls: array<int, string>} URLs indexed by part number (starting from 1)
     */
    public function startMultipart($backupDetails, $backupType = self::BACKUP_TYPE_STANDARD): array
    {
        if (!isset($backupDetails['file_info']['backup_filename'])) {
            throw new Exception('Invalid backup details');
        }
        if (!preg_match(DUPLICATOR_ARCHIVE_REGEX_PATTERN, $backupDetails['file_info']['backup_filename'])) {
            throw new Exception("Invalid backup name: " . $backupDetails['file_info']['backup_filename']);
        }

        $result = self::request(
            'website/multipart',
            $this->storageToken,
            Requests::POST,
            [],
            [
                'range'          => '1-' . self::MAX_PART_REQUEST_COUNT,
                'backup_type'    => $backupType,
                'backup_details' => $backupDetails,
            ]
        );

        if (!$result['success']) {
            DupLog::traceObject('Failed to start multipart upload', $result);
            throw new Exception($result['message']);
        }

        // Check if uuid and urls are set and not empty
        if (empty($result['data']['uuid']) || empty($result['data']['urls'])) {
            DupLog::traceObject('UUID or URLs are not set or empty', $result);
            throw new Exception('UUID or URLs are not set or empty');
        }

        return [
            'uuid' => $result['data']['uuid'],
            'urls' => self::reIndexArray($result['data']['urls'], 1),
        ];
    }

    /**
     * Upload Part
     *
     * @param string $url     The url
     * @param mixed  $content The content
     *
     * @return void
     *
     * @throws PresignedUrlExpiredException If the presigned URL has expired
     * @throws Exception If the upload fails for other reasons
     */
    public function uploadPart($url, $content)
    {
        $response = Requests::request(
            $url,
            [],
            $content,
            Requests::PUT,
            ['timeout' => 300]
        );

        if ($response->status_code !== 200) {
            DupLog::traceObject('Failed to upload part', $response);
            if ($response->status_code === 403) {
                // most likely an expired URL
                throw new PresignedUrlExpiredException('Presigned URL has expired');
            }

            throw new Exception('Request to upload part failed');
        }

        if ($response->success === false) {
            throw new Exception('Request to upload part failed');
        }
    }

    /**
     * Get presigned URLs for uploading parts
     *
     * @param string $uuid            The uuid
     * @param int    $startPartNumber The start part number (1-indexed)
     *
     * @return array<int, string> URLs indexed by part number (starting from $startPartNumber)
     */
    public function getPartUrls($uuid, $startPartNumber)
    {
        $result = self::request(
            'website/multipart/' . $uuid,
            $this->storageToken,
            Requests::GET,
            [],
            ['range' => $startPartNumber . '-' . ($startPartNumber + self::MAX_PART_REQUEST_COUNT - 1)]
        );

        if (!$result['success']) {
            DupLog::traceObject('Failed to get multipart upload URLs', $result);
            throw new Exception($result['message']);
        }

        if (empty($result['data']['urls'])) {
            DupLog::traceObject('URLs are not set or empty', $result);
            throw new Exception('URLs are not set or empty');
        }

        if (count($result['data']['urls']) !== self::MAX_PART_REQUEST_COUNT) {
            DupLog::traceObject('URLs count is not equal to the requested range', $result);
            throw new Exception('URLs count is not equal to the requested range');
        }

        return self::reIndexArray($result['data']['urls'], $startPartNumber);
    }

    /**
     * Complete upload
     *
     * @param string                                          $uuid       The uuid
     * @param array<array{'ETag': string, 'PartNumber': int}> $parts      The parts
     * @param int                                             $maxBackups The max backups
     *
     * @return bool
     * @throws Exception If encoding parts as JSON fails
     */
    public function completeMultipart($uuid, $parts, $maxBackups = 0)
    {
        // Encode parts as JSON string to avoid max_input_vars limit
        $partsJson = json_encode($parts);
        if ($partsJson === false) {
            throw new Exception('Failed to encode parts as JSON');
        }

        DupLog::info("Sending complete request with parts: " . $partsJson);
        $result = self::request(
            'website/multipart/' . $uuid,
            $this->storageToken,
            Requests::POST,
            [],
            [
                'parts'       => $partsJson,
                'max_backups' => $maxBackups,
            ]
        );
        if (!$result['success']) {
            DupLog::traceObject('Failed to complete multipart upload', $result);
            throw new Exception($result['message']);
        }

        return $result['success'];
    }


    /**
     * Upload Part
     *
     * @param string $url    The url
     * @param int    $offset The offset
     * @param int    $length The length, if < 0 download the whole file
     *
     * @return string|false The response body or false on failure
     */
    public function downloadChunk($url, $offset, $length = -1)
    {
        $headers = [];
        if ($length > 0) {
            $headers['Range'] = 'bytes=' . $offset . '-' . ($offset + $length - 1);
        }

        $response = Requests::get(
            $url,
            $headers,
            ['timeout' => 300]
        );

        if ($length < 0 && $response->status_code !== 200) {
            DupLog::traceObject('Error downloading whole file', $response);
            return false;
        } elseif ($length > 0 && $response->status_code !== 206) {
            DupLog::traceObject('Error downloading chunk', $response);
            return false;
        }

        return $response->body;
    }

    /**
     * Get the auth url
     *
     * @param string $url The url
     *
     * @return string
     * @throws \Exception
     */
    public static function getAuthUrl($url): string
    {
        if (empty($url)) {
            throw new Exception('URL is required');
        }

        return DUPLICATOR_CLOUD_HOST . self::AUTH_PATH . base64_encode($url);
    }

    /**
     * Get the API URL
     *
     * @param string $path The path
     *
     * @return string
     */
    private static function getApiUrl(string $path = ''): string
    {
        return DUPLICATOR_CLOUD_HOST . self::API_PATH . trim($path, '/');
    }

    /**
     * Authenticate site using compound token (license key + sanctum token)
     *
     * @param string $compoundToken  The compound token in format: {license_key}.{sanctum_token}
     * @param string $siteIdentifier The unique site identifier
     *
     * @return array{token:string,website:array{id:int,name:string,url:string},storage:array{id:int,name:string,total_space:int,used_space:int}}
     * @throws Exception If the authentication fails
     */
    public function authenticateSite(string $compoundToken, string $siteIdentifier): array
    {
        DupLog::trace('AUTHENTICATE SITE !!!!');
        // Parse compound token
        $parts = explode('.', $compoundToken, 2);
        if (count($parts) !== 2) {
            throw new Exception(__('Invalid token format. Please ensure you copied the complete token.', 'duplicator-pro'));
        }

        $licenseKey   = $parts[0];
        $sanctumToken = $parts[1];

        if (empty($licenseKey) || empty($sanctumToken)) {
            throw new Exception(__('Invalid token. Both license key and authentication token are required.', 'duplicator-pro'));
        }

        // Get site info for the request
        $siteUrl  = get_home_url();
        $siteName = get_bloginfo('name');
        if (empty($siteName)) {
            $siteName = parse_url($siteUrl, PHP_URL_HOST) ?: 'WordPress Site';
        }

        $requestData = [
            'license_key'     => $licenseKey,
            'sanctum_token'   => $sanctumToken,
            'site_identifier' => $siteIdentifier,
            'name'            => $siteName,
            'url'             => $siteUrl,
            'backup_type'     => self::BACKUP_TYPE_STANDARD, // Default to standard
        ];

        // Call Laravel authenticate-site endpoint
        $result = self::request(
            'auth/authenticate-site',
            '', // No bearer token needed for this endpoint
            Requests::POST,
            [],
            $requestData
        );

        if (!$result['success']) {
            DupLog::trace('Failed to authenticate site: ' . $result['message']);
            throw new Exception($result['message']);
        }

        // Validate response has required fields
        if (empty($result['data']['token'])) {
            DupLog::traceObject('No token received from authenticate-site', $result);
            throw new Exception(__('Authentication succeeded but no storage token was provided.', 'duplicator-pro'));
        }

        // Set the storage token for future API calls
        $this->setStorageToken($result['data']['token']);

        $redactedData          = $result['data'];
        $redactedData['token'] = SnapString::obfuscateString($result['data']['token'], 5);
        DupLog::traceObject('AUTH TOKEN DATA ', $redactedData);

        // Return the response data directly
        return $result['data'];
    }

    /**
     * Downloads a backup from the cloud storage
     *
     * @param string $filename The name of the file to download
     *
     * @return array{download_url:string, size:int, expires_at:int} The download data
     * @throws Exception If the request fails or backup name is invalid
     */
    public function getDownloadData(string $filename): array
    {
        if (empty($this->storageToken)) {
            throw new Exception('No storage token provided', 401);
        }

        if (!self::isAllowedFileName($filename)) {
            throw new Exception('The provided file name is invalid: ' . $filename);
        }

        $result = self::request(
            'backups/' . $filename . '/download',
            $this->storageToken,
            Requests::GET
        );

        if (!$result['success']) {
            DupLog::trace('Failed to get download data for backup ' . $filename . ' ' . $result['message']);
            throw new Exception($result['message']);
        }

        return $result['data'];
    }

    /**
     * Deletes all backups off the website from the cloud storage
     *
     * @return bool Returns true if deletion was successful
     * @throws Exception If the request fails or backup name is invalid
     */
    public function deleteAllBackups(): bool
    {
        if (empty($this->storageToken)) {
            throw new Exception('No storage token provided', 401);
        }

        $result = self::request(
            'backups',
            $this->storageToken,
            Requests::DELETE
        );

        if (!$result['success']) {
            DupLog::trace('Failed to delete backup all backups ' . $result['message']);
            throw new Exception($result['message']);
        }

        return true;
    }

    /**
     * Delete a backup from the cloud storage
     *
     * @param string $filename The backup name to delete
     *
     * @return bool Returns true if deletion was successful
     * @throws Exception If the request fails or backup name is invalid
     */
    public function deleteFile(string $filename): bool
    {
        if (empty($this->storageToken)) {
            throw new Exception('No storage token provided', 401);
        }

        if (!self::isAllowedFileName($filename)) {
            throw new Exception('The provided backup name is invalid');
        }

        $result = self::request(
            'backups/' . urlencode($filename),
            $this->storageToken,
            Requests::DELETE
        );

        if (!$result['success']) {
            DupLog::trace('Failed to delete backup ' . $filename . ' ' . $result['message']);
            throw new Exception($result['message']);
        }

        return true;
    }

    /**
     * Get list of backups from the cloud storage
     *
     * @return ?StoragePathInfo[] Returns array of backup information
     *
     * @throws Exception If the request fails or authentication is invalid
     */
    public function getFileList(): ?array
    {
        if (empty($this->storageToken)) {
            throw new Exception('No storage token provided', 401);
        }

        $result = self::request(
            'backups',
            $this->storageToken,
            Requests::GET,
        );

        if (!$result['success']) {
            DupLog::trace('Failed to get backup list ' . $result['message']);
            throw new Exception($result['message']);
        }

        $infoList = [];
        foreach ($result['data'] as $key => $value) {
            $info           = new StoragePathInfo();
            $info->path     = $value['path'];
            $info->exists   = $value['exists'];
            $info->isDir    = $value['isDir'];
            $info->size     = $value['size'];
            $info->created  = $value['created'];
            $info->modified = $value['modified'];

            $infoList[$key] = $info;
        }

        return $infoList;
    }

    /**
     * Get detailed information about a specific backup
     *
     * @param string $filename The file name to get info for
     *
     * @return StoragePathInfo Returns detailed backup information
     *
     * @throws Exception If the request fails or backup name is invalid
     */
    public function getFileInfo(string $filename): StoragePathInfo
    {
        if (empty($this->storageToken)) {
            throw new Exception('No storage token provided', 401);
        }

        // Validate backup name is a single filename
        if (!self::isAllowedFileName($filename)) {
            throw new Exception('The provided file name is invalid: ' . $filename);
        }

        $result = self::request(
            'backups/' . urlencode($filename),
            $this->storageToken,
            Requests::GET
        );

        if (!$result['success']) {
            DupLog::trace('Failed to get backup info ' . $filename . ' ' . $result['message']);
            $emptyFile       = new StoragePathInfo();
            $emptyFile->path = $filename;
            return $emptyFile;
        }

        $info           = new StoragePathInfo();
        $info->path     = $result['data']['path'];
        $info->exists   = $result['data']['exists'];
        $info->isDir    = $result['data']['isDir'];
        $info->size     = $result['data']['size'];
        $info->created  = $result['data']['created'];
        $info->modified = $result['data']['modified'];

        return $info;
    }

    /**
     * Duplicator Cloud Storage only allows file operations on backup files
     *
     * @param string $path The path to check
     *
     * @return bool
     */
    public static function isAllowedFileName(string $path): bool
    {
        $filename = ltrim(rtrim($path, '\\/'), '\\/');

        if (DupCloudStorageAdapter::getDirectUploadFileType($filename) !== null) {
            return true;
        }

        return preg_match(DUPLICATOR_GEN_FILE_REGEX_PATTERN, $filename) === 1;
    }

    /**
     * Duplicator Cloud Storage only allows directory operations on the root directory.
     *
     * @param string $path The path to check
     *
     * @return bool
     */
    public static function isRootDir(string $path): bool
    {
        return $path === '/' || $path === '';
    }

    /**
     * Reindex array starting from $startIndex
     *
     * @param array<mixed> $array      Array to reindex
     * @param int          $startIndex Starting index
     *
     * @return array<int, mixed> Reindexed array
     */
    private static function reIndexArray(array $array, int $startIndex): array
    {
        $result = [];
        foreach (array_values($array) as $index => $value) {
            $result[$startIndex + $index] = $value;
        }
        return $result;
    }
}
