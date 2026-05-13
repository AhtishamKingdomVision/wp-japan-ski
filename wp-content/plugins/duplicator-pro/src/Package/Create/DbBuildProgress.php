<?php

namespace Duplicator\Package\Create;

use Duplicator\Libs\WpUtils\WpDbUtils;
use Exception;

/**
 * Database build progress class
 */
class DbBuildProgress
{
    /** @var string[] */
    public $tablesToProcess = [];
    /** @var bool */
    public $validationStage1 = false;
    /** @var bool */
    public $doneInit = false;
    /** @var bool */
    public $doneFiltering = false;
    /** @var bool */
    public $doneCreates = false;
    /** @var bool */
    public $completed = false;
    /** @var float */
    public $startTime = 0.0;
    /** @var bool */
    public $wasInterrupted = false;
    /** @var bool */
    public $errorOut = false;
    /** @var int */
    public $failureCount = 0;
    /** @var array{impreciseTotalRows: int, countTotal: int, tables: mixed[]} */
    public $countCheckData = [
        'impreciseTotalRows' => 0,
        'countTotal'         => 0,
        'tables'             => [],
    ];

    /**
     * Initializes the structure used by the validation to verify the count of entries.
     *
     * @return void
     */
    public function countCheckSetStart(): void
    {
        $this->countCheckData = [
            'countTotal'         => 0,
            'impreciseTotalRows' => WpDbUtils::getImpreciseTotaTablesRows($this->tablesToProcess),
            'tables'             => [],
        ];

        foreach ($this->tablesToProcess as $table) {
            $this->countCheckData['tables'][$table] = [
                'start'  => 0,
                'end'    => 0,
                'count'  => 0,
                'create' => false,
            ];
        }
    }

    /**
     * Reset build progress values
     *
     * @return void
     */
    public function reset(): void
    {
        $this->tablesToProcess  = [];
        $this->validationStage1 = false;
        $this->doneInit         = false;
        $this->doneFiltering    = false;
        $this->doneCreates      = false;
        $this->completed        = false;
        $this->startTime        = 0;
        $this->wasInterrupted   = false;
        $this->errorOut         = false;
        $this->failureCount     = 0;
        $this->countCheckData   = [
            'impreciseTotalRows' => 0,
            'countTotal'         => 0,
            'tables'             => [],
        ];
    }

    /**
     * set count value at the beginning of table insert
     *
     * @param string $table talbe name
     *
     * @return void
     */
    public function tableCountStart($table): void
    {
        if (!isset($this->countCheckData['tables'][$table])) {
            throw new Exception('Table ' . $table . ' no found in progress strunct');
        }
        $tablesRows = WpDbUtils::getTablesRows($table);

        if (!isset($tablesRows[$table])) {
            throw new Exception('Table ' . $table . ' in database not found');
        }
        $this->countCheckData['tables'][$table]['start'] = $tablesRows[$table];
    }

    /**
     * set count valute ad end of table insert and real count of rows dumped
     *
     * @param string $table talbe name
     * @param int    $count num rows
     *
     * @return void
     */
    public function tableCountEnd($table, $count): void
    {
        if (!isset($this->countCheckData['tables'][$table])) {
            throw new Exception('Table ' . $table . ' no found in progress strunct');
        }
        $tablesRows = WpDbUtils::getTablesRows($table);

        if (!isset($tablesRows[$table])) {
            throw new Exception('Table ' . $table . ' in database not found');
        }
        $this->countCheckData['tables'][$table]['end']   = $tablesRows[$table];
        $this->countCheckData['tables'][$table]['count'] = (int) $count;
        $this->countCheckData['countTotal']             += (int) $count;
    }
}
