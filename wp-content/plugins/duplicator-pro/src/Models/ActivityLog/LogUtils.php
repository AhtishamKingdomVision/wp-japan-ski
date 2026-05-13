<?php

declare(strict_types=1);

namespace Duplicator\Models\ActivityLog;

use Duplicator\Utils\Logging\DupLog;
use Duplicator\Models\DynamicGlobalEntity;

final class LogUtils
{
    const DEFAULT_RETENTION_MONTHS = 6 * MONTH_IN_SECONDS;
    const BULK_DELETE_LIMIT_CHUNK  = 1000;

    /** @var array<string,class-string<AbstractLogEvent>> Associative array of log type => class name */
    private static array $registeredLogTypes = [];

    /**
     * Register all log types
     *
     * @return void
     */
    public static function registerAllLogTypes(): void
    {
        self::registerLogType(LogEventBackupCreate::getType(), LogEventBackupCreate::class);
        self::registerLogType(LogEventBackupDelete::getType(), LogEventBackupDelete::class);
        self::registerLogType(LogEventWebsitesScan::getType(), LogEventWebsitesScan::class);
        self::registerLogType(LogEventSchedule::getType(), LogEventSchedule::class);
        self::registerLogType(LogEventSettingsChange::getType(), LogEventSettingsChange::class);
        self::registerLogType(LogEventBrandCreateUpdate::getType(), LogEventBrandCreateUpdate::class);
        self::registerLogType(LogEventBrandDelete::getType(), LogEventBrandDelete::class);
        self::registerLogType(LogEventLicenseActivation::getType(), LogEventLicenseActivation::class);
        self::registerLogType(LogEventLicenseDeactivation::getType(), LogEventLicenseDeactivation::class);
        self::registerLogType(LogEventLicenseKeyCleared::getType(), LogEventLicenseKeyCleared::class);
        self::registerLogType(LogEventLicenseVisibilityChanged::getType(), LogEventLicenseVisibilityChanged::class);
        self::registerLogType(LogEventLicenseStatusChanged::getType(), LogEventLicenseStatusChanged::class);
        self::registerLogType(LogEventOrphanCleanup::getType(), LogEventOrphanCleanup::class);
        self::registerLogType(LogEventStorageDelete::getType(), LogEventStorageDelete::class);
    }

    /**
     * Get all log types, key is type, value is label
     *
     * @return array<string,string>
     */
    public static function getAllLogTypes(): array
    {
        $result = [];
        foreach (self::$registeredLogTypes as $type => $class) {
            $result[$type] = $class::getTypeLabel();
        }
        return $result;
    }

    /**
     * Register a log type
     *
     * @param string                         $type  The log type
     * @param class-string<AbstractLogEvent> $class The class name
     *
     * @return void
     */
    public static function registerLogType(string $type, string $class): void
    {
        self::$registeredLogTypes[$type] = $class;
    }

    /**
     * Get a class by log type
     *
     * @param string $type The log type
     *
     * @return class-string<AbstractLogEvent>
     */
    public static function getClassByType(string $type): string
    {
        return self::$registeredLogTypes[$type] ?? LogEventUnknown::class;
    }

    /**
     * Get labels of severity levels
     *
     * @return array<int,string>
     */
    public static function getSeverityLabels(): array
    {
        return [
            AbstractLogEvent::SEVERITY_INFO    => __('Info', 'duplicator-pro'),
            AbstractLogEvent::SEVERITY_WARNING => __('Warning', 'duplicator-pro'),
            AbstractLogEvent::SEVERITY_ERROR   => __('Error', 'duplicator-pro'),
        ];
    }

    /**
     * Clean up old activity logs based on retention settings
     * Deletes all logs older than the specified retention period in seconds
     *
     * @return int Number of logs deleted, 0 if retention is disabled or no logs to delete
     */
    public static function cleanupOldLogs(): int
    {
        /** @var \wpdb $wpdb */
        global $wpdb;

        $dGlobal          = DynamicGlobalEntity::getInstance();
        $retentionSeconds = $dGlobal->getValInt('activity_log_retention', self::DEFAULT_RETENTION_MONTHS);

        // If retention is 0, keep all logs forever
        if ($retentionSeconds <= 0) {
            return 0;
        }

        // Calculate cutoff date (X seconds ago)
        $cutoffDate = gmdate('Y-m-d H:i:s', time() - $retentionSeconds);
        $tableName  = AbstractLogEvent::getTableName();

        // Delete old logs
        $deletedRows = $wpdb->query($wpdb->prepare(
            "DELETE FROM `{$tableName}` WHERE created_at < %s",
            $cutoffDate
        ));

        return (int) $deletedRows;
    }

    /**
     * Delete all activity logs from the database in batches
     *
     * @return int Number of logs deleted
     */
    public static function deleteAllLogs(): int
    {
        $totalDeleted = 0;

        while (($deleted = self::deleteLogsChunk()) > 0) {
            $totalDeleted += $deleted;
        }

        return $totalDeleted;
    }

    /**
     * Delete a chunk of activity logs
     *
     * @param int $limit Number of logs to delete in this chunk
     *
     * @return int Number of logs deleted in this chunk
     */
    public static function deleteLogsChunk(int $limit = self::BULK_DELETE_LIMIT_CHUNK): int
    {
        /** @var \wpdb $wpdb */
        global $wpdb;

        $tableName = AbstractLogEvent::getTableName();

        $deletedRows = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM `{$tableName}` ORDER BY id ASC LIMIT %d",
                $limit
            )
        );

        return $deletedRows !== false ? (int) $deletedRows : 0;
    }
}
