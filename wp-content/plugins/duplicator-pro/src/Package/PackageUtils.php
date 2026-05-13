<?php

namespace Duplicator\Package;

use DateTime;
use Duplicator\Models\GlobalEntity;
use Duplicator\Utils\Logging\DupLog;
use Duplicator\Package\DupPackage;
use Duplicator\Models\TemplateEntity;
use Duplicator\Core\Constants;
use Exception;
use Duplicator\Installer\Models\MigrateData;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapJson;
use Duplicator\Package\Archive\PackageArchive;
use Duplicator\Package\Recovery\RecoveryPackage;
use Duplicator\Models\DynamicGlobalEntity;
use Duplicator\Models\ActivityLog\LogUtils;

class PackageUtils
{
    const DEFAULT_BACKUP_TYPE     = 'Standard';
    const BULK_DELETE_LIMIT_CHUNK = 100;

    /**
     * Restise excecure packages registration
     *
     * @return void
     */
    public static function registerStandardPackageType(): void
    {
        DupPackage::registerType();
    }

    /**
     * Update CREATED AFTER INSTALL FLAGS
     *
     * @param MigrateData $migrationData migration data
     *
     * @return void
     */
    public static function updateCreatedAfterInstallFlags(MigrateData $migrationData): void
    {
        if ($migrationData->restoreBackupMode == false) {
            return;
        }

        // Refresh recovery Backup set beforw backup
        $ids = DupPackage::dbSelect('FIND_IN_SET(\'' . DupPackage::FLAG_DISASTER_SET . '\', `flags`)', 0, 0, '', 'ids');
        if (count($ids)) {
            RecoveryPackage::setRecoveablePackage($ids[0]);
        }

        // Update all backups with created after restore flag or created after install time
        DupPackage::dbSelectCallback(
            function (DupPackage $package): void {
                $package->updateMigrateAfterInstallFlag();
                $package->save();
            },
            'FIND_IN_SET(\'' . DupPackage::FLAG_CREATED_AFTER_RESTORE . '\', `flags`) OR
            (
                `id` > ' .  $migrationData->packageId . ' AND
                `created` < \'' . esc_sql($migrationData->installTime) . '\'
            )'
        );
    }

    /**
     * Get the number of Backups
     *
     * @param string[]                                             $backupTypes      backup types to include, is empty all types are included
     * @param array<string|int,string|array{op:string,status:int}> $statusConditions status filter conditions, if empty all statuses are included
     *
     * @return int
     */
    public static function getNumPackages(array $backupTypes = [], array $statusConditions = []): int
    {
        $ids = DupPackage::getIdsByStatus(
            $statusConditions,
            0,
            0,
            '',
            $backupTypes
        );
        return count($ids);
    }

    /**
     * Get the number of complete Backups
     *
     * @param string[] $backupTypes backup types to include, is empty all types are included
     *
     * @return int
     */
    public static function getNumCompletePackages(array $backupTypes = []): int
    {
        $ids = DupPackage::getIdsByStatus(
            [
                [
                    'op'     => '>=',
                    'status' => AbstractPackage::STATUS_COMPLETE,
                ],
            ],
            0,
            0,
            '',
            $backupTypes
        );
        return count($ids);
    }

    /**
     * Get packages without storages
     *
     * @param int $limit Limit the number of packages to return, if 0 no limit is applied
     *
     * @return int[]
     */
    public static function getPackageWithoutStorages(int $limit = 0): array
    {
        $where = '(`status` = ' . AbstractPackage::STATUS_COMPLETE . ' OR `status` < ' . AbstractPackage::STATUS_PRE_PROCESS . ')' .
            ' AND FIND_IN_SET(\'' . DupPackage::FLAG_HAVE_LOCAL . '\', `flags`) = 0' .
            ' AND FIND_IN_SET(\'' . DupPackage::FLAG_HAVE_REMOTE . '\', `flags`) = 0';
        return DupPackage::dbSelect(
            $where,
            $limit,
            0,
            '',
            'ids',
            [DupPackage::getBackupType()],
            false,
            true   // Allow querying packages without storage
        );
    }

    /**
     * Massive delete packages without storages using direct SQL query
     *
     * @param int $limit Limit the number of packages to return, if 0 no limit is applied
     *
     * @return int Number of packages deleted
     */
    public static function bulkDeletePackageWithoutStorages(int $limit = 0): int
    {
        // In that case we can use direct SQL query because the backup don't have storages,so we don't need remove local files
        global $wpdb;

        $table = DupPackage::getTableName();

        $ids   = self::getPackageWithoutStorages($limit);
        $count = count($ids);

        if ($count == 0) {
            return 0;
        }

        $idList = implode(',', array_map('intval', $ids));

        $query  = "DELETE FROM `{$table}` WHERE id IN ({$idList})";
        $result = $wpdb->query($query);

        if ($result === false) {
            throw new Exception("Error deleting packages without storages: " . $wpdb->last_error);
        }

        return (int) $result;
    }

    /**
     * Delete packages without storages in chunks
     *
     * @return int Number of packages deleted in this chunk, -1 if error
     */
    public static function bulkDeletePackageWithoutStoragesChunk(): int
    {
        try {
            return self::bulkDeletePackageWithoutStorages(self::BULK_DELETE_LIMIT_CHUNK);
        } catch (Exception $e) {
            DupLog::trace("Error in bulkDeletePackageWithoutStoragesChunk: " . $e->getMessage());
            return -1;
        }
    }

    /**
     * Creates a default name
     *
     * @param bool $preDate if true prepend date to name
     *
     * @return string Default Backup name
     */
    public static function getDefaultPackageName(bool $preDate = true): string
    {
        //Remove specail_chars from final result
        $special_chars = [
            ".",
            "-",
        ];
        $name          = ($preDate) ?
            date('Ymd') . '_' . sanitize_title(get_bloginfo('name', 'display')) :
            sanitize_title(get_bloginfo('name', 'display')) . '_' . date('Ymd');
        $name          = substr(sanitize_file_name($name), 0, 40);
        return str_replace($special_chars, '', $name);
    }

    /**
     *  Provides various date formats
     *
     *  @param string $utcDate created date in the GMT timezone
     *  @param int    $format  Various date formats to apply
     *
     *  @return string formatted date
     */
    public static function formatLocalDateTime(string $utcDate, int $format = 1): string
    {
        $date = get_date_from_gmt($utcDate);
        $date = new DateTime($date);
        switch ($format) {
            //YEAR
            case 1:
                return $date->format('Y-m-d H:i');
            case 2:
                return $date->format('Y-m-d H:i:s');
            case 3:
                return $date->format('y-m-d H:i');
            case 4:
                return $date->format('y-m-d H:i:s');
                //MONTH
            case 5:
                return $date->format('m-d-Y H:i');
            case 6:
                return $date->format('m-d-Y H:i:s');
            case 7:
                return $date->format('m-d-y H:i');
            case 8:
                return $date->format('m-d-y H:i:s');
                //DAY
            case 9:
                return $date->format('d-m-Y H:i');
            case 10:
                return $date->format('d-m-Y H:i:s');
            case 11:
                return $date->format('d-m-y H:i');
            case 12:
                return $date->format('d-m-y H:i:s');
            default:
                return $date->format('Y-m-d H:i');
        }
    }

    /**
     *  Cleanup all tmp files
     *
     *  @param bool $all empty all contents
     *
     *  @return bool true on success fail on failure
     */
    public static function tmpCleanup($all = false): bool
    {
        //Delete all files now
        if ($all) {
            $dir = DUPLICATOR_SSDIR_PATH_TMP . "/*";
            foreach (glob($dir) as $file) {
                if (basename($file) === 'index.php') {
                    continue;
                }
                SnapIO::rrmdir($file);
            }
        } else {
            // Remove scan files that are 24 hours old
            $dir = DUPLICATOR_SSDIR_PATH_TMP . "/*_scan.json";
            foreach (glob($dir) as $file) {
                if (filemtime($file) <= time() - Constants::TEMP_CLEANUP_SECONDS) {
                    SnapIO::rrmdir($file);
                }
            }
        }

        // Clean up extras directory if it is still hanging around
        $extras_directory = SnapIO::safePath(DUPLICATOR_SSDIR_PATH_TMP) . '/extras';
        if (file_exists($extras_directory)) {
            try {
                if (!SnapIO::rrmdir($extras_directory)) {
                    throw new Exception('Failed to delete: ' . $extras_directory);
                }
            } catch (Exception $ex) {
                DupLog::trace("Couldn't recursively delete {$extras_directory}");
            }
        }

        return true;
    }

    /**
     * Safe tmp cleanup
     *
     * @param bool $purgeTempArchives if true purge temp archives
     *
     * @return void
     */
    public static function safeTmpCleanup(bool $purgeTempArchives = false): void
    {
        if ($purgeTempArchives) {
            $dir = DUPLICATOR_SSDIR_PATH_TMP . "/*_archive.zip.*";
            foreach (glob($dir) as $file_path) {
                unlink($file_path);
            }
            $dir = DUPLICATOR_SSDIR_PATH_TMP . "/*_archive.daf.*";
            foreach (glob($dir) as $file_path) {
                unlink($file_path);
            }
        } else {
            $dir   = DUPLICATOR_SSDIR_PATH_TMP . "/*";
            $files = glob($dir);
            if ($files !== false) {
                foreach ($files as $file_path) {
                    if (basename($file_path) === 'index.php') {
                        continue;
                    }
                    if (filemtime($file_path) <= time() - Constants::TEMP_CLEANUP_SECONDS) {
                        SnapIO::rrmdir($file_path);
                    }
                }
            }
        }
    }

    /**
     * Get type string
     *
     * @param int $executionType execution type
     * @param int $templateId    template id
     *
     * @return string
     */
    public static function getExecTypeString(int $executionType, int $templateId = -1): string
    {
        switch ($executionType) {
            case AbstractPackage::EXEC_TYPE_MANUAL:
                if ($templateId != -1) {
                    $template = TemplateEntity::getById($templateId);
                    if (isset($template->is_manual) && !$template->is_manual) {
                        return __('Template', 'duplicator-pro') . ' ' . $template->name;
                    }
                }
                return __('Manual', 'duplicator-pro');
            case AbstractPackage::EXEC_TYPE_SCHEDULED:
                return __('Schedule', 'duplicator-pro');
            case AbstractPackage::EXEC_TYPE_RUN_NOW:
                return __('Schedule (Run Now)', 'duplicator-pro');
            default:
                return __('Unknown', 'duplicator-pro');
        }
    }

    /**
     * Returns the Backup engine type string
     *
     * @param int $engineType     Backup engine type
     * @param int $zipArchiveMode Zip archive mode
     *
     * @return string
     */
    public static function getEngineTypeString(int $engineType, int $zipArchiveMode): string
    {
        switch ($engineType) {
            case PackageArchive::BUILD_MODE_SHELL_EXEC:
                return __('Shell Exec', 'duplicator-pro');
            case PackageArchive::BUILD_MODE_ZIP_ARCHIVE:
                if ($zipArchiveMode == PackageArchive::ZIP_MODE_SINGLE_THREAD) {
                    return __('Zip Archive ST', 'duplicator-pro');
                }
                return __('Zip Archive', 'duplicator-pro');
            case PackageArchive::BUILD_MODE_DUP_ARCHIVE:
                return __('Dup Archive', 'duplicator-pro');
            default:
                return __('Unknown', 'duplicator-pro');
        }
    }

    /**
     * Returns an array with stats about the orphaned files
     *
     * @return string[] The full path of the orphaned file
     */
    public static function getOrphanedPackageFiles(): array
    {
        $global  = GlobalEntity::getInstance();
        $orphans = [];

        $numPackages = DupPackage::countByStatus([], [DupPackage::getBackupType()]);
        $numPerPage  = 100;
        $pages       = floor($numPackages / $numPerPage) + 1;

        $skipStart = ['dup_pro'];
        for ($page = 0; $page < $pages; $page++) {
            $offset       = $page * $numPerPage;
            $pagePackages = DupPackage::getRowByStatus(
                [],
                $numPerPage,
                $offset,
                '`id` ASC',
                [DupPackage::getBackupType()]
            );
            foreach ($pagePackages as $cPack) {
                $skipStart[] = $cPack->name . '_' . $cPack->hash;
            }
        }
        $pagePackages      = null;
        $fileTimeSkipInSec = (
            max(
                Constants::DEFAULT_MAX_PACKAGE_RUNTIME_IN_MIN,
                $global->max_package_runtime_in_min
            ) + Constants::ORPAHN_CLEANUP_DELAY_MAX_PACKAGE_RUNTIME
        ) * 60;

        // Only scan backup directory - log files are excluded as they're preserved for Activity Log
        foreach (
            [DUPLICATOR_SSDIR_PATH] as $rootPathToCheck
        ) {
            if (file_exists($rootPathToCheck) && ($handle = opendir($rootPathToCheck)) !== false) {
                while (false !== ($fileName = readdir($handle))) {
                    if ($fileName == '.' || $fileName == '..') {
                        continue;
                    }

                    $fileFullPath = $rootPathToCheck . '/' . $fileName;

                    if (is_dir($fileFullPath)) {
                        continue;
                    }
                    if (time() - filemtime($fileFullPath) < $fileTimeSkipInSec) {
                        // file younger than 2 hours skip for security
                        continue;
                    }
                    if (!preg_match(DUPLICATOR_FULL_GEN_BACKUP_FILE_REGEX_PATTERN, $fileName)) {
                        continue;
                    }
                    foreach ($skipStart as $skip) {
                        if (strpos($fileName, $skip) === 0) {
                            continue 2;
                        }
                    }
                    $orphans[] = $fileFullPath;
                }
                closedir($handle);
            }
        }
        return $orphans;
    }

    /**
     * Returns an array with stats about the orphaned files
     *
     * @return array{size:int,count:int} The total count and file size of orphaned files
     */
    public static function getOrphanedPackageInfo(): array
    {
        $files         = self::getOrphanedPackageFiles();
        $info          = [];
        $info['size']  = 0;
        $info['count'] = 0;
        if (count($files)) {
            foreach ($files as $path) {
                $get_size = @filesize($path);
                if ($get_size > 0) {
                    $info['size'] += $get_size;
                    $info['count']++;
                }
            }
        }
        return $info;
    }

    /**
     * Cleanup old log files based on Activity Log retention period
     *
     * @return array{deleted_count:int,freed_size:int} Statistics about deleted files
     */
    public static function cleanupOldLogFiles(): array
    {
        $result = [
            'deleted_count' => 0,
            'freed_size'    => 0,
        ];

        $dGlobal          = DynamicGlobalEntity::getInstance();
        $retentionSeconds = $dGlobal->getValInt('activity_log_retention', LogUtils::DEFAULT_RETENTION_MONTHS);

        // If retention is 0 or less, keep all logs forever
        if ($retentionSeconds <= 0) {
            DupLog::trace("Log cleanup: Retention disabled (value: {$retentionSeconds}), keeping all log files");
            return $result;
        }

        $expirationTimestamp = time() - $retentionSeconds;
        $logsPath            = DUPLICATOR_LOGS_PATH;

        if (!file_exists($logsPath) || !is_dir($logsPath)) {
            DupLog::trace("Log cleanup: Logs directory does not exist: {$logsPath}");
            return $result;
        }

        $retentionDays = round($retentionSeconds / DAY_IN_SECONDS);
        DupLog::trace("Log cleanup: Scanning for files older than {$retentionDays} days ({$retentionSeconds} seconds) in {$logsPath}");

        $globFiles = glob(SnapIO::safePath(SnapIO::untrailingslashit($logsPath) . "/*_log.txt"));
        if ($globFiles === false) {
            DupLog::trace("Log cleanup: Failed to scan logs directory: {$logsPath}");
            return $result;
        }

        foreach ($globFiles as $filePath) {
            $fileTime = @filemtime($filePath);

            if ($fileTime === false) {
                DupLog::trace("Log cleanup: Failed to get modification time for: " . basename($filePath));
                continue;
            }

            if ($fileTime < $expirationTimestamp) {
                $fileSize = @filesize($filePath);

                if (SnapIO::unlink($filePath)) {
                    $result['deleted_count']++;
                    $result['freed_size'] += ($fileSize !== false ? $fileSize : 0);
                    DupLog::trace("Log cleanup: Deleted expired log file: " . basename($filePath));
                } else {
                    DupLog::trace("Log cleanup: Failed to delete log file: " . basename($filePath));
                }
            }
        }

        DupLog::trace(
            sprintf(
                "Log cleanup: Completed. Deleted %d file(s), freed %s",
                $result['deleted_count'],
                size_format($result['freed_size'])
            )
        );

        return $result;
    }

    /**
     * Get the local overwrite params file name
     *
     * @param string $packageHash Package hash
     *
     * @return string
     */
    public static function getOverwriteParamFileName(string $packageHash): string
    {
        return DUPLICATOR_LOCAL_OVERWRITE_PARAMS  . '_' . $packageHash . '.json';
    }


    /**
     * Write installer overwrite params file and trigger hook for legacy compatibility
     *
     * @param string               $directory   Directory where to write the file
     * @param string               $packageHash Package hash for filename
     * @param array<string, mixed> $params      Parameters to write
     *
     * @return string Full path to the created file
     *
     * @throws Exception If file cannot be written
     */
    public static function writeOverwriteParams(string $directory, string $packageHash, array $params): string
    {
        $filePath = trailingslashit($directory) . self::getOverwriteParamFileName($packageHash);

        if (file_put_contents($filePath, SnapJson::jsonEncodePPrint($params)) === false) {
            throw new Exception('Can\'t create overwrite param file: ' . $filePath);
        }

        /**
         * Fires after installer overwrite params file is created
         *
         * @param string               $filePath    Full path to the created file
         * @param array<string, mixed> $params      Parameters written to the file
         * @param string               $packageHash Package hash used in filename
         */
        do_action('duplicator_after_overwrite_params_created', $filePath, $params, $packageHash);

        return $filePath;
    }
}
