<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snapcreek LLC
 */

namespace Duplicator\Addons\FtpAddon\Models;

use Exception;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Models\Storages\AbstractStorageAdapter;
use Duplicator\Models\Storages\StoragePathInfo;
use VendorDuplicator\phpseclib3\Crypt\Common\PrivateKey;
use VendorDuplicator\phpseclib3\Crypt\PublicKeyLoader;
use VendorDuplicator\phpseclib3\Net\SFTP;

/**
 * SFTP class adapter
 */
class SFTPStorageAdapter extends AbstractStorageAdapter
{
    /** @var int */
    const DEFAULT_CHUNK_SIZE = 2 * 1024 * 1024;
    /** @var string */
    protected $server = '';
    protected int $port;
    /** @var string */
    protected $username = '';
    /** @var string */
    protected $password = '';
    protected string $root;
    /** @var string */
    protected $privateKey = '';
    /** @var string */
    protected $privateKeyPassword = '';
    protected int $timeout;
    /** @var resource */
    private $sourceFileHandle;
    /** @var string */
    private $lastSourceFilePath = '';
    /** @var resource */
    private $destFileHandle;
    /** @var string */
    private $lastDestFilePath = '';
    /** @var ?SFTP */
    private $connection;

    /**
     * Class contructor
     *
     * @param string $server             hosting domain or ip address
     * @param int    $port               hosting port
     * @param string $username           hosting username
     * @param string $password           hosting password
     * @param string $root               hosting root path
     * @param string $privateKey         hosting private key
     * @param string $privateKeyPassword hosting private key password
     * @param int    $timeout            hosting timeout
     */
    public function __construct(
        $server,
        $port = 22,
        $username = '',
        $password = '',
        $root = '',
        $privateKey = '',
        $privateKeyPassword = '',
        $timeout = 15
    ) {
        $this->server             = $server;
        $this->port               = (int) $port;
        $this->username           = $username;
        $this->password           = $password;
        $this->root               = SnapIO::trailingslashit($root);
        $this->privateKey         = $privateKey;
        $this->privateKeyPassword = $privateKeyPassword;
        $this->timeout            = (int) $timeout;
    }

    /**
     * Initialize the storage on creation.
     *
     * @param string $errorMsg The error message if storage is invalid.
     *
     * @return bool true on success or false on failure.
     */
    public function initialize(&$errorMsg = ''): bool
    {
        if (!$this->isDir('/') && !$this->createDir('/')) {
            $errorMsg = 'Could not create root directory';
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
        return $this->delete('/', true);
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
            $errorMsg = __('SFTP connection info is invalid.', 'duplicator-pro');
            return false;
        }

        if ($this->getConnection($errorMsg) === null) {
            $errorMsg = __('SFTP connection failed.', 'duplicator-pro');
            return false;
        }

        if (!$this->isDir('/')) {
            $errorMsg = __('SFTP root directory doesn\'t exist.', 'duplicator-pro');
            return false;
        }

        return true;
    }

    /**
     * Check if connection info is valid
     *
     * @param string $errorMsg error message
     *
     * @return bool true if connection info is valid, false otherwise
     */
    private function isConnectionInfoValid(&$errorMsg = ''): bool
    {
        try {
            if (strlen($this->server) == 0) {
                throw new Exception('Server name is required to make sftp connection');
            }

            if ($this->port < 1) {
                throw new Exception('Server port is required to make sftp connection');
            }

            if (strlen($this->username) == 0) {
                throw new Exception('Username is required to make sftp connection');
            }

            if (strlen($this->password) == 0 && strlen($this->privateKey) == 0) {
                throw new Exception('You should provide either sftp user pasword or the private key to make sftp connection');
            }

            if (strlen($this->privateKey) > 0 && strlen($this->privateKeyPassword) == 0) {
                throw new Exception('You should provide private key password');
            }
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
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
        if (($conn = $this->getConnection()) === null) {
            return false;
        }

        if ($this->isDir($path)) {
            return true;
        }

        $path = $this->getFullPath($path, true);
        try {
            return $conn->mkdir($path, -1, true) !== false;
        } catch (Exception $e) {
            return false;
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
        if (($conn = $this->getConnection()) === null) {
            return false;
        }

        if (($fullPath = $this->getFullPath($path)) === false) {
            return false;
        }

        try {
            $parentDir = dirname($path);
            if ($this->createDir($parentDir) === false) {
                return false;
            }

            if ($conn->put($fullPath, $content) === false) {
                return false;
            }

            return strlen($content);
        } catch (Exception $e) {
            return false;
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
        if (($conn = $this->getConnection()) === null) {
            return false;
        }

        if (($path = $this->getFullPath($path)) === false) {
            return false;
        }

        try {
            return $conn->get($path);
        } catch (Exception $e) {
            return false;
        }
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
        if (($conn = $this->getConnection()) === null) {
            return false;
        }

        if (($oldPath = $this->getFullPath($oldPath)) === false) {
            return false;
        }

        if (($newPath = $this->getFullPath($newPath)) === false) {
            return false;
        }

        try {
            return $conn->rename($oldPath, $newPath);
        } catch (Exception $e) {
            return false;
        }
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
        if (($conn = $this->getConnection()) === null) {
            return false;
        }

        $fullPath = $this->getFullPath($path, true);
        try {
            $info = $conn->stat($fullPath);
            if ($info === false) {
                throw new Exception('Could not get path info');
            }

            $pathInfo           = new StoragePathInfo();
            $pathInfo->exists   = true;
            $pathInfo->path     = $path;
            $pathInfo->isDir    = $info['type'] === 2;
            $pathInfo->size     = $pathInfo->isDir ? 0 : $info['size'];
            $pathInfo->modified = $info['mtime'];
            $pathInfo->created  = $info['ctime'] ?? $info['mtime'];

            return $pathInfo;
        } catch (Exception $e) {
            $pathInfo       = new StoragePathInfo();
            $pathInfo->path = $path;

            return $pathInfo;
        }
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
        if (($conn = $this->getConnection()) === null) {
            return [];
        }

        $path = $this->getFullPath($path, true);
        try {
            $list = $conn->nlist($path);
            if ($list === false) {
                return [];
            }

            $result = [];
            foreach ($list as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $itemPath = SnapIO::trailingslashit($path) . $item;
                if ($conn->is_dir($itemPath)) {
                    if ($folders) {
                        $result[] = $item;
                    }
                } else {
                    if ($files) {
                        $result[] = $item;
                    }
                }
            }

            return $result;
        } catch (Exception $e) {
            return [];
        }
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
        if ($this->isDir($path) === false) {
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
        $startTime = microtime(true);

        if (($conn = $this->getConnection()) === null) {
            return false;
        }

        if (($storageFileFullPath = $this->getFullPath($storageFile)) === false) {
            return false;
        }

        $parentDir = dirname($storageFile);
        if ($offset === 0 && !$this->createDir($parentDir)) {
            return false;
        }

        //Uplaod file at once without any other operation
        if (($timeout === 0 && $offset === 0 && $length < 0) || filesize($sourceFile) < $length) {
            if (($content = file_get_contents($sourceFile)) === false) {
                return false;
            }

            return $this->createFile($storageFile, $content);
        }

        if (($sourceFileHandle = $this->getSourceFileHandle($sourceFile)) === false) {
            return false;
        }

        if (@fseek($sourceFileHandle, $offset) === -1) {
            return false;
        }

        $bytesWritten = 0;
        $length       = $length < 0 ? self::DEFAULT_CHUNK_SIZE : $length;
        try {
            $result = $conn->put(
                $storageFileFullPath,
                function ($size) use ($timeout, $startTime, &$bytesWritten, $sourceFileHandle, $length): ?string {
                    if ($timeout !== 0 && (microtime(true) - $startTime) * SECONDS_IN_MICROSECONDS > $timeout) {
                        return null;
                    }

                    if ($timeout === 0 && $bytesWritten >= $length) {
                        return null;
                    }

                    if (feof($sourceFileHandle)) {
                        return null;
                    }

                    if (($data = @fread($sourceFileHandle, $size)) === false) {
                        return null;
                    }

                    return $data;
                },
                SFTP::SOURCE_CALLBACK | SFTP::RESUME,
                $offset,
                -1,
                function ($sent) use (&$bytesWritten): void {
                    $bytesWritten = $sent;
                }
            );

            if ($result === false) {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }

        return $timeout === 0 ? $length : $bytesWritten;
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
        $startTime = microtime(true);

        if (($conn = $this->getConnection()) === null) {
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

        if (($handle = $this->getDestFileHandle($destFile)) === false) {
            return false;
        }

        $bytesWritten = 0;
        $length       = $length < 0 ? self::DEFAULT_CHUNK_SIZE : $length;
        try {
            do {
                $content = $conn->get($fullPath, false, $offset, $length);

                if (
                    $content === false ||
                    @fseek($handle, $offset) === -1 ||
                    @fwrite($handle, $content) === false
                ) {
                    return false;
                }

                if ($timeout === 0) {
                    return $length;
                }

                $bytesWritten += strlen($content);
                $offset       += strlen($content);
            } while (self::getElapsedTime($startTime) < $timeout);
        } catch (Exception $e) {
            return false;
        }

        return $bytesWritten;
    }

    /**
     * Returns an SFTP object
     *
     * @param string $errorMsg error message
     *
     * @return ?SFTP
     */
    private function getConnection(string &$errorMsg = ''): ?SFTP
    {

        if ($this->connection instanceof SFTP) {
            return $this->connection;
        }

        try {
            if (!$this->isConnectionInfoValid($errorMsg)) {
                throw new Exception($errorMsg);
            }

            $this->connection = new SFTP($this->server, $this->port, $this->timeout);

            if (strlen($this->privateKey) > 0) {
                if (($key = $this->getPrivateKey()) === null) {
                    throw new Exception('Invalid private key');
                }

                if (!$this->connection->login($this->username, $key)) {
                    throw new Exception('Invalid username or private key');
                }
            } else {
                if (!$this->connection->login($this->username, $this->password)) {
                    throw new Exception('Invalid username or password');
                }
            }
        } catch (Exception $e) {
            if ($this->connection !== null && $this->connection->isConnected()) {
                $this->connection->disconnect();
            }
            $this->connection = null;
            $errorMsg         = $e->getMessage();
            return null;
        }

        return $this->connection;
    }

    /**
     * Set an SFTP Private Key
     *
     * @return ?PrivateKey return key object or null
     */
    protected function getPrivateKey(): ?PrivateKey
    {
        if (strlen($this->privateKey) == 0) {
            return null;
        }

        $password = strlen($this->privateKeyPassword) > 0 ? $this->privateKeyPassword : false;
        $key      = PublicKeyLoader::load($this->privateKey, $password);

        if ($key instanceof PrivateKey) {
            return $key;
        } else {
            return null;
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
        if (($conn = $this->getConnection()) === null) {
            return false;
        }

        if ($this->exists($path) === false) {
            return true;
        }

        $fullPath = $this->getFullPath($path, true);
        try {
            // have to use hack below because phpseclib doesn't work well with
            // directories in none recursive mode
            $isDir      = $this->isDir($path);
            $isEmptyDir = $isDir && $this->isDirEmpty($path);
            if ($isDir) {
                if ($isEmptyDir === false && $recursive === false) {
                    return false;
                }

                return $conn->delete($fullPath, true);
            } else {
                return $conn->delete($fullPath);
            }
        } catch (Exception $e) {
            return false;
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
     * @return resource|false
     */
    private function getSourceFileHandle(string $sourceFilePath)
    {
        if ($this->lastSourceFilePath === $sourceFilePath) {
            return $this->sourceFileHandle;
        }

        if (is_resource($this->sourceFileHandle)) {
            @fclose($this->sourceFileHandle);
        }

        if (($this->sourceFileHandle = @fopen($sourceFilePath, 'r')) === false) {
            return false;
        }

        $this->lastSourceFilePath = $sourceFilePath;
        return $this->sourceFileHandle;
    }

    /**
     * Get elapsed time in microseconds
     *
     * @param float $startTime start time
     *
     * @return float
     */
    private function getElapsedTime($startTime)
    {
        return (microtime(true) - $startTime) * SECONDS_IN_MICROSECONDS;
    }


    /**
     * Class destructor
     *
     * @return void
     */
    public function __destruct()
    {
        if ($this->connection !== null && $this->connection->isConnected()) {
            $this->connection->disconnect();
        }
    }
}
