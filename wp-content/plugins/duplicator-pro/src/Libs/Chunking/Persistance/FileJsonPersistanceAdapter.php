<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

declare(strict_types=1);

namespace Duplicator\Libs\Chunking\Persistance;

use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Utils\Logging\DupLog;
use VendorDuplicator\Amk\JsonSerialize\JsonSerialize;
use Exception;
use Throwable;

class FileJsonPersistanceAdapter extends AbstractPersistanceAdapter
{
    /** @var string persistance file path */
    protected $path = '';

    /**
     * Class constructor
     *
     * @param string $path persistance file path
     */
    public function __construct(string $path)
    {
        if (strlen($path) == 0) {
            throw new Exception('Persistance file path can\'t be empty');
        }
        $this->path = $path;
    }

    /**
     * Actually load data from storage
     *
     * @return ?array{isProcessing: bool, extraData: mixed, position: mixed}
     */
    protected function loadPersistanceData()
    {
        if (($content = SnapIO::safeFileGetContents($this->path)) === false) {
            return null;
        }

        $data = JsonSerialize::unserialize($content);
        if (
            !is_array($data) ||
            !array_key_exists('extraData', $data) ||
            !array_key_exists('position', $data) ||
            !array_key_exists('isProcessing', $data)
        ) {
            return null;
        }

        return $data;
    }

    /**
     * Actually delete data from storage
     *
     * @return bool This function returns true on success, or FALSE on failure.
     */
    protected function doDeletePersistanceData(): bool
    {
        return (file_exists($this->path) ? unlink($this->path) : true);
    }

    /**
     * Actually write data to storage
     *
     * @param array{isProcessing: bool, extraData: mixed, position: mixed} $data data to save
     *
     * @return bool This function returns true on success, or FALSE on failure.
     */
    protected function writePersistanceData($data): bool
    {
        $handle = null;
        try {
            if (($json = JsonSerialize::serialize($data)) === false) {
                throw new Exception('Failed to serialize persistance data');
            }

            $handle = fopen($this->path, 'w');
            if ($handle === false) {
                throw new Exception('Failed to open persistance file: ' . $this->path);
            }

            $written = SnapIO::fwriteChunked($handle, $json);
            if ($written !== strlen($json)) {
                throw new Exception('Failed to write all bytes to persistance file, written: ' . $written . ', expected: ' . strlen($json));
            }

            if (function_exists('fsync')) {
                fsync($handle);
            } elseif (function_exists('fflush')) {
                fflush($handle);
            }

            return true;
        } catch (Throwable $e) {
            DupLog::traceError('Persistance write error: ' . $e->getMessage());
            return false;
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }
}
