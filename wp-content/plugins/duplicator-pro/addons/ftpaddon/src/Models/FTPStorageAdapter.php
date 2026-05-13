<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Addons\FtpAddon\Models;

use Duplicator\Addons\FtpAddon\Utils\FTPUtils;
use Duplicator\Models\Storages\StoragePathInfo;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Models\Storages\AbstractStorageAdapter;
use FTP\Connection;
use Exception;

/**
 * Description of cls-ftp-chunker
 */
class FTPStorageAdapter extends AbstractStorageAdapter
{
    /** @var int */
    const DEFAULT_CHUNK_SIZE = 2 * 1024 * 1024; // 2MB
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
    private bool $passiveMode;
    /** @var resource */
    private $destFileHandle;
    /** @var string */
    private $lastDestFilePath = '';
    /** @var resource */
    private $sourceFileHandle;
    /** @var string */
    private $lastSourceFilePath = '';
    /** @var resource */
    private $tempFileHandle;
    /** @var false|resource|Connection */
    private $connection = false; // @phpstan-ignore property.unusedType
    private int $throttle;

    /**
     * Class constructor
     *
     * @param string $server       The server to connect to
     * @param int    $port         The port to connect to
     * @param string $username     The username to use
     * @param string $password     The password to use
     * @param string $root         The root directory to use
     * @param int    $timeoutInSec The timeout in seconds
     * @param bool   $ssl          Whether to use SSL
     * @param bool   $passiveMode  Whether to use passive mode
     * @param int    $throttle     The throttle in microseconds
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
        $throttle = 0
    ) {
        $this->server       = $server;
        $this->port         = (int) $port;
        $this->username     = $username;
        $this->password     = $password;
        $this->root         = SnapIO::trailingslashit($root);
        $this->timeoutInSec = max(1, (int) $timeoutInSec);
        $this->ssl          = (bool) $ssl;
        $this->passiveMode  = (bool) $passiveMode;
        $this->throttle     = max(0, (int) $throttle);
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
        if (!$this->createDir('/')) {
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

        if ($this->getConnection() === false) {
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
     * Create the directory specified by pathname, recursively if necessary.
     *
     * @param string $path The directory path.
     *
     * @return bool true on success or false on failure.
     */
    protected function realCreateDir(string $path): bool
    {
        $isRoot = ($this->getFullPath($path) === false);
        $path   = $isRoot ? $this->root : $this->getFullPath($path);

        return $this->createDirRecursively($path);
    }

    /**
     * Create the directory specified by pathname, recursively if necessary.
     *
     * @param string $path The full path to the directory.
     *
     * @return bool true on success or false on failure.
     */
    private function createDirRecursively($path): bool
    {
        try {
            if (($connection = $this->getConnection()) === false) {
                return false;
            }

            if (@ftp_chdir($connection, $path) === true) {
                if ($path !== $this->root) {
                    @ftp_chdir($connection, $this->root);
                }
                return true;
            }

            $parent = dirname($path);
            if (!$this->createDirRecursively($parent)) {
                return false;
            }

            if (@ftp_mkdir($connection, $path) === false) {
                return false;
            }
        } finally {
            $this->wait();
        }

        return true;
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
        if (($infoList = $this->getDirContentsInfo($path)) === false) {
            return $this->scanDirNlist($path, $files, $folders);
        }

        $result = [];
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
     * Get the list of files and directories inside the specified path.
     * Uses ftp_nlist() to get the list of files and directories.
     *
     * @param string $path    Relative storage path, if empty, scan root path.
     * @param bool   $files   If true, add files to the list. Default to true.
     * @param bool   $folders If true, add folders to the list. Default to true.
     *
     * @return string[] The list of files and directories, empty array if path is invalid.
     */
    private function scanDirNlist(string $path, bool $files = true, bool $folders = true): array
    {
        if (($connection = $this->getConnection()) === false) {
            return [];
        }

        if (($fullPath = $this->getFullPath($path, true)) == false) {
            return [];
        }

        if (($list = @ftp_nlist($connection, "$fullPath")) === false) {
            return [];
        }

        $path   = SnapIO::trailingslashit($path);
        $result = [];
        foreach ($list as $item) {
            $item = basename($item);
            if ($item == '.' || $item == '..') {
                continue;
            }

            $itemPath = $path . $item;
            if (!$folders && $this->isDir($itemPath)) {
                continue;
            }

            if (!$files && $this->isFile($itemPath)) {
                continue;
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * Get the list of files and directories inside the specified path.
     * Uses ftp_rawlist() to get the list of files and directories.
     *
     * @param string $path              Relative storage path, if empty, scan root path.
     * @param string $defaultSystemType The default system type to use if ftp_systype() fails.
     *
     * @return array{array{name: string, size: int, modified: int, created: int, isDir: bool}}|false
     */
    private function getDirContentsInfo(string $path, string $defaultSystemType = '')
    {
        if (($connection = $this->getConnection()) === false) {
            return false;
        }

        if (($systemType = @ftp_systype($connection)) === false || strlen($systemType) === 0) {
            if (strlen($defaultSystemType) > 0) {
                $systemType = strtoupper($defaultSystemType);
            } else {
                return false;
            }
        } else {
            $systemType = strtoupper($systemType);
            if ($systemType !== FTPUtils::SYS_TYPE_UNIX && $systemType !== FTPUtils::SYS_TYPE_WINDOWS_NT) {
                return false;
            }
        }

        $path = $this->getFullPath($path, true);
        if (($list = @ftp_rawlist($connection, "$path")) === false) {
            return false;
        }

        $result = [];
        foreach ($list as $item) {
            if (($item = FTPUtils::parseRawListString($item, $systemType)) === false) {
                continue;
            }

            if ($item['name'] == '.' || $item['name'] == '..') {
                continue;
            }

            $result[] = $item;
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
     * Get path info and cache it, is path not exists return path info with exists property set to false.
     * Gets the path info but has to do seperate calls to get the size and modified time.
     * Also the modified time doesn't work for directories.
     *
     * @param string $path Relative storage path, if empty, return root path info.
     *
     * @return StoragePathInfo|false The path info or false on error.
     */
    protected function getRealPathInfo(string $path)
    {
        if (($connection = $this->getConnection()) === false) {
            return false;
        }

        $fullPath = $this->getFullPath($path, true);

        $info       = new StoragePathInfo();
        $info->path = $path;

        // First check for directory existence becuase
        // Filesize can return non-zero value for directories
        if (@ftp_chdir($connection, $fullPath) === true) {
            if ($fullPath !== $this->getFullPath('', true)) {
                @ftp_chdir($connection, $this->root);
            }

            $info->exists = true;
            $info->isDir  = true;
            $info->size   = 0;
        } elseif (
            ($size = @ftp_size($connection, $fullPath)) >= 0 ||
            ($size = $this->getSizeFromRawList($path)) >= 0
        ) {
            $info->exists = true;
            $info->isDir  = false;
            $info->size   = $size;
        } else {
            $info->exists = false;
            $info->isDir  = false;
            $info->size   = 0;
        }

        if ($info->exists) {
            if ($info->isDir) {
                $info->modified = time();
            } else {
                if (($info->modified = (int) @ftp_mdtm($connection, $fullPath)) === -1) {
                    $info->modified = 0;
                }
            }
            $info->created = $info->modified;
        }

        return $info;
    }

    /**
     * Get the size of the file.
     *
     * @param string $path The path to file.
     *
     * @return int<-1,max> The size of file or -1 on failure.
     */
    protected function getSizeFromRawList($path)
    {
        $parent = dirname($path) !== '.' ? dirname($path) : '';
        if (($contents = $this->getDirContentsInfo($parent, FTPUtils::SYS_TYPE_UNIX)) === false) {
            return -1;
        }

        foreach ($contents as $item) {
            if ($item['name'] === basename($path) && $item['isDir'] === false) {
                return $item['size'];
            }
        }

        return -1;
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
            if (($connection = $this->getConnection()) === false) {
                return false;
            }

            if (($storageFileFullPath = $this->getFullPath($path)) == false) {
                return false;
            }

            $parent = dirname($path);
            if (!$this->createDir($parent)) {
                return false;
            }

            $tmpFile = tempnam(sys_get_temp_dir(), 'duplicator-');
            if (($bytesWritten = file_put_contents($tmpFile, $content)) === false) {
                return false;
            }

            //ftp_put overwrites the file if it exists, no need to delete it first
            if (@ftp_put($connection, $storageFileFullPath, $tmpFile, FTP_BINARY) === false) {
                SnapIO::unlink($tmpFile);
                return false;
            }

            SnapIO::unlink($tmpFile);
            return $bytesWritten;
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

            if (($connection = $this->getConnection()) === false) {
                return false;
            }

            if (!is_file($sourceFile)) {
                return false;
            }

            if (($storageFileFullPath = $this->getFullPath($storageFile)) == false) {
                return false;
            }

            $parent = dirname($storageFile);
            if ($offset === 0 && !$this->createDir($parent)) {
                return false;
            }

            // Uplaod file at once without any other operation
            if ($timeout === 0 && (($offset === 0 && $length < 0) || filesize($sourceFile) < $length)) {
                if (@ftp_put($connection, $storageFileFullPath, $sourceFile, FTP_BINARY) === false) {
                    return false;
                }

                return filesize($sourceFile);
            }

            $sourceFileHandle = $this->getSourceFileHandle($sourceFile);
            $tempFileHandle   = $this->getTempFileHandle();

            $length       = $length < 0 ? self::DEFAULT_CHUNK_SIZE : $length;
            $bytesWritten = 0;
            do {
                if (@fseek($sourceFileHandle, $offset) === -1 || ($chunk = @fread($sourceFileHandle, $length)) === false) {
                    return false;
                }

                if (
                    @ftruncate($tempFileHandle, 0) === false ||
                    @rewind($tempFileHandle) === false ||
                    @fwrite($tempFileHandle, $chunk) === false
                ) {
                    return false;
                }

                @rewind($tempFileHandle);

                // No need to delete remote file, ftp_fput overwrites the file if the offset is 0
                if (@ftp_fput($connection, $storageFileFullPath, $tempFileHandle, FTP_BINARY, $offset) === false) {
                    return false;
                }

                //abort on first chunk if no timeout set
                if ($timeout === 0) {
                    return $length;
                }

                $bytesWritten += strlen($chunk);
                $offset       += strlen($chunk);
            } while ((self::getElapsedTime($startTime) < $timeout) && !feof($sourceFileHandle));

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

            if (($connection = $this->getConnection()) === false) {
                return false;
            }

            if (($fullPath = $this->getFullPath($storageFile)) === false) {
                return false;
            }

            if (wp_mkdir_p(dirname($destFile)) == false) {
                return false;
            }

            if ($offset === 0 && @file_exists($destFile) && !@unlink($destFile)) {
                return false;
            }

            if ($offset > 0 && !@file_exists($destFile)) {
                return false;
            }

            if (!$this->isFile($storageFile)) {
                return false;
            }

            if ($timeout === 0 && $offset === 0 && $length < 0) {
                if (($content = $this->getFileContent($storageFile)) === false) {
                    return false;
                }

                return @file_put_contents($destFile, $content);
            }

            if (
                ($destHandle = $this->getDestFileHandle($destFile)) === false ||
                @fseek($destHandle, $offset) === -1
            ) {
                return false;
            }

            //This is necessary to be able to call this function multiple times in one session
            //Otherwise ftp_nb_fget will fail if the file is already opened for upload
            if (isset($extraData['multiPartInProgress']) && $extraData['multiPartInProgress'] === true) {
                if (($connection = $this->resetConnection()) === false) {
                    return false;
                }
            } else {
                $extraData['multiPartInProgress'] = true;
            }

            $sizeBefore = filesize($destFile);
            $result     = @ftp_nb_fget($connection, $destHandle, $fullPath, FTP_BINARY, $offset);
            while (
                $result === FTP_MOREDATA &&
                (
                    ($timeout !== 0 && self::getElapsedTime($startTime) < $timeout) ||
                    ($timeout === 0 && @ftell($destHandle) - $sizeBefore <= $length)
                )
            ) {
                $result = @ftp_nb_continue($connection);
            }

            if ($result === FTP_FAILED) {
                return false;
            }

            if ($timeout !== 0) {
                return @ftell($destHandle) - $sizeBefore;
            } else {
                return $length;
            }
        } finally {
            $this->wait();
        }
    }

    /**
     * Resets the connection
     *
     * @return false|resource|Connection True on success, false on failure
     */
    private function resetConnection()
    {
        if ($this->connection !== false) {
            @ftp_close($this->connection);
        }

        $this->connection = false;

        if (($connection = $this->getConnection()) === false) {
            return false;
        }

        return $connection;
    }

    /**
     * Delete reletative path from storage root.
     *
     * @param string $path      The path to delete. (Accepts directories and files)
     * @param bool   $recursive Allows the deletion of nested directories specified in the pathname. Default to false.
     *
     * @return bool true on success or false on failure.
     */
    protected function realDelete(string $path, bool $recursive = false): bool
    {
        try {
            if (($connection = $this->getConnection()) === false) {
                return false;
            }

            if (($fullPath = $this->getFullPath($path, true)) == false) {
                return false;
            }

            if ($this->isDir($path)) {
                if ($recursive) {
                    foreach ($this->scanDir($path) as $item) {
                        if (!$this->delete(SnapIO::trailingslashit($path) . $item, true)) {
                            return false;
                        }
                    }
                }
                return @ftp_rmdir($connection, $fullPath);
            } elseif ($this->isFile($path)) {
                return @ftp_delete($connection, $fullPath);
            } else {
                //path doesn't exist
                return true;
            }
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
        if (($connection = $this->getConnection()) === false) {
            return false;
        }

        if (($fullPath = $this->getFullPath($path)) == false) {
            return false;
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'duplicator-pro');
        if (@ftp_get($connection, $tmpFile, $fullPath, FTP_BINARY) === false) {
            SnapIO::unlink($tmpFile);
            return false;
        }

        if (($content = file_get_contents($tmpFile)) === false) {
            SnapIO::unlink($tmpFile);
            return false;
        }

        SnapIO::unlink($tmpFile);
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
            if (($connection = $this->getConnection()) === false) {
                return false;
            }

            $newPathParent = dirname($newPath);
            if (!$this->createDir($newPathParent)) {
                return false;
            }

            if (($oldPath = $this->getFullPath($oldPath)) == false) {
                return false;
            }

            if (($newPath = $this->getFullPath($newPath)) == false) {
                return false;
            }

            return @ftp_rename($connection, $oldPath, $newPath);
        } finally {
            $this->wait();
        }
    }

    /**
     * Destroy the storage on deletion.
     *
     * @return bool true on success or false on failure.
     */
    public function destroy(): bool
    {
        //Don't accidentally delete root directory
        if (
            preg_match('/^[a-zA-Z]:\/$/', $this->root) === 1 ||
            preg_match('/^\/$/', $this->root) === 1
        ) {
            return true;
        }

        return $this->delete('/', true);
    }

    /**
     * Returns an empty file stream to temporarlly store chunk data.
     *
     * @return resource
     */
    private function getTempFileHandle()
    {
        if (is_resource($this->tempFileHandle)) {
            if (ftruncate($this->tempFileHandle, 0) === false) {
                throw new Exception('Can\'t truncate temp file');
            }
            return $this->tempFileHandle;
        }

        if (($this->tempFileHandle = @fopen('php://temp', 'wb+')) === false) {
            throw new Exception('Can\'t open temp handle');
        }

        return $this->tempFileHandle;
    }

    /**
     * Returns the dest file handle
     *
     * @param string $destFilePath The dest file path
     *
     * @return resource|false The dest file handle or false on failure
     */
    private function getDestFileHandle(string $destFilePath)
    {
        if ($this->lastDestFilePath === $destFilePath && is_resource($this->destFileHandle)) {
            return $this->destFileHandle;
        }

        if (@is_resource($this->destFileHandle)) {
            @fclose($this->destFileHandle);
        }

        if (($this->destFileHandle = @fopen($destFilePath, 'cb')) === false) {
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
     * @return resource
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
            throw new Exception('Can\'t open ' . $sourceFilePath . ' file');
        }

        $this->lastSourceFilePath = $sourceFilePath;
        return $this->sourceFileHandle;
    }

    /**
     * Opens the FTP connection
     *
     * @param string $errorMsg The error message to return
     *
     * @return bool True on success, false on failure
     */
    private function connect(&$errorMsg = ''): bool
    {
        if ($this->connection !== false) {
            return true;
        }

        if (!$this->isConnectionInfoValid($errorMsg)) {
            return false;
        }

        try {
            if (!function_exists('ftp_connect')) {
                throw new Exception('FTP functions are not available.');
            }

            if ($this->ssl && !function_exists('ftp_ssl_connect')) {
                throw new Exception('Attempted to open FTP SSL connection when OpenSSL hasn\'t been statically built into this PHP install.');
            }

            if ($this->ssl) {
                $this->connection = @ftp_ssl_connect($this->server, $this->port, $this->timeoutInSec);
            } else {
                $this->connection = @ftp_connect($this->server, $this->port, $this->timeoutInSec);
            }

            if ($this->connection === false) {
                throw new Exception('Error connecting to FTP server. ' . $this->server . ':' . $this->port);
            }

            if (ftp_login($this->connection, $this->username, $this->password) === false) {
                throw new Exception('Error logging in user ' . $this->username . ', double check your username and password');
            }

            if ($this->passiveMode && !@ftp_pasv($this->connection, true)) {
                throw new Exception("Couldn't set the connection into passive mode.");
            }

            if (ftp_set_option($this->getConnection(), FTP_AUTOSEEK, false) === false) {
                throw new Exception("Couldn't disable auto seek.");
            }
        } catch (Exception $e) {
            if ($this->connection !== false) {
                ftp_close($this->connection);
                $this->connection = false;
            }
            $errorMsg = $e->getMessage();
            return false;
        }

        return true;
    }

    /**
     * Returns the FTP connection resource
     *
     * @return false|resource|Connection The FTP connection resource or false if not connected
     */
    public function getConnection()
    {
        return ($this->connect() === true ? $this->connection : false);
    }

    /**
     * Closes the FTP connection
     */
    public function __destruct()
    {
        if ($this->connection !== false) {
            @ftp_close($this->connection);
        }

        if (is_resource($this->sourceFileHandle)) {
            @fclose($this->sourceFileHandle);
        }

        if (is_resource($this->tempFileHandle)) {
            @fclose($this->tempFileHandle);
        }

        if (is_resource($this->destFileHandle)) {
            @fclose($this->destFileHandle);
        }
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
            return $acceptEmpty ? SnapIO::untrailingslashit($this->root) : false;
        }
        return $this->root . $path;
    }

    /**
     * Get the elapsed time in microseconds
     *
     * @param float $startTime The start time
     *
     * @return float The elapsed time in microseconds
     */
    private static function getElapsedTime($startTime)
    {
        return (microtime(true) - $startTime) * SECONDS_IN_MICROSECONDS;
    }
}
