<?php

/**
 * Trait for package scan operations
 *
 * @package   Duplicator
 * @copyright (c) 2017, Snap Creek LLC
 */

declare(strict_types=1);

namespace Duplicator\Package;

use Duplicator\Addons\ProBase\License\License;
use Duplicator\Libs\Index\FileIndexManager;
use Duplicator\Libs\Snap\SnapOpenBasedir;
use Duplicator\Libs\Snap\SnapString;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Libs\WpUtils\WpArchiveUtils;
use Duplicator\Libs\WpUtils\WpUtilsMultisite;
use Duplicator\Models\GlobalEntity;
use Duplicator\Package\Archive\PackageArchive;
use Duplicator\Package\Create\BuildComponents;
use Duplicator\Package\Database\DatabasePkg;
use Duplicator\Utils\Logging\DupLog;
use Exception;
use VendorDuplicator\Amk\JsonSerialize\JsonSerialize;

/**
 * Trait TraitPackageScan
 *
 * Handles package scanning operations including scan report generation
 * and retrieval of scan data from JSON files.
 *
 * @phpstan-require-extends AbstractPackage
 *
 * @property string         $ScanFile   Scan file name
 * @property PackageArchive $Archive    Package archive object
 * @property DatabasePkg    $Database   Database package object
 * @property PackMultisite  $Multisite  Multisite configuration
 * @property string[]       $components Build components flags
 */
trait TraitPackageScan
{
    /**
     * Generates a scan report
     *
     * @return array<string,mixed> of scan results
     */
    public function createScanReport(): array
    {
        global $wpdb;
        $report = [];
        DupLog::trace('Scanning');
        try {
            $global = GlobalEntity::getInstance();
            do_action('duplicator_before_scan_report', $this);

            //Set tree filters
            $this->Archive->setTreeFilters();

            //Load scan data necessary for report
            $db                        = $this->Database->getScanData();
            $timerStart                = microtime(true);
            $this->ScanFile            = "{$this->getNameHash()}_scan.json";
            $report['RPT']['ScanTime'] = "0";
            $report['RPT']['ScanFile'] = $this->ScanFile;
            //FILES
            $scanPath              = DUPLICATOR_SSDIR_PATH_TMP . "/{$this->ScanFile}";
            $dirCount              = $this->Archive->DirCount;
            $fileCount             = $this->Archive->FileCount;
            $fullCount             = $dirCount + $fileCount;
            $unreadable            = array_merge($this->Archive->FilterInfo->Files->Unreadable, $this->Archive->FilterInfo->Dirs->Unreadable);
            $site_warning_size     = $global->archive_build_mode === PackageArchive::BUILD_MODE_ZIP_ARCHIVE ?
                DUPLICATOR_SCAN_SITE_ZIP_ARCHIVE_WARNING_SIZE : DUPLICATOR_SCAN_SITE_WARNING_SIZE;
            $filteredTables        = ($this->Database->FilterOn ? explode(',', $this->Database->FilterTables) : []);
            $subsites              = WpUtilsMultisite::getSubsites($this->Multisite->FilterSites, $filteredTables);
            $hasImportableSites    = SnapUtil::inArrayExtended($subsites, fn($subsite): bool => count($subsite->filteredTables) === 0);
            $hasNotImportableSites = SnapUtil::inArrayExtended($subsites, fn($subsite): bool => count($subsite->filteredTables) > 0);
            $hasFilteredSiteTables = $this->Database->info->tablesBaseCount !== $this->Database->info->tablesFinalCount;
            $pathsOutOpenbaseDir   = array_filter($this->Archive->FilterInfo->Dirs->Unknown, fn($path): bool => !SnapOpenBasedir::isPathValid($path));

            // Filtered subsites
            $filteredSites = [];
            if (is_multisite() && License::can(License::CAPABILITY_MULTISITE_PLUS)) {
                $filteredSites = array_map(
                    fn($siteId) => get_blog_details(['blog_id' => $siteId]),
                    $this->Multisite->FilterSites
                );
            }

            // Check if the user has the privileges to show the CREATE FUNCTION and CREATE PROCEDURE statements
            $privileges_to_show_create_func = true;
            $query                          = $wpdb->prepare("SHOW PROCEDURE STATUS WHERE `Db` = %s", $wpdb->dbname);
            $procedures                     = $wpdb->get_col($query, 1);
            if (count($procedures)) {
                $create                         = $wpdb->get_row("SHOW CREATE PROCEDURE `" . $procedures[0] . "`", ARRAY_N);
                $privileges_to_show_create_func = isset($create[2]);
            }

            $query     = $wpdb->prepare("SHOW FUNCTION STATUS WHERE `Db` = %s", $wpdb->dbname);
            $functions = $wpdb->get_col($query, 1);
            if (count($functions)) {
                $create                         = $wpdb->get_row("SHOW CREATE FUNCTION `" . $functions[0] . "`", ARRAY_N);
                $privileges_to_show_create_func = $privileges_to_show_create_func && isset($create[2]);
            }
            $privileges_to_show_create_func = apply_filters('duplicator_privileges_to_show_create_func', $privileges_to_show_create_func);

            //Add info to report to
            $report = [
                'Status' => 1,
                'ARC'    => [
                    'Size'                => SnapString::byteSize($this->Archive->Size),
                    'DirCount'            => number_format($dirCount),
                    'FileCount'           => number_format($fileCount),
                    'FullCount'           => number_format($fullCount),
                    'USize'               => $this->Archive->Size,
                    'UDirCount'           => $dirCount,
                    'UFileCount'          => $fileCount,
                    'UFullCount'          => $fullCount,
                    'UnreadableDirCount'  => $this->Archive->FilterInfo->Dirs->getUnreadableCount(),
                    'UnreadableFileCount' => $this->Archive->FilterInfo->Files->getUnreadableCount(),
                    'FilterDirsAll'       => $this->Archive->FilterDirsAll,
                    'FilterFilesAll'      => $this->Archive->FilterFilesAll,
                    'FilterExtsAll'       => $this->Archive->FilterExtsAll,
                    'FilteredCoreDirs'    => $this->Archive->filterWpCoreFoldersList(),
                    'RecursiveLinks'      => $this->Archive->RecursiveLinks,
                    'UnreadableItems'     => $unreadable,
                    'PathsOutOpenbaseDir' => $pathsOutOpenbaseDir,
                    'FilteredSites'       => $filteredSites,
                    'Subsites'            => $subsites,
                    'Status'              => [
                        'Size'                   => $this->Archive->Size <= $site_warning_size && $this->Archive->Size >= 0,
                        'Big'                    => count($this->Archive->FilterInfo->Files->Size) <= 0,
                        'AddonSites'             => count($this->Archive->FilterInfo->Dirs->AddonSites) <= 0,
                        'UnreadableItems'        => empty($this->Archive->RecursiveLinks) && empty($unreadable) && empty($pathsOutOpenbaseDir),
                        'showCreateFuncStatus'   => $privileges_to_show_create_func,
                        'showCreateFunc'         => $privileges_to_show_create_func,
                        'HasImportableSites'     => $hasImportableSites,
                        'HasNotImportableSites'  => $hasNotImportableSites,
                        'HasFilteredCoreFolders' => $this->Archive->hasWpCoreFolderFiltered(),
                        'HasFilteredSiteTables'  => $hasFilteredSiteTables,
                        'HasFilteredSites'       => !empty($filteredSites),
                        'IsDBOnly'               => $this->isDBOnly(),
                        'Network'                => !$hasNotImportableSites && empty($filteredSites),
                        'PackageIsNotImportable' => !(
                            (!$hasFilteredSiteTables || $hasImportableSites) &&
                            (!$hasNotImportableSites || License::can(License::CAPABILITY_MULTISITE_PLUS))
                        ),
                    ],
                ],
                'DB'     => [
                    'Status'         => $db['Status'],
                    'SizeInBytes'    => $db['Size'],
                    'Size'           => SnapString::byteSize($db['Size']),
                    'Rows'           => number_format($db['Rows']),
                    'TableCount'     => $db['TableCount'],
                    'TableList'      => $db['TableList'],
                    'FilteredTables' => ($this->Database->FilterOn ? explode(',', $this->Database->FilterTables) : []),
                    'DBExcluded'     => BuildComponents::isDBExcluded($this->components),
                ],
                'SRV'    => BuildRequirements::getChecks($this)['SRV'],
                'RPT'    => [
                    'ScanCreated' => @date("Y-m-d H:i:s"),
                    'ScanTime'    => SnapString::formattedElapsedTime(microtime(true), $timerStart),
                    'ScanPath'    => $scanPath,
                    'ScanFile'    => $this->ScanFile,
                ],
            ];

            if (($json = JsonSerialize::serialize($report, JSON_PRETTY_PRINT | JsonSerialize::JSON_SKIP_CLASS_NAME)) === false) {
                throw new Exception('Problem encoding json');
            }

            if (@file_put_contents($scanPath, $json) === false) {
                throw new Exception('Problem writing scan file');
            }

            //Safe to clear at this point only JSON
            //report stores the full directory and file lists
            $this->Archive->Dirs  = [];
            $this->Archive->Files = [];
            /**
             * don't save filter info in report scan json.
             */
            $report['ARC']['FilterInfo'] = $this->Archive->FilterInfo;
            DupLog::trace("TOTAL SCAN TIME = " . SnapString::formattedElapsedTime(microtime(true), $timerStart));
        } catch (Exception $ex) {
            DupLog::trace("SCAN ERROR: " . $ex->getMessage());
            DupLog::trace("SCAN ERROR: " . $ex->getTraceAsString());
            DupLog::errorAndDie("An error has occurred scanning the file system.", $ex->getMessage());
        }

        do_action('duplicator_after_scan_report', $this, $report);
        return $report;
    }

    /**
     * Adds file and dirs lists to scan report.
     *
     * @param string $json_path    string The path to the json file
     * @param bool   $includeLists Include the file and dir lists in the report
     *
     * @return mixed The scan report
     */
    public function getScanReportFromJson($json_path, $includeLists = false)
    {
        if (!file_exists($json_path)) {
            $message = sprintf(
                __(
                    "ERROR: Can't find Scanfile %s. Please ensure there no non-English characters in the Backup or schedule name.",
                    'duplicator-pro'
                ),
                $json_path
            );
            throw new Exception($message);
        }

        $json_contents = file_get_contents($json_path);

        $report = json_decode($json_contents);
        if ($report === null) {
            throw new Exception("Couldn't decode scan file.");
        }

        if ($includeLists) {
            $targetRootPath     = WpArchiveUtils::getTargetRootPath();
            $indexManager       = $this->Archive->getIndexManager();
            $report->ARC->Dirs  = $indexManager->getPathArray(FileIndexManager::LIST_TYPE_DIRS, $targetRootPath);
            $report->ARC->Files = $indexManager->getPathArray(FileIndexManager::LIST_TYPE_FILES, $targetRootPath);
        }

        return $report;
    }
}
