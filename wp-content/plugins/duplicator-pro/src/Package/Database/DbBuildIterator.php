<?php

namespace Duplicator\Package\Database;

use Duplicator\Utils\Logging\DupLog;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapLog;
use Duplicator\Libs\WpUtils\WpDbUtils;
use Exception;
use Iterator;

/**
 * Dump database tables for PHPDump (single and multi)
 *
 * @implements Iterator<mixed,mixed>
 */
class DbBuildIterator implements Iterator
{
    const TEMP_COUNTER_FILE_PREFIX      = 'duplicator_db_build_progress_';
    const UPDATE_POINTER_FILE_EACH_ROWS = 500;

    /** @var ?string store file where pute last offsets */
    private $storeProgressFile;
    /** @var bool is true if use store progress file */
    private $isStoreProgress = false;
    /** @var bool */
    private $isValid = false;
    /** @var string[] tables list to iterate */
    private array $tables;
    /** @var int count of tables */
    private int $numTables;
    /** @var int current table index */
    private $tableIndex = -1;
    /** @var int current table offset */
    private $tableOffset = 0;
    /** @var int table rows */
    private $tableRows = 0;
    /** @var mixed is last index offset, can be last primary key or unique key, single o compound */
    private $lastIndexOffset = 0;
    /** @var int total rows insered count. */
    private $totalRowsOffset = 0;
    /** @var int files size */
    private $fileSize = 0;
    /** @var bool This value becomes true only by calling a specific function and returns false to the first next row or table. */
    private $lastIsCompleteInsert = false;
    /** @var callable function called at the beginning of the table parsing. */
    private $startTableCallback;
    /** @var callable function called at the end of the table parsing. */
    private $endTableCallback;
    /** @var int */
    private $storePointerRowCount = 0;

    /**
     * Class constructor
     *
     * @param string[]  $tables             tables list to iterate
     * @param ?string   $storeProgressFile  if null the store progress file system isn\'t used. I'ts faster
     * @param ?callable $startTableCallback callback called at the begin of current table insert
     * @param ?callable $endTableCallback   ccallback called at the end of current table insert
     */
    public function __construct(
        array $tables,
        ?string $storeProgressFile = null,
        ?callable $startTableCallback = null,
        ?callable $endTableCallback = null
    ) {
        $this->tables    = (array) $tables;
        $this->numTables = count($this->tables);

        if ($this->setStoreProgressFile($storeProgressFile) == false) {
            throw new Exception('Can\t set progress file');
        }
        $this->setPosition();

        if (is_callable($startTableCallback)) {
            $this->startTableCallback = $startTableCallback;
        }

        if (is_callable($endTableCallback)) {
            $this->endTableCallback = $endTableCallback;
        }
    }

    /**
     * set current position if progress file exists or rewrind the iterator
     *
     * @return void
     */
    protected function setPosition(): void
    {
        if (!$this->isStoreProgress) {
            $this->rewind();
            return;
        }

        DupLog::trace("LOAD DATA DATABASE ITERATOR");

        if (($content = SnapIO::safeFileGetContents($this->storeProgressFile)) === false) {
            throw new Exception('Can\'t read database store progress file');
        }

        if (strlen($content) === 0) {
            throw new Exception('Store progress file is empty');
        }

        if (($data = json_decode($content)) === null) {
            throw new Exception('Can\'t decode json progress data content: ' . SnapLog::v2str($content));
        }

        $this->tableIndex           = $data[0];
        $this->tableOffset          = $data[1];
        $this->lastIndexOffset      = is_scalar($data[2]) ? $data[2] : (array) $data[2];
        $this->totalRowsOffset      = $data[3];
        $this->lastIsCompleteInsert = $data[4];
        $this->tableRows            = $data[5];
        $this->fileSize             = $data[6];
        $this->isValid              = $data[7];

        DupLog::trace("SET POSITION TABLE INDEX " . $this->tableIndex . " OFFSET INDEX " . SnapLog::v2str($this->lastIndexOffset));
    }

    /**
     * save current position in progress file if initialized
     *
     * @return bool
     */
    protected function saveCounterFile(): bool
    {
        $this->storePointerRowCount = 0;

        if (!$this->isStoreProgress) {
            return true;
        }

        $data = [
            $this->tableIndex,
            $this->tableOffset,
            $this->lastIndexOffset,
            $this->totalRowsOffset,
            $this->lastIsCompleteInsert,
            $this->tableRows,
            $this->fileSize,
            $this->isValid,
        ];

        if (($dataEncoded = json_encode($data)) === false) {
            throw new Exception('Can\'t encode database iterator pointer DATA: ' . SnapLog::v2str($data));
        }

        // file_put_content is less optimized than fopen,
        // fwrite but in some serve keep the hadler file open and do fseek in massive way rarely generate corrupted files.
        // So writing and closing the file is the safest method.
        if (file_put_contents($this->storeProgressFile, $dataEncoded) !== strlen($dataEncoded)) {
            throw new Exception('Can\'t write database store progress file');
        }

        return true;
    }

    /**
     * rewind current iterator (reset all offset and table counts)
     *
     * @return void
     */
    public function rewind(): void
    {
        DupLog::infoTrace("REWIND DATABASE ITERATOR");
        $this->tableIndex           = -1;
        $this->tableOffset          = 0;
        $this->lastIndexOffset      = 0;
        $this->totalRowsOffset      = 0;
        $this->lastIsCompleteInsert = true;
        $this->tableRows            = 0;
        $this->fileSize             = 0;
        $this->storePointerRowCount = 0;
        $this->next();
    }

    /**
     * remove store progress file
     *
     * @return void
     */
    public function removeCounterFile(): void
    {
        if (file_exists($this->storeProgressFile)) {
            unlink($this->storeProgressFile);
        }
        $this->isStoreProgress   = false;
        $this->storeProgressFile = null;
    }

    /**
     * open store pregress file, if don't exists create and initialize it.
     *
     * @param ?string $storeProgressFile path to store progress file
     *
     * @return boolean
     */
    public function setStoreProgressFile(?string $storeProgressFile = null): bool
    {
        $this->storeProgressFile = null;
        $this->isStoreProgress   = false;

        if (empty($storeProgressFile)) {
            return true;
        }

        if (($fileExists = file_exists($storeProgressFile))) {
            if (!is_writable($storeProgressFile)) {
                return false;
            }
        } elseif (!is_writable(dirname($storeProgressFile))) {
            return false;
        }

        $this->storeProgressFile = $storeProgressFile;
        $this->isStoreProgress   = true;
        if (!$fileExists) {
            $this->rewind();
        }

        return true;
    }

    /**
     * next element (table) of iterator, put all table offsets at 0 and count tableRows
     *
     * If set call endTableCallback and startTableCallback
     *
     * @return void
     */
    public function next(): void
    {
        if ($this->tableIndex >= 0 && is_callable($this->endTableCallback)) {
            call_user_func($this->endTableCallback, $this);
        }

        $this->tableOffset     = 0;
        $this->lastIndexOffset = 0;
        $this->tableRows       = 0;
        $this->tableIndex++;

        if (($this->isValid = ($this->tableIndex < $this->numTables))) {
            $res             = WpDbUtils::getTablesRows($this->current());
            $this->tableRows = $res[$this->current()];

            if (is_callable($this->startTableCallback)) {
                call_user_func($this->startTableCallback, $this);
            }
            DupLog::infoTrace(
                "INSERT ROWS TABLE[INDEX:" . $this->tableIndex . "] " . $this->tables[$this->tableIndex] . " NUM ROWS: " . $this->tableRows
            );
        }
        $this->saveCounterFile();
    }

    /**
     * increment current table offsets and update store process file if exists
     *
     * @param mixed $lastIndexOffset last index offset selected, can be a primary key or mixed unique key also composed
     * @param int   $addFileSize     add file size to current file size
     *
     * @return int return total rows parsed count
     */
    public function nextRow($lastIndexOffset = 0, int $addFileSize = 0): int
    {
        $this->totalRowsOffset++;
        $this->tableOffset++;
        $this->lastIndexOffset      = $lastIndexOffset;
        $this->lastIsCompleteInsert = false;
        $this->fileSize            += $addFileSize;
        $this->storePointerRowCount++;

        if ($this->storePointerRowCount >= self::UPDATE_POINTER_FILE_EACH_ROWS) {
            $this->saveCounterFile();
        }

        return $this->totalRowsOffset;
    }

    /**
     * set last is complete inster at true and save it in store preocess file.
     *
     * @param int $addFileSize size to add
     *
     * @return void
     */
    public function setLastIsCompleteInsert(int $addFileSize = 0): void
    {
        $this->fileSize            += $addFileSize;
        $this->lastIsCompleteInsert = true;
        $this->saveCounterFile();
    }

    /**
     * @param int $fileSize Size to add
     *
     * @return void
     */
    public function addFileSize(int $fileSize = 0): void
    {
        $this->fileSize += $fileSize;
        $this->saveCounterFile();
    }

    /**
     * @return bool
     */
    public function isCurrentTableOffsetValid(): bool
    {
        return $this->tableOffset < $this->tableRows;
    }

    /**
     *
     * @return bool
     */
    public function valid(): bool
    {
        return $this->isValid;
    }

    /**
     *
     * @return string|bool // current table name or false if isn\'t valid
     */
    #[\ReturnTypeWillChange]
    public function current()
    {
        return $this->isValid ? $this->tables[$this->tableIndex] : false;
    }

    /**
     *
     * @return int table rows of current table
     */
    public function getCurrentRows(): int
    {
        return $this->tableRows;
    }

    /**
     *
     * @return int current offset of current table
     */
    public function getCurrentOffset(): int
    {
        return $this->tableOffset;
    }

    /**
     *
     * @return mixed last index offset selecte, can be a primary key or mixed unique key also composed
     */
    public function getLastIndexOffset()
    {
        return $this->lastIndexOffset;
    }

    /**
     *
     * @return int total rows dumped
     */
    public function getTotalsRowsOffset(): int
    {
        return $this->totalRowsOffset;
    }

    /**
     *
     * @return int stored file size
     */
    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    /**
     *
     * @return bool return true if the last inserted sub loop is completed
     */
    public function lastIsCompleteInsert(): bool
    {
        return $this->lastIsCompleteInsert;
    }

    /**
     *
     * @return int current table index
     */
    public function key(): int
    {
        return $this->tableIndex;
    }

    /**
     *
     * @return int num table to process
     */
    public function count(): int
    {
        return $this->numTables;
    }
}
