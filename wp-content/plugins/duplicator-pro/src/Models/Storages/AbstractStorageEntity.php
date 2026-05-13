<?php

namespace Duplicator\Models\Storages;

use Duplicator\Models\ActivityLog\LogEventStorageDelete;
use Duplicator\Models\GlobalEntity;
use Duplicator\Utils\Logging\DupLog;
use Duplicator\Package\DupPackage;
use Duplicator\Package\Storage\UploadInfo;
use DateTime;
use Duplicator\Core\Models\AbstractEntity;
use Duplicator\Core\Models\TraitEntitySerializationEncryption;
use Duplicator\Core\Models\TraitGenericModelList;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapURL;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Package\AbstractPackage;
use Duplicator\Utils\Crypt\CryptBlowfish;
use Duplicator\Utils\IncrementalStatusMessage;
use Duplicator\Utils\Settings\ModelMigrateSettingsInterface;
use Duplicator\Package\Storage\StorageTransferChunkFiles;
use Error;
use Exception;
use ReflectionClass;
use VendorDuplicator\Amk\JsonSerialize\JsonSerialize;
use Duplicator\Models\ScheduleEntity;
use Duplicator\Models\StaticGlobal;

abstract class AbstractStorageEntity extends AbstractEntity implements ModelMigrateSettingsInterface
{
    use TraitGenericModelList;
    use TraitEntitySerializationEncryption;

    /**
     * Encrypted properties for storage entities
     *
     * @var string[]
     */
    protected static array $encryptedProperties = ['config'];

    const BACKUP_RECORDS_REMOVE_ALL     = 0;
    const BACKUP_RECORDS_REMOVE_DEFAULT = 1;
    const BACKUP_RECORDS_REMOVE_NEVER   = 2;

    /** @var array<int,string> Class list registered */
    private static $storageTypes = [];

    /** @var string */
    protected $name = '';
    /** @var string */
    protected $notes            = '';
    protected int $storage_type = -1000; // -1000 is the default value, used to detect if the storage type is not set
    /** @var array<string,scalar>  Storage configuration data */
    protected $config = [];
    /** @var IncrementalStatusMessage Inclemental messages system */
    protected \Duplicator\Utils\IncrementalStatusMessage $testLog;
    /** @var ?AbstractStorageAdapter */
    protected $adapter = null;

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->name         = __('New Storage', "duplicator-pro");
        $this->storage_type = static::getSType();
        $this->testLog      = new IncrementalStatusMessage();
        $this->config       = static::getDefaultConfig();
    }

    /**
     * Entity type
     *
     * @return string
     */
    public static function getType(): string
    {
        return 'Storage_Entity';
    }

    /**
     * Return the storage type
     *
     * @return int
     */
    abstract public static function getSType(): int;

    /**
     * Returns the storage type icon URL
     *
     * @return string Returns the storage icon URL
     */
    abstract public static function getStypeIconURL(): string;

    /**
     * Returns the img html icon tag
     *
     * @param bool $title if true add title attr on img tag
     *
     * @return string Returns the img html tag
     */
    public static function getStypeIcon(bool $title = true): string
    {
        $iconUrl = static::getStypeIconURL();
        if (empty($iconUrl)) {
            return '';
        }

        $alt       = esc_attr(static::getStypeName());
        $titleAttr = $title ? ' title="' . $alt . '"' : '';

        return '<img src="' . esc_url($iconUrl) . '" class="dup-storage-icon" alt="' . $alt . '"' . $titleAttr . ' />';
    }

    /**
     * Returns the storage type name.
     *
     * @return string
     */
    abstract public static function getStypeName(): string;

    /**
     * Render the settings page for this storage.
     * Subclasses should override this method to render their own settings page.
     *
     * @return void
     */
    public static function renderGlobalOptions(): void
    {
    }

    /**
     * Initizalize entity from JSON
     *
     * @param string               $json           JSON string
     * @param array<string,scalar> $rowData        DB row data
     * @param ?string              $overwriteClass Overwrite class object, class must extend AbstractEntity
     *
     * @return static
     */
    protected static function getEntityFromJson(string $json, array $rowData, ?string $overwriteClass = null)
    {
        if ($overwriteClass === null) {
            $tmp            = JsonSerialize::unserialize($json);
            $overwriteClass = AbstractStorageEntity::getSTypePHPClass($tmp);
        }
        return parent::getEntityFromJson($json, $rowData, $overwriteClass);
    }

    /**
     * Get default config
     *
     * @return array<string,scalar>
     */
    protected static function getDefaultConfig(): array
    {
        return [
            'storage_folder' => self::getDefaultStorageFolder(),
            'max_packages'   => 10,
        ];
    }

    /**
     * Serialize the storage entity
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        $data = JsonSerialize::serializeToData($this, JsonSerialize::JSON_SKIP_MAGIC_METHODS |  JsonSerialize::JSON_SKIP_CLASS_NAME);

        // Encrypt properties using trait
        $data = $this->encryptSerializedProperties($data);

        unset($data['testLog']);
        unset($data['adapter']);
        return $data;
    }

    /**
     * Unserialize the storage entity
     *
     * @param array<string,mixed> $data Serialized data
     *
     * @return void
     */
    public function __unserialize(array $data): void
    {
        // Decrypt properties
        $data = $this->decryptSerializedProperties($data);

        // Validate config is array, apply defaults if not
        if (!isset($data['config']) || !is_array($data['config'])) {
            $data['config'] = static::getDefaultConfig();
        }

        // Merge config with defaults for any missing keys
        $defaults = static::getDefaultConfig();
        foreach (array_keys($defaults) as $key) {
            if (!isset($data['config'][$key])) {
                $data['config'][$key] = $defaults[$key];
            }
        }

        // Initialize testLog as new instance
        $data['testLog'] = new IncrementalStatusMessage();

        // Assign properties
        foreach ($data as $pName => $val) {
            if (!property_exists($this, $pName)) {
                continue;
            }
            $this->$pName = $val;
        }
    }

    /**
     * Legacy decryption for properties
     *
     * @param array<string,mixed> $data Serialized data
     *
     * @return array<string,mixed> Data with legacy format converted
     */
    protected function legacyDecryptProperties(array $data): array
    {
        // If config is a string, it's the old format - decrypt and decode to array
        if (isset($data['config']) && is_string($data['config'])) {
            $decrypted = CryptBlowfish::decryptIfAvaiable($data['config'], null, true);
            $config    = JsonSerialize::unserialize($decrypted);

            // If decode succeeded, use it (validation happens in __unserialize)
            if (is_array($config)) {
                $data['config'] = $config;
            }
        }

        return $data;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get notes
     *
     * @return string
     */
    public function getNotes(): string
    {
        return $this->notes;
    }

    /**
     * Get priority, used to sort storages; 0 is the highest priority
     *
     * @return int
     */
    abstract public static function getPriority(): int;

    /**
     * Check if a grid break should be inserted after this storage type in the selector UI
     * This allows visual grouping of storage types in the grid layout
     *
     * @return bool True to insert a break after this storage type, false otherwise
     */
    public static function isGridBreakAfter(): bool
    {
        return false;
    }

    /**
     * Register storage type
     *
     * @return void
     */
    public static function registerType(): void
    {
        if (isset(self::$storageTypes[static::getSType()])) {
            throw new Exception("Storage type " . static::getSType() . " already registered with class " . self::$storageTypes[static::getSType()]);
        }
        self::$storageTypes[static::getSType()] = static::class;
    }

    /**
     * Get storages types
     *
     * @return int[]
     */
    final public static function getResisteredTypes(): array
    {
        return array_keys(self::$storageTypes);
    }

    /**
     * Get storages types sorted by priority
     *
     * @return int[]
     */
    final public static function getResisteredTypesByPriority(): array
    {
        $types = self::getResisteredTypes();
        usort($types, function ($a, $b): int {
            $aClass = self::$storageTypes[$a];
            $bClass = self::$storageTypes[$b];
            return $aClass::getPriority() <=> $bClass::getPriority();
        });
        return $types;
    }

    /**
     * Get storage type class
     *
     * @param int|array<string,mixed> $data Storage data or storage type id
     *
     * @return class-string<AbstractStorageEntity>
     */
    final public static function getSTypePHPClass($data): string
    {
        if (is_array($data)) {
            $type  = ($data['storage_type'] ?? UnknownStorage::getSType());
            $class = self::$storageTypes[$type] ?? UnknownStorage::class;
        } else {
            $type  = (int) $data;
            $class = self::$storageTypes[$type] ?? UnknownStorage::class;
            $data  = [];
        }
        return apply_filters('duplicator_storage_type_class', $class, $type, $data);
    }

    /**
     * Get new storage object by type
     *
     * @param int $type Storage type
     *
     * @return self
     */
    final public static function getNewStorageByType(int $type): self
    {
        $class = self::getSTypePHPClass($type);
        /** @var self */
        return new $class();
    }

    /**
     * Render config fields by storage type
     *
     * @param int|self $type Storage type or storage object
     * @param bool     $echo Echo or return
     *
     * @return string
     */
    final public static function renderSTypeConfigFields($type, bool $echo = true): string
    {
        if ($type instanceof self) {
            $storage = $type;
        } else {
            $class = self::getSTypePHPClass($type);
            /** @var self */
            $storage = new $class();
        }
        return $storage->renderConfigFields($echo);
    }

    /**
     * Get storage adapter
     *
     * @return AbstractStorageAdapter
     */
    abstract protected function getAdapter(): AbstractStorageAdapter;

    /**
     * Update data from http request, this method don't save data, just update object properties
     *
     * @param string $message Message
     *
     * @return bool True if success and all data is valid, false otherwise
     */
    public function updateFromHttpRequest(&$message = ''): bool
    {
        $this->adapter = null; // Reset the adapter on update
        $this->name    = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'name', '');

        if (strlen($this->name) == 0) {
            $message = __('Storage name is required.', 'duplicator-pro');
            return false;
        }

        $this->notes = SnapUtil::sanitizeDefaultInput(SnapUtil::INPUT_REQUEST, 'notes', '');

        return true;
    }

    /**
     * Sanitize storage folder
     *
     * @param string $inputKey Input key
     * @param string $root     add,remove,none (add root, remove root, do nothing)
     *
     * @return string
     */
    protected static function getSanitizedInputFolder(string $inputKey, string $root = 'none'): string
    {
        $folder = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, $inputKey, '');
        $folder = SnapIO::safePathUntrailingslashit($folder);
        $folder = trim(stripslashes($folder));
        $folder = ltrim($folder, '/\\');

        switch ($root) {
            case 'add':
                $folder = ltrim($folder, '/\\');
                $folder = '/' . $folder;
                break;
            case 'remove':
                $folder = ltrim($folder, '/\\');
                break;
            case 'none':
            default:
                break;
        }

        return $folder;
    }

    /**
     * Is unique, if true only one storage of this type can exist
     *
     * @return bool
     */
    public static function isUnique(): bool
    {
        return false;
    }

    /**
     * Is hidden, if true the storage type will not be shown in the storage list, and is not selectable, it's only for internal use
     *
     * @return bool
     */
    public static function isHidden(): bool
    {
        return false;
    }

    /**
     * Check if this storage type should be disabled in the selector
     * This is used to show the storage in the UI but prevent selection
     *
     * @param string $reason Reference to store the reason why it's disabled
     *
     * @return bool True if disabled, false if selectable
     */
    public static function isSelectDisabled(string &$reason = ''): bool
    {
        // Check if unique and already exists
        if (static::isUnique()) {
            $storages = static::getAllBySType(static::getSType());
            if (count($storages) > 0) {
                $reason = sprintf(
                    __('Only one %s storage can be created at a time', 'duplicator-pro'),
                    static::getStypeName()
                );
                return true;
            }
        }

        // Check if supported (PHP extensions, etc.)
        if (!static::isSupported()) {
            $reason = static::getNotSupportedNotice();
            return true;
        }

        $reason = '';
        return false;
    }

    /**
     * Is type selectable, if false the storage type will not be shown in the storage selector UI
     * This controls visibility, not whether it's clickable (use isSelectDisabled for that)
     *
     * @return bool
     */
    public static function isSelectable(): bool
    {
        // Only hidden storages are not selectable (completely hidden from UI)
        return !static::isHidden();
    }

    /**
     * If storage is default can't be deleted and the name can't be changed
     *
     * @return bool
     */
    public static function isDefault(): bool
    {
        return false;
    }

    /**
     * Check if storage is supported
     *
     * @return bool
     */
    public static function isSupported(): bool
    {
        return true;
    }

    /**
     * Get supported notice, displayed if storage isn't supported
     *
     * @return string html string or empty if storage is supported
     */
    public static function getNotSupportedNotice(): string
    {
        if (self::isSupported()) {
            return '';
        }

        $result = sprintf(
            __(
                'The Storage %s is not supported on this server.',
                'duplicator-pro'
            ),
            static::getStypeName()
        );
        return esc_html($result);
    }

    /**
     * Returns true if storage type is local
     *
     * @return bool
     */
    public static function isLocal(): bool
    {
        return false;
    }

    /**
     * Get storage folder
     *
     * @return string
     */
    protected function getStorageFolder(): string
    {
        return (string) $this->config['storage_folder'];
    }

    /**
     * Get storage location string
     *
     * @return string
     */
    abstract public function getLocationString(): string;

    /**
     * Returns an html anchor tag of location
     *
     * @return string Returns an html anchor tag with the storage location as a hyperlink.
     *
     * @example
     * OneDrive Example return
     * <a target="_blank" href="https://1drv.ms/f/sAFrQtasdrewasyghg">https://1drv.ms/f/sAFrQtasdrewasyghg</a>
     */
    public function getHtmlLocationLink(): string
    {
        if ($this->isValid()) {
            return '<a href="' . esc_url($this->getLocationString()) . '" target="_blank" >' . esc_html($this->getLocationString()) . '</a>';
        } else {
            return esc_html($this->getLocationString());
        }
    }

    /**
     * Check if storage is valid and ready to use.
     *
     * @param ?string $errorMsg Reference to store error message
     * @param bool    $force    Force the storage to be revalidated
     *
     * @return bool Return true if storage is valid and ready to use, false otherwise
     */
    final public function isValid(?string &$errorMsg = '', bool $force = false): bool
    {
        try {
            return $this->realIsValid($errorMsg, $force);
        } catch (\Throwable $e) {
            DupLog::infoTraceException($e, "Storage validation failed: ");
            $errorMsg = $e->getMessage();
            return false;
        }
    }

    /**
     * Check if storage is valid and ready to use.
     *
     * Subclasses must implement this method. Exceptions thrown here are caught
     * by the final isValid() wrapper, so the storage list page won't crash.
     *
     * @param ?string $errorMsg Reference to store error message
     * @param bool    $force    Force the storage to be revalidated
     *
     * @return bool Return true if storage is valid and ready to use, false otherwise
     */
    abstract protected function realIsValid(?string &$errorMsg = '', bool $force = false): bool;

    /**
     * Return max storage Backups, 0 unlimited
     *
     * @return int<0,max>
     */
    public function getMaxPackages(): int
    {
        /** @var int<0,max> */
        return $this->config['max_packages'];
    }

    /**
     * Get upload chunk size in bytes
     *
     * @return int bytes size, -1 unlimited
     */
    abstract public function getUploadChunkSize(): int;

    /**
     * Get download chunk size in bytes
     *
     * @return int bytes size, -1 unlimited
     */
    abstract public function getDownloadChunkSize(): int;

    /**
     * Get upload chunk timeout in seconds
     *
     * @return int timeout in microseconds, 0 unlimited
     */
    public function getUploadChunkTimeout(): int
    {
        return 0;
    }

    /**
     * Return Backup transfer files
     *
     * @param AbstractPackage $package the Backup
     *
     * @return array<string,string> return array from => to
     */
    protected function getPackageUploadFiles(AbstractPackage $package): array
    {
        return [
            $package->Installer->getSafeFilePath() => $package->Installer->getInstallerName(),
            $package->Archive->getSafeFilePath()   => $package->Archive->getFileName(),
        ];
    }

    /**
     * Get general data for backup
     *
     * @param AbstractPackage $package the Backup
     *
     * @return array<string,mixed>
     */
    protected function getGeneralExtraData(AbstractPackage $package): array
    {
        // To be overridden by child classes if needed
        return [];
    }

    /**
     * Copies the Backup files from the default local storage to another local storage location
     *
     * @param AbstractPackage $package     the Backup
     * @param UploadInfo      $upload_info the upload info
     *
     * @return void
     */
    public function copyFromDefault(AbstractPackage $package, UploadInfo $upload_info): void
    {
        DupLog::infoTrace(
            "Copying to Storage " . $this->name . '[ID: ' . $this->id . '] [TYPE:' .
            static::getStypeName() . '] POS: ' . wp_json_encode($upload_info->chunkPosition)
        );

        $upload_info->generalExtraData = $this->getGeneralExtraData($package);

        $storageUpload = new StorageTransferChunkFiles(
            [
                'replacements' => $this->getPackageUploadFiles($package),
                'chunkSize'    => $this->getUploadChunkSize(),
                'chunkTimeout' => $this->getUploadChunkTimeout(),
                'upload_info'  => $upload_info,
                'package'      => $package,
                'adapter'      => $this->getAdapter(),
            ],
            0,
            $this->getUploadChunkTimeout()
        );

        if ($storageUpload->wasProcessingIncomplete()) {
            DupLog::infoTrace('Previous storage upload process exited unexpectedly, resuming from last saved position');
            $upload_info->increaseFailureCount();
        }

        switch ($storageUpload->start()) {
            case StorageTransferChunkFiles::CHUNK_COMPLETE:
                DupLog::infoTrace("UPLOAD IN CHUNKS COMPLETED\n-----------------------------------------\n");
                $upload_info->copied_installer = true;
                $upload_info->copied_archive   = true;

                if ($this->config['max_packages'] > 0) {
                    DupLog::trace('Purge old local Backups');
                    $this->purgeOldPackages();
                }
                do_action('duplicator_upload_complete', $upload_info);
                break;
            case StorageTransferChunkFiles::CHUNK_STOP:
                DupLog::trace('LOCAL UPLOAD IN CHUNKS NOT COMPLETED >> CONTINUE NEXT CHUNK');

                // Reset failure count on successful chunk progress
                if ($upload_info->failure_count > 0) {
                    DupLog::infoTrace(
                        "Chunk uploaded successfully, resetting failure count from " .
                        "{$upload_info->failure_count} to 0 [Storage Id: {$this->id}]"
                    );
                    $upload_info->failure_count = 0;
                }
                break;
            case StorageTransferChunkFiles::CHUNK_ERROR:
            default:
                DupLog::infoTrace('Local upload in chunks, upload error: ' . $storageUpload->getLastErrorMessage());
                $upload_info->increaseFailureCount();
        }
        $package->update();
    }

    /**
     * Return Backup transfer files
     *
     * @param AbstractPackage $package the Backup
     *
     * @return array<string,string> return array from => to
     */
    protected function getPackageDownloadFiles(AbstractPackage $package): array
    {
        $archiveTmpPath   = DUPLICATOR_SSDIR_PATH_TMP . '/' . $package->Archive->getFileName() . '.part';
        $installerTmpPath = DUPLICATOR_SSDIR_PATH_TMP . '/' . $package->Installer->getInstallerName() . '.part';
        return [
            $package->Installer->getInstallerName() => $installerTmpPath,
            $package->Archive->getFileName()        => $archiveTmpPath,
        ];
    }

    /**
     * Return Backup transfer files
     *
     * @param AbstractPackage $package the Backup
     *
     * @return array<string,string>[] return array from => to
     */
    protected function getPartFileMapping(AbstractPackage $package): array
    {
        $archiveTmpPath   = DUPLICATOR_SSDIR_PATH_TMP . '/' . $package->Archive->getFileName() . '.part';
        $installerTmpPath = DUPLICATOR_SSDIR_PATH_TMP . '/' . $package->Installer->getInstallerName() . '.part';
        return [
            [
                'source' => $archiveTmpPath,
                'dest'   => $package->Archive->getSafeFilePath(),
                'type'   => 'archive',
            ],
            [
                'source' => $installerTmpPath,
                'dest'   => $package->Installer->getSafeFilePath(),
                'type'   => 'installer',
            ],
        ];
    }

    /**
     * Copies the Backup files from the default local storage to another local storage location
     *
     * @param AbstractPackage $package     the Backup
     * @param UploadInfo      $upload_info the upload info
     *
     * @return void
     */
    public function copyToDefault(AbstractPackage $package, UploadInfo $upload_info): void
    {
        DupLog::infoTrace(
            "Copying to Storage " . $this->name . '[ID: ' . $this->id . '] [TYPE:' .
            static::getStypeName() . '] POS: ' . wp_json_encode($upload_info->chunkPosition)
        );
        DupLog::infoTrace("Download chunk size: " . $this->getDownloadChunkSize());
        $replacements    = $this->getPackageDownloadFiles($package);
        $storageDownload = new StorageTransferChunkFiles(
            [
                'replacements' => $replacements,
                'chunkSize'    => $this->getDownloadChunkSize(),
                'chunkTimeout' => $this->getUploadChunkTimeout(),
                'upload_info'  => $upload_info,
                'package'      => $package,
                'adapter'      => $this->getAdapter(),
                'download'     => true,
            ],
            0,
            $this->getUploadChunkTimeout()
        );

        switch ($storageDownload->start()) {
            case StorageTransferChunkFiles::CHUNK_COMPLETE:
                DupLog::infoTrace("DOWNLOAD FROM REMOTE IN CHUNKS COMPLETED\n-----------------------------------------\n");
                if ($this->renamePartDownloadFiles($package, $upload_info) == false) {
                    // No retry on file rename error
                    DupLog::infoTrace("Upload failed without retry [Storage Id: $this->id]");
                    $upload_info->uploadFailed();
                }
                break;
            case StorageTransferChunkFiles::CHUNK_STOP:
                DupLog::trace('DOWNLOAD IN CHUNKS NOT COMPLETED >> CONTINUE NEXT CHUNK');

                // Reset failure count on successful chunk progress
                if ($upload_info->failure_count > 0) {
                    DupLog::trace(
                        "Chunk downloaded successfully, resetting failure count from " .
                        "{$upload_info->failure_count} to 0 [Storage Id: {$this->id}]"
                    );
                    $upload_info->failure_count = 0;
                }
                break;
            case StorageTransferChunkFiles::CHUNK_ERROR:
            default:
                DupLog::infoTrace('Local download in chunks, download error: ' . $storageDownload->getLastErrorMessage());
                $upload_info->increaseFailureCount();
        }
        $package->update();
    }

    /**
     * Rename part download file to the final file
     *
     * @param AbstractPackage $package    the Backup
     * @param UploadInfo      $uploadInfo the upload info
     *
     * @return bool True if success, false otherwise
     */
    protected function renamePartDownloadFiles(AbstractPackage $package, UploadInfo $uploadInfo): bool
    {
        foreach ($this->getPartFileMapping($package) as $file) {
            if (
                SnapIO::rename(
                    $file['source'],
                    $file['dest'],
                    true
                ) == false
            ) {
                DupLog::infoTrace("Failed to rename part file [{$file['source']}] to {$file['dest']}");
                return false;
            } else {
                if ($file['type'] == 'archive') {
                    $uploadInfo->copied_archive = true;
                } elseif ($file['type'] == 'installer') {
                    $uploadInfo->copied_installer = true;
                }

                DupLog::infoTrace("Renamed part file [{$file['source']}] to {$file['dest']}");
            }
        }

        return true;
    }

    /**
     * Returns true if the storage has the backup
     *
     * @param AbstractPackage $package the Backup
     *
     * @return bool
     */
    public function hasPackage(AbstractPackage $package): bool
    {
        try {
            return $this->getAdapter()->isFile($package->Archive->getFileName());
        } catch (Exception $e) {
            DupLog::traceException($e, 'Error getting storage adapter');
            return false;
        }
    }

    /**
     * Check if storage is full
     *
     * @return bool
     */
    public function isFull(): bool
    {
        $adapter       = $this->getAdapter();
        $fullFilesList = $adapter->scanDir('', true, false);
        $packagesList  = array_filter(
            $fullFilesList,
            fn($file): bool => preg_match(DUPLICATOR_ARCHIVE_REGEX_PATTERN, $file) === 1
        );
        return ($this->config['max_packages'] > 0 && count($packagesList) >= $this->config['max_packages']);
    }

    /**
     * Purge old Backups.
     * If a backup is in the exclude list, it will be skipped, but the number of Backups to delete will not be reduced,
     * so that the total number of Backups in the storage will be equal to the max_packages setting.
     *
     * @param array<string> $exclude List of Backups to exclude from deletion
     *
     * @return false|string[] false on failure or array of deleted files of Backups
     */
    public function purgeOldPackages(array $exclude = [])
    {
        if ($this->config['max_packages'] <= 0) {
            return [];
        }

        DupLog::infoTrace("Attempting to purge old Backups at " . $this->name . '[ID: ' . $this->id . '] type: ' . static::getSTypeName());

        $result        = [];
        $global        = GlobalEntity::getInstance();
        $adapter       = $this->getAdapter();
        $fullFilesList = $adapter->scanDir('', true, false);
        $filesToPurge  = self::getPurgeFileList($fullFilesList, $this->config['max_packages'], $exclude);
        try {
            foreach ($filesToPurge as $file) {
                if (!$adapter->delete($file)) {
                    DupLog::infoTrace("Failed to purge backup from remote storage: " . $file);
                    continue;
                }
                $result[] = $file;

                if (preg_match(DUPLICATOR_ARCHIVE_REGEX_PATTERN, $file) !== 1) {
                    // Skip non-archive files
                    continue;
                }

                $package = DupPackage::getByArchiveName($file);
                if ($package === null) {
                    DupLog::infoTrace("Won't update package storage status, package not found");
                    continue;
                }

                // Update the Backup storage status
                $package->unsetStorage(
                    $this->getId(),
                    $global->getPurgeBackupRecords() === self::BACKUP_RECORDS_REMOVE_ALL
                );
            }
        } catch (Exception $e) {
            DupLog::infoTraceException($e, "FAIL: purge Backup for storage " . $this->name . '[ID: ' . $this->id . '] type:' . static::getStypeName());
            return false;
        }

        DupLog::infoTrace("Purge of old Backups at " . $this->name . '[ID: ' . $this->id . "] storage completed. Num packages deleted " . count($result));
        return $result;
    }

    /**
     * Returns the list of Backups files to delete based on the max_packages setting
     * If a backup is in the exclude list, it will be skipped, but the number of Backups to delete will not be reduced,
     * so that the total number of Backups in the storage will be equal to the max_packages setting.
     * Note: Don't return only archvie files, return all files that are part of a Backup
     *
     * @param string[] $fullFileList List of all files in the storage
     * @param int      $maxBackups   Max number of Backups to keep
     * @param string[] $exclude      List of Backups to exclude from deletion
     *
     * @return string[] array of backups files to delete (archive, installer, logs etc)
     */
    public static function getPurgeFileList(array $fullFileList, int $maxBackups, array $exclude = []): array
    {
        $backupList = array_filter(
            $fullFileList,
            fn($file): bool => preg_match(DUPLICATOR_ARCHIVE_REGEX_PATTERN, $file) === 1
        );

        if (count($backupList) <= $maxBackups) {
            return [];
        }

        // Calculate before exlude to get the correct number of Backups to delete
        $numToDelete = count($backupList) - $maxBackups;
        if (!empty($exclude)) {
            $backupList = array_diff($backupList, $exclude);
        }

        self::sortBackupListByDate($backupList, true);

        $archivesToDelete = array_slice($backupList, 0, $numToDelete);
        $suffixLength     = strlen('_archive.zip');

        $nameHashes = array_map(fn($archiveName): string => substr($archiveName, 0, -$suffixLength), $archivesToDelete);

        $filesToDelete = array_filter(
            $fullFileList,
            function ($file) use ($nameHashes): bool {
                foreach ($nameHashes as $nameHash) {
                    if (strpos($file, $nameHash) !== false) {
                        return true;
                    }
                }

                return false;
            }
        );

        return array_values($filesToDelete);
    }

    /**
     * Sorts the Backup list by date
     *
     * @param string[] $backupList List of Backups
     * @param bool     $ascending  Sort from oldest to newest
     *
     * @return void
     */
    public static function sortBackupListByDate(array &$backupList, bool $ascending = true): void
    {
        // Calculate the date string position and length
        $dateStrLen = strlen(date(DupPackage::PACKAGE_HASH_DATE_FORMAT));
        $dateStrPos = - (strlen('_archive.zip') + $dateStrLen);

        // Sort by reverse creation time
        usort($backupList, function ($a, $b) use ($dateStrPos, $dateStrLen, $ascending): int {
            $aDate = DateTime::createFromFormat(
                DupPackage::PACKAGE_HASH_DATE_FORMAT,
                substr($a, $dateStrPos, $dateStrLen)
            )->getTimestamp();
            $bDate = DateTime::createFromFormat(
                DupPackage::PACKAGE_HASH_DATE_FORMAT,
                substr($b, $dateStrPos, $dateStrLen)
            )->getTimestamp();

            //reverse sort
            if ($aDate == $bDate) {
                return 0;
            } elseif ($aDate < $bDate) {
                return $ascending ? -1 : 1;
            } else {
                return $ascending ? 1 : -1;
            }
        });
    }

    /**
     * List quick view
     *
     * @param bool $echo Echo or return
     *
     * @return string HTML string
     */
    public function getListQuickView(bool $echo = true): string
    {
        ob_start();
        ?>
        <div>
            <label><?php esc_html_e('Location', 'duplicator-pro') ?>:</label>
            <?php
            echo wp_kses(
                $this->getHtmlLocationLink(),
                [
                    'a' => [
                        'href'   => [],
                        'target' => [],
                    ],
                ]
            );
            ?>
        </div>
        <?php
        if ($echo) {
            ob_end_flush();
            return '';
        } else {
            return (string) ob_get_clean();
        }
    }

    /**
     * List quick view
     *
     * @param bool $echo Echo or return
     *
     * @return string HTML string
     */
    public function getDeleteView(bool $echo = true): string
    {
        ob_start();
        ?>
        <div class="item">
            <span class="lbl">Name:</span><?php echo esc_html($this->getName()); ?><br>
            <span class="lbl">Type:</span>&nbsp;
            <?php
            echo wp_kses(
                static::getStypeIcon(),
                [
                    'i'   => [
                        'class' => [],
                    ],
                    'img' => [
                        'src'   => [],
                        'alt'   => [],
                        'class' => [],
                    ],
                ]
            );
            ?>
            &nbsp;<?php echo esc_html(static::getStypeName()); ?>
        </div>
        <?php
        if ($echo) {
            ob_end_flush();
            return '';
        } else {
            return (string) ob_get_clean();
        }
    }

    /**
     * Get action key text for uploads
     *
     * @param string $key Key name (action, pending, failed, cancelled, success)
     *
     * @return string
     */
    protected function getUploadActionKeyText(string $key): string
    {
        switch ($key) {
            case 'action':
                return sprintf(
                    __('Transferring to %1$s folder:<br/> <i>%2$s</i>', "duplicator-pro"),
                    static::getStypeName(),
                    $this->getStorageFolder()
                );
            case 'pending':
                return sprintf(
                    __('Transfer to %1$s folder %2$s is pending', "duplicator-pro"),
                    static::getStypeName(),
                    $this->getStorageFolder()
                );
            case 'failed':
                return sprintf(
                    __('Failed to transfer to %1$s folder %2$s', "duplicator-pro"),
                    static::getStypeName(),
                    $this->getStorageFolder()
                );
            case 'cancelled':
                return sprintf(
                    __('Cancelled before could transfer to %1$s folder %2$s', "duplicator-pro"),
                    static::getStypeName(),
                    $this->getStorageFolder()
                );
            case 'success':
                return sprintf(
                    __('Transferred Backup to %1$s folder %2$s', "duplicator-pro"),
                    static::getStypeName(),
                    $this->getStorageFolder()
                );
            default:
                throw new Exception('Invalid key');
        }
    }

    /**
     * Get action key text for downloads
     *
     * @param string $key Key name (action, pending, failed, cancelled, success)
     *
     * @return string
     */
    protected function getDownloadActionKeyText(string $key): string
    {
        switch ($key) {
            case 'action':
                return sprintf(
                    __('Downloading from %1$s folder:<br/> <i>%2$s</i>', "duplicator-pro"),
                    static::getStypeName(),
                    $this->getStorageFolder()
                );
            case 'pending':
                return sprintf(
                    __('Download from %1$s folder %2$s is pending', "duplicator-pro"),
                    static::getStypeName(),
                    $this->getStorageFolder()
                );
            case 'failed':
                return sprintf(
                    __('Failed to download from %1$s folder %2$s', "duplicator-pro"),
                    static::getStypeName(),
                    $this->getStorageFolder()
                );
            case 'cancelled':
                return sprintf(
                    __('Cancelled before could download from %1$s folder %2$s', "duplicator-pro"),
                    static::getStypeName(),
                    $this->getStorageFolder()
                );
            case 'success':
                return sprintf(
                    __('Downloaded Backup from %1$s folder %2$s', "duplicator-pro"),
                    static::getStypeName(),
                    $this->getStorageFolder()
                );
            default:
                throw new Exception('Invalid key');
        }
    }

    /**
     * Get action text
     *
     * @param bool $isDownload Changes the text if the transfer is from storage to local
     *
     * @return string
     */
    public function getActionText(bool $isDownload = false): string
    {
        return $isDownload ? $this->getDownloadActionKeyText('action') : $this->getUploadActionKeyText('action');
    }

    /**
     * Get pending action text
     *
     * @param bool $isDownload Changes the text if the transfer is from storage to local
     *
     * @return string
     */
    public function getPendingText(bool $isDownload = false): string
    {
        return $isDownload ? $this->getDownloadActionKeyText('pending') : $this->getUploadActionKeyText('pending');
    }

    /**
     * Returns the text to display when the Backup has failed to copy to the storage location
     *
     * @param bool $isDownload Changes the text if the transfer is from storage to local
     *
     * @return string
     */
    public function getFailedText(bool $isDownload = false): string
    {
        return $isDownload ? $this->getDownloadActionKeyText('failed') : $this->getUploadActionKeyText('failed');
    }

    /**
     * Returns the text to display when the Backup has been cancelled before it could be copied to the storage location
     *
     * @param bool $isDownload Changes the text if the transfer is from storage to local
     *
     * @return string
     */
    public function getCancelledText(bool $isDownload = false): string
    {
        return $isDownload ? $this->getDownloadActionKeyText('cancelled') : $this->getUploadActionKeyText('cancelled');
    }

    /**
     * Returns the text to display when the Backup has been successfully copied to the storage location
     *
     * @param bool $isDownload Changes the text if the transfer is from storage to local
     *
     * @return string
     */
    public function getSuccessText(bool $isDownload = false): string
    {
        return $isDownload ? $this->getDownloadActionKeyText('success') : $this->getUploadActionKeyText('success');
    }

    /**
     *
     * @return string
     */
    protected static function getDefaultStorageFolder(): string
    {
        /** @var array<string,scalar> */
        $parsetUrl = SnapURL::parseUrl(get_home_url());
        if (is_string($parsetUrl['host']) && strlen($parsetUrl['host']) > 0) {
            $parsetUrl['host'] = preg_replace("([^\w\d\-_~,;\[\]\(\)\/\.])", '', $parsetUrl['host']);
        }
        $parsetUrl['scheme']   = false;
        $parsetUrl['port']     = false;
        $parsetUrl['query']    = false;
        $parsetUrl['fragment'] = false;
        $parsetUrl['user']     = false;
        $parsetUrl['pass']     = false;
        if (is_string($parsetUrl['path']) && strlen($parsetUrl['path']) > 0) {
            $parsetUrl['path'] = preg_replace("([^\w\d\-_~,;\[\]\(\)\/\.])", '', $parsetUrl['path']);
        }
        return ltrim(SnapURL::buildUrl($parsetUrl), '/\\');
    }

    /**
     * Render form config fields
     *
     * @param bool $echo Echo or return
     *
     * @return string
     */
    public function renderConfigFields(bool $echo = true): string
    {
        if (static::isHidden()) {
            return '';
        }

        try {
            $templateData = $this->getConfigFieldsData();
        } catch (Exception | Error $e) {
            TplMng::getInstance()->render(
                'admin_pages/storages/parts/storage_error',
                ['exception' => $e]
            );
            $templateData = $this->getDefaultConfigFieldsData();
        }

        return TplMng::getInstance()->render(
            $this->getConfigFieldsTemplatePath(),
            $templateData,
            $echo
        );
    }

    /**
     * Returns the config fields template path
     *
     * @return string
     */
    abstract protected function getConfigFieldsTemplatePath(): string;

    /**
     * Returns the config fields template data
     *
     * @return array<string, mixed>
     */
    abstract protected function getConfigFieldsData(): array;

    /**
     * Returns the default config fields template data
     *
     * @return array<string, mixed>
     */
    abstract protected function getDefaultConfigFieldsData(): array;

    /**
     * Render remote localtion info
     *
     * @param bool $failed        Failed upload
     * @param bool $cancelled     Cancelled upload
     * @param bool $packageExists Backup exists
     * @param bool $echo          Echo or return
     *
     * @return string
     */
    public function renderRemoteLocationInfo(bool $failed = false, bool $cancelled = false, bool $packageExists = true, bool $echo = true): string
    {
        return TplMng::getInstance()->render(
            'admin_pages/storages/parts/remote_localtion_info',
            [
                'failed'        => $failed,
                'cancelled'     => $cancelled,
                'packageExists' => $packageExists,
                'storage'       => $this,
            ],
            $echo
        );
    }

    /**
     * Storages test
     *
     * @param string $message Test message
     *
     * @return bool return true if success, false otherwise
     */
    public function test(string &$message = ''): bool
    {
        $this->testLog->reset();
        $message = sprintf(__('Testing %s storage...', 'duplicator-pro'), static::getStypeName());
        $this->testLog->addMessage($message);

        if (static::isSupported() == false) {
            $message = sprintf(__('Storage %s isn\'t supported on current server', 'duplicator-pro'), static::getStypeName());
            $this->testLog->addMessage($message);
            return false;
        }
        if ($this->isValid() == false) {
            $message = sprintf(__('Storage %s config data isn\'t valid', 'duplicator-pro'), static::getStypeName());
            $this->testLog->addMessage($message);
            return false;
        }

        try {
            $adapter = $this->getAdapter();
        } catch (Exception $e) {
            // This exception is captured temporally until all storage has implemented its adapter.
            /** @todo remove this remove this when it is okay */
            return true;
        }
        $testFileName = 'dup_test_' . md5(uniqid((string) random_int(0, mt_getrandmax()), true)) . '.txt';

        $this->testLog->addMessage(sprintf(__('Checking if the temporary file exists "%1$s"...', 'duplicator-pro'), $testFileName));
        if ($adapter->exists($testFileName)) {
            $this->testLog->addMessage(sprintf(__(
                'File with the temporary file name already exists, please try again "%1$s"',
                'duplicator-pro'
            ), $testFileName));
            $message = __('File with the temporary file name already exists, please try again', 'duplicator-pro');
            return false;
        }

        $this->testLog->addMessage(sprintf(__('Creating temporary file "%1$s"...', 'duplicator-pro'), $testFileName));
        if (!$adapter->createFile($testFileName, 'test')) {
            $this->testLog->addMessage(
                __(
                    'There was a problem when storing the temporary file',
                    'duplicator-pro'
                )
            );
            $message = __('There was a problem storing the temporary file', 'duplicator-pro');
            return false;
        }

        $this->testLog->addMessage(sprintf(__('Checking if the temporary file exists "%1$s"...', 'duplicator-pro'), $testFileName));
        if (!$adapter->isFile($testFileName)) {
            $this->testLog->addMessage(sprintf(__(
                'The temporary file was not found "%1$s"',
                'duplicator-pro'
            ), $testFileName));
            $message = __('The temporary file was not found', 'duplicator-pro');
            return false;
        }

        $this->testLog->addMessage(sprintf(__('Deleting temporary file "%1$s"...', 'duplicator-pro'), $testFileName));
        if (!$adapter->delete($testFileName)) {
            $this->testLog->addMessage(sprintf(__(
                'There was a problem when deleting the temporary file "%1$s"',
                'duplicator-pro'
            ), $testFileName));
            $message = __('There was a problem deleting the temporary file', 'duplicator-pro');
            return false;
        }

        $this->testLog->addMessage(__('Successfully stored and deleted file', 'duplicator-pro'));
        $message = __('Successfully stored and deleted file', 'duplicator-pro');
        return true;
    }

    /**
     * Get last test messages
     *
     * @return string
     */
    public function getTestLog()
    {
        return (string) $this->testLog;
    }

    /**
     * Get copied storage from source id.
     * If destId is existing storage is accepted source id with only the same type
     *
     * @param int $sourceId Source storage id
     * @param int $targetId Target storage id, if <= 0 create new storage
     *
     * @return false|static Return false on failure or storage object with updated value
     */
    public static function getCopyStorage(int $sourceId, int $targetId = -1)
    {
        if (($source = static::getById($sourceId)) === false) {
            return false;
        }

        if ($targetId <= 0) {
            $class = get_class($source);
            /** @var static */
            $target = new $class();
        } else {
            /** @var false|static */
            $target = static::getById($targetId);
            if ($target == false) {
                return false;
            }
            if ($source->getSType() != $target->getSType()) {
                return false;
            }
        }

        $skipProps = [
            'id',
            'testLog',
        ];

        $reflect = new ReflectionClass($source);
        foreach ($reflect->getProperties() as $prop) {
            if (in_array($prop->getName(), $skipProps)) {
                continue;
            }
            if ($prop->isStatic()) {
                continue;
            }
            if (PHP_VERSION_ID < 80100) {
                $prop->setAccessible(true);
            }
            if ($prop->getName() == 'name') {
                $newName = sprintf(__('%1$s - Copy', "duplicator-pro"), $prop->getValue($source));
                $prop->setValue($target, $newName);
            } else {
                $prop->setValue($target, $prop->getValue($source));
            }
        }

        return $target;
    }

    /**
     * Get all storages by type
     *
     * @param int $sType Storage type
     *
     * @return static[]|false return entities list of false on failure
     */
    public static function getAllBySType(int $sType)
    {
        return self::getAll(0, 0, null, fn(self $storage): bool => $storage->getSType() == $sType);
    }

    /**
     * To export data
     *
     * @return array<string, mixed>
     */
    public function settingsExport(): array
    {
        $data = JsonSerialize::serializeToData($this, JsonSerialize::JSON_SKIP_MAGIC_METHODS |  JsonSerialize::JSON_SKIP_CLASS_NAME);
        unset($data['testLog']);
        return $data;
    }

    /**
     * Update object properties from import data
     *
     * @param array<string, mixed> $data        data to import
     * @param string               $dataVersion version of data
     * @param array<string, mixed> $extraData   extra data, useful form id mapping etc.
     *
     * @return bool True if success, otherwise false
     */
    public function settingsImport(array $data, string $dataVersion, array $extraData = []): bool
    {
        $skipProps = ['id'];

        $reflect = new ReflectionClass(self::class);
        $props   = $reflect->getProperties();

        foreach ($props as $prop) {
            if (in_array($prop->getName(), $skipProps)) {
                continue;
            }
            if (!isset($data[$prop->getName()])) {
                continue;
            }
            if (PHP_VERSION_ID < 80100) {
                $prop->setAccessible(true);
            }
            if (is_array($data[$prop->getName()])) {
                $value = array_merge($prop->getValue($this), $data[$prop->getName()]);
                $prop->setValue($this, $value);
            } else {
                $prop->setValue($this, $data[$prop->getName()]);
            }
        }

        return true;
    }

    /**
     * This method is called before insert new Model, can be overridden by child classes
     *
     * @return void
     */
    protected function beforeInsert(): void
    {
        if (static::isUnique()) {
            $storages = static::getAllBySType(static::getSType());
            if (count($storages) > 0) {
                throw new Exception('Only one ' . static::getSTypeName() . ' storage can exist');
            }
        }

        parent::beforeInsert();
    }


    /**
     * Save new storage to DB
     *
     * @return int|false The id or false on failure
     */
    protected function insert()
    {
        if (($id = parent::insert()) === false) {
            return false;
        }

        do_action('duplicator_after_storage_create', $id);
        return $id;
    }

    /**
     * Delete current entity
     *
     * @return bool True on success, or false on error.
     */
    public function delete(): bool
    {
        $id = $this->id;

        // Collect storage information for Activity Log before deletion
        $storageId   = $this->id;
        $storageName = $this->getName();
        $storageType = $this->getStypeName();

        if (parent::delete() === false) {
            return false;
        }

        // Single pass: count affected packages AND cleanup package references
        $affectedPackages = 0;
        DupPackage::dbSelectByStatusCallback(function (DupPackage $package) use ($id, &$affectedPackages): void {
            foreach ($package->upload_infos as $key => $upload_info) {
                if ($upload_info->getStorageId() == $id) {
                    $affectedPackages++; // Count while cleaning up
                    DupLog::traceObject("deleting uploadinfo from package {$package->getId()}", $upload_info);
                    unset($package->upload_infos[$key]);
                    $package->save();
                    break;
                }
            }
        });

        ScheduleEntity::listCallback(function (ScheduleEntity $schedule) use ($id): void {
            if (($key = array_search($id, $schedule->storage_ids)) !== false) {
                $key = (int) $key;
                //use array_splice() instead of unset() to reset keys
                array_splice($schedule->storage_ids, $key, 1);
                if (count($schedule->storage_ids) === 0) {
                    $schedule->setActive(false);
                }
                $schedule->save();
            }
        });

        do_action('duplicator_after_storage_delete', $id);

        // Create Activity Log entry for storage deletion
        try {
            LogEventStorageDelete::create($storageId, $storageName, $storageType, $affectedPackages);
        } catch (Exception $e) {
            DupLog::traceError('Failed to create storage deletion log event: ' . $e->getMessage());
        }

        return true;
    }
}
