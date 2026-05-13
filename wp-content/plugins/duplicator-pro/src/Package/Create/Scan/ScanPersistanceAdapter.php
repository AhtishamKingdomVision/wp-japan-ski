<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Package\Create\Scan;

use Duplicator\Libs\Chunking\Iterators\GenericSeekableIteratorInterface;
use Duplicator\Libs\Chunking\Persistance\FileJsonPersistanceAdapter;
use Duplicator\Package\Create\Scan\ScanResult;
use Exception;

class ScanPersistanceAdapter extends FileJsonPersistanceAdapter
{
    const PERSSITANCE_FILE_POSTFIX = '_scan_persistance.json';

    protected \Duplicator\Package\Create\Scan\ScanResult $scanResult;

    /**
     * Class constructor
     *
     * @param string     $hash       persistance file hash
     * @param ScanResult $scanResult scan result object
     */
    public function __construct($hash, ScanResult $scanResult)
    {
        if (empty($hash)) {
            throw new Exception('hash can\'t be empty');
        }
        $path             = DUPLICATOR_SSDIR_PATH_TMP . '/' . $hash . self::PERSSITANCE_FILE_POSTFIX;
        $this->scanResult = $scanResult;
        parent::__construct($path);
    }

    /**
     * Called after loadPersistanceData, so the data is available
     *
     * @return void
     */
    protected function afterLoadPersistanceData()
    {
        $data = $this->getExtraData();
        $this->scanResult->import($data);
    }

    /**
     * Modify the data before write
     *
     * @param mixed                            $position the position to save
     * @param GenericSeekableIteratorInterface $it       current iterator
     *
     * @return void
     */
    protected function beforeWritePersistanceData($position, GenericSeekableIteratorInterface $it)
    {
        $this->setExtraData($this->scanResult);
    }
}
