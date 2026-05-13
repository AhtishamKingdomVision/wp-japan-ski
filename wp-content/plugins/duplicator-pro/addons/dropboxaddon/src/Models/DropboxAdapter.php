<?php

namespace Duplicator\Addons\DropboxAddon\Models;

use Duplicator\Utils\Logging\DupLog;
use Duplicator\Addons\DropboxAddon\Utils\DropboxClient;
use Duplicator\Models\Storages\AbstractStorageAdapter;
use Duplicator\Models\Storages\StoragePathInfo;
use Error;
use Exception;
use VendorDuplicator\Spatie\Dropbox\UploadSessionCursor;

class DropboxAdapter extends AbstractStorageAdapter
{
    /** @var string */
    protected $accessToken = '';
    protected DropboxClient $client;
    protected string $storageFolder;
    /** @var bool */
    protected $sslVerify = true;
    /** @var string If empty use server cert else use custom cert path */
    protected $sslCert = '';
    /** @var bool */
    protected $ipv4Only = false;

    /**
     * @param string $accessToken   Dropbox access token.
     * @param string $storageFolder Dropbox storage folder.
     * @param bool   $sslVerify     If true, use SSL
     * @param string $sslCert       If empty use server cert
     * @param bool   $ipv4Only      If true, use IPv4 only
     */
    public function __construct(
        $accessToken,
        $storageFolder = '',
        $sslVerify = true,
        $sslCert = '',
        $ipv4Only = false
    ) {
        $this->accessToken   = $accessToken;
        $this->storageFolder = '/' . trim($storageFolder, '/') . '/';
        $this->sslVerify     = $sslVerify;
        $this->sslCert       = $sslCert;
        $this->ipv4Only      = $ipv4Only;
        $this->client        = new DropboxClient(
            $accessToken,
            null,
            DropboxClient::MAX_CHUNK_SIZE,
            0,
            $sslVerify,
            $sslCert,
            $ipv4Only
        );
    }

    /**
     * Get the Dropbox client.
     *
     * @return DropboxClient
     */
    public function getClient(): DropboxClient
    {
        return $this->client;
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
        if (! $this->exists('/')) {
            try {
                $this->createDir('/');
            } catch (Exception $e) {
                DupLog::trace($e->getMessage());
                $errorMsg = $e->getMessage();
                return false;
            }
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
        $this->delete('/', true);

        return true;
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
        try {
            $this->client->getMetadata($this->storageFolder);
        } catch (Exception $e) {
            DupLog::trace("Dropbox storage is invalid: " . $e->getMessage());
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
        $path = $this->formatPath($path);

        try {
            $this->client->createFolder($path);
        } catch (Exception $e) {
            DupLog::trace($e->getMessage());
            return false;
        }

        return true;
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
        $path = $this->formatPath($path);

        try {
            $response = $this->client->upload($path, $content, 'overwrite');
        } catch (Exception $e) {
            DupLog::trace($e->getMessage());
            return false;
        }

        return $response['size'];
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
        $path = $this->formatPath($path);
        if (! $recursive) {
            try {
                $response = $this->client->listFolder($path);
                if (count($response['entries']) > 0) {
                    return false;
                }
            } catch (Exception $e) {
                // Path is not a directory, so we can delete it.
            }
        }
        try {
            $this->client->delete($path);
        } catch (Exception $e) {
            DupLog::trace($e->getMessage());
            return false;
        }
        return true;
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
        $content = '';

        try {
            $stream = $this->client->download($this->formatPath($path));
            while ($chunk = fgets($stream)) {
                $content .= $chunk;
            }
        } catch (Exception $e) {
            DupLog::trace($e->getMessage());
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
        $oldPath = $this->formatPath($oldPath);
        $newPath = $this->formatPath($newPath);

        try {
            $this->client->move($oldPath, $newPath);
        } catch (Exception $e) {
            DupLog::trace($e->getMessage());
            return false;
        }

        return true;
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
        try {
            $response = $this->client->getMetadata($this->formatPath($path));
        } catch (Exception $e) {
            $response = [];
        }

        return $this->buildPathInfo($response);
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
        $path = rtrim($this->formatPath($path), '/') . '/';

        $filterFunc = function ($entry) use ($files, $folders): bool {
            if ($entry['.tag'] === 'file' && $files) {
                return true;
            }

            if ($entry['.tag'] === 'folder' && $folders) {
                return true;
            }

            return false;
        };
        try {
            $response = $this->client->listFolder($path);
        } catch (Exception $e) {
            DupLog::trace('[DropboxAddon] ' . $e->getMessage());
            return [];
        }

        // We filter out the entries as needed, then only keep the path.
        // We do this early to keep the memory usage as low as possible.
        $entries = array_map(fn($entry): string => substr($entry['path_display'], strlen($path)), array_filter($response['entries'], $filterFunc));

        while ($response['has_more']) {
            $response = $this->client->listFolderContinue($response['cursor']);
            $entries  = array_merge(
                $entries,
                array_map(
                    fn($entry): string => substr($entry['path_display'], strlen($path)),
                    array_filter($response['entries'], $filterFunc)
                )
            );
        }

        return $entries;
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
        $path = $this->formatPath($path);
        try {
            $response = $this->client->listFolder($path);
        } catch (Exception $e) {
            DupLog::trace($e->getMessage());
            return false;
        }
        if (count($response['entries']) === 0) {
            return true;
        } elseif (empty($filters)) {
            // we have no filters, and the folder is not empty, so it must contain something
            return false;
        }
        $regexFilters = $normalFilters = [];

        foreach ($filters as $filter) {
            if ($filter[0] === '/' && substr($filter, -1) === '/') {
                $regexFilters[] = $filter; // It's a regex filter as it starts and ends with a slash
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
        try {
            $storageFile = $this->formatPath($storageFile);
            $fileSize    = filesize($sourceFile);
            $chunkSize   = $length > 0 ? $length : 4 * MB_IN_BYTES;
            $fileKey     = md5($sourceFile . $storageFile);
            $completeKey = $fileKey . '_complete';

            $result = false;
            if (isset($extraData[$completeKey]) && $extraData[$completeKey] === true) {
                // The file is already uploaded
                return $fileSize;
            }

            // Check if we can read the source file
            if (!$handle = @fopen($sourceFile, 'rb')) {
                throw new Exception("Could not open source file: {$sourceFile}");
            }

            if ($fileSize <= $chunkSize || $length < 0) {
                // We need to upload the whole file in one go
                if (($file = $this->uploadCompleteFile($handle, $storageFile, $chunkSize)) == false) {
                    throw new Exception("Failed to upload file: " . $storageFile);
                }
                if (! isset($file['.tag']) || $file['.tag'] !== 'file') {
                    throw new Exception("Failed to upload file: " . json_encode($file));
                }
                $extraData[$completeKey] = true;
                $result                  =  $fileSize;
            } else {
                // At this point we know we need to upload the file in sequential chunks.
                if (fseek($handle, $offset) !== 0) {
                    throw new Exception("Could not seek to offset {$offset} in source file: {$sourceFile}");
                }

                $cursor = null;

                if (!empty($extraData[$fileKey])) {
                    $sessionId = $extraData[$fileKey];
                    $cursor    = new UploadSessionCursor($sessionId, $offset);
                }

                $contents = @fread($handle, $chunkSize);
                if ($cursor === null) {
                    // We need to start a new session as we don't have a cursor yet
                    $cursor              = $this->client->uploadSessionStart($contents);
                    $extraData[$fileKey] = $cursor->session_id;
                } elseif (strlen($contents) < $chunkSize) {
                    // As the content size is less than the chunk size, we need to finish the session
                    $this->client->uploadSessionFinish($contents, $cursor, $storageFile, 'overwrite');
                    $extraData[$completeKey] = true;
                    $cursor->offset         += $chunkSize;
                } else {
                    // A session is already started, we can append to it
                    $cursor              = $this->client->uploadSessionAppend($contents, $cursor);
                    $extraData[$fileKey] = $cursor->session_id;
                }
                $result = $cursor->offset - $offset;
            }
        } catch (Exception | Error $e) {
            $this->client->setTimeout(0);
            DupLog::infoTraceException($e, "[DROPBOX] CopyToStorage error");
            return false;
        }

        return $result;
    }

    /**
     * Upload a whole file in one go
     *
     * @param resource $sourceHandle Resource handle for the file we are uploading
     * @param string   $storageFile  Storage path for the uploaded file
     * @param int      $chunkSize    Chunk size to use when uploading
     *
     * @return array<string, string>|false
     */
    protected function uploadCompleteFile($sourceHandle, string $storageFile, int $chunkSize)
    {
        if (@fseek($sourceHandle, 0) !== 0) {
            DupLog::info("[DropboxAddon] Could not seek to start of source file for {$storageFile}");
            return false;
        }
        $cursor = $this->client->uploadSessionStart(@fread($sourceHandle, $chunkSize));
        $file   = null;
        while (!feof($sourceHandle)) {
            $contents = @fread($sourceHandle, $chunkSize);
            if ($contents === false) {
                return false;
            }
            if (strlen($contents) < $chunkSize) {
                $file = $this->client->uploadSessionFinish($contents, $cursor, $storageFile, 'overwrite');
                break;
            }
            $cursor = $this->client->uploadSessionAppend($contents, $cursor);
        }
        if ($file === null) {
            $file = $this->client->uploadSessionFinish('', $cursor, $storageFile, 'overwrite');
        }
        return $file;
    }

    /**
     * Normalize path, add storage root path if needed.
     *
     * @param string $path Relative storage path.
     *
     * @return string
     */
    protected function formatPath($path): string
    {
        return $this->storageFolder . ltrim($path, '/');
    }

    /**
     * Build StoragePathInfo object from Dropbox API response.
     *
     * @param array<string,mixed> $response Dropbox API response.
     *
     * @return StoragePathInfo
     */
    protected function buildPathInfo($response)
    {
        $info         = new StoragePathInfo();
        $info->exists = isset($response['.tag']);

        if (!$info->exists) {
            return $info;
        }

        $info->path     = $this->getRelativeStoragePath($response['path_display']);
        $info->isDir    = $response['.tag'] === 'folder';
        $info->size     = $response['size'] ?? 0;
        $info->created  = isset($response['client_modified']) ? strtotime($response['client_modified']) : time();
        $info->modified = isset($response['server_modified']) ? strtotime($response['server_modified']) : time();

        return $info;
    }

    /**
     * Get relative storage path from Dropbox path display.
     *
     * @param string $path_display Dropbox path display.
     * @param string $subPath      Sub path to remove from the path display.
     *
     * @return string
     */
    protected function getRelativeStoragePath($path_display, $subPath = ''): string
    {
        $rootPath = $this->storageFolder;
        if (!empty($subPath)) {
            $rootPath .= trim($subPath) . '/';
        }
        return substr($path_display, strlen($rootPath));
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
        if (! $this->exists($storageFile)) {
            DupLog::trace("[DropboxAddon] Storage file {$storageFile} does not exist");
            return false;
        }

        if ($offset > 0 && !@file_exists($destFile)) {
            return false;
        }

        if (! isset($extraData['resuming']) && file_put_contents($destFile, '') === false) {
            DupLog::trace("[DropboxAddon] Could not open destination file for writing. File: {$destFile}");
            return false;
        }
        $extraData['resuming'] = true;
        if (!isset($extraData['fileSize'])) {
            $extraData['fileSize'] = $this->getPathInfo($storageFile)->size;
        }

        $this->client->setTimeout($timeout / SECONDS_IN_MICROSECONDS);

        $bytesWritten = $offset;
        $chunkSize    = $length > 0 ? $length : 5 * MB_IN_BYTES;
        while ($bytesWritten < $extraData['fileSize'] && ($length < 0 || $bytesWritten < $offset + $length)) {
            try {
                $content = $this->client->downloadPartial($this->formatPath($storageFile), $bytesWritten, $chunkSize);
            } catch (Exception $e) {
                DupLog::info('[DropboxAddon] Failed to download file: ' . $e->getMessage());
                break;
            }

            if (file_put_contents($destFile, $content, FILE_APPEND) === false) {
                DupLog::info("[DropboxAddon] Could not write to destination file. File: {$destFile}");
                break;
            }

            $bytesWritten += strlen($content);
        }
        $this->client->setTimeout(0);

        if ($bytesWritten === $offset) {
            // nothing was downloaded
            return false;
        }

        return $length > 0 ? $length : $bytesWritten - $offset;
    }
}
