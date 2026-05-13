<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Libs\Index;

use Duplicator\Libs\Binary\AbstractBinaryEncodable;
use Duplicator\Libs\Index\Header\IndexHeaderHandler;
use Duplicator\Libs\Index\Header\IndexHeaderInterface;
use Exception;
use Generator;

/**
 * The index manager is a class to create, write, read the index file of duplicator.
 *
 * @template T of AbstractBinaryEncodable
 */
abstract class AbstractIndexManager
{
    /** @var string */
    protected string $path = '';

    /** @var ?resource */
    protected $handle;

    protected \Duplicator\Libs\Index\Header\IndexHeaderInterface $header;

    /** @var bool */
    protected bool $isOnWriteMode = false;

    /** @var array<int, IndexList> */
    protected array $indexLists = [];

    /**
     * Constructor
     *
     * @param string $path   Path to the index file
     * @param bool   $create Whether to create the file if it doesn't exist
     *
     * @return void
     */
    public function __construct(string $path, bool $create = false)
    {
        $created    = false;
        $this->path = $path;
        if (!file_exists($this->path) || filesize($this->path) === 0) {
            // Consider empty file as new file
            if ($create) {
                if (!touch($this->path)) {
                    throw new Exception("Couldn't create index file.");
                }
                $created = true;
            } else {
                throw new Exception('Index file does not exist.');
            }
        }

        $this->getHandle();
        if ($created) {
            // If the file was created, we need to lock it exclusively to create the header
            $this->setExclusiveLock();
        }

        $this->header = IndexHeaderHandler::getIndexHeader(
            $this->handle,
            static::getListTypes(),
            static::getIndexType()
        );

        if ($this->header->getType() !== static::getIndexType()) {
            throw new Exception('Index file type mismatch. Using wrong class to read the index file.');
        }

        if ($created) {
            // If the file was created, we need to save the header and free the exclusive lock
            $this->save();
        }
    }

    /**
     * Returns the handle of the index file
     *
     * @return resource
     */
    protected function getHandle()
    {
        if (is_resource($this->handle)) {
            return $this->handle;
        }

        if ($this->path === '') {
            throw new Exception('Path is not set.');
        }

        if (($handle = fopen($this->path, 'r+b')) === false) {
            throw new \Exception('Error opening index file.');
        }
        $this->handle = $handle;

        $this->setSharedLock();

        return $this->handle;
    }

    /**
     * Get the type of the index. Has to be a unique hexadecimal string of 12 characters
     * describing the type of the index.
     *
     * @return string The index type
     */
    abstract protected static function getIndexType(): string;

    /**
     * Returns the list types
     *
     * @return int[] The list types
     */
    abstract protected static function getListTypes(): array;

    /**
     * Returns the class name of the items the list is going to store
     *
     * @return class-string<T>
     */
    abstract protected function getItemClass(): string;

    /**
     * Adds a scan node into the list
     *
     * @param int $listType List type
     * @param T   $node     Node to add
     *
     * @return void
     */
    public function add(int $listType, AbstractBinaryEncodable $node): void
    {
        $this->writeOpen();
        $data = $this->beforeWrite($node->getBinaryValues());
        $this->indexLists[$listType]->add($data, $node->getBinaryFormats());
    }

    /**
     * Modify the data before writing
     *
     * @param array<string|int, mixed> $data The data to write
     *
     * @return array<string|int, mixed> The modified data
     */
    protected function beforeWrite(array $data)
    {
        return $data;
    }

    /**
     * Get file index path
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Iterate of a specific list type.
     *
     * @param int $listType List type
     * @param int $seek     The number of the item to seek to
     *
     * @return Generator<int, T> The generator for iteration
     */
    public function iterate(int $listType, int $seek = -1): Generator
    {
        $itemClass = $this->getItemClass();
        $formats   = $itemClass::getBinaryFormats();
        if ($this->isOnWriteMode) {
            $iterator = $this->indexLists[$listType]->iterate($formats, $seek);
        } else {
            $start    = $this->header->getListStart($listType);
            $end      = $this->header->getListEnd($listType);
            $iterator = IndexList::iterateFromHandle($this->getHandle(), $formats, $seek, $start, $end);
        }

        foreach ($iterator as $data) {
            yield $itemClass::objectFromData($data);
        }
    }

    /**
     * Returns the number of items in a specific list type
     *
     * @param int $listType List type
     *
     * @return int The number of items
     */
    public function getCount(int $listType): int
    {
        if ($this->isOnWriteMode) {
            return $this->indexLists[$listType]->getCount();
        }

        return $this->header->getListCount($listType);
    }

    /**
     * Set exclusive lock on the index file
     *
     * @return void
     */
    protected function setExclusiveLock(): void
    {
        // Explicit unlock before lock for better Windows file system support
        flock($this->handle, LOCK_UN);
        if (flock($this->handle, LOCK_EX) === false) {
            throw new Exception('Error locking index file.');
        }
    }

    /**
     * Set shared lock on the index file
     *
     * @return void
     */
    protected function setSharedLock(): void
    {
        // Explicit unlock before lock for better Windows file system support
        flock($this->handle, LOCK_UN);
        if (flock($this->handle, LOCK_SH) === false) {
            throw new Exception('Error locking index file.');
        }
    }

    /**
     * Split index file into index lists
     *
     * @return void
     */
    protected function writeOpen(): void
    {
        if ($this->isOnWriteMode) {
            return;
        }

        $this->setExclusiveLock();

        $header = $this->header;
        $header->markOpen();
        foreach (static::getListTypes() as $listType) {
            $this->indexLists[$listType] = new IndexList(dirname($this->path), $listType, $this->header->getListCount($listType));
            if ($header->getListSize($listType) !== 0) {
                $this->indexLists[$listType]->copyFromMain(
                    $this->getHandle(),
                    $header->getListStart($listType),
                    $header->getListSize($listType),
                    $header->getListCount($listType)
                );
            }
        }

        $this->isOnWriteMode = true;
    }

    /**
     * Merges the index files into the main index file
     *
     * @return void
     */
    public function save(): void
    {
        try {
            if (!$this->isOnWriteMode) {
                return;
            }

            $this->header->close($this->indexLists);
            $this->setSharedLock();
        } catch (Exception $e) {
            throw new Exception("Error closing index file: " . $e->getMessage());
        } finally {
            $this->isOnWriteMode = false;
            $this->flush();
        }
    }

    /**
     * Reset the index manager
     *
     * @return void
     */
    public function reset(): void
    {
        $this->setExclusiveLock();
        $this->isOnWriteMode = false;
        foreach ($this->indexLists as $indexList) {
            $indexList->reset();
        }
        $this->truncate();
        $this->header->reset();
        $this->save();
    }

    /**
     * Truncate the index file
     *
     * @return void
     */
    protected function truncate(): void
    {
        if (ftruncate($this->getHandle(), 0) === false) {
            throw new Exception("Couldn't truncate index handle.");
        }

        if (rewind($this->getHandle()) === false) {
            throw new Exception("Couldn't rewind index handle.");
        }
    }

    /**
     * Destructor
     *
     * @return void
     */
    protected function flush()
    {
        if (is_resource($this->handle)) {
            if (fflush($this->handle) === false) {
                throw new Exception("Couldn't flush index file before close.");
            }
        }
    }

    /**
     * Destructor
     *
     * @return void
     */
    public function __destruct()
    {
        if (is_resource($this->handle)) {
            $this->save();

            if (flock($this->handle, LOCK_UN) === false) {
                throw new Exception("Couldn't unlock index file before close.");
            }

            if (fclose($this->handle) === false) {
                throw new Exception("Couldn't close index file handle.");
            }
        }
    }
}
