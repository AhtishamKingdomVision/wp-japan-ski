<?php

namespace Duplicator\Package\Database;

use Duplicator\Models\GlobalEntity;
use Duplicator\Utils\Logging\DupLog;
use Duplicator\Core\Constants;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapLog;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Libs\Snap\SnapDB;
use Duplicator\Libs\Snap\SnapURL;
use Duplicator\Libs\Snap\SnapWP;
use Duplicator\Libs\Shell\Shell;
use Duplicator\Libs\Snap\SnapServer;
use Duplicator\Libs\Snap\SnapString;
use Duplicator\Libs\WpUtils\WpDbUtils;
use Duplicator\Models\SystemGlobalEntity;
use Duplicator\Package\AbstractPackage;
use Duplicator\Package\Create\BuildComponents;
use Duplicator\Package\Database\DatabaseInfo;
use Duplicator\Package\Database\DbBuildIterator;
use Duplicator\Utils\GroupOptions;
use Exception;
use wpdb;

/**
 * Class used to do the actual working of building the database file
 * There are currently three modes: PHP, MYSQLDUMP, PHPCHUNKING
 * PHPCHUNKING and PHP will eventually be combined as one routine
 */
class DatabasePkg
{
    /**
     * Marks the end of the CREATEs in the SQL file which have to be
     * run together in one chunk during install
     */
    const TABLE_CREATION_END_MARKER = "/***** TABLE CREATION END *****/\n";
    /**
     * Updating the percentage of progression in the serialized structure in the database is a heavy action so every TOT entries are made.
     */
    const ROWS_NUM_TO_UPDATE_PROGRESS = 10000;
    /**
     * The mysqldump allowed size difference to memory limit in bytes. Run musqldump only on DBs smaller than memory_limit minus this value.
     */
    const MYSQLDUMP_ALLOWED_SIZE_DIFFERENCE = 50 * MB_IN_BYTES;
    /**
     * prefix of the file used to save the offsets of the inserted tables
     */
    const STORE_DB_PROGRESS_FILE_PREFIX = 'duplicator_db_export_progress_';
    const CLOSE_INSERT_QUERY            = ";\n\n";
    const PHP_DUMP_CHUNK_WORKER_TIME    = 5;

    /** @var ?DatabaseInfo */
    public $info;
    /** @var string */
    public $Type = 'MySQL';
    /** @var int */
    public $Size            = 0;
    protected ?string $File = '';
    /** @var string tables with comma separated */
    public $FilterTables = '';
    /** @var bool */
    public $FilterOn = false;
    /** @var bool */
    public $prefixFilter = false;
    /** @var bool */
    public $prefixSubFilter = false;
    /** @var string */
    public $DBMode = 'PHP';
    /** @var string */
    public $Compatible = '';
    /** @var string */
    public $Comments = '';
    /** @var string */
    public $dbStorePathPublic = '';
    private AbstractPackage $Package;
    /** @var int */
    private $throttleDelayInUs = 0;

    /**
     * Class constructor
     *
     * @param AbstractPackage $package The Backup object
     */
    public function __construct(AbstractPackage $package)
    {
        $this->Package           = $package;
        $this->File              = $package->getNameHash() . '_database.sql';
        $this->DBMode            = WpDbUtils::getBuildMode();
        $this->info              = new DatabaseInfo();
        $global                  = GlobalEntity::getInstance();
        $this->throttleDelayInUs = $global->getMicrosecLoadReduction();

        $dbcomments     = WpDbUtils::getVariable('version_comment');
        $dbcomments     = is_null($dbcomments) ? '- unknown -' : $dbcomments;
        $this->Comments = esc_html($dbcomments);

        self::setTimeout();
    }

    /**
     * Filter props on json encode
     *
     * @return string[]
     */
    public function __sleep()
    {
        $props = array_keys(get_object_vars($this));
        return array_diff($props, ['traceLogEnabled', 'Package', 'throttleDelayInUs']);
    }

    /**
     * Increment mysql time out only one time
     *
     * @return void
     */
    protected static function setTimeout()
    {
        static $isTimeoutSet = false;

        if ($isTimeoutSet) {
            return;
        }

        global $wpdb;
        $query = $wpdb->prepare("SET SESSION wait_timeout = %d", DUPLICATOR_DB_MAX_TIME);
        $wpdb->query($query);
        $isTimeoutSet = true;
    }

    /**
     * Clone
     *
     * @return void
     */
    public function __clone()
    {
        $this->info = clone $this->info;
    }

    /**
     * Runs the build process for the database
     *
     * @return void
     */
    public function build(): void
    {
        DupLog::trace("BUILDING DATABASE");
        do_action('duplicator_build_database_before_start', $this->Package);
        $global = GlobalEntity::getInstance();
        $this->Package->db_build_progress->startTime = microtime(true);
        $this->Package->setStatus(AbstractPackage::STATUS_DBSTART);
        $this->dbStorePathPublic = "{$this->Package->StorePath}/{$this->File}";
        $mysqlDumpPath           = WpDbUtils::getMySqlDumpPath();
        $mode                    = WpDbUtils::getBuildMode();
        // ($mysqlDumpPath && $global->package_mysqldump) ? 'MYSQLDUMP' : 'PHP';

        $mysqlDumpSupport = ($mysqlDumpPath) ? 'Is Supported' : 'Not Supported';
        $log              = "\n********************************************************************************\n";
        $log             .= "DATABASE:\n";
        $log             .= "********************************************************************************\n";
        $log             .= "BUILD MODE:   {$mode} ";
        if (($mode == 'MYSQLDUMP') && strlen($this->Compatible)) {
            $log .= " (Legacy SQL)";
        }

        $log .= "(query limit - {$global->package_mysqldump_qrylimit})\n";
        $log .= "MYSQLDUMP:    {$mysqlDumpSupport}\n";
        $log .= "MYSQLTIMEOUT: " . DUPLICATOR_DB_MAX_TIME;
        DupLog::info($log);
        $log = null;
        do_action('duplicator_build_database_start', $this->Package);
        switch ($mode) {
            case 'MYSQLDUMP':
                $this->runMysqlDump($mysqlDumpPath);
                break;
            case 'PHP':
                $this->runPHPDump();
                $this->validateStage1();
                break;
        }

        $this->doFinish();
    }

    /**
     * Gets the database.sql file path and name
     *
     * @return string   Returns the full file path and file name of the database.sql file
     */
    public function getSafeFilePath(): string
    {
        return SnapIO::safePath(DUPLICATOR_SSDIR_PATH . "/{$this->File}");
    }

    /**
     * Get temp safe file path
     *
     * @return string
     */
    public function getTempSafeFilePath(): string
    {
        return SnapIO::safePath(DUPLICATOR_SSDIR_PATH_TMP . "/{$this->File}");
    }

    /**
     * Get package store path
     *
     * @return string
     */
    public function getStorePath(): string
    {
        return SnapIO::safePath("{$this->Package->StorePath}/{$this->Package->Database->File}");
    }

    /**
     * @return string Returns the URL to the sql file
     */
    public function getURL(): string
    {
        return DUPLICATOR_SSDIR_URL . "/{$this->File}";
    }

    /**
     * Get store progress file
     *
     * @return string
     */
    protected function getStoreProgressFile(): string
    {
        return trailingslashit(DUPLICATOR_SSDIR_PATH_TMP) . self::STORE_DB_PROGRESS_FILE_PREFIX . $this->Package->getHash() . '.json';
    }

    /**
     * Return list of base tables to dump
     *
     * @param bool $nameOnly if true return only table names
     *
     * @return null|string[]|array<array{name: string, rows: int, size: int}>
     */
    protected function getBaseTables(bool $nameOnly = false)
    {
        /** @var wpdb $wpdb */
        global $wpdb;

        // (TABLE_NAME REGEXP '^rte4ed_(2|6)_' OR TABLE_NAME NOT REGEXP '^rte4ed_[0-9]+_')
        $query = 'SELECT  `TABLE_NAME` as `name`, `TABLE_ROWS` as `rows`, DATA_LENGTH + INDEX_LENGTH as `size` FROM `information_schema`.`tables`';

        $where = [
            'TABLE_SCHEMA = "' . esc_sql(DB_NAME) . '"',
            'TABLE_TYPE != "VIEW"',
        ];

        $prefix = esc_sql(SnapDB::quoteRegex($wpdb->prefix));

        if ($this->prefixFilter) {
            $where[] = 'TABLE_NAME REGEXP "^' . $prefix . '"';
        }

        if ($this->prefixSubFilter) {
            $where[] = '(TABLE_NAME REGEXP "^' . $prefix . '(' . implode('|', SnapWP::getSitesIds()) . ')_" ' .
                'OR TABLE_NAME NOT REGEXP "^' . $prefix . '[0-9]+_")';
        }

        $query .= ' WHERE ' . implode(' AND ', $where);
        $query .= ' ORDER BY TABLE_NAME';

        if ($nameOnly) {
            return $wpdb->get_col($query, 0);
        }

        $results = $wpdb->get_results($query, ARRAY_A);
        if (is_array($results)) {
            for ($i = 0, $count = count($results); $i < $count; $i++) {
                $results[$i]['size'] = (int) $results[$i]['size'];
                $results[$i]['rows'] = (int) $results[$i]['rows'];
            }
        }

        return $results;
    }

    /**
     *  Gets all the scanner information about the database
     *
     *  @return array<string,mixed> Returns an array of information about the database
     */
    public function getScanData(): array
    {
        global $wpdb;
        $filterTables               = explode(',', $this->FilterTables);
        $tblBaseCount               = 0;
        $tblFinalCount              = 0;
        $muFilteredTableCount       = 0;
        $tables                     = $this->getBaseTables();
        $views                      = $wpdb->get_results("SHOW FULL TABLES WHERE Table_Type = 'VIEW'", ARRAY_A);
        $query                      = $wpdb->prepare("SHOW PROCEDURE STATUS WHERE `Db`=%s", DB_NAME);
        $procs                      = $wpdb->get_results($query, ARRAY_A);
        $query                      = $wpdb->prepare("SHOW FUNCTION STATUS WHERE `Db`=%s", DB_NAME);
        $funcs                      = $wpdb->get_results($query, ARRAY_A);
        $info                       = [];
        $info['Status']['Success']  = !is_null($tables);
        $info['Status']['Size']     = true;
        $info['Status']['Rows']     = true;
        $info['Status']['Excluded'] = !BuildComponents::isDBExcluded($this->Package->components);
        $info['Size']               = 0;
        $info['Rows']               = 0;
        $info['TableCount']         = 0;
        $info['TableList']          = [];
        $tblCaseFound               = false;
        $ms_tables_to_filter        = $this->Package->Multisite->getTablesToFilter();
        $allTableNames              = array_column($tables, 'name');
        $stagingTablesToFilter      = $this->getStagingTablesToFilter($allTableNames);
        $this->info->tablesList     = [];
        //Only return what we really need
        foreach ($tables as $table) {
            $name = WpDbUtils::updateCaseSensitivePrefix($table["name"]);

            // Skip staging tables before counting - they are not part of the site
            if (in_array($name, $stagingTablesToFilter)) {
                continue;
            }

            $tblBaseCount++;
            if (BuildComponents::isDBExcluded($this->Package->components)) {
                continue;
            }

            if (in_array($name, $ms_tables_to_filter)) {
                $muFilteredTableCount++;
                continue;
            }

            if ($this->FilterOn) {
                if (in_array($name, $filterTables)) {
                    continue;
                }
            }

            //$table["Data_length"] + $table["Index_length"] $table["Rows"] $table["Name"]

            $size                              = $table["size"];
            $info['Size']                     += $size;
            $info['Rows']                     += ($table["rows"]);
            $info['TableList'][$name]['Case']  = preg_match('/[A-Z]/', $name) ? 1 : 0;
            $info['TableList'][$name]['Rows']  = empty($table["rows"]) ? '0' : number_format($table["rows"]);
            $info['TableList'][$name]['Size']  = SnapString::byteSize($size);
            $info['TableList'][$name]['USize'] = $size;
            $tblFinalCount++;
            $this->info->addTableInList($name, $table["rows"], $size);
            //Table Uppercase
            if ($info['TableList'][$name]['Case']) {
                $tblCaseFound = true;
            }
        }

        $this->info->addTriggers();
        $info['Status']['Size']                   = $info['Size'] <= DUPLICATOR_SCAN_DB_ALL_SIZE;
        $info['Status']['Rows']                   = $info['Rows'] <= DUPLICATOR_SCAN_DB_ALL_ROWS;
        $info['Status']['Triggers']               = count($this->info->triggerList) <= 0;
        $info['Status']['mysqlDumpMemoryCheck']   = self::mysqldumpMemoryCheck($info['Size']);
        $info['Status']['requiredMysqlDumpLimit'] = SnapString::byteSize(self::requiredMysqlDumpLimit($info['Size']));

        $info['TableCount']               = $tblFinalCount;
        $this->info->name                 = $wpdb->dbname;
        $this->info->isNameUpperCase      = (preg_match('/[A-Z]/', $wpdb->dbname) === 1);
        $this->info->isTablesUpperCase    = $tblCaseFound;
        $this->info->tablesBaseCount      = $tblBaseCount;
        $this->info->tablesFinalCount     = $tblFinalCount;
        $this->info->muFilteredTableCount = $muFilteredTableCount;
        $this->info->tablesRowCount       = $info['Rows'];
        $this->info->tablesSizeOnDisk     = $info['Size'];
        $this->info->dbEngine             = WpDbUtils::getDbEngine();
        $this->info->version              = WpDbUtils::getVersion();
        $this->info->versionComment       = WpDbUtils::getVariable('version_comment');
        $tables                           = $this->getFilteredTables();
        $this->info->charSetList          = WpDbUtils::getTableCharSetList($tables);
        $this->info->collationList        = WpDbUtils::getTableCollationList($tables);
        $this->info->engineList           = WpDbUtils::getTableEngineList($tables);
        $this->info->buildMode            = WpDbUtils::getBuildMode();
        $this->info->lowerCaseTableNames  = WpDbUtils::getLowerCaseTableNames();
        $this->info->viewCount            = count($views);
        $this->info->procCount            = count($procs);
        $this->info->funcCount            = count($funcs);

        return $info;
    }

    /**
     * Runs the mysqldump process to build the database.sql script
     *
     * @param string $exePath The path to the mysqldump executable
     *
     * @return bool Returns true if the mysqldump process ran without issues
     */
    private function runMysqlDump($exePath): bool
    {
        DupLog::trace("RUN MYSQL DUMP");
        $sql_header = "/* DUPLICATOR-PRO (MYSQL-DUMP BUILD MODE) MYSQL SCRIPT CREATED ON : " . @date("Y-m-d H:i:s") . " */\n\n";
        if (file_put_contents($this->dbStorePathPublic, $sql_header, FILE_APPEND) === false) {
            DupLog::error("file_put_content failed", "file_put_content failed while writing to {$this->dbStorePathPublic}");
            return false;
        }

        if (!BuildComponents::isDBExcluded($this->Package->components) && $this->mysqlDumpWriteCreates($exePath) != true) {
            DupLog::trace("Mysqldump error while writing CREATE queries");
            return false;
        }

        if ($this->mysqlDumpWriteInserts($exePath) != true) {
            DupLog::trace("Mysqldump error while writing INSERT queries");
            return false;
        }

        return true;
    }

    /**
     * @param string $exePath The path to the mysqldump executable
     *
     * @return bool returns true if successful
     */
    private function mysqlDumpWriteCreates(string $exePath): bool
    {
        /** @var wpdb $wpdb */
        global $wpdb;

        DupLog::trace("START WRITING CREATES TO SQL FILE");

        $extraFlags          = [
            '--no-data',
            '--skip-triggers',
        ];
        $optionFlagsToIgnore = ['routines'];

        // Create user and usermeta tables before other tables
        $filtered      = $this->getFilteredTables(true);
        $userTable     = $wpdb->prefix . 'users';
        $userMetaTable = $wpdb->prefix . 'usermeta';

        if (!in_array($userTable, $filtered)) {
            $cmd         = $this->getMysqlDumpCmd($exePath, $extraFlags, $userTable, [], $optionFlagsToIgnore);
            $mysqlResult = $this->mysqlDumpWriteCmd($cmd, $exePath);
            $filtered[]  = $userTable;
        }
        if (!in_array($userMetaTable, $filtered)) {
            $cmd         = $this->getMysqlDumpCmd($exePath, $extraFlags, $userMetaTable, [], $optionFlagsToIgnore);
            $mysqlResult = $this->mysqlDumpWriteCmd($cmd, $exePath);
            $filtered[]  = $userMetaTable;
        }

        $extraFlags[] = '--routines'; //include procs and funcs
        $cmd          = $this->getMysqlDumpCmd($exePath, $extraFlags, '', $filtered);
        $mysqlResult  = $this->mysqlDumpWriteCmd($cmd, $exePath);

        if (file_put_contents($this->dbStorePathPublic, self::TABLE_CREATION_END_MARKER . "\n", FILE_APPEND) === false) {
            DupLog::error("file_put_content failed", "file_put_content failed while writing to {$this->dbStorePathPublic}");
            return false;
        }
        return $this->mysqlDumpEvaluateResult($mysqlResult);
    }

    /**
     * @param string $exePath The path to the mysqldump executable
     *
     * @return bool returns true if successful
     */
    private function mysqlDumpWriteInserts(string $exePath): bool
    {
        /** @var wpdb $wpdb */
        global $wpdb;

        DupLog::trace("START WRITING INSERTS TO SQL FILE");

        $extraFlags          = [
            '--no-create-info',
            '--skip-triggers',
            '--insert-ignore',
        ];
        $optionFlagsToIgnore = ['routines'];
        // Inserts user and usermeta tables before other tables
        $filtered      = $this->getFilteredTables(true);
        $userTable     = $wpdb->prefix . 'users';
        $userMetaTable = $wpdb->prefix . 'usermeta';

        if (!in_array($userTable, $filtered)) {
            $cmd         = $this->getMysqlDumpCmd($exePath, $extraFlags, $userTable, [], $optionFlagsToIgnore);
            $mysqlResult = $this->mysqlDumpWriteCmd($cmd, $exePath);
            $filtered[]  = $userTable;
        }
        if (!in_array($userMetaTable, $filtered)) {
            $cmd         = $this->getMysqlDumpCmd($exePath, $extraFlags, $userMetaTable, [], $optionFlagsToIgnore);
            $mysqlResult = $this->mysqlDumpWriteCmd($cmd, $exePath);
            $filtered[]  = $userMetaTable;
        }

        $cmd         = $this->getMysqlDumpCmd($exePath, $extraFlags, '', $filtered, $optionFlagsToIgnore);
        $mysqlResult = $this->mysqlDumpWriteCmd($cmd, $exePath);
        $sql_footer  = "\n\n/* Duplicator WordPress Timestamp: " . date("Y-m-d H:i:s") . "*/\n";
        $sql_footer .= "/* " . DUPLICATOR_DB_EOF_MARKER . " */\n";
        if (file_put_contents($this->dbStorePathPublic, $sql_footer, FILE_APPEND) === false) {
            DupLog::error("file_put_content failed", "file_put_content failed while writing to {$this->dbStorePathPublic}");
            return false;
        }
        return $this->mysqlDumpEvaluateResult($mysqlResult);
    }

    /**
     * Get Mysql dump query fixes
     *
     * @return array{search:string[],replace:string[]}
     */
    private function getMysqlDumpFixes(): array
    {
        return [
            'search'  => [
                '/^(\s*CREATE\s+TABLE)(\s+`.+`.*)$/im',
                '/^(\s*INSERT)(\s+INTO\s+`.+`.*)$/im',
            ],
            'replace' => [
                '$1 IF NOT EXISTS$2',
                '$1 IGNORE$2',
            ],
        ];
    }

    /**
     * @param string $command        The mysqldump command to be run
     * @param string $executablePath The path to the mysqldump executable
     *
     * @return int The result of the mysql dump
     */
    private function mysqlDumpWriteCmd(string $command, string $executablePath): int
    {
        DupLog::trace('WRITING CREATES/INSERTS VIA STREAM');

        $tableRenameMap      = $this->buildTableRenameMap();
        $shouldRewriteTables = ! empty($tableRenameMap);
        $queryFixPatterns    = $this->getMysqlDumpFixes();

        $fileHandle = $this->openSqlFile();
        if (! $fileHandle) {
            return 1;
        }

        $hadWriteError    = false;
        $hasSeenFirstLine = false;

        $exitCode = Shell::runCommandStream(
            $command,
            function (string $line) use (
                $fileHandle,
                &$hasSeenFirstLine,
                &$hadWriteError,
                $shouldRewriteTables,
                $tableRenameMap,
                $queryFixPatterns
            ): void {
                $this->processLine(
                    $line,
                    $fileHandle,
                    $hasSeenFirstLine,
                    $hadWriteError,
                    $shouldRewriteTables,
                    $tableRenameMap,
                    $queryFixPatterns
                );
            }
        );

        fclose($fileHandle);
        return $hadWriteError ? 1 : (int) $exitCode;
    }

    /**
     * Build map of original to case-sensitive table names
     *
     * @return array<string,string> Map of original table names to case-sensitive versions
     */
    private function buildTableRenameMap(): array
    {
        $tables              = $this->getFilteredTables(true);
        $caseSensitiveTables = array_map(
            [
                WpDbUtils::class,
                'updateCaseSensitivePrefix',
            ],
            $tables
        );
        $map                 = [];
        foreach ($tables as $index => $original) {
            if ($original !== $caseSensitiveTables[$index]) {
                $map[$original] = $caseSensitiveTables[$index];
            }
        }
        return $map;
    }

    /**
     * Open the target SQL file for appending
     *
     * @return resource|false File handle for the target SQL file or false on failure
     */
    private function openSqlFile()
    {
        $path   = $this->dbStorePathPublic;
        $handle = @fopen($path, 'a');
        if (! $handle) {
            DupLog::error('Cannot open SQL file', $path);
        }
        return $handle;
    }

    /**
     * Process a line of the mysqldump output
     *
     * @param string                                     $line                The line to process
     * @param resource                                   $fileHandle          The file handle to write to
     * @param bool                                       $hasSeenFirstLine    Whether the first line has been seen
     * @param bool                                       $hadWriteError       Whether a write error has occurred
     * @param bool                                       $shouldRewriteTables Whether to rewrite table names
     * @param array<string,string>                       $tableRenameMap      Map of original table names to case-sensitive versions
     * @param array{search: string[], replace: string[]} $queryFixPatterns    Map of search patterns to replace patterns
     *
     * @return void
     */
    private function processLine(
        string $line,
        $fileHandle,
        bool &$hasSeenFirstLine,
        bool &$hadWriteError,
        bool $shouldRewriteTables,
        array $tableRenameMap,
        array $queryFixPatterns
    ): void {
        // Skip initial warnings on the very first line
        if (! $hasSeenFirstLine) {
            $hasSeenFirstLine = true;
            if ($this->isWarningLine($line)) {
                return;
            }
        }

        // Optionally rename tables
        if ($shouldRewriteTables) {
            $line = $this->applyTableRenames($line, $tableRenameMap);
        }

        // Apply generic query fixes
        $line = preg_replace(
            $queryFixPatterns['search'],
            $queryFixPatterns['replace'],
            $line
        );

        // Write the line out
        if (fwrite($fileHandle, $line) === false) {
            DupLog::error('fwrite failed', $this->dbStorePathPublic);
            $hadWriteError = true;
        }
    }

    /**
     * Detect and skip first-line warnings
     *
     * @param string $line The line to check
     *
     * @return bool True if the line is a warning, false otherwise
     */
    private function isWarningLine(string $line): bool
    {
        return stripos($line, 'Using a password on the command line interface can be insecure') !== false
            || stripos($line, 'WARNING: Forcing protocol to') !== false;
    }

    /**
     * Apply case-sensitive table rename transformations on a line
     *
     * @param string               $line           The line of SQL to process
     * @param array<string,string> $tableRenameMap Map of original table names to case-sensitive versions
     *
     * @return string Processed line with table names replaced
     */
    private function applyTableRenames(string $line, array $tableRenameMap): string
    {
        foreach (
            [
                '/^(\\s*CREATE TABLE `)([^`]+)(`)/',
                '/^(\\s*(?:INSERT\\s+(?:IGNORE\\s+)?INTO `))([^`]+)(`)/',
                '/^(LOCK TABLES `)([^`]+)(`)/',
            ] as $pattern
        ) {
            if (preg_match($pattern, $line, $matches)) {
                [,
                    $prefix,
                    $table,
                    $suffix,
                ] = $matches;
                if (isset($tableRenameMap[$table])) {
                    $line = $prefix . $tableRenameMap[$table] . $suffix . substr($line, strlen($matches[0]));
                }
                break;
            }
        }
        return $line;
    }

    /**
     * @param int $mysqlResult The result of the mysql dump
     *
     * @return bool returns true if the result was valid
     */
    private function mysqlDumpEvaluateResult(int $mysqlResult): bool
    {
        if ($mysqlResult !== 0) {
            /**
             * -1 error command shell
             * mysqldump return
             * 0 - Success
             * 1 - Warning
             * 2 - Exception
             */
            DupLog::infoTrace('MYSQL DUMP ERROR ' . print_r($mysqlResult, true));
            DupLog::error(
                __('Shell mysql dump failed. Last 10 lines of dump file below.', 'duplicator-pro'),
                implode(
                    "\n",
                    SnapIO::getLastLinesOfFile(
                        $this->dbStorePathPublic,
                        DUPLICATOR_DB_MYSQLDUMP_ERROR_CONTAINING_LINE_COUNT,
                        DUPLICATOR_DB_MYSQLDUMP_ERROR_CHARS_IN_LINE_COUNT
                    )
                )
            );
            $this->setError(
                __('Shell mysql dump error. Take a look at the Backup log for details.', 'duplicator-pro'),
                __('Change SQL engine to PHP', 'duplicator-pro'),
                [
                    'global' => ['package_mysqldump' => 0],
                ]
            );
            return false;
        }
        DupLog::trace("Operation was successful");
        return true;
    }

    /**
     * Checks if database size is within the mysqldump size limit
     *
     * @param int $dbSize Size of the database to check
     *
     * @return bool Returns true if DB size is within the mysqldump size limit, otherwise false
     */
    protected static function mysqldumpMemoryCheck(int $dbSize): bool
    {
        $mem        = SnapUtil::phpIniGet('memory_limit', false);
        $memInBytes = SnapUtil::convertToBytes($mem);

        // If the memory limit is unknown or unlimited (-1), return true
        if ($mem === false || $memInBytes <= 0) {
            return true;
        }

        return (self::requiredMysqlDumpLimit($dbSize) <= $memInBytes);
    }

    /**
     * Return mysql required limit
     *
     * @param int $dbSize Size of the database to check
     *
     * @return int
     */
    protected static function requiredMysqlDumpLimit(int $dbSize): int
    {
        return $dbSize + self::MYSQLDUMP_ALLOWED_SIZE_DIFFERENCE;
    }

    /**
     * Get mysql dump command
     *
     * @param string   $exePath           mysqldump exec path
     * @param string[] $extraFlags        extra mysqldump flags
     * @param string   $onlyTalbe         if set dump only selected table
     * @param string[] $filtered          filtered tables
     * @param string[] $ignoreOptionFlags command option flag not to be added
     *
     * @return string
     */
    private function getMysqlDumpCmd(
        string $exePath,
        array $extraFlags = [],
        string $onlyTalbe = '',
        array $filtered = [],
        array $ignoreOptionFlags = []
    ): string {
        $global     = GlobalEntity::getInstance();
        $parsedHost = SnapURL::parseUrl(DB_HOST);
        $port       = $parsedHost['port'];
        $host       = $parsedHost['host'];

        $extraFlags = array_map(fn($val): ?string => preg_replace('/(--)(.+)/', '$2', $val), $extraFlags);

        $ignoreOptionFlags = array_map(fn($val): ?string => preg_replace('/(--)(.+)/', '$2', $val), $ignoreOptionFlags);

        $mysqlcompat_on = (strlen($this->Compatible) > 0);
        //Build command

        $cmd  = escapeshellarg($exePath);
        $cmd .= ' --no-create-db';
        $cmd .= ' --single-transaction';
        $cmd .= ' --hex-blob';
        $cmd .= ' --skip-add-drop-table';
        $cmd .= ' --quote-names';
        $cmd .= ' --skip-comments';
        $cmd .= ' --skip-set-charset';
        $cmd .= ' --allow-keywords';
        $cmd .= ' --net_buffer_length=' . SnapUtil::getIntBetween(
            $global->package_mysqldump_qrylimit,
            Constants::MYSQL_DUMP_CHUNK_SIZE_MIN_LIMIT,
            Constants::MYSQL_DUMP_CHUNK_SIZE_MAX_LIMIT
        );
        $cmd .= ' --no-tablespaces';

        /** @var GroupOptions[] */
        $dumpOptions = [];
        foreach ($global->getMysqldumpOptions() as $option) {
            $dumpOptions[] = clone $option;
        }

        foreach ($extraFlags as $flag) {
            if (GroupOptions::optionExists($dumpOptions, $flag) !== false) {
                continue;
            }
            $dumpOptions[] = new GroupOptions($flag, GlobalEntity::INPUT_MYSQLDUMP_OPTION_PREFIX, true);
        }

        foreach ($ignoreOptionFlags as $flag) {
            if (($index = GroupOptions::optionExists($dumpOptions, $flag)) === false) {
                continue;
            }
            $dumpOptions[$index]->disable();
        }

        $extraOptions = GroupOptions::getShellOptions($dumpOptions);

        if (strlen($extraOptions)) {
            $cmd .= ' ' . $extraOptions;
        }

        //Compatibility mode
        if ($mysqlcompat_on) {
            DupLog::info("COMPATIBLE: [{$this->Compatible}]");
            $cmd .= " --compatible={$this->Compatible}";
        }

        // get excluded table list
        foreach ($filtered as $table) {
            $cmd .= " --ignore-table=" . DB_NAME . "." . $table . " ";
        }

        $cmd .= ' -u ' . escapeshellarg(DB_USER);
        $cmd .= (DB_PASSWORD) ? ' -p' . Shell::escapeshellargWindowsSupport(DB_PASSWORD) : ''; // @phpstan-ignore-line
        $cmd .= ' -h ' . escapeshellarg($host);
        $cmd .= (!empty($port) && is_numeric($port)) ? ' -P ' . $port : '';
        $cmd .= ' ' . escapeshellarg(DB_NAME);
        if (strlen($onlyTalbe) > 0) {
            $cmd .= ' ' . escapeshellarg($onlyTalbe);
        }

        return $cmd . ' 2>&1';
    }

    /**
     * return a tables list.
     * If $getExcludedTables is false return the included tables list else return the filtered table list
     *
     * @param bool $getExcludedTables if true return the excluded tables list
     *
     * @return string[]
     */
    private function getFilteredTables(bool $getExcludedTables = false): array
    {
        $result = [];
        // ALL TABLES
        $allTables = $this->getBaseTables(true);
        // MANUAL FILTER TABLE
        $filterTables = ($this->FilterOn ? explode(',', $this->FilterTables) : []);
        // SUB SITE FILTER TABLE
        $muFilterTables = $this->Package->Multisite->getTablesToFilter();
        // STAGING FILTER TABLES
        $stagingFilterTables = $this->getStagingTablesToFilter($allTables);
        //COMPONENT FILTER TABLE
        $componentFilterTables = BuildComponents::isDBExcluded($this->Package->components) ? $allTables : [];
        // TOTAL FILTER TABLES
        $allFilterTables = !empty($componentFilterTables)
            ? $componentFilterTables
            : array_unique(array_merge($filterTables, $muFilterTables, $stagingFilterTables));
        $allTablesCount  = count($allTables);
        $allFilterCount  = count($allFilterTables);
        $createCount     = $allTablesCount - $allFilterCount;
        DupLog::infoTrace("TABLES: total: " . $allTablesCount . " | filtered:" . $allFilterCount . " | create:" . $createCount);
        if (!empty($filterTables)) {
            DupLog::infoTrace("MANUAL FILTER TABLES: \n\t" . implode("\n\t", $filterTables));
        }
        if (!empty($muFilterTables)) {
            DupLog::infoTrace("MU SITE FILTER TABLES: \n\t" . implode("\n\t", $muFilterTables));
        }
        if (!empty($stagingFilterTables)) {
            DupLog::infoTrace("STAGING FILTER TABLES: \n\t" . implode("\n\t", $stagingFilterTables));
        }

        if ($getExcludedTables) {
            $result = $allFilterTables;
        } else {
            if (empty($allFilterTables)) {
                $result = $allTables;
            } else {
                foreach ($allTables as $val) {
                    if (!in_array($val, $allFilterTables)) {
                        $result[] = $val;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Get staging tables to filter from backup
     *
     * Staging tables follow the pattern: dstg{id}_{prefix}{table}
     * For example: dstg1_wp_posts, dstg2_wp_options
     *
     * @param string[] $allTables All database tables
     *
     * @return string[]
     */
    private function getStagingTablesToFilter(array $allTables): array
    {
        $stagingTables = [];

        foreach ($allTables as $tableName) {
            // Match tables starting with dstg followed by digits and underscore
            if (preg_match('/^dstg\d+_/', $tableName)) {
                $stagingTables[] = $tableName;
            }
        }

        return $stagingTables;
    }

    /**
     * Callback called in the insert iterator at the beginning of the current table dump.
     *
     * @param DbBuildIterator $iterator The iterator
     *
     * @return void
     */
    public function startTableIteratorCallback(DbBuildIterator $iterator): void
    {
        $this->Package->db_build_progress->tableCountStart($iterator->current());
    }

    /**
     * Callback called in the insert iterator at the end of the current table dump.
     *
     * @param DbBuildIterator $iterator The iterator
     *
     * @return void
     */
    public function endTableIteratorCallback(DbBuildIterator $iterator): void
    {
        $this->Package->db_build_progress->tableCountEnd($iterator->current(), $iterator->getCurrentOffset());
    }

    /**
     * Creates the database.sql script using PHP code
     *
     * @return void
     */
    private function runPHPDump(): void
    {
        DupLog::trace("RUN PHP DUMP");

        /** @var wpdb $wpdb */
        global $wpdb;

        $global = GlobalEntity::getInstance();
        $dbConn = WpDbUtils::getDbConn();
        $query  = $wpdb->prepare("SET session wait_timeout = %d", DUPLICATOR_DB_MAX_TIME);
        $wpdb->query($query);
        $this->doFiltering();
        $this->writeCreates();
        $handle           = @fopen($this->dbStorePathPublic, 'a');
        $dbInsertIterator = $this->getDbBuildIterator();

        //BUILD INSERTS:
        for (; $dbInsertIterator->valid(); $dbInsertIterator->next()) {
            if ($dbInsertIterator->getCurrentRows() <= 0) {
                continue;
            }

            $table = $dbInsertIterator->current();
            $dbInsertIterator->addFileSize(SnapIO::fwrite($handle, "\n/* INSERT TABLE DATA: {$table} */\n"));
            $row_offset       = 0;
            $currentQuerySize = 0;
            $firstInsert      = true;
            $insertQueryLine  = true;

            do {
                $result = SnapDB::selectUsingPrimaryKeyAsOffset(
                    $dbConn,
                    'SELECT * FROM `' . $table . '` WHERE 1',
                    $table,
                    $row_offset,
                    Constants::PHP_DUMP_READ_PAGE_SIZE,
                    $row_offset
                );
                if (($lastSelectNumRows = SnapDB::numRows($result)) > 0) {
                    while (($row = SnapDB::fetchAssoc($result))) {
                        if ($currentQuerySize >= $global->package_mysqldump_qrylimit) {
                            $insertQueryLine = true;
                        }

                        if ($insertQueryLine) {
                            $line             = ($firstInsert ? '' : self::CLOSE_INSERT_QUERY) . 'INSERT IGNORE INTO `' . $table . '` VALUES ' . "\n";
                            $insertQueryLine  = $firstInsert      = false;
                            $currentQuerySize = 0;
                        } else {
                            $line = ",\n";
                        }
                        $line             .= '(' . implode(',', array_map([WpDbUtils::class, 'escSqlAndQuote'], $row)) . ')';
                        $lineSize          = SnapIO::fwriteChunked($handle, $line);
                        $totalCount        = $dbInsertIterator->nextRow(0, $lineSize);
                        $currentQuerySize += $lineSize;
                        if (0 == ($totalCount % self::ROWS_NUM_TO_UPDATE_PROGRESS)) {
                            $this->setProgressPer($totalCount);
                        }
                    }

                    if ($this->throttleDelayInUs > 0) {
                        usleep($this->throttleDelayInUs * Constants::PHP_DUMP_READ_PAGE_SIZE);
                    }
                } elseif ($insertQueryLine == false) {
                    // if false exists a insert to close
                    $dbInsertIterator->addFileSize(SnapIO::fwrite($handle, self::CLOSE_INSERT_QUERY));
                }

                SnapDB::freeResult($result);
            } while ($lastSelectNumRows > 0);
        }

        $this->writeSQLFooter($handle);
        $wpdb->flush();
        SnapIO::fclose($handle);
    }

    /**
     * Initialize the build iterator, based on the phpdumpmode, the storeprogress file is used or not.
     *
     * @return DbBuildIterator
     */
    private function getDbBuildIterator(): DbBuildIterator
    {
        static $iterator = null;

        if (is_null($iterator)) {
            $iterator = new DbBuildIterator(
                $this->Package->db_build_progress->tablesToProcess,
                (WpDbUtils::getBuildMode() === WpDbUtils::BUILD_MODE_PHP_MULTI_THREAD ? $this->getStoreProgressFile() : null),
                [
                    $this,
                    'startTableIteratorCallback',
                ],
                [
                    $this,
                    'endTableIteratorCallback',
                ]
            );
        }
        return $iterator;
    }

    /**
     * Set error and quick fix
     *
     * @param string        $message  The error message
     * @param string        $fix      The fix message
     * @param false|mixed[] $quickFix The quick fix
     *
     * @return void
     */
    private function setError(string $message, string $fix, $quickFix = false): void
    {
        DupLog::trace($message);
        $this->Package->build_progress->failed = true;
        DupLog::trace('Database: buildInChunks Failed');
        $this->Package->update();
        DupLog::error("**RECOMMENDATION:  $fix.", $message);
        $system_global = SystemGlobalEntity::getInstance();
        if ($quickFix === false) {
            $system_global->addTextFix($message, $fix);
        } else {
            $system_global->addQuickFix($message, $fix, $quickFix);
        }
    }

    /**
     * Uses PHP to build the SQL file in chunks over multiple http requests
     *
     * @return void
     */
    public function buildInChunks(): void
    {
        DupLog::trace("Database: buildInChunks Start");
        if ($this->Package->db_build_progress->wasInterrupted) {
            $this->Package->db_build_progress->failureCount++;
            $log_msg = 'Database: buildInChunks failure count increased to  ' . $this->Package->db_build_progress->failureCount;
            DupLog::trace($log_msg);
            SnapUtil::errorLog($log_msg);
        }

        if (
            $this->Package->db_build_progress->errorOut ||
            $this->Package->db_build_progress->failureCount > DUPLICATOR_SQL_SCRIPT_PHP_CODE_MULTI_THREADED_MAX_RETRIES
        ) {
            $this->Package->build_progress->failed = true;
            DupLog::trace('Database: buildInChunks Failed');
            $this->Package->update();
            return;
        }

        $this->Package->db_build_progress->wasInterrupted = true;
        $this->Package->update();
        //TODO: See where else it needs to directly error out
        if (!$this->Package->db_build_progress->doneInit) {
            DupLog::trace("Database: buildInChunks Init");
            $this->doInit();
            $this->Package->db_build_progress->doneInit = true;
        } elseif (!$this->Package->db_build_progress->doneFiltering) {
            DupLog::trace("Database: buildInChunks Filtering");
            $this->doFiltering();
            $this->Package->db_build_progress->doneFiltering = true;
        } elseif (!$this->Package->db_build_progress->doneCreates) {
            DupLog::trace("Database: buildInChunks WriteCreates");
            $this->writeCreates();
            $this->Package->db_build_progress->doneCreates = true;
        } elseif (!$this->Package->db_build_progress->completed) {
            DupLog::trace("Database: buildInChunks WriteInsertChunk");
            $this->writeInsertChunk();
        }

        $this->Package->build_progress->database_script_built = false;
        if ($this->Package->db_build_progress->completed) {
            if (!$this->Package->db_build_progress->validationStage1) {
                $this->validateStage1();
            } else {
                DupLog::trace("Database: buildInChunks completed");
                $this->Package->build_progress->database_script_built = true;
                $this->doFinish();
            }
        }

        DupLog::trace("Database: buildInChunks End");
        // Resetting failure count since we if it recovers after a single failure we won't count it against it.
        $this->Package->db_build_progress->failureCount   = 0;
        $this->Package->db_build_progress->wasInterrupted = false;
        $this->Package->update();
    }

    /**
     * Performs validation of the values entered based on build progress counts
     *
     * @return void
     */
    protected function validateStage1(): void
    {
        DupLog::trace("DB VALIDATION 1");
        $isValid = true;
        // SEARCH END MARKER
        $lastLines = SnapIO::tailFile($this->dbStorePathPublic, 3);
        if (strpos($lastLines, (string) DUPLICATOR_DB_EOF_MARKER) === false) {
            DupLog::infoTrace('DB VALIDATION 1: can\'t find SQL EOR MARKER in sql file');
            $isValid = false;
        }

        foreach ($this->Package->db_build_progress->countCheckData['tables'] as $table => $tableInfo) {
            if ($tableInfo['create'] === false) {
                DupLog::infoTrace("DB VALIDATION STAGE 1 FAILED: CREATE query for the table {$table} does not exist");
                $isValid = false;
            }

            $skipValidation = in_array($table, self::getTablesFilteredFromValidation());
            $minVal         = min($tableInfo['start'], $tableInfo['end']);
            $maxVal         = max($tableInfo['start'], $tableInfo['end']);
            $delta          = $maxVal - $minVal;
            // The rows entered must be between the start value of the dump on the table and the end value.
            // The more difference there is between the initial and final count (delta), the less accurate the validation is.
            if (
                $skipValidation == false &&
                (
                    $tableInfo['count'] < ($minVal - $delta) ||
                    $tableInfo['count'] > ($maxVal + $delta)
                )
            ) {
                DupLog::infoTrace(
                    'DB VALIDATION FAIL: count check table "' . $table . '"' .
                        ' START: ' . $tableInfo['start'] .
                        ' END: ' . $tableInfo['end'] .
                        ' DELTA: ' . $delta .
                        ' COUNT: ' . $tableInfo['count']
                );
                $isValid = false;
            } else {
                $this->info->addInsertedRowsInTableList($table, $tableInfo['count']);
                $message = 'DB VALIDATION ' . ($skipValidation ? 'SKIPPED FROM WP-CONFIG' : 'SUCCESS') . ': ';
                DupLog::trace(
                    $message . 'count check table "' . $table . '"' .
                        ' START: ' . $tableInfo['start'] .
                        ' END: ' . $tableInfo['end'] .
                        ' DELTA: ' . $delta .
                        ' COUNT: ' . $tableInfo['count']
                );
            }
        }

        $dbInsertIterator = $this->getDbBuildIterator();
        clearstatcache();
        if (filesize($this->dbStorePathPublic) !== $dbInsertIterator->getFileSize()) {
            DupLog::infoTrace(
                'SQL FILE SIZE CHECK FAILED, EXPECTED: ' . $dbInsertIterator->getFileSize() .
                    ' FILE SIZE: ' . filesize($this->dbStorePathPublic) . ' OF FILE ' . $this->dbStorePathPublic
            );
            $isValid = false;
        } else {
            DupLog::infoTrace('SQL FILE SIZE CHECK OK, SIZE: ' . $dbInsertIterator->getFileSize());
        }

        $dbInsertIterator->removeCounterFile();
        if ($isValid) {
            DupLog::trace("DB VALIDATION 1: successful");
            $this->Package->db_build_progress->validationStage1 = true;
            $this->Package->update();
        } else {
            DupLog::infoTrace("DB VALIDATION 1: failed to validate");
            throw new Exception("DB VALIDATION 1: failed to validate");
        }
    }

    /**
     * Returns an array of table names that have been filtered from validation via constant
     *
     * @return string[]
     */
    private static function getTablesFilteredFromValidation(): array
    {
        static $tableList = null;
        if (is_null($tableList)) {
            $tableList = [];
            if (!is_array(DUPLICATOR_TABLE_VALIDATION_FILTER_LIST)) { // @phpstan-ignore-line function.alreadyNarrowedType
                $list = (strlen((string) DUPLICATOR_TABLE_VALIDATION_FILTER_LIST) > 0 ? [DUPLICATOR_TABLE_VALIDATION_FILTER_LIST] : []);
            } else {
                $list = DUPLICATOR_TABLE_VALIDATION_FILTER_LIST;
            }
            foreach ($list as $table) {
                $table = trim($table);
                if (strlen($table) == 0) {
                    continue;
                }
                $tableList[] = $table;
            }
        }
        return $tableList;
    }

    /**
     * Used to initialize the PHP chunking logic
     *
     * @return void
     */
    private function doInit(): void
    {
        $global = GlobalEntity::getInstance();
        do_action('duplicator_build_database_before_start', $this->Package);
        $this->Package->db_build_progress->startTime = microtime(true);
        $this->Package->setStatus(AbstractPackage::STATUS_DBSTART);
        $this->dbStorePathPublic = "{$this->Package->StorePath}/{$this->File}";
        $log                     = "\n********************************************************************************\n";
        $log                    .= "DATABASE:\n";
        $log                    .= "********************************************************************************\n";
        $log                    .= "BUILD MODE:   PHP + CHUNKING ";
        $log                    .= "(query size limit - {$global->package_mysqldump_qrylimit} )\n";
        DupLog::info($log);
        do_action('duplicator_build_database_start', $this->Package);
        $this->Package->update();
    }

    /**
     * Initialize the table to be processed for the dump.
     *
     * @return void
     */
    private function doFiltering(): void
    {
        /** @var wpdb */
        global $wpdb;
        $query = $wpdb->prepare("SET session wait_timeout = %d", DUPLICATOR_DB_MAX_TIME);
        $wpdb->query($query);

        $tables          = $this->getFilteredTables();
        $tablesToProcess = array_map([WpDbUtils::class, 'updateCaseSensitivePrefix'], $tables);

        // PUT TABLES ON TOP, the ored is important
        $tablesOnTop = [
            $wpdb->prefix . 'users',
            $wpdb->prefix . 'usermeta',
        ];

        foreach (array_reverse($tablesOnTop) as $tableOnTop) {
            if (($index = array_search($tableOnTop, $tablesToProcess)) !== false) {
                unset($tablesToProcess[$index]);
                array_unshift($tablesToProcess, $tableOnTop);
            }
        }
        $this->Package->db_build_progress->tablesToProcess = array_values($tablesToProcess);

        $this->Package->db_build_progress->countCheckSetStart();
        $this->Package->db_build_progress->doneFiltering = true;
        $this->Package->update();
        // MAKE SURE THE ITERATOR IS RESET
        $dbInsertIterator = $this->getDbBuildIterator();
        $dbInsertIterator->rewind();
    }

    /**
     * Dumps the structure of the view table and procedures.
     *
     * @return void
     */
    private function writeCreates(): void
    {
        global $wpdb;
        $handle = @fopen($this->dbStorePathPublic, 'a');
        // Added 'NO_AUTO_VALUE_ON_ZERO' at plugin version 3.4.8 to fix :
        //**ERROR** database error write 'Invalid default value for for older mysql versions
        $sql_header  = "/* DUPLICATOR-PRO (";
        $sql_header .= (
            WpDbUtils::getBuildMode() === WpDbUtils::BUILD_MODE_PHP_MULTI_THREAD ?
            'PHP MULTI-THREADED BUILD MODE' :
            'PHP SINGLE-THREAD BUILD MODE'
        );
        $sql_header .= ") MYSQL SCRIPT CREATED ON : " . date("Y-m-d H:i:s") . " */\n\n";
        $sql_header .= "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n";
        $sql_header .= "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n";
        $sql_header .= "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;\n\n";
        SnapIO::fwrite($handle, $sql_header);
        // BUILD CREATES:
        // All creates must be created before inserts do to foreign key constraints
        foreach ($this->Package->db_build_progress->tablesToProcess as $table) {
            if (($create = $wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N)) === null) {
                throw new Exception("DB ERROR: Could not get the CREATE query for the table {$table}. " . $wpdb->last_error);
            }

            // UPDATE CASE SENSITIVE TABLE PREFIX NAME
            $create_table_query = str_ireplace($table, $table, $create[1]);
            $create_table_query = preg_replace('/^(\s*CREATE\s+TABLE\s+(?!IF NOT EXISTS))(`.+?`)/m', '$1IF NOT EXISTS $2', $create_table_query);
            if (SnapIO::fwrite($handle, "{$create_table_query};\n\n") > 0) {
                $this->Package->db_build_progress->countCheckData['tables'][$table]['create'] = true;
                DupLog::trace("DATABASE CREATE TABLE: " . $table . " OK");
            }
        }

        if (!BuildComponents::isDBExcluded($this->Package->components)) {
            $query      = $wpdb->prepare("SHOW PROCEDURE STATUS WHERE `Db` = %s", $wpdb->dbname);
            $procedures = $wpdb->get_col($query, 1);
            if (count($procedures)) {
                foreach ($procedures as $procedure) {
                    SnapIO::fwrite($handle, "DELIMITER ;;\n");
                    $create = $wpdb->get_row("SHOW CREATE PROCEDURE `{$procedure}`", ARRAY_N);
                    SnapIO::fwrite($handle, "{$create[2]} ;;\n");
                    SnapIO::fwrite($handle, "DELIMITER ;\n\n");
                }
            }

            $query     = $wpdb->prepare("SHOW FUNCTION STATUS WHERE `Db` = %s", $wpdb->dbname);
            $functions = $wpdb->get_col($query, 1);
            if (count($functions)) {
                foreach ($functions as $function) {
                    SnapIO::fwrite($handle, "DELIMITER ;;\n");
                    $create = $wpdb->get_row("SHOW CREATE FUNCTION `{$function}`", ARRAY_N);
                    SnapIO::fwrite($handle, "{$create[2]} ;;\n");
                    SnapIO::fwrite($handle, "DELIMITER ;\n\n");
                }
            }

            $views = $wpdb->get_col("SHOW FULL TABLES WHERE Table_Type = 'VIEW'");
            if (count($views)) {
                foreach ($views as $view) {
                    $create = $wpdb->get_row("SHOW CREATE VIEW `{$view}`", ARRAY_N);
                    SnapIO::fwrite($handle, "{$create[1]};\n\n");
                }
            }
        }

        SnapIO::fwrite($handle, self::TABLE_CREATION_END_MARKER);
        $dbInsertIterator = $this->getDbBuildIterator();
        $fileStat         = fstat($handle);
        $dbInsertIterator->addFileSize($fileStat['size']);
        SnapIO::fclose($handle);
        $this->Package->db_build_progress->errorOut    = true;
        $this->Package->db_build_progress->doneCreates = true;
        $this->Package->update();
        $this->Package->db_build_progress->errorOut = false;
    }

    /**
     *
     * @global wpdb $wpdb
     * @return void
     *
     * @throws Exception
     */
    private function writeInsertChunk(): void
    {
        $dbConn           = WpDbUtils::getDbConn();
        $startTime        = microtime(true);
        $elapsedTime      = 0;
        $totalCount       = 0;
        $global           = GlobalEntity::getInstance();
        $dbInsertIterator = $this->getDbBuildIterator();
        $dbBuildProgress  = $this->Package->db_build_progress;
        $this->truncateSqlFileOnExpectedSize($dbInsertIterator->getFileSize());
        if (($handle = fopen($this->dbStorePathPublic, 'a')) === false) {
            $msg = print_r(error_get_last(), true);
            throw new Exception("FILE READ ERROR: Could not open file {$this->dbStorePathPublic} {$msg}");
        }

        if (!$dbInsertIterator->lastIsCompleteInsert()) {
            $dbInsertIterator->setLastIsCompleteInsert(SnapIO::fwrite($handle, self::CLOSE_INSERT_QUERY));
        }

        $traceLogEnabled = DupLog::isTraceEnabled();

        for (; $dbInsertIterator->valid(); $dbInsertIterator->next()) {
            $table        = $dbInsertIterator->current();
            $indexColumns = SnapDB::getUniqueIndexColumn($dbConn, $table);
            if ($traceLogEnabled) {
                $table_number = $dbInsertIterator->key() + 1;
                DupLog::trace("------------ DB SCAN CHUNK LOOP ------------");
                DupLog::trace("table: " . $table . " (" . $table_number . " of " . $dbInsertIterator->count() . ")");
                DupLog::trace("worker_time: " . $elapsedTime . " Max worker time: " . self::PHP_DUMP_CHUNK_WORKER_TIME);
                DupLog::trace("row_offset: " . $dbInsertIterator->getCurrentOffset() . " of " . $dbInsertIterator->getCurrentRows());
                if ($indexColumns === false) {
                    DupLog::trace("no key column found, use normal offset ");
                } else {
                    DupLog::trace("primary column for offset system: " . SnapLog::v2str($indexColumns));
                }
                DupLog::trace("last_index_offset: " . SnapLog::v2str($dbInsertIterator->getLastIndexOffset()));
                DupLog::trace("query size limit: " . $global->package_mysqldump_qrylimit);
            }

            if ($dbInsertIterator->getCurrentRows() <= 0) {
                continue;
            }

            $currentQuerySize = 0;
            $firstInsert      = true;
            $insertQueryLine  = true;

            do {
                $result = SnapDB::selectUsingPrimaryKeyAsOffset(
                    $dbConn,
                    'SELECT * FROM `' . $table . '` WHERE 1',
                    $table,
                    $dbInsertIterator->getLastIndexOffset(),
                    Constants::PHP_DUMP_READ_PAGE_SIZE
                );
                if (($lastSelectNumRows = SnapDB::numRows($result)) > 0) {
                    while (($row = SnapDB::fetchAssoc($result))) {
                        if ($currentQuerySize >= $global->package_mysqldump_qrylimit) {
                            $insertQueryLine = true;
                        }

                        if ($insertQueryLine) {
                            $line             = ($firstInsert ? '' : self::CLOSE_INSERT_QUERY) . 'INSERT IGNORE INTO `' . $table . '` VALUES ' . "\n";
                            $insertQueryLine  = $firstInsert      = false;
                            $currentQuerySize = 0;
                        } else {
                            $line = ",\n";
                        }
                        $line    .= '(' . implode(',', array_map([WpDbUtils::class, 'escSqlAndQuote'], $row)) . ')';
                        $lineSize = SnapIO::fwriteChunked($handle, $line);
                        /* TEST INTERRUPTION START *** */
                        /* mt_srand((double) microtime() * 1000000);
                          if (mt_rand(1, 1000) > 997) {
                          die();
                          } */
                        /* TEST INTERRUPTION END *** */

                        $totalCount        = $dbInsertIterator->nextRow(
                            SnapDB::getOffsetFromRowAssoc(
                                $row,
                                $indexColumns,
                                $dbInsertIterator->getLastIndexOffset()
                            ),
                            $lineSize
                        );
                        $currentQuerySize += $lineSize;
                        if (0 == ($totalCount % self::ROWS_NUM_TO_UPDATE_PROGRESS)) {
                            $this->setProgressPer($totalCount);
                        }

                        if (($elapsedTime = microtime(true) - $startTime) >= self::PHP_DUMP_CHUNK_WORKER_TIME) {
                            break;
                        }
                    }

                    if ($this->throttleDelayInUs > 0) {
                        usleep($this->throttleDelayInUs * Constants::PHP_DUMP_READ_PAGE_SIZE);
                    }

                    if ($elapsedTime >= self::PHP_DUMP_CHUNK_WORKER_TIME) {
                        break 2;
                    }
                } elseif ($insertQueryLine == false) {
                    // if false exists a insert to close
                    $dbInsertIterator->setLastIsCompleteInsert(SnapIO::fwrite($handle, self::CLOSE_INSERT_QUERY));
                }

                SnapDB::freeResult($result);
            } while ($lastSelectNumRows > 0);
        }

        // make sure file is updated, wait 0.01 sec to prevent file corruption
        usleep(10000);
        $dbInsertIterator->addFileSize(0);
        if (($dbBuildProgress->completed = !$dbInsertIterator->valid())) {
            $this->writeSQLFooter($handle);
            $this->Package->update();
        } else {
            $this->setProgressPer($totalCount);
        }

        SnapIO::fclose($handle);
    }

    /**
     * Truncates the sql file to the expected size
     *
     * @param int $size The expected size
     *
     * @return boolean
     */
    private function truncateSqlFileOnExpectedSize($size): bool
    {
        clearstatcache();
        if (filesize($this->dbStorePathPublic) === $size) {
            return true;
        }

        $handle = @fopen($this->dbStorePathPublic, 'r+');
        if ($handle === false) {
            $msg = print_r(error_get_last(), true);
            throw new Exception("FILE READ ERROR: Could not open file {$this->dbStorePathPublic} {$msg}");
        }

        if (ftruncate($handle, $size)) {
            DupLog::trace("SQL FILE DON'T MATCH SIZE, TRUNCATE AT " . $size);
        } else {
            throw new Exception("FILE TRUNCATE ERROR: Could not truncate to file size " . $size);
        }
        SnapIO::fclose($handle);
        return true;
    }

    /**
     * Writes the footer of the SQL file
     *
     * @param resource $fileHandle The file handle
     *
     * @return void
     */
    private function writeSQLFooter($fileHandle): void
    {
        $sql_footer       = "\n/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;\n";
        $sql_footer      .= "/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;\n";
        $sql_footer      .= "/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;\n\n";
        $sql_footer      .= "/* Duplicator WordPress Timestamp: " . date("Y-m-d H:i:s") . "*/\n";
        $sql_footer      .= "/* " . DUPLICATOR_DB_EOF_MARKER . " */\n";
        $dbInsertIterator = $this->getDbBuildIterator();
        $dbInsertIterator->addFileSize(SnapIO::fwrite($fileHandle, $sql_footer));
    }

    /**
     * Sets the progress bar percentage
     *
     * @param int $offset The current offset
     *
     * @return void
     */
    private function setProgressPer(int $offset): void
    {
        $per = SnapUtil::getWorkPercent(
            AbstractPackage::STATUS_DBSTART,
            AbstractPackage::STATUS_DBDONE,
            $this->Package->db_build_progress->countCheckData['impreciseTotalRows'],
            $offset
        );
        $this->Package->setProgressPercent($per);
        $this->Package->update();
    }

    /**
     * Called when the build is complete
     *
     * @return void
     */
    private function doFinish(): void
    {
        DupLog::info("SQL CREATED: {$this->File}");
        $time_end      = microtime(true);
        $elapsed_time  = SnapString::formattedElapsedTime($time_end, $this->Package->db_build_progress->startTime);
        $sql_file_size = filesize($this->dbStorePathPublic);
        if ($sql_file_size <= 0) {
            DupLog::errorAndDie(
                "SQL file generated zero bytes.",
                "No data was written to the sql file.  Check permission on file and parent directory at [{$this->dbStorePathPublic}]"
            );
        }
        DupLog::info("SQL FILE SIZE: " . SnapString::byteSize($sql_file_size));
        DupLog::info("SQL FILE TIME: " . date("Y-m-d H:i:s"));
        DupLog::info("SQL RUNTIME: {$elapsed_time}");
        DupLog::info("MEMORY STACK: " . SnapServer::getPHPMemory());
        $this->Size = @filesize($this->dbStorePathPublic);
        $this->Package->setStatus(AbstractPackage::STATUS_DBDONE);
        do_action('duplicator_build_database_completed', $this->Package);
    }
}
