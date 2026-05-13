<?php

namespace Duplicator\Models\ActivityLog;

use Duplicator\Core\CapMng;
use Duplicator\Core\Views\TplMng;
use Duplicator\Package\AbstractPackage;
use Duplicator\Package\PackageUtils;
use Duplicator\Package\Archive\PackageArchive;
use Duplicator\Package\Create\BuildComponents;
use Duplicator\Package\DupPackage;
use Duplicator\Utils\Logging\DupLog;
use Duplicator\Libs\Snap\SnapString;
use Duplicator\Controllers\ToolsPageController;
use Exception;

/**
 * Log event for backup creation
 */
class LogEventBackupCreate extends AbstractLogEvent
{
    const SUB_TYPE_ERROR     = 'error';
    const SUB_TYPE_CANCELLED = 'cancelled';
    const SUB_TYPE_START     = 'start';
    const SUB_TYPE_DB_DUMP   = 'db_dump';
    const SUB_TYPE_FILE_DUMP = 'file_dump';
    const SUB_TYPE_TRANSFER  = 'transfer';
    const SUB_TYPE_END       = 'end';

    /**
     * Class constructor
     *
     * @param AbstractPackage $package  Package
     * @param int             $parentId Parent ID, if 0 the event have no event parent
     */
    public function __construct(AbstractPackage $package, int $parentId = 0)
    {
        $this->parentId = $parentId;
        $this->setStatusBasedProperties($package);
        $this->initializeBasicData($package);
        $this->collectPackageMetadata($package);
        $this->collectContextData($package);
        $this->collectSizeAndDbData($package);
        $this->collectTimingData($package);
    }

    /**
     * Initialize basic data fields
     *
     * @param AbstractPackage $package Package
     *
     * @return void
     */
    private function initializeBasicData(AbstractPackage $package): void
    {
        $this->data['packageId']     = $package->getId();
        $this->data['packageName']   = $package->getName();
        $this->data['packageStatus'] = $package->getStatus();
        $this->data['components']    = $package->components;
        $this->data['nameHash']      = $package->getNameHash();
        $this->data['logFileName']   = $package->getLogFilename();

        // Assign execution timing data
        $timingData = $package->getExecutionTimingData();
        foreach ($timingData as $key => $value) {
            $this->data[$key] = $value;
        }
    }

    /**
     * Collect package metadata (filters, counts, engines)
     *
     * @param AbstractPackage $package Package
     *
     * @return void
     */
    private function collectPackageMetadata(AbstractPackage $package): void
    {
        // Archive filters and counts
        $this->data['filterOn']    = $package->Archive->FilterOn;
        $this->data['filterDirs']  = strlen($package->Archive->FilterDirs) > 0 ? explode(';', $package->Archive->FilterDirs) : [];
        $this->data['filterExts']  = strlen($package->Archive->FilterExts) > 0 ? explode(';', $package->Archive->FilterExts) : [];
        $this->data['filterFiles'] = strlen($package->Archive->FilterFiles) > 0 ? explode(';', $package->Archive->FilterFiles) : [];
        $this->data['fileCount']   = $package->Archive->FileCount;
        $this->data['dirCount']    = $package->Archive->DirCount;
        $this->data['size']        = $package->Archive->Size;

        // DB filters - only collect if database component is included
        if (!BuildComponents::isDBExcluded($package->components)) {
            $this->data['dbFilterOn']     = $package->Database->FilterOn;
            $this->data['dbFilterTables'] = strlen($package->Database->FilterTables) > 0 ? explode(';', $package->Database->FilterTables) : [];
            $this->data['dbPrefixFilter'] = $package->Database->prefixFilter;
        } else {
            $this->data['dbFilterOn']     = false;
            $this->data['dbFilterTables'] = [];
            $this->data['dbPrefixFilter'] = false;
        }

        // Engines
        $this->data['archiveEngine'] = $this->getArchiveEngineLabel($package->build_progress->current_build_mode);
        if (!BuildComponents::isDBExcluded($package->components)) {
            $this->data['databaseEngine'] = $package->Database->DBMode;
        } else {
            $this->data['databaseEngine'] = '';
        }
    }

    /**
     * Collect execution and storage context
     *
     * @param AbstractPackage $package Package
     *
     * @return void
     */
    private function collectContextData(AbstractPackage $package): void
    {
        // Execution context
        $this->data['execType']     = PackageUtils::getExecTypeString($package->getExecutionType(), $package->template_id);
        $this->data['scheduleName'] = $this->getScheduleName($package);
        $this->data['storageNames'] = $this->getStorageNames($package);
    }

    /**
     * Collect size and database statistics
     *
     * @param AbstractPackage $package Package
     *
     * @return void
     */
    private function collectSizeAndDbData(AbstractPackage $package): void
    {
        // Archive size and upload summaries will be updated when the backup reaches terminal state
        $this->data['archiveSizeDisplay'] = '';
        $this->data['uploadSummaries']    = [];

        // DB stats - only collect if database component is included
        $this->data['dbExcluded'] = BuildComponents::isDBExcluded($package->components);
        if (!$this->data['dbExcluded'] && $package->Database->info) {
            $this->data['dbTableCount']  = (int) ($package->Database->info->tablesFinalCount);
            $this->data['dbSizeDisplay'] = SnapString::byteSize((int) ($package->Database->info->tablesSizeOnDisk));
        }
    }

    /**
     * Collect timing data from package execution (simple, reliable approach)
     *
     * @param AbstractPackage $package The package object
     *
     * @return void
     */
    private function collectTimingData(AbstractPackage $package): void
    {
        // Store package start time (reliable)
        $this->data['execution_start_time'] = $package->timer_start > 0 ? $package->timer_start : strtotime($package->getCreated());

        // Store total runtime for completed packages (this is accurate)
        if ($package->getStatus() >= AbstractPackage::STATUS_COMPLETE && !empty($package->Runtime)) {
            $this->data['total_runtime'] = $package->Runtime;
        }
    }

    /**
     * Set properties based on package status
     *
     * @param AbstractPackage $package Package
     *
     * @return void
     */
    private function setStatusBasedProperties(AbstractPackage $package): void
    {
        $status = $package->getStatus();

        if ($status == AbstractPackage::STATUS_BUILD_CANCELLED) {
            $this->subType  = self::SUB_TYPE_CANCELLED;
            $this->title    = sprintf(__('Backup cancelled: %s', 'duplicator-pro'), $package->getName());
            $this->severity = self::SEVERITY_WARNING;
        } elseif ($status < AbstractPackage::STATUS_PRE_PROCESS) {
            $this->subType                  = self::SUB_TYPE_ERROR;
            $this->title                    = sprintf(__('Backup creation: %s - Error', 'duplicator-pro'), $package->getName());
            $this->severity                 = self::SEVERITY_ERROR;
            $this->data['backupLogContext'] = $this->captureBackupLogContext($package);
        } elseif ($status < AbstractPackage::STATUS_DBSTART) {
            $this->subType = self::SUB_TYPE_START;
            $this->title   = sprintf(__('Backup creation: %s', 'duplicator-pro'), $package->getName());
        } elseif ($status < AbstractPackage::STATUS_ARCSTART) {
            $this->subType = self::SUB_TYPE_DB_DUMP;
            $this->title   = sprintf(__('Backup creation: %s - Database Dump', 'duplicator-pro'), $package->getName());
        } elseif ($status < AbstractPackage::STATUS_COPIEDPACKAGE) {
            $this->subType = self::SUB_TYPE_FILE_DUMP;
            $this->title   = sprintf(__('Backup creation: %s - File Archive', 'duplicator-pro'), $package->getName());
        } elseif ($status < AbstractPackage::STATUS_COMPLETE) {
            $this->subType = self::SUB_TYPE_TRANSFER;
            $this->title   = sprintf(__('Backup creation: %s - Transfer', 'duplicator-pro'), $package->getName());
        } else {
            $this->subType = self::SUB_TYPE_END;
            $this->title   = sprintf(__('Backup creation: %s - Completed', 'duplicator-pro'), $package->getName());
        }
    }

    /**
     * Get archive size display
     *
     * @return string Archive size display string, empty if not available
     */
    public function getArchiveSizeForDisplay(): string
    {
        return (string) ($this->data['archiveSizeDisplay'] ?? '');
    }

    /**
     * Get upload summaries
     *
     * @return array{completed:int,total:int} Upload completion status
     */
    public function getUploadCompletionStatus(): array
    {
        // Return stored data if available
        if (!empty($this->data['uploadSummaries']) && is_array($this->data['uploadSummaries'])) {
            return $this->data['uploadSummaries'];
        }

        // No data stored, return empty
        return [
            'completed' => 0,
            'total'     => 0,
        ];
    }

    /**
     * Update timing data for this log
     *
     * @param array<string,mixed> $timingData Timing data to merge into this log's data
     *
     * @return void
     */
    public function updateTimingData(array $timingData): void
    {
        foreach ($timingData as $key => $value) {
            $this->data[$key] = $value;
        }
    }

    /**
     * Get child logs by parent ID
     *
     * This method retrieves child activity logs for a given parent log ID without
     * applying capability filtering. It should ONLY be used internally.
     *
     * Use `getList()` for user-facing queries and admin interface display.
     *
     * Why this exists:
     * During package execution (background/cron context), there's no authenticated user,
     * so capability filtering in getList() returns empty results even though child logs
     * exist. This method bypasses capability checks for legitimate internal operations.
     *
     * @param int    $parentId Parent log ID
     * @param string $order    Sort order: 'ASC' or 'DESC' (default: 'ASC')
     * @param int    $limit    Maximum number of logs to return (default: 100)
     *
     * @return self[] Array of child log instances
     */
    public static function getChildLogsByParentId(int $parentId, string $order = 'ASC', int $limit = 100): array
    {
        global $wpdb;

        $table = self::getTableName(true);
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

        $sql     = "SELECT * FROM `{$table}` WHERE parent_id = %d AND type = %s ORDER BY created_at {$order}, id {$order} LIMIT %d";
        $results = $wpdb->get_results($wpdb->prepare($sql, $parentId, self::getType(), $limit), ARRAY_A);

        if (!is_array($results)) {
            return [];
        }

        $logs = [];
        foreach ($results as $row) {
            try {
                $log = self::getModelFromRow($row);
                if ($log instanceof self) {
                    $logs[] = $log;
                }
            } catch (Exception $e) {
                DupLog::traceException($e, "Child log query error");
                continue;
            }
        }

        return $logs;
    }

    /**
     * Update log with final backup data (called when backup completes/fails/cancelled)
     *
     * @return void
     */
    public function updateFinalData(): void
    {
        $package = DupPackage::getById($this->data['packageId'] ?? 0);

        if (!$package instanceof DupPackage) {
            return;
        }

        // Update parent log with final data
        $this->data['archiveSizeDisplay'] = $package->getDisplaySize();
        $this->data['uploadSummaries']    = $this->calculateUploadStatus($package);
        $this->save();

        // Get child logs
        $childLogs = self::getChildLogsByParentId($this->getId(), 'ASC');

        foreach ($childLogs as $childLog) {
            $updated = false;

            switch ($childLog->getSubType()) {
                case self::SUB_TYPE_FILE_DUMP:
                    // File dump sub-event needs archive size
                    $childLog->data['archiveSizeDisplay'] = $this->data['archiveSizeDisplay'];
                    $updated                              = true;
                    break;

                case self::SUB_TYPE_TRANSFER:
                    // Transfer sub-event needs upload summaries
                    $childLog->data['uploadSummaries'] = $this->data['uploadSummaries'];
                    $updated                           = true;
                    break;

                case self::SUB_TYPE_END:
                    // End sub-event needs both archive size and upload summaries
                    $childLog->data['archiveSizeDisplay'] = $this->data['archiveSizeDisplay'];
                    $childLog->data['uploadSummaries']    = $this->data['uploadSummaries'];
                    $updated                              = true;
                    break;

                default:
                    break;
            }

            if ($updated) {
                $childLog->save();
            }
        }
    }

    /**
     * Calculate upload completion status from package
     *
     * @param AbstractPackage $package Package
     *
     * @return array{completed:int,total:int} Upload counts
     */
    private function calculateUploadStatus(AbstractPackage $package): array
    {
        $result = [
            'completed' => 0,
            'total'     => 0,
        ];

        if (is_array($package->upload_infos)) {
            foreach ($package->upload_infos as $uInfo) {
                // Count only uploads (skip downloads)
                if ($uInfo->isDownloadFromRemote()) {
                    continue;
                }
                $result['total']++;
                if ($uInfo->hasCompleted(true)) {
                    $result['completed']++;
                }
            }
        }

        return $result;
    }

    /**
     * Get archive engine label
     *
     * @param int $buildMode Build mode constant
     *
     * @return string
     */
    private function getArchiveEngineLabel(int $buildMode): string
    {
        switch ($buildMode) {
            case PackageArchive::BUILD_MODE_SHELL_EXEC:
                return __('Shell Exec', 'duplicator-pro');
            case PackageArchive::BUILD_MODE_ZIP_ARCHIVE:
                return __('Zip Archive', 'duplicator-pro');
            case PackageArchive::BUILD_MODE_DUP_ARCHIVE:
                return __('Dup Archive', 'duplicator-pro');
            default:
                return __('Unknown', 'duplicator-pro');
        }
    }

    /**
     * Get schedule name if available
     *
     * @param AbstractPackage $package Package
     *
     * @return string
     */
    private function getScheduleName(AbstractPackage $package): string
    {
        $schedule = $package->getSchedule();
        return $schedule ? (string) $schedule->name : '';
    }

    /**
     * Get storage names
     *
     * @param AbstractPackage $package Package
     *
     * @return string[]
     */
    private function getStorageNames(AbstractPackage $package): array
    {
        $storages = $package->getStorages();
        return array_map(function ($storage) {
            return $storage->getName();
        }, $storages);
    }

    /**
     * Get execution time for a specific sub-event phase using atomic timing data
     *
     * @param string $subType The sub-event type to get execution time for
     *
     * @return string Formatted execution time string
     */
    public function getExecutionTimeForPhase(string $subType): string
    {
        // Use atomic timing data stored in $this->data - no cross-queries
        $start = 0;
        $end   = 0;

        switch ($subType) {
            case self::SUB_TYPE_START:
                $start = $this->data['buildTimeStart'] ?? 0;
                // The START phase ends when DB phase starts, or if no DB, when files phase starts
                if (!empty($this->data['dbTimeStart'])) {
                    $end = $this->data['dbTimeStart'];
                } elseif (!empty($this->data['filesTimeStart'])) {
                    $end = $this->data['filesTimeStart'];
                } else {
                    $end = 0;
                }
                break;
            case self::SUB_TYPE_DB_DUMP:
                $start = $this->data['dbTimeStart'] ?? 0;
                $end   = $this->data['dbTimeEnd'] ?? 0;
                break;
            case self::SUB_TYPE_FILE_DUMP:
                $start = $this->data['filesTimeStart'] ?? 0;
                $end   = $this->data['filesTimeEnd'] ?? 0;
                break;
            case self::SUB_TYPE_TRANSFER:
                $start = $this->data['transferTimeStart'] ?? 0;
                $end   = $this->data['transferTimeEnd'] ?? 0;
                break;
            case self::SUB_TYPE_END:
                // For the end phase, return total runtime
                return $this->getTotalRuntime();
            default:
                return __('N/A', 'duplicator-pro');
        }

        // If we don't have both start and end times, return N/A
        if ($start <= 0 || $end <= 0) {
            return __('N/A', 'duplicator-pro');
        }

        $duration = max(0, $end - $start);

        // For durations less than 1 second, show decimal seconds
        if ($duration < 1) {
            return sprintf(__('%.1f sec', 'duplicator-pro'), $duration);
        }

        return SnapString::formatHumanReadableDuration($duration, 0);
    }

    /**
     * Get total runtime using atomic timing data
     *
     * @return string Formatted total runtime
     */
    public function getTotalRuntime(): string
    {
        // Use atomic timing data stored in $this->data - no cross-queries
        $start = $this->data['buildTimeStart'] ?? 0;
        $end   = $this->data['buildTimeEnd'] ?? 0;

        // If we don't have both start and end times, return N/A
        if ($start <= 0 || $end <= 0) {
            return __('N/A', 'duplicator-pro');
        }

        $duration = max(0, $end - $start);
        return SnapString::formatHumanReadableDuration($duration, 0);
    }

    /**
     * Return entity type identifier
     *
     * @return string
     */
    public static function getType(): string
    {
        return 'backup_create';
    }

    /**
     * Return entity type label
     *
     * @return string
     */
    public static function getTypeLabel(): string
    {
        return __('Backup Creation', 'duplicator-pro');
    }

    /**
     * Return required capability for this log event
     *
     * @return string
     */
    public static function getCapability(): string
    {
        return CapMng::CAP_BASIC;
    }

    /**
     * Return short description
     *
     * @return string
     */
    public function getShortDescription(): string
    {
        switch ($this->subType) {
            case self::SUB_TYPE_ERROR:
                return __('Backup Error', 'duplicator-pro');
            case self::SUB_TYPE_CANCELLED:
                return __('Backup Cancelled', 'duplicator-pro');
            case self::SUB_TYPE_START:
                $subEvents = array_merge(
                    self::getList(
                        [
                            'parent_id' => $this->getId(),
                            'order'     => 'DESC',
                            'orderby'   => 'created_at',
                            'per_page'  => 1,
                        ]
                    )
                );
                if (count($subEvents) > 0) {
                    return $subEvents[0]->getShortDescription();
                } else {
                    return __('Backup Creation', 'duplicator-pro');
                }
            case self::SUB_TYPE_DB_DUMP:
                // Only show DB dump info if database component is included
                if (!empty($this->data['dbExcluded'])) {
                    return __('Database Dump', 'duplicator-pro'); // No stats for excluded DB
                }
                if (!empty($this->data['dbTableCount']) && !empty($this->data['dbSizeDisplay'])) {
                    return sprintf(
                        __('Database Dump (%1$d tables, %2$s)', 'duplicator-pro'),
                        (int) $this->data['dbTableCount'],
                        (string) $this->data['dbSizeDisplay']
                    );
                }
                return __('Database Dump', 'duplicator-pro');
            case self::SUB_TYPE_FILE_DUMP:
                $archiveSize = $this->getArchiveSizeForDisplay();
                if (!empty($archiveSize)) {
                    return sprintf(__('File Archive (%s)', 'duplicator-pro'), $archiveSize);
                }
                return __('File Archive', 'duplicator-pro');
            case self::SUB_TYPE_TRANSFER:
                $uploadStatus = $this->getUploadCompletionStatus();
                if ($uploadStatus['total'] > 0) {
                    return sprintf(
                        __('Backup Transfer (%1$d/%2$d completed)', 'duplicator-pro'),
                        $uploadStatus['completed'],
                        $uploadStatus['total']
                    );
                }
                return __('Backup Transfer', 'duplicator-pro');
            case self::SUB_TYPE_END:
                $sizeText = $this->getArchiveSizeForDisplay();
                $storages = count($this->data['storageNames'] ?? []);

                if ($sizeText && $storages > 0) {
                    return sprintf(
                        _n(
                            'Backup Completed (%1$s, %2$d storage)',
                            'Backup Completed (%1$s, %2$d storages)',
                            $storages,
                            'duplicator-pro'
                        ),
                        $sizeText,
                        $storages
                    );
                } elseif ($sizeText) {
                    return sprintf(__('Backup Completed (%s)', 'duplicator-pro'), $sizeText);
                }
                return __('Backup Completed', 'duplicator-pro');
            default:
                return __('Backup Creation', 'duplicator-pro');
        }
    }

    /**
     * Display detailed information in html format
     *
     * @return void
     */
    public function detailHtml(): void
    {
        ?>
        <div class="dup-log-detail-meta">
            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('Archive Engine:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type">
                    <?php echo esc_html($this->data['archiveEngine']); ?>
                </span>
            </div>
            <?php if (empty($this->data['dbExcluded'])) : ?>
                <div class="dup-log-type-wrapper">
                    <strong><?php esc_html_e('Database Engine:', 'duplicator-pro'); ?></strong>
                    <span class="dup-log-type">
                        <?php echo esc_html($this->data['databaseEngine']); ?>
                    </span>
                </div>
            <?php endif; ?>
            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('Components:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type">
                    <?php echo esc_html(BuildComponents::displayComponentsList($this->data['components'], ", ")); ?>
                </span>
            </div>
            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('Run Type:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type">
                    <?php echo esc_html($this->data['execType'] ?? ''); ?>
                </span>
            </div>
            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('Execution Time:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type">
                    <?php
                    // For child logs (sub-events), get execution time from parent
                    if ($this->getParentId() > 0) {
                        echo esc_html($this->getExecutionTimeForPhase($this->subType));
                    } else {
                        echo esc_html($this->getTotalRuntime());
                    }
                    ?>
                </span>
            </div>
            <?php if (!empty($this->data['scheduleName'])) : ?>
            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('Schedule Name:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type">
                    <?php echo esc_html($this->data['scheduleName']); ?>
                </span>
            </div>
            <?php endif; ?>
            <?php if (!empty($this->data['storageNames'])) : ?>
            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('Storages:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type">
                    <?php echo esc_html(implode(', ', $this->data['storageNames'] ?? [])); ?>
                </span>
            </div>
            <?php endif; ?>
            <?php $archiveSizeDisplay = $this->getArchiveSizeForDisplay(); ?>
            <?php if (!empty($archiveSizeDisplay)) : ?>
            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('Backup Size:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type">
                    <?php echo esc_html($archiveSizeDisplay); ?>
                </span>
            </div>
            <?php endif; ?>
            <?php if (empty($this->data['dbExcluded'])) : ?>
                <?php if (!empty($this->data['dbTableCount']) || !empty($this->data['dbSizeDisplay'])) : ?>
                <div class="dup-log-type-wrapper">
                    <strong><?php esc_html_e('Database Stats:', 'duplicator-pro'); ?></strong>
                    <span class="dup-log-type">
                        <?php
                            $parts = [];
                        if (!empty($this->data['dbTableCount'])) {
                            $parts[] = sprintf(esc_html__('%d tables', 'duplicator-pro'), (int) $this->data['dbTableCount']);
                        }
                        if (!empty($this->data['dbSizeDisplay'])) {
                            $parts[] = sprintf(esc_html__('%s SQL', 'duplicator-pro'), (string) $this->data['dbSizeDisplay']);
                        }
                            echo esc_html(implode(' · ', $parts));
                        ?>
                    </span>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php // File and directory counts ?>
            <?php if (!empty($this->data['fileCount']) || !empty($this->data['dirCount'])) : ?>
            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('Files/Folders:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type">
                    <?php
                        $counts = [];
                    if (!empty($this->data['fileCount'])) {
                        $counts[] = sprintf(esc_html__('%s files', 'duplicator-pro'), number_format((int) $this->data['fileCount']));
                    }
                    if (!empty($this->data['dirCount'])) {
                        $counts[] = sprintf(esc_html__('%s folders', 'duplicator-pro'), number_format((int) $this->data['dirCount']));
                    }
                        echo esc_html(implode(' · ', $counts));
                    ?>
                </span>
            </div>
            <?php endif; ?>

            <hr>

            <?php
            // Check if any filters are active
            $hasArchiveFilters = $this->data['filterOn']
                && ($this->data['filterOn'] == true)
                && (!empty($this->data['filterDirs']) || !empty($this->data['filterExts']) || !empty($this->data['filterFiles']));
            $hasDbFilters      = empty($this->data['dbExcluded'])
                && $this->data['dbFilterOn']
                && ($this->data['dbFilterOn'] == true)
                && (!empty($this->data['dbFilterTables']) || !empty($this->data['dbPrefixFilter']));

            if ($hasArchiveFilters || $hasDbFilters) : ?>
                <div class="dup-log-type-wrapper mb-10">
                    <strong><?php esc_html_e('Applied Filters:', 'duplicator-pro'); ?></strong>
                </div>

                <table class="widefat dup-table-list striped dup-activity-log-table small dup-applied-filters-table">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column"><?php esc_html_e('Filter Type', 'duplicator-pro'); ?></th>
                            <th scope="col" class="manage-column"><?php esc_html_e('Items', 'duplicator-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($this->data['filterDirs'])) : ?>
                            <tr>
                                <td><strong><?php esc_html_e('Excluded Directories', 'duplicator-pro'); ?></strong></td>
                                <td>
                                    <?php
                                    $dirs = array_slice($this->data['filterDirs'], 0, 5);
                                    foreach ($dirs as $dir) {
                                        echo '<div>' . esc_html($dir) . '</div>';
                                    }
                                    if (count($this->data['filterDirs']) > 5) {
                                        echo '<div class="dup-more-count">' .
                                        sprintf(esc_html__('... and %d more directories', 'duplicator-pro'), count($this->data['filterDirs']) - 5) .
                                        '</div>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php if (!empty($this->data['filterExts'])) : ?>
                            <tr>
                                <td><strong><?php esc_html_e('Excluded Extensions', 'duplicator-pro'); ?></strong></td>
                                <td>
                                    <?php
                                    $extensions    = array_slice($this->data['filterExts'], 0, 20);
                                    $formattedExts = array_map(function ($ext) {
                                        return '.' . ltrim($ext, '.');
                                    }, array_filter($extensions));
                                    echo esc_html(implode(', ', $formattedExts));
                                    if (count($this->data['filterExts']) > 20) {
                                        echo '<div class="dup-more-count">' .
                                        sprintf(esc_html__('... and %d more extensions', 'duplicator-pro'), count($this->data['filterExts']) - 20) .
                                        '</div>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php if (!empty($this->data['filterFiles'])) : ?>
                            <tr>
                                <td><strong><?php esc_html_e('Excluded Files', 'duplicator-pro'); ?></strong></td>
                                <td>
                                    <?php
                                    $files = array_slice($this->data['filterFiles'], 0, 5);
                                    foreach ($files as $file) {
                                        echo '<div>' . esc_html($file) . '</div>';
                                    }
                                    if (count($this->data['filterFiles']) > 5) {
                                        echo '<div class="dup-more-count">' .
                                        sprintf(esc_html__('... and %d more files', 'duplicator-pro'), count($this->data['filterFiles']) - 5) .
                                        '</div>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php if (!empty($this->data['dbFilterTables'])) : ?>
                            <tr>
                                <td><strong><?php esc_html_e('Excluded Database Tables', 'duplicator-pro'); ?></strong></td>
                                <td>
                                    <?php
                                    $tables = array_slice($this->data['dbFilterTables'], 0, 10);
                                    echo esc_html(implode(', ', $tables));
                                    if (count($this->data['dbFilterTables']) > 10) {
                                        echo '<div class="dup-more-count">' .
                                        sprintf(esc_html__('... and %d more tables', 'duplicator-pro'), count($this->data['dbFilterTables']) - 10) .
                                        '</div>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php if (!empty($this->data['dbPrefixFilter'])) : ?>
                            <tr>
                                <td><strong><?php esc_html_e('Database Prefix Filter', 'duplicator-pro'); ?></strong></td>
                                <td><?php esc_html_e('Enabled', 'duplicator-pro'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <hr>
            <?php endif;

            $subEvents = array_merge(
                // [$this],
                self::getList(
                    [
                        'parent_id' => $this->getId(),
                        'order'     => 'ASC',
                        'orderby'   => 'created_at',
                    ]
                )
            );
        if (count($subEvents) > 0) {
            ?>
                <div class="margin-top-1">
                <?php TplMng::getInstance()->render('admin_pages/activity_log/parts/sub_table_mini', ['logs' => $subEvents, 'parentLog' => $this]); ?>
                </div>
        <?php } ?>

        <?php
            // Show error context for error sub-events OR parent events that have error child events
            $backupLogContext = $this->getBackupLogContextForDisplay();
        if (!empty($backupLogContext)) : ?>
                <hr>
                <div class="dup-log-type-wrapper">
                    <strong><?php esc_html_e('Error Context:', 'duplicator-pro'); ?></strong>
                </div>

                <div class="dup-log-context-content">
                    <?php $this->renderBackupLogContext($backupLogContext); ?>
                </div>
        <?php endif;
        ?>
        </div>
        <?php
    }

    /**
     * Get backup log context for display - either from current event or from child error events
     *
     * @return string[] Array of log lines
     */
    private function getBackupLogContextForDisplay(): array
    {
        // If this is an error event with backup log context, return it directly
        if ($this->subType === self::SUB_TYPE_ERROR && !empty($this->data['backupLogContext'])) {
            return $this->data['backupLogContext'];
        }

        // If this is a parent event (parentId = 0) with error severity, look for error context in child events
        if ($this->parentId === 0 && $this->severity === self::SEVERITY_ERROR) {
            $childEvents = self::getList([
                'parent_id' => $this->getId(),
                'order'     => 'DESC',
                'orderby'   => 'created_at',
            ]);

            // Find the first child event with backup log context
            foreach ($childEvents as $childEvent) {
                if ($childEvent->subType === self::SUB_TYPE_ERROR && !empty($childEvent->data['backupLogContext'])) {
                    return $childEvent->data['backupLogContext'];
                }
            }
        }

        return [];
    }

    /**
     * Render backup log context in a simple collapsible section
     *
     * @param string[] $logLines Array of log lines
     *
     * @return void
     */
    private function renderBackupLogContext(array $logLines): void
    {
        $logFileName = $this->data['logFileName'] ?? '';
        $logUrl      = ToolsPageController::getLogViewerURL($logFileName, false);

        TplMng::getInstance()->render('admin_pages/activity_log/parts/error_log_context', ['logLines' => $logLines, 'logUrl' => $logUrl]);
    }

    /**
     * Capture the last 15 lines from backup log for error context
     *
     * @param AbstractPackage $package The package object
     *
     * @return string[] Array of recent log lines
     */
    private function captureBackupLogContext(AbstractPackage $package): array
    {
        try {
            return DupLog::getLogContext($package->getNameHash(), 15);
        } catch (Exception $e) {
            return ['Error reading backup log: ' . $e->getMessage()];
        }
    }

    /**
     * Return object type label, can be overridden by child classes
     * by default it returns the same as static::getTypeLabel() but can change in base of object properties
     *
     * @return string
     */
    public function getObjectTypeLabel(): string
    {
        switch ($this->subType) {
            case self::SUB_TYPE_ERROR:
                return __('Backup Error', 'duplicator-pro');
            case self::SUB_TYPE_CANCELLED:
                return __('Backup Cancelled', 'duplicator-pro');
            case self::SUB_TYPE_START:
                return __('Backup Creation', 'duplicator-pro');
            case self::SUB_TYPE_DB_DUMP:
                return __('Database Dump', 'duplicator-pro');
            case self::SUB_TYPE_FILE_DUMP:
                return __('File Archive', 'duplicator-pro');
            case self::SUB_TYPE_TRANSFER:
                return __('Backup Transfer', 'duplicator-pro');
            case self::SUB_TYPE_END:
                return __('Backup Completed', 'duplicator-pro');
            default:
                return __('Backup Creation', 'duplicator-pro');
        }
    }
}
