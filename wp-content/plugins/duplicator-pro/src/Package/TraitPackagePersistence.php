<?php

/**
 * Trait for package persistence operations (CRUD)
 *
 * @package   Duplicator
 * @copyright (c) 2017, Snap Creek LLC
 */

declare(strict_types=1);

namespace Duplicator\Package;

use Duplicator\Installer\Package\ArchiveDescriptor;
use Duplicator\Installer\Package\DescriptorDBTableInfo;
use Duplicator\Libs\DupArchive\Headers\DupArchiveFileHeader;
use Duplicator\Libs\DupArchive\Headers\DupArchiveHeader;
use Duplicator\Libs\DupArchive\Processors\DupArchiveProcessingFailure;
use Duplicator\Libs\Index\FileIndexManager;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapOrigFileManager;
use Duplicator\Libs\Snap\SnapString;
use Duplicator\Libs\Snap\SnapWP;
use Duplicator\Libs\WpConfig\WPConfigTransformer;
use Duplicator\Models\ActivityLog\LogEventBackupDelete;
use Duplicator\Models\Storages\Local\LocalStorage;
use Duplicator\Package\Archive\Filters\ArchiveFitersInfo;
use Duplicator\Package\Archive\Filters\ScopeBase;
use Duplicator\Package\Archive\Filters\ScopeDirectory;
use Duplicator\Package\Archive\Filters\ScopeFile;
use Duplicator\Package\Archive\PackageArchive;
use Duplicator\Package\Create\BuildComponents;
use Duplicator\Package\Create\BuildProgress;
use Duplicator\Package\Create\DbBuildProgress;
use Duplicator\Package\Create\DupArchive\PackageDupArchiveCreateState;
use Duplicator\Package\Create\DupArchive\PackageDupArchiveExpandState;
use Duplicator\Package\Create\PackInstaller;
use Duplicator\Package\Create\Scan\Tree\Tree;
use Duplicator\Package\Database\DatabaseInfo;
use Duplicator\Package\Database\DatabasePkg;
use Duplicator\Package\Recovery\RecoveryPackage;
use Duplicator\Package\Recovery\RecoveryStatus;
use Duplicator\Package\Storage\UploadInfo;
use Duplicator\Utils\Logging\DupLog;
use Exception;
use ReflectionObject;
use Throwable;
use VendorDuplicator\Amk\JsonSerialize\JsonSerialize;
use VendorDuplicator\Amk\JsonSerialize\JsonUnserializeMap;

/**
 * Trait TraitPackagePersistence
 *
 * Handles package persistence operations including save, update, and delete
 * for database records and local storage files.
 *
 * @phpstan-require-extends AbstractPackage
 *
 * @property int<-1,max>   $ID        Package ID
 * @property string        $version   Package version
 * @property string        $name      Package name
 * @property string        $hash      Package hash
 * @property int           $status    Package status
 * @property string[]      $flags     Package flags
 * @property string        $created   Created date
 * @property string        $updated   Updated date
 * @property PackInstaller $Installer Package installer object
 */
trait TraitPackagePersistence
{
    /**
     * Save package to database
     *
     * @param bool $die if true die on error otherwise return true on success and false on error
     *
     * @return bool
     */
    public function save($die = true)
    {
        if ($this->ID < 1) {
            /** @var \wpdb $wpdb */
            global $wpdb;

            $this->version = DUPLICATOR_VERSION;
            // Created is set in the constructor
            $this->updated = gmdate("Y-m-d H:i:s");

            $results = $wpdb->insert(
                static::getTableName(),
                [
                    'type'         => static::getBackupType(),
                    'name'         => $this->name,
                    'hash'         => $this->hash,
                    'archive_name' => $this->getArchiveFilename(),
                    'status'       => 0,
                    'flags'        => '',
                    'package'      => '',
                    'version'      => $this->version,
                    'created'      => $this->created,
                    'updated_at'   => $this->updated,
                ],
                [
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                ]
            );
            if ($results === false) {
                DupLog::trace("Problem inserting Backup: {$wpdb->last_error}");
                if ($die) {
                    DupLog::errorAndDie(
                        "Duplicator is unable to insert a Backup record into the database table.",
                        "'{$wpdb->last_error}'"
                    );
                }
                return false;
            }
            $this->ID = $wpdb->insert_id;
        }
        // I run the update in each case even after the insert because the saved object does not have the id
        return $this->update($die);
    }

    /**
     * Update Backup in database
     *
     * @param bool $die if true die on error otherwise return true on success and false on error
     *
     * @return bool
     */
    public function update($die = true): bool
    {
        /** @var \wpdb $wpdb */
        global $wpdb;

        $this->cleanObjectBeforeSave();
        $this->updatePackageFlags();
        $this->version = DUPLICATOR_VERSION;
        $this->updated = gmdate("Y-m-d H:i:s");

        $packageObj = JsonSerialize::serialize($this, JSON_PRETTY_PRINT | JsonSerialize::JSON_SKIP_CLASS_NAME);
        if (!$packageObj) {
            if ($die) {
                DupLog::errorAndDie("Backup SetStatus was unable to serialize Backup object while updating record.");
            }
            return false;
        }
        $wpdb->flush();
        if (
            $wpdb->update(
                static::getTableName(),
                [
                    'name'         => $this->name,
                    'hash'         => $this->hash,
                    'archive_name' => $this->getArchiveFilename(),
                    'status'       => (int) $this->status,
                    'flags'        => implode(',', $this->flags),
                    'package'      => $packageObj,
                    'version'      => $this->version,
                    'created'      => $this->created,
                    'updated_at'   => $this->updated,
                ],
                ['ID' => $this->ID],
                [
                    '%s',
                    '%s',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                ],
                ['%d']
            ) === false
        ) {
            if ($die) {
                DupLog::errorAndDie("Database update error: " . $wpdb->last_error);
            } else {
                DupLog::infoTrace("Database update error: " . $wpdb->last_error);
            }
            return false;
        }

        return true;
    }

    /**
     * Delete package from database and files
     *
     * @param boolean $delete_temp Deprecated, always true
     *
     * @return boolean
     */
    public function delete($delete_temp = false)
    {
        // Log deletion for completed backups only
        if ($this->getStatus() === AbstractPackage::STATUS_COMPLETE) {
            try {
                LogEventBackupDelete::create($this);
            } catch (Exception $e) {
                DupLog::traceError('Failed to create deletion log event: ' . $e->getMessage());
            }
        }

        $ret_val = false;
        global $wpdb;
        $tblName   = static::getTableName();
        $getResult = $wpdb->get_results($wpdb->prepare("SELECT name, hash FROM `{$tblName}` WHERE id = %d", $this->ID), ARRAY_A);
        if ($getResult) {
            $row       = $getResult[0];
            $name_hash = "{$row['name']}_{$row['hash']}";
            $delResult = $wpdb->query($wpdb->prepare("DELETE FROM `{$tblName}` WHERE id = %d", $this->ID));
            if ($delResult != 0) {
                $ret_val = true;
                static::deleteDefaultLocalFiles($name_hash, $delete_temp);
                $this->deleteLocalStorageFiles();
            }
        }

        return $ret_val;
    }

    /**
     * Delete local storage files
     *
     * @return void
     */
    protected function deleteLocalStorageFiles(): void
    {
        $storages           = $this->getStorages();
        $archive_filename   = $this->getArchiveFilename();
        $installer_filename = $this->Installer->getInstallerLocalName();
        $index_filename     = $this->getIndexFileName();

        foreach ($storages as $storage) {
            if ($storage->getSType() !== LocalStorage::getSType()) {
                continue;
            }
            $path               = $storage->getLocationString();
            $archive_filepath   = $path . "/" . $archive_filename;
            $installer_filepath = $path . "/" . $installer_filename;
            $index_filepath     = $path . "/" . $index_filename;
            @unlink($archive_filepath);
            @unlink($installer_filepath);
            @unlink($index_filepath);
        }
    }

    /**
     * Removes all files related to the namehash from the directory
     *
     * @param string $nameHash       Package namehash
     * @param string $dir            path to dir
     * @param bool   $deleteLogFiles if set to true will delete log files too
     *
     * @return void
     */
    public static function deletePackageFilesInDir($nameHash, $dir, $deleteLogFiles = false): void
    {
        $globFiles = glob(SnapIO::safePath(SnapIO::untrailingslashit($dir) . "/" . $nameHash . "_*"));
        foreach ($globFiles as $globFile) {
            if (!$deleteLogFiles && SnapString::endsWith($globFile, '_log.txt')) {
                DupLog::trace("Skipping purge of $globFile because deleteLogFiles is false.");
                continue;
            }

            if (SnapIO::unlink($globFile)) {
                DupLog::trace("Successful purge of $globFile.");
            } else {
                DupLog::trace("Failed purge of $globFile.");
            }
        }
    }

    /**
     * Delete default local files
     *
     * @param string $name_hash   Package namehash
     * @param bool   $delete_temp if set to true will delete temp files too
     *
     * @return void
     */
    public static function deleteDefaultLocalFiles($name_hash, $delete_temp): void
    {
        if ($delete_temp) {
            static::deletePackageFilesInDir($name_hash, DUPLICATOR_SSDIR_PATH_TMP, true);
        }
        static::deletePackageFilesInDir($name_hash, DUPLICATOR_SSDIR_PATH, false);
    }

    /**
     * Use only in extreme cases to get rid of a runaway Backup
     *
     * @param int $id Backup ID
     *
     * @return bool
     */
    public static function forceDelete(int $id): bool
    {
        $ret_val = false;
        global $wpdb;
        $tblName   = static::getTableName();
        $getResult = $wpdb->get_results($wpdb->prepare("SELECT name, hash FROM `{$tblName}` WHERE id = %d", $id), ARRAY_A);
        if ($getResult) {
            $row       = $getResult[0];
            $name_hash = "{$row['name']}_{$row['hash']}";
            $delResult = $wpdb->query($wpdb->prepare("DELETE FROM `{$tblName}` WHERE id = %d", $id));
            if ($delResult != 0) {
                $ret_val = true;
                static::deleteDefaultLocalFiles($name_hash, true);
            }
        }

        return $ret_val;
    }

    /**
     * Gets the Backup by ID
     *
     * @param int $id Backup ID
     *
     * @return false|static false if fail
     */
    public static function getById($id)
    {
        if ($id < 0) {
            return false;
        }

        global $wpdb;
        $table = static::getTableName();
        $sql   = $wpdb->prepare("SELECT * FROM `{$table}` where ID = %d", $id);
        $row   = $wpdb->get_row($sql);
        // DupLog::traceObject('Object row', $row);
        if ($row) {
            return static::packageFromRow($row);
        } else {
            return false;
        }
    }

    /**
     * Get Backup table name
     *
     * @return string
     */
    public static function getTableName()
    {
        global $wpdb;
        return $wpdb->base_prefix . "duplicator_backups";
    }

    /**
     * Init entity table
     *
     * @return string[] Strings containing the results of the various update queries.
     */
    final public static function initTable()
    {
        /** @var \wpdb $wpdb */
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $tableName       = static::getTableName();

        $flags = [
            AbstractPackage::FLAG_MANUAL,
            AbstractPackage::FLAG_SCHEDULE,
            AbstractPackage::FLAG_SCHEDULE_RUN_NOW,
            AbstractPackage::FLAG_DB_ONLY,
            AbstractPackage::FLAG_MEDIA_ONLY,
            AbstractPackage::FLAG_HAVE_LOCAL,
            AbstractPackage::FLAG_HAVE_REMOTE,
            AbstractPackage::FLAG_DISASTER_AVAIABLE,
            AbstractPackage::FLAG_DISASTER_SET,
            AbstractPackage::FLAG_CREATED_AFTER_RESTORE,
            AbstractPackage::FLAG_ACTIVE,
            AbstractPackage::FLAG_TEMPLATE,
            AbstractPackage::FLAG_ZIP_ARCHIVE,
            AbstractPackage::FLAG_DUP_ARCHIVE,
            AbstractPackage::FLAG_TEMPORARY,
        ];

        $flagsStr = array_map(fn($flag): string => "'{$flag}'", $flags);
        $flagsStr = implode(',', $flagsStr);

        // PRIMARY KEY must have 2 spaces before for dbDelta to work
        // Mysql 5.5 can't have more than 1 DEFAULT CURRENT_TIMESTAMP
        $sql = <<<SQL
CREATE TABLE `{$tableName}` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `type` varchar(100) NOT NULL,
    `name` varchar(250) NOT NULL,
    `hash` varchar(50) NOT NULL,
    `archive_name` varchar(350) NOT NULL DEFAULT '',
    `status` int(11) NOT NULL,
    `flags` set({$flagsStr}) NOT NULL DEFAULT '',
    `package` longtext NOT NULL,
    `version` varchar(30) NOT NULL DEFAULT '',
    `created` TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (`id`),
    KEY `type_idx` (`type`),
    KEY `hash` (`hash`),
    KEY `flags` (`flags`),
    KEY `version` (`version`),
    KEY `created` (`created`),
    KEY `updated_at` (`updated_at`),
    KEY `status` (`status`),
    KEY `name` (`name`(191)),
    KEY `archive_name` (`archive_name`(191))
) {$charset_collate};
SQL;

        return SnapWP::dbDelta($sql);
    }

    /**
     * Create package from database row
     *
     * @param object $row Database row
     *
     * @return ?static
     */
    protected static function packageFromRow($row)
    {
        $package = null;

        if (strlen($row->hash) == 0) {
            DupLog::trace("Hash is 0 for the Backup $row->id...");
            return null;
        }

        if (property_exists($row, 'id')) {
            $row->id = (int) $row->id;
        }
        if (property_exists($row, 'type')) {
            $row->type = (string) $row->type;
        }
        if (property_exists($row, 'status')) {
            $row->status = (int) $row->status;
        }
        if (property_exists($row, 'flags')) {
            $row->flags = strlen($row->flags) == 0 ? [] : explode(',', $row->flags);
        }

        try {
            $class   = static::getClassNameByType($row->type);
            $package = static::getFromJson($row->package, $class, $row);
        } catch (Throwable $ex) {
            DupLog::infoTraceException($ex, "Problem getting Backup from json.");
            return null;
        }

        return $package;
    }

    /**
     * Return the package class name by type
     *
     * @param string $type Backup type
     *
     * @return class-string<AbstractPackage>
     */
    final protected static function getClassNameByType(string $type): string
    {
        $typesMap = apply_filters('duplicator_package_type_classes_map', []);

        if (isset($typesMap[$type])) {
            if (!is_subclass_of($typesMap[$type], AbstractPackage::class)) {
                throw new Exception("Package type $type is not a subclass of " . AbstractPackage::class);
            }
            return $typesMap[$type];
        } else {
            throw new Exception("Package type $type not supported");
        }
    }

    /**
     * Return Backup from json
     *
     * @param string       $json      json string
     * @param class-string $mainClass Main object class name
     * @param ?object      $rowData   Database row data
     *
     * @return static
     */
    protected static function getFromJson(string $json, string $mainClass, ?object $rowData = null)
    {
        if (!is_subclass_of($mainClass, AbstractPackage::class)) {
            throw new Exception("Package type {$mainClass} is not a subclass of " . AbstractPackage::class);
        }

        $map = new JsonUnserializeMap(
            [
                ''                                           => 'cl:' . $mainClass,
                'Archive'                                    => 'cl:' . PackageArchive::class,
                'Archive/Package'                            => 'rf:',
                'Archive/FileIndexManager'                   => 'cl:' . FileIndexManager::class,
                'Archive/FilterInfo'                         => 'cl:' . ArchiveFitersInfo::class,
                'Archive/FilterInfo/Dirs'                    => '?cl:' . ScopeDirectory::class,
                'Archive/FilterInfo/Files'                   => '?cl:' . ScopeFile::class,
                'Archive/FilterInfo/Exts'                    => '?cl:' . ScopeBase::class,
                'Archive/FilterInfo/TreeSize'                => '?cl:' . Tree::class,
                'Multisite'                                  => 'cl:' . PackMultisite::class,
                'Installer'                                  => 'cl:' . PackInstaller::class,
                'Installer/Package'                          => 'rf:',
                'Installer/origFileManger'                   => '?cl:' . SnapOrigFileManager::class,
                'Installer/configTransformer'                => '?cl:' . WPConfigTransformer::class,
                'Installer/archiveDescriptor'                => '?cl:' . ArchiveDescriptor::class,
                'Database'                                   => 'cl:' . DatabasePkg::class,
                'Database/Package'                           => 'rf:',
                'Database/info'                              => 'cl:' . DatabaseInfo::class,
                'Database/info/tablesList/*'                 => 'cl:' . DescriptorDBTableInfo::class,
                'build_progress'                             => 'cl:' . BuildProgress::class,
                'build_progress/dupCreate'                   => '?cl:' . PackageDupArchiveCreateState::class,
                'build_progress/dupCreate/package'           => 'rf:',
                'build_progress/dupCreate/archiveHeader'     => 'cl:' . DupArchiveHeader::class,
                'build_progress/dupCreate/failures/*'        => 'cl:' . DupArchiveProcessingFailure::class,
                'build_progress/dupExpand'                   => '?cl:' . PackageDupArchiveExpandState::class,
                'build_progress/dupExpand/package'           => 'rf:',
                'build_progress/dupExpand/archiveHeader'     => 'cl:' . DupArchiveHeader::class,
                'build_progress/dupExpand/currentFileHeader' => '?cl:' . DupArchiveFileHeader::class,
                'build_progress/dupExpand/failures/*'        => 'cl:' . DupArchiveProcessingFailure::class,
                'db_build_progress'                          => 'cl:' . DbBuildProgress::class,
                'upload_infos/*'                             => 'cl:' . UploadInfo::class,
            ]
        );

        /** @var ?static */
        $package = JsonSerialize::unserializeWithMap($json, $map);
        if (!$package instanceof $mainClass) {
            throw new Exception('Can\'t read json object ');
        }
        // MAKE SURE THIS IS TRUE TO AVOID INFINITE LOOPS
        $package->flagUpdatedAfterLoad = true;

        if (is_object($rowData)) {
            $reflect = new ReflectionObject($package);

            $dbValuesToProps = [
                'id'         => 'ID',
                'name'       => 'name',
                'hash'       => 'hash',
                'status'     => 'status',
                'flags'      => 'flags',
                'version'    => 'version',
                'created'    => 'created',
                'updated_at' => 'updated',
            ];

            foreach ($dbValuesToProps as $dbKey => $propName) {
                if (
                    !isset($rowData->{$dbKey}) ||
                    !property_exists($package, $propName)
                ) {
                    continue;
                }

                $prop = $reflect->getProperty($propName);
                if (PHP_VERSION_ID < 80100) {
                    $prop->setAccessible(true);
                }
                $prop->setValue($package, $rowData->{$dbKey});
            }
        }

        if ($package->execType) {
            if (strlen($package->getVersion()) == 0) {
                $tmp              = JsonSerialize::unserialize($json);
                $package->version = $tmp['Version'];
            }
        }

        // For legacy packages, set execType if not set
        if ($package->execType === AbstractPackage::EXEC_TYPE_NOT_SET) {
            if ($package->hasFlag(AbstractPackage::FLAG_MANUAL)) {
                $package->execType = AbstractPackage::EXEC_TYPE_MANUAL;
            } elseif ($package->hasFlag(AbstractPackage::FLAG_SCHEDULE)) {
                $package->execType = AbstractPackage::EXEC_TYPE_SCHEDULED;
            } elseif ($package->hasFlag(AbstractPackage::FLAG_SCHEDULE_RUN_NOW)) {
                $package->execType = AbstractPackage::EXEC_TYPE_RUN_NOW;
            }
        }

        // THIS MUST BE SET AT THE END OF THE FUNCTION TO AVOID INFINITE LOOPS.
        // DON'T move this line elsewhere, as it ensures that the package flags
        // are updated correctly without causing recursive calls to updatePackageFlags().
        $package->flagUpdatedAfterLoad = false;
        return $package;
    }

    /**
     * Update package flags based on current state
     *
     * @return void
     */
    protected function updatePackageFlags(): void
    {
        if (empty($this->flags)) {
            switch ($this->getExecutionType()) {
                case AbstractPackage::EXEC_TYPE_MANUAL:
                    $this->flags[] = AbstractPackage::FLAG_MANUAL;
                    break;
                case AbstractPackage::EXEC_TYPE_SCHEDULED:
                    $this->flags[] = AbstractPackage::FLAG_SCHEDULE;
                    break;
                case AbstractPackage::EXEC_TYPE_RUN_NOW:
                    $this->flags[] = AbstractPackage::FLAG_SCHEDULE_RUN_NOW;
                    break;
            }

            $this->flags[] = $this->Archive->Format == 'ZIP' ? AbstractPackage::FLAG_ZIP_ARCHIVE : AbstractPackage::FLAG_DUP_ARCHIVE;

            if ($this->isDBOnly()) {
                $this->flags[] = AbstractPackage::FLAG_DB_ONLY;
            }

            if (BuildComponents::isMediaOnly($this->components)) {
                $this->flags[] = AbstractPackage::FLAG_MEDIA_ONLY;
            }
        }

        $this->flags = array_diff(
            $this->flags,
            [
                AbstractPackage::FLAG_HAVE_LOCAL,
                AbstractPackage::FLAG_HAVE_REMOTE,
                AbstractPackage::FLAG_DISASTER_SET,
                AbstractPackage::FLAG_DISASTER_AVAIABLE,
            ]
        );

        if ($this->status == AbstractPackage::STATUS_COMPLETE) {
            // ONLY for complete Backups
            if ($this->haveLocalStorage()) {
                $this->flags[] = AbstractPackage::FLAG_HAVE_LOCAL;
            }

            if ($this->haveRemoteStorage()) {
                $this->flags[] = AbstractPackage::FLAG_HAVE_REMOTE;
            }

            if (RecoveryPackage::getRecoverPackageId() === $this->ID) {
                $this->flags[] = AbstractPackage::FLAG_DISASTER_SET;
            } else {
                $status = new RecoveryStatus($this);
                if ($status->isRecoverable()) {
                    $this->flags[] = AbstractPackage::FLAG_DISASTER_AVAIABLE;
                }
            }
        }
    }

    /**
     * Clean object before save
     *
     * @return void
     */
    protected function cleanObjectBeforeSave(): void
    {
        if ($this->status == AbstractPackage::STATUS_COMPLETE || $this->status < AbstractPackage::STATUS_PRE_PROCESS) {
            // If complete or error, clean build progress to remove temp data
            $this->build_progress->reset();
            $this->db_build_progress->reset();
            $this->Archive->FilterInfo->reset();

            // For error states, ensure failed flag is consistent with status
            if ($this->status < AbstractPackage::STATUS_PRE_PROCESS) {
                $this->build_progress->failed = true;
            }
        }
    }
}
