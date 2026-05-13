<?php

declare(strict_types=1);

namespace Duplicator\Addons\StagingAddon\Models;

use Duplicator\Addons\StagingAddon\StagingAddon;
use Duplicator\Core\MigrationMng;
use Duplicator\Core\Models\AbstractEntity;
use Duplicator\Core\Models\TraitGenericModelList;
use Duplicator\Libs\Snap\SnapIO;

/**
 * Staging site entity
 */
class StagingEntity extends AbstractEntity
{
    use TraitGenericModelList;

    const STATUS_CREATING        = 'creating';
    const STATUS_PENDING_INSTALL = 'pending_install';
    const STATUS_READY           = 'ready';
    const STATUS_FAILED          = 'failed';

    /** @var string Regex pattern to identify staging tables (format: dstg{id}_{prefix}) */
    const STAGING_TABLE_PREFIX_PATTERN = '/^dstg\d+_/';

    /** @var string 32-char hash for URL obfuscation */
    protected string $hash = '';

    /** @var string */
    protected string $status = self::STATUS_CREATING;

    /** @var int */
    protected int $backupId = 0;

    /** @var string */
    protected string $backupName = '';

    /** @var string */
    protected string $backupDate = '';

    /** @var string */
    protected string $wpVersion = '';

    /** @var string */
    protected string $dupVersion = '';

    /** @var string Isolates staging tables from production using format: dstg{id}_{prefix} */
    protected string $dbPrefix = '';

    /** @var string */
    protected string $title = '';

    /** @var string Visual distinction for staging admin (e.g., 'sunrise', 'midnight') */
    protected string $colorScheme = 'fresh';

    /** @var string */
    protected string $installerLink = '';

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->hash  = self::generateHash();
        $this->title = __('Staging Site', 'duplicator-pro');
    }

    /**
     * Create a new staging entity with initial values
     *
     * @param int    $backupId    Source backup package ID
     * @param string $backupName  Source backup name
     * @param string $backupDate  Source backup date
     * @param string $title       Staging site title (optional)
     * @param string $colorScheme Admin color scheme (optional)
     *
     * @return self
     */
    public static function create(
        int $backupId,
        string $backupName,
        string $backupDate,
        string $title = '',
        string $colorScheme = 'fresh'
    ): self {
        $entity              = new self();
        $entity->backupId    = $backupId;
        $entity->backupName  = $backupName;
        $entity->backupDate  = $backupDate;
        $entity->colorScheme = $colorScheme;
        $entity->status      = self::STATUS_CREATING;
        $entity->title       = !empty($title)
            ? $title
            : sprintf(__('Staging from %s', 'duplicator-pro'), $backupName);

        return $entity;
    }

    /**
     * Entity type
     *
     * @return string
     */
    public static function getType(): string
    {
        return 'Staging_Entity';
    }

    /**
     * Generates cryptographically secure hash for URL obfuscation
     *
     * @return string 32-character hex string
     */
    public static function generateHash(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Unique identifier combining ID and hash for security
     *
     * @return string Format: {id}_{hash}
     */
    public function getIdentifier(): string
    {
        return $this->getId() . '_' . $this->hash;
    }

    /**
     * Get the staging site folder path
     *
     * @return string
     */
    public function getPath(): string
    {
        return StagingAddon::getStagingBasePath() . '/' . $this->getIdentifier();
    }

    /**
     * Get the staging site URL
     *
     * @return string
     */
    public function getUrl(): string
    {
        return StagingAddon::getStagingBaseUrl() . '/' . $this->getIdentifier();
    }

    /**
     * Get the admin URL for the staging site
     *
     * @return string
     */
    public function getAdminUrl(): string
    {
        return trailingslashit($this->getUrl()) . 'wp-admin/';
    }

    /**
     * Check if the staging folder exists
     *
     * @return bool
     */
    public function folderExists(): bool
    {
        return is_dir($this->getPath());
    }

    /**
     * Get the database prefix for staging tables
     *
     * @return string
     */
    public function getDbPrefix(): string
    {
        return $this->dbPrefix;
    }

    /**
     * Generate unique database prefix for this staging site
     *
     * Format: dstg{id}_{original_prefix}
     *
     * @return string
     */
    public function generateDbPrefix(): string
    {
        global $wpdb;
        $this->dbPrefix = 'dstg' . $this->getId() . '_' . $wpdb->base_prefix;
        return $this->dbPrefix;
    }

    /**
     * Get status
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Set status
     *
     * @param string $status Status constant
     *
     * @return void
     */
    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    /**
     * Check if staging site is ready
     *
     * @return bool
     */
    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    /**
     * Check if staging site is pending installation
     *
     * @return bool
     */
    public function isPendingInstall(): bool
    {
        return $this->status === self::STATUS_PENDING_INSTALL;
    }

    /**
     * Check if staging installation is complete by querying the staging site's migration data
     *
     * @return bool True if staging site installation is complete
     */
    public function isInstallComplete(): bool
    {
        global $wpdb;

        if (empty($this->dbPrefix)) {
            return false;
        }

        $optionsTable = esc_sql($this->dbPrefix . 'options');

        $migrationData = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM `{$optionsTable}` WHERE option_name = %s",
                MigrationMng::MIGRATION_DATA_OPTION
            )
        );

        if (empty($migrationData)) {
            return false;
        }

        $data = json_decode($migrationData, true);

        return is_array($data) && !empty($data['staging']['enabled']);
    }

    /**
     * Get hash
     *
     * @return string
     */
    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * Get backup ID
     *
     * @return int
     */
    public function getBackupId(): int
    {
        return $this->backupId;
    }

    /**
     * Get backup name
     *
     * @return string
     */
    public function getBackupName(): string
    {
        return $this->backupName;
    }

    /**
     * Get backup date
     *
     * @return string
     */
    public function getBackupDate(): string
    {
        return $this->backupDate;
    }

    /**
     * Get WordPress version
     *
     * @return string
     */
    public function getWpVersion(): string
    {
        return $this->wpVersion;
    }

    /**
     * Set WordPress version
     *
     * @param string $version WordPress version
     *
     * @return void
     */
    public function setWpVersion(string $version): void
    {
        $this->wpVersion = $version;
    }

    /**
     * Get Duplicator version
     *
     * @return string
     */
    public function getDupVersion(): string
    {
        return $this->dupVersion;
    }

    /**
     * Set Duplicator version
     *
     * @param string $version Duplicator version
     *
     * @return void
     */
    public function setDupVersion(string $version): void
    {
        $this->dupVersion = $version;
    }

    /**
     * Get title
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Get color scheme
     *
     * @return string
     */
    public function getColorScheme(): string
    {
        return $this->colorScheme;
    }

    /**
     * Get installer link
     *
     * @return string
     */
    public function getInstallerLink(): string
    {
        return $this->installerLink;
    }

    /**
     * Set installer link
     *
     * @param string $link Installer URL
     *
     * @return void
     */
    public function setInstallerLink(string $link): void
    {
        $this->installerLink = $link;
    }

    /**
     * Get formatted creation date
     *
     * @param string $format Date format, defaults to WordPress date format
     *
     * @return string
     */
    public function getCreatedFormatted(string $format = ''): string
    {
        if (empty($format)) {
            $format = get_option('date_format') . ' ' . get_option('time_format');
        }

        return date_i18n($format, strtotime($this->created));
    }

    /**
     * Delete staging site data (folder and tables)
     *
     * @return bool true on success
     */
    public function delete(): bool
    {
        $id = $this->getId();

        do_action('duplicator_before_staging_delete', $this);

        $this->dropStagingTables();

        if ($this->folderExists()) {
            SnapIO::rrmdir($this->getPath());
        }

        $result = parent::delete();

        do_action('duplicator_after_staging_delete', $id, $result);

        return $result;
    }

    /**
     * Drop all database tables with the staging prefix
     *
     * @return int Number of tables dropped
     */
    public function dropStagingTables(): int
    {
        global $wpdb;

        if (empty($this->dbPrefix)) {
            return 0;
        }

        $tables = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $wpdb->esc_like($this->dbPrefix) . '%'
            ),
            ARRAY_N
        );

        if (empty($tables)) {
            return 0;
        }

        // Collect valid table names
        $tablesToDrop = [];
        foreach ($tables as $table) {
            $tableName = $table[0];

            // Security: Validate table name matches our expected prefix pattern.
            // Defense-in-depth even though SHOW TABLES output is trusted.
            // The prefix format is: dstg{id}_{wp_prefix} (e.g., "dstg1_wp_")
            if (strpos($tableName, $this->dbPrefix) !== 0) {
                continue;
            }

            $tablesToDrop[] = '`' . $tableName . '`';
        }

        if (empty($tablesToDrop)) {
            return 0;
        }

        // Drop all tables in a single query
        $tableList = implode(', ', $tablesToDrop);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table names from SHOW TABLES, validated above
        $wpdb->query("DROP TABLE IF EXISTS {$tableList}");

        return count($tablesToDrop);
    }

    /**
     * Get all staging sites, cleaning up orphans in the process
     *
     * @param bool $cleanOrphans Whether to clean orphan entities
     *
     * @return self[]
     */
    public static function getAllWithCleanup(bool $cleanOrphans = true): array
    {
        $stagingSites = self::getAll(0, 0, null, null, ['col' => 'id', 'mode' => 'DESC']);

        if ($stagingSites === false) {
            return [];
        }

        if (!$cleanOrphans) {
            return $stagingSites;
        }

        $basePath = StagingAddon::getStagingBasePath();

        // Only perform orphan cleanup if base path exists
        if (!is_dir($basePath)) {
            return $stagingSites;
        }

        $folders = scandir($basePath);
        if ($folders === false) {
            // Cannot read directory - skip cleanup to prevent data loss
            return $stagingSites;
        }

        $existingFolder = array_flip($folders);

        $result = [];
        foreach ($stagingSites as $staging) {
            $folderName   = basename($staging->getPath());
            $folderExists = isset($existingFolder[$folderName]);

            if (!$folderExists && $staging->isReady()) {
                $staging->delete();
                continue;
            }
            $result[] = $staging;
        }

        return $result;
    }

    /**
     * Get multiple staging entities by IDs in a single query
     *
     * @param int[] $ids Array of entity IDs
     *
     * @return self[] Array of entities
     */
    public static function getByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        global $wpdb;

        $ids          = array_map('intval', $ids);
        $ids          = array_filter($ids, fn($id) => $id > 0);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $query = $wpdb->prepare(
            "SELECT * FROM `" . self::getTableName(true) . "` WHERE `id` IN ({$placeholders})",
            ...$ids
        );

        $rows = $wpdb->get_results($query, ARRAY_A);
        if ($rows === null) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $entity = static::getModelFromRow($row);
            if ($entity !== false) {
                $result[] = $entity;
            }
        }

        return $result;
    }

    /**
     * Find staging entity by identifier
     *
     * @param string $identifier Staging identifier ({id}_{hash})
     *
     * @return self|false
     */
    public static function getByIdentifier(string $identifier)
    {
        $parts = explode('_', $identifier, 2);
        if (count($parts) !== 2) {
            return false;
        }

        $id   = (int) $parts[0];
        $hash = $parts[1];

        $entity = self::getById($id);
        if ($entity === false || $entity->getHash() !== $hash) {
            return false;
        }

        return $entity;
    }
}
