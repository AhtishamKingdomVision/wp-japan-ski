<?php

namespace Duplicator\Addons\FtpAddon\Models;

use CurlHandle;
use Duplicator\Addons\FtpAddon\Utils\FTPUtils;
use Duplicator\Models\Storages\AbstractStorageAdapter;
use Duplicator\Models\Storages\StoragePathInfo;
use Duplicator\Libs\Snap\SnapIO;

class FTPCurlStorageAdapter extends AbstractStorageAdapter
{
    /** @var int */
    const DEFAULT_CHUNK_SIZE = 2 * 1024 * 1024;
    private string $root;
    /** @var string */
    private $server = '';
    private int $port;
    /** @var string */
    private $username = '';
    /** @var string */
    private $password = '';
    private int $timeoutInSec;
    private bool $ssl;
    private bool $sslVerify;
    /** @var string */
    private $sslCertificate = '';
    private bool $passiveMode;
    /** @var resource */
    private $sourceFileHandle;
    /** @var string */
    private $lastSourceFilePath = '';
    /** @var resource */
    private $destFileHandle;
    /** @var string */
    private $lastDestFilePath = '';
    /** @var resource */
    private $tempFileHandle;
    /** @var null|resource|CurlHandle */
    private $connection; // @phpstan-ignore property.unusedType
    private int $throttle;
    /** @var string|null */
    private $cachedSystemType = null;

    /**
     * Class constructor
     *
     * @param string $server         The server to connect to
     * @param int    $port           The port to connect to
     * @param string $username       The username to use
     * @param string $password       The password to use
     * @param string $root           The root directory to use
     * @param int    $timeoutInSec   The timeout in seconds
     * @param bool   $ssl            Whether to use SSL
     * @param bool   $passiveMode    Whether to use passive mode
     * @param int    $throttle       The throttle in microseconds
     * @param bool   $sslVerify      Whether to verify SSL
     * @param string $sslCertificate The SSL certificate
     */
    public function __construct(
        $server,
        $port = 21,
        $username = '',
        $password = '',
        $root = '/',
        $timeoutInSec = 15,
        $ssl = false,
        $passiveMode = false,
        $throttle = 0,
        $sslVerify = false,
        $sslCertificate = ''
    ) {
        $this->server         = $server;
        $this->port           = (int) $port;
        $this->username       = $username;
        $this->password       = $password;
        $this->root           = SnapIO::trailingslashit($root);
        $this->timeoutInSec   = max(1, (int) $timeoutInSec);
        $this->ssl            = (bool) $ssl;
        $this->passiveMode    = (bool) $passiveMode;
        $this->throttle       = max(0, (int) $throttle);
        $this->sslVerify      = (bool) $sslVerify;
        $this->sslCertificate = $sslCertificate;
    }

    /**
     * Opens the FTP connection and initializes root directory
     *
     * @param string $errorMsg The error message to return
     *
     * @return bool True on success, false on failure
     */
    public function initialize(&$errorMsg = ''): bool
    {
        if (!$this->isDir('/') && !$this->createDir('/')) {
            $errorMsg = "Couldn't create root directory.";
            return false;
        }
        $this->wait();
        return true;
    }

    /**
     * Throttle the connection
     *
     * @return void
     */
    protected function wait()
    {
        if ($this->throttle > 0) {
            usleep($this->throttle);
        }
    }

    /**
     * Check if storage is valid and ready to use.
     *
     * @param string $errorMsg The error message if storage is invalid.
     *
     * @return bool
     */
    protected function realIsValid(string &$errorMsg = ''): bool
    {
        if (!$this->isConnectionInfoValid($errorMsg)) {
            $errorMsg = __('FTP connection info is invalid.', 'duplicator-pro');
            return false;
        }

        if ($this->testConnection($errorMsg) === false) {
            $errorMsg = __('FTP connection failed.', 'duplicator-pro');
            return false;
        }

        if (!$this->isDir('/')) {
            $errorMsg = __('FTP root directory doesn\'t exist.', 'duplicator-pro');
            return false;
        }

        return true;
    }

    /**
     * Checks if the connection info is valid
     *
     * @param string $errorMsg The error message to return
     *
     * @return bool
     */
    protected function isConnectionInfoValid(&$errorMsg = ''): bool
    {
        if (strlen($this->server) < 1) {
            $errorMsg = "FTP server is empty.";
            return false;
        }

        if (strlen($this->username) < 1) {
            $errorMsg = "FTP username is empty.";
            return false;
        }

        if (strlen($this->password) < 1) {
            $errorMsg = "FTP password is empty.";
            return false;
        }

        if ($this->port < 1) {
            $errorMsg = "FTP port is invalid.";
            return false;
        }

        if (strlen($this->root) < 1) {
            $errorMsg = "FTP root directory is empty.";
            return false;
        }

        return true;
    }

    /**
     * test ftp connection
     *
     * @param string $errorMsg error message
     *
     * @return boolean
     */
    private function testConnection(string &$errorMsg = ''): bool
    {
        $path = $this->getFullPath('/', true);
        return $this->curlCall($path, [CURLOPT_TIMEOUT => $this->timeoutInSec]) !== false;
    }

    /**
     * Create the directory specified by pathname, recursively if necessary.
     *
     * @param string $path The directory path.
     *
     * @return bool true on success or false on failure.
     */
    protected function realCreateDir(string $path): bool
    {
        try {
            $path = SnapIO::trailingslashit($this->getFullPath($path, true));
            return $this->curlCall($path, [CURLOPT_FTP_CREATE_MISSING_DIRS => true]) !== false;
        } finally {
            $this->wait();
        }
    }

    /**
     * Create file with content.
     *
     * @param string $path    The path to file.
     * @param string $content The content of file.
     *
     * @return false|int The number of bytes that were written to the file, or false on failure.
     */
    protected function realCreateFile(string $path, string $content)
    {
        try {
            if (($fullPath = $this->getFullPath($path)) === false) {
                return false;
            }

            if ($this->exists($path) && !$this->delete($path)) {
                return false;
            }

            if (($stream = @fopen('php://temp', 'r+')) === false) {
                return false;
            }

            if (($bytesWritten = @fwrite($stream, $content)) === false) {
                @fclose($stream);
                return false;
            }

            @rewind($stream);

            $success = $this->curlCall(
                $fullPath,
                [
                    CURLOPT_UPLOAD                  => true,
                    CURLOPT_NOPROGRESS              => true,
                    CURLOPT_FTP_CREATE_MISSING_DIRS => true,
                    CURLOPT_INFILE                  => $stream,
                    CURLOPT_INFILESIZE              => $bytesWritten,
                ]
            );

            @fclose($stream);
            if ($success === false) {
                return false;
            }

            return $bytesWritten;
        } finally {
            $this->wait();
        }
    }

    /**
     * Get file content.
     *
     * @param string $path The path to file.
     *
     * @return string|false The content of file or false on failure.
     */
    public function getFileContent(string $path)
    {
        if (($path = $this->getFullPath($path)) === false) {
            return false;
        }

        if (($content = $this->curlCall($path)) === false) {
            return false;
        }

        return $content;
    }

    /**
     * Move and/or rename a file or directory.
     *
     * @param string $oldPath Relative storage path
     * @param string $newPath Relative storage path
     *
     * @return bool true on success or false on failure.
     */
    protected function realMove(string $oldPath, string $newPath): bool
    {
        try {
            if (($fullOldPath = $this->getFullPath($oldPath)) === false) {
                return false;
            }

            if (($fullNewPath = $this->getFullPath($newPath)) === false) {
                return false;
            }

            return $this->curlCall('/', [CURLOPT_QUOTE => ["RNFR " . $fullOldPath, "RNTO " . $fullNewPath]]) !== false;
        } finally {
            $this->wait();
        }
    }

    /**
     * Delete relative path from storage root.
     *
     * @param string $path      The path to delete. (Accepts directories and files)
     * @param bool   $recursive Allows the deletion of nested directories specified in the pathname. Default to false.
     *
     * @return bool true on success or false on failure.
     */
    protected function realDelete(string $path, bool $recursive = false): bool
    {
        try {
            $fullPath = $this->getFullPath($path, true);
            if ($this->isDir($path)) {
                $fullPath = SnapIO::trailingslashit($fullPath);
                if ($recursive) {
                    foreach ($this->scanDir($path) as $item) {
                        if (!$this->realDelete(SnapIO::trailingslashit($path) . $item, true)) {
                            return false;
                        }
                    }
                }
                return $this->curlCall('/', [CURLOPT_QUOTE => ["RMD " . $fullPath]]) !== false;
            } elseif ($this->isFile($path)) {
                return $this->curlCall('/', [CURLOPT_QUOTE => ["DELE " . $fullPath]]) !== false;
            } else {
                //path doesn't exist
                return true;
            }
        } finally {
            $this->wait();
        }
    }

    /**
     * Copy local file to storage, partial copy is supported.
     * If destination file exists, it will be overwritten.
     * If offset is less than the destination file size, the file will be truncated.
     *
     * @param string              $sourceFile       The source file full path
     * @param string              $storageFile      Storage destination path
     * @param int<0,max>          $offset           The offset where the data starts.
     * @param int                 $length           The maximum number of bytes read. Default to -1 (read all the remaining buffer).
     * @param int                 $timeout          The timeout for the copy operation in microseconds. Default to 0 (no timeout).
     * @param array<string,mixed> $extraData        Extra data to pass to copy function and updated during copy.
     *                                              This data is intended to be per-file and may be reset between files.
     * @param array<string,mixed> $generalExtraData Extra data to pass to copy function that persists across files
     *                                              during the entire transfer operation.
     *
     * @return false|int The number of bytes that were written to the file, or false on failure.
     */
    protected function realCopyToStorage(
        string $sourceFile,
        string $storageFile,
        int $offset = 0,
        int $length = -1,
        int $timeout = 0,
        array &$extraData = [],
        array &$generalExtraData = []
    ) {
        try {
            $startTime = microtime(true);

            if (($storageFileFullPath = $this->getFullPath($storageFile)) === false) {
                return false;
            }

            if (!is_file($sourceFile)) {
                return false;
            }

            if ($offset === 0 && $this->isFile($storageFile) && !$this->delete($storageFile)) {
                return false;
            }

            // Uplaod file at once without any other operation
            if (($timeout === 0 && $offset === 0 && $length < 0) || filesize($sourceFile) < $length) {
                if (($content = @file_get_contents($sourceFile)) === false) {
                    return false;
                }

                return $this->createFile($storageFile, $content);
            }

            if (
                ($sourceFileHandle = $this->getSourceFileHandle($sourceFile)) === false ||
                ($tempFileHandle   = $this->getTempFileHandle()) === false
            ) {
                return false;
            }

            $bytesWritten = 0;
            $length       = $length < 0 ? self::DEFAULT_CHUNK_SIZE : $length;
            do {
                if (
                    @fseek($sourceFileHandle, $offset) === -1 ||
                    ($chunk = @fread($sourceFileHandle, $length)) === false
                ) {
                    return false;
                }

                $chunkSize = strlen($chunk);

                if (
                    @ftruncate($tempFileHandle, 0) === false ||
                    @rewind($tempFileHandle) === false ||
                    @fwrite($tempFileHandle, $chunk) === false
                ) {
                    return false;
                }

                // Free chunk memory before cURL reads from the stream to avoid double-buffering
                unset($chunk);
                @rewind($tempFileHandle);

                $result = $this->curlCall(
                    $storageFileFullPath,
                    [
                        CURLOPT_FTPAPPEND               => true,
                        CURLOPT_UPLOAD                  => true,
                        CURLOPT_FTP_CREATE_MISSING_DIRS => true,
                        CURLOPT_INFILE                  => $tempFileHandle,
                        CURLOPT_INFILESIZE              => $chunkSize,
                    ]
                );

                if ($result === false) {
                    return false;
                }

                //abort on first chunk if no timeout
                if ($timeout === 0) {
                    return $length;
                }

                $bytesWritten += $chunkSize;
                $offset       += $chunkSize;
            } while (self::getElapsedTime($startTime) < $timeout && !feof($sourceFileHandle));

            return $bytesWritten;
        } finally {
            $this->wait();
        }
    }

    /**
     * Copy storage file to local file, partial copy is supported.
     * If destination file exists, it will be overwritten.
     * If offset is less than the destination file size, the file will be truncated.
     *
     * @param string              $storageFile      The storage file path
     * @param string              $destFile         The destination local file full path
     * @param int<0,max>          $offset           The offset where the data starts.
     * @param int                 $length           The maximum number of bytes read. Default to -1 (read all the remaining buffer).
     * @param int                 $timeout          The timeout for the copy operation in microseconds. Default to 0 (no timeout).
     * @param array<string,mixed> $extraData        Extra data to pass to copy function and updated during copy.
     *                                              This data is intended to be per-file and may be reset between files.
     * @param array<string,mixed> $generalExtraData Extra data to pass to copy function that persists across files
     *                                              during the entire transfer operation.
     *
     * @return false|int The number of bytes that were written to the file, or false on failure.
     */
    public function copyFromStorage(
        string $storageFile,
        string $destFile,
        int $offset = 0,
        int $length = -1,
        int $timeout = 0,
        array &$extraData = [],
        array &$generalExtraData = []
    ) {
        try {
            $startTime = microtime(true);

            if (($fullPath = $this->getFullPath($storageFile)) === false) {
                return false;
            }

            if (wp_mkdir_p(dirname($destFile)) == false) {
                return false;
            }

            if ($offset === 0) {
                if (@file_exists($destFile) && !@unlink($destFile)) {
                    return false;
                }

                if (!$this->isFile($storageFile)) {
                    return false;
                }

                if ($timeout === 0 && $length < 0) {
                    if (($content = $this->getFileContent($storageFile)) === false) {
                        return false;
                    }

                    return @file_put_contents($destFile, $content);
                }
            }

            if (($handle = $this->getDestFileHandle($destFile)) === false) {
                return false;
            }

            if (@fseek($handle, $offset) === -1) {
                return false;
            }

            $filesize     = $this->getPathInfo($storageFile)->size;
            $bytesWritten = 0;
            $length       = $length < 0 ? self::DEFAULT_CHUNK_SIZE : $length;
            $errorMsg     = '';
            do {
                if (@fseek($handle, $offset) === -1) {
                    return false;
                }

                if ($offset + $length - 1 < $filesize && $filesize !== 0) {
                    $range = sprintf('%d-%d', $offset, $offset + $length - 1);
                } else {
                    $range = sprintf('%d-', $offset);
                }

                $content = $this->curlCall($fullPath, [CURLOPT_RANGE => $range], $errorMsg);
                if (
                    $content === false ||
                    (strlen($content) > 0 && @fwrite($handle, $content) === false)
                ) {
                    return false;
                }

                if ($timeout === 0) {
                    return $length;
                }

                $bytesWritten += strlen($content);
                $offset       += strlen($content);
            } while (self::getElapsedTime($startTime) < $timeout && strlen($content) > 0);

            return $bytesWritten;
        } finally {
            $this->wait();
        }
    }

    /**
     * Get all files meta information in a folder
     *
     * @param string $path       remote dir path
     * @param bool   $filterDots filters . and .. from the list
     *
     * @return array{array{name: string, size: int, modified: int, created: int, isDir: bool}}|array{}
     */
    private function getRawListInfo(string $path, $filterDots = true): array
    {
        //direactories need the trailing slash to be recognized as such
        $path = SnapIO::trailingslashit($this->getFullPath($path, true));
        $res  = $this->curlCall($path, [CURLOPT_CUSTOMREQUEST => 'LIST']);

        if ($res === false) {
            return [];
        }

        $items = explode("\n", $res);
        $items = array_map(fn($item): string => rtrim($item), $items);
        $items = array_filter($items, fn($item): bool => !empty($item));

        if (empty($items)) {
            return [];
        }

        if (strpos($items[0], 'total') !== false) {
            array_shift($items);
        }

        $result = [];
        foreach ($items as $key => $item) {
            if (($parsed = FTPUtils::parseRawListString($item, $this->getSystemType())) !== false) {
                if ($filterDots && ($parsed['name'] === '.' || $parsed['name'] === '..')) {
                    continue;
                }

                $result[] = $parsed;
            }
        }

        return $result;
    }

    /**
     * Get System type
     *
     * @return string
     */
    private function getSystemType(): string
    {
        if ($this->cachedSystemType !== null) {
            return $this->cachedSystemType;
        }

        if (
            (($res = $this->curlCall('/', [CURLOPT_CUSTOMREQUEST => 'SYST'])) !== false) &&
            strpos(strtoupper($res), 'WINDOWS') !== false
        ) {
            $this->cachedSystemType = 'WINDOWS_NT';
        } else {
            $this->cachedSystemType = 'UNIX';
        }

        return $this->cachedSystemType;
    }

    /**
     * Get path info and cache it, is path not exists return path info with exists property set to false.
     *
     * @param string $path Relative storage path, if empty, return root path info.
     *
     * @return StoragePathInfo|false The path info or false on error.
     */
    protected function getRealPathInfo(string $path)
    {
        if (($fullPath = $this->getFullPath($path, true)) === false) {
            return false;
        }

        $matches    = [];
        $info       = new StoragePathInfo();
        $info->path = $path;

        if (
            ($response = $this->curlCall($fullPath, [CURLOPT_HEADER => true, CURLOPT_NOBODY => true])) !== false &&
            preg_match('/^Content-Length:\s*(\d+)/im', $response, $matches) === 1
        ) {
            // Is file
            $info->exists = true;
            $info->isDir  = false;
            $info->size   = (int) $matches[1];

            $response       = $this->curlCall($fullPath, [CURLOPT_CUSTOMREQUEST => 'MDTM']);
            $matches        = [];
            $info->modified = preg_match('/^(\d{14})/', $response, $matches) === 1 ? strtotime($matches[1]) : time();

            $info->created = $info->modified;
        } elseif ($this->curlCall(trailingslashit($fullPath), [CURLOPT_CUSTOMREQUEST => 'NLST']) !== false) {
            // Is folder
            $info           = new StoragePathInfo();
            $info->path     = $path;
            $info->exists   = true;
            $info->isDir    = true;
            $info->size     = 0;
            $info->created  = time();
            $info->modified = time();
        } else {
            // Not exists
            $info->exists = false;
            $info->isDir  = false;
        }

        return $info;
    }

    /**
     * Get the list of files and directories inside the specified path.
     *
     * @param string $path    Relative storage path, if empty, scan root path.
     * @param bool   $files   If true, add files to the list. Default to true.
     * @param bool   $folders If true, add folders to the list. Default to true.
     *
     * @return string[] The list of files and directories, empty array if path is invalid.
     */
    public function scanDir(string $path, bool $files = true, bool $folders = true): array
    {
        $infoList = $this->getRawListInfo($path);
        $result   = [];
        foreach ($infoList as $item) {
            if ($item['isDir'] && !$folders) {
                continue;
            }

            if (!$item['isDir'] && !$files) {
                continue;
            }

            $result[] = $item['name'];
        }

        return $result;
    }

    /**
     * Check if directory is empty.
     *
     * @param string   $path    The folder path
     * @param string[] $filters Filters to exclude files and folders from the check, if start and end with /, use regex.
     *
     * @return bool True is ok, false otherwise
     */
    public function isDirEmpty(string $path, array $filters = []): bool
    {
        if (!$this->isDir($path)) {
            return false;
        }

        $regexFilters  = [];
        $normalFilters = [];
        foreach ($filters as $filter) {
            if (preg_match('/^\/.*\/$/', $filter) === 1) {
                $regexFilters[] = $filter;
            } else {
                $normalFilters[] = $filter;
            }
        }

        $contents = $this->scanDir($path);
        foreach ($contents as $item) {
            if (in_array($item, $normalFilters)) {
                continue;
            }

            foreach ($regexFilters as $regexFilter) {
                if (preg_match($regexFilter, $item) === 1) {
                    continue 2;
                }
            }

            return false;
        }

        return true;
    }

    /**
     * Destroy the storage on deletion.
     *
     * @return bool true on success or false on failure.
     */
    public function destroy(): bool
    {
        // Don't delete if root directory
        if (
            preg_match('/^[a-zA-Z]:\/$/', $this->root) === 1 ||
            preg_match('/^\/$/', $this->root) === 1
        ) {
            return true;
        }

        return $this->delete('/', true);
    }

    /**
     * Destruct
     *
     * @return void
     */
    public function __destruct()
    {
        if (is_resource($this->sourceFileHandle)) {
            fclose($this->sourceFileHandle);
        }

        if (is_resource($this->tempFileHandle)) {
            fclose($this->tempFileHandle);
        }

        if (is_resource($this->destFileHandle)) {
            fclose($this->destFileHandle);
        }

        if ($this->connection !== null) {
            curl_close($this->connection);
        }
    }

    /**
     * Do a curl call
     *
     * @param string           $path     where the curl call occur
     * @param array<int,mixed> $options  configuration options
     * @param string           $errorMsg error message
     *
     * @return string|false response or false on failure
     */
    private function curlCall($path = '/', array $options = [], string &$errorMsg = '')
    {
        if ($this->connection === null) {
            $this->connection = curl_init();
        } else {
            curl_reset($this->connection);
        }

        if ($this->connection === false) {
            return false;
        }

        $path                 = ltrim($path, '/\\');
        $options[CURLOPT_URL] = sprintf('ftp://%s:%d/%s', $this->server, $this->port, $path);
        $options              = array_replace($this->getDefaultOptions(), $options);

        curl_setopt_array($this->connection, $options);

        if (($response = curl_exec($this->connection)) === false) {
            if (($errno = curl_errno($this->connection))) {
                switch ($errno) {
                    case 6:
                    case 7:
                        $errorMsg = 'Unable to connect to FTP server. Please check your FTP hostname, port, and active mode settings. Error code: ' . $errno;
                        break;
                    case 8:
                        $errorMsg = 'Got an unexpected reply from FTP server. Error code: ' . $errno;
                        break;
                    case 9:
                        $errorMsg = 'Unable to change FTP directory. Please ensure that you have permission on the server. Error code: ' . $errno;
                        break;
                    case 23:
                        $errorMsg = 'Unable to download file from FTP server. Please ensure that you have enough disk space. Error code: ' . $errno;
                        break;
                    case 28:
                        $errorMsg = 'Connecting to FTP server timed out. Please check FTP hostname, port, username, password, and active mode ' .
                            'settings. Error code: ' . $errno;
                        break;
                    case 67:
                        $errorMsg = 'Unable to login to FTP server. Please check your username and password. Error code: ' . $errno;
                        break;
                    default:
                        $errorMsg = 'Unable to connect to FTP. Error code: ' . $errno . '. Error message: ' . curl_error($this->connection);
                        break;
                }
            }

            return false;
        }

        $http_code = curl_getinfo($this->connection, CURLINFO_HTTP_CODE);
        if ($http_code >= 400) {
            $errorMsg = sprintf('Error code: %s.', $http_code);
            return false;
        }

        return $response;
    }

    /**
     * Returns default options for cURL
     *
     * @return array<int,mixed>
     */
    private function getDefaultOptions(): array
    {
        $options = [
            CURLOPT_USERPWD        => sprintf('%s:%s', $this->username, $this->password),
            CURLOPT_HEADER         => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->timeoutInSec,
            CURLOPT_TIMEOUT        => 0,
        ];


        if ($this->ssl) {
            if (defined('CURLOPT_USE_SSL')) {
                $options[CURLOPT_USE_SSL] = CURLUSESSL_TRY;
            } else {
                $options[CURLOPT_FTP_SSL] = CURLFTPSSL_TRY;
            }
            $options[CURLOPT_FTPSSLAUTH] = CURLFTPAUTH_TLS;
        }

        if ($this->sslVerify) {
            $options[CURLOPT_SSL_VERIFYPEER] = true;
            $options[CURLOPT_SSL_VERIFYHOST] = 2;
            $options[CURLOPT_CAINFO]         = strlen($this->sslCertificate) > 0 ? $this->sslCertificate : false;
        } else {
            $options[CURLOPT_SSL_VERIFYPEER] = false;
            $options[CURLOPT_SSL_VERIFYHOST] = 0;
        }

        if ($this->passiveMode) {
            $options[CURLOPT_FTP_USE_EPSV] = true;
        } else {
            $options[CURLOPT_FTP_USE_EPRT] = true;
            $options[CURLOPT_FTPPORT]      = 0;
        }

        return $options;
    }

    /**
     * Returns the source file handle
     *
     * @param string $destFilePath The source file path
     *
     * @return resource|false returns the file handle or false on failure
     */
    private function getDestFileHandle(string $destFilePath)
    {
        if ($this->lastDestFilePath === $destFilePath) {
            return $this->destFileHandle;
        }

        if (is_resource($this->destFileHandle)) {
            fclose($this->destFileHandle);
        }

        if (($this->destFileHandle = SnapIO::fopen($destFilePath, 'cb')) === false) {
            return false;
        }

        $this->lastDestFilePath = $destFilePath;
        return $this->destFileHandle;
    }

    /**
     * Returns the source file handle
     *
     * @param string $sourceFilePath The source file path
     *
     * @return resource|false returns the file handle or false on failure
     */
    private function getSourceFileHandle(string $sourceFilePath)
    {
        if ($this->lastSourceFilePath === $sourceFilePath) {
            return $this->sourceFileHandle;
        }

        if (is_resource($this->sourceFileHandle)) {
            fclose($this->sourceFileHandle);
        }

        if (($this->sourceFileHandle = SnapIO::fopen($sourceFilePath, 'r')) === false) {
            return false;
        }

        $this->lastSourceFilePath = $sourceFilePath;
        return $this->sourceFileHandle;
    }

    /**
     * Returns an empty file stream to temporarlly store chunk data.
     *
     * @return resource|false
     */
    private function getTempFileHandle()
    {
        if (is_resource($this->tempFileHandle)) {
            if (ftruncate($this->tempFileHandle, 0) === false) {
                return false;
            }
            if (rewind($this->tempFileHandle) === false) {
                return false;
            }
            return $this->tempFileHandle;
        }

        if (($this->tempFileHandle = @fopen('php://temp', 'wb+')) === false) {
            return false;
        }

        return $this->tempFileHandle;
    }

    /**
     * Return the full path of storage from relative path.
     *
     * @param string $path        The relative storage path
     * @param bool   $acceptEmpty If true, return root path if path is empty. Default to false.
     *
     * @return string|false The full path or false if path is invalid.
     */
    protected function getFullPath($path, $acceptEmpty = false)
    {
        $path = ltrim((string) $path, '/\\');
        if (strlen($path) === 0) {
            return $acceptEmpty ? SnapIO::trailingslashit($this->root) : false;
        }
        return $this->root . $path;
    }

    /**
     * Elapsed time in microseconds
     *
     * @param float $startTime start time in microseconds
     *
     * @return float
     */
    private static function getElapsedTime($startTime)
    {
        return (microtime(true) - $startTime) * SECONDS_IN_MICROSECONDS;
    }
}
