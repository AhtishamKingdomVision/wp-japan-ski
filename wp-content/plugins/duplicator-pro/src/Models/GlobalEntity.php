<?php

namespace Duplicator\Models;

use Duplicator\Utils\Logging\DupLog;
use Duplicator\Addons\ProBase\License\License;
use Duplicator\Core\Constants;
use Duplicator\Core\MigrationMng;
use Duplicator\Core\Models\AbstractEntity;
use Duplicator\Core\Models\TraitEntitySerializationEncryption;
use Duplicator\Core\Models\TraitGenericModelSingleton;
use Duplicator\Libs\Shell\ShellZipUtils;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapLog;
use Duplicator\Libs\Snap\SnapURL;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Models\DynamicGlobalEntity;
use Duplicator\Models\StaticGlobal;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\Storages\StoragesUtil;
use Duplicator\Utils\CronUtils;
use Duplicator\Utils\Crypt\CryptBlowfish;
use Duplicator\Utils\Email\EmailSummaryBootstrap;
use Duplicator\Utils\Email\EmailSummary;
use Duplicator\Utils\ZipArchiveExtended;
use VendorDuplicator\Amk\JsonSerialize\JsonSerialize;
use Duplicator\Utils\GroupOptions;
use Duplicator\Utils\LockUtil;
use Duplicator\Libs\WpUtils\PathUtil;
use Duplicator\Utils\Settings\ModelMigrateSettingsInterface;
use Duplicator\Utils\UsageStatistics\CommStats;
use Duplicator\Libs\WpUtils\WpArchiveUtils;
use Duplicator\Libs\WpUtils\WpDbUtils;
use Duplicator\Package\Archive\PackageArchive;
use Duplicator\Package\Create\PackInstaller;
use Duplicator\Utils\Settings\ServerThrottle;
use Exception;
use ReflectionClass;

class GlobalEntity extends AbstractEntity implements ModelMigrateSettingsInterface
{
    use TraitGenericModelSingleton;
    use TraitEntitySerializationEncryption;

    /**
     * No properties need encryption in GlobalEntity
     *
     * @var string[]
     */
    protected static array $encryptedProperties = [];

    const INSTALLER_NAME_MODE_WITH_HASH = 'withhash';
    const INSTALLER_NAME_MODE_SIMPLE    = 'simple';

    const CLEANUP_HOOK                  = 'duplicator_cleanup_hook';
    const CLEANUP_INTERVAL_NAME         = 'duplicator_custom_interval';
    const CLEANUP_FILE_TIME_DELAY       = 81000; // In seconds, 22.5 hours
    const CLEANUP_EMAIL_NOTICE_INTERVAL = 24; // In hours

    const CLEANUP_MODE_OFF  = 0;
    const CLEANUP_MODE_MAIL = 1;
    const CLEANUP_MODE_AUTO = 2;

    const EMAIL_BUILD_MODE_NEVER   = 0;
    const EMAIL_BUILD_MODE_FAILURE = 1;
    const EMAIL_BUILD_MODE_ALL     = 2;

    const INPUT_MYSQLDUMP_OPTION_PREFIX = 'package_mysqldump_';


    //GENERAL
    /** @var string email summary frequency */
    private $email_summary_frequency = EmailSummary::SEND_FREQ_WEEKLY;
    /** @var string[] email summary recipients */
    private $email_summary_recipients = [];
    /** @var bool */
    private $usageTracking = true;
    /** @var bool if true AM Notifications are enabled */
    private $amNotices = true;
    //PACKAGES::Visual
    /** @var bool */
    public $package_mysqldump = false;
    /** @var string */
    public $package_mysqldump_path = '';
    /** @var int<0, 1> ENUM */
    public $package_phpdump_mode = WpDbUtils::PHPDUMP_MODE_MULTI;
    /** @var int<0, max> */
    public $package_mysqldump_qrylimit = Constants::DEFAULT_MYSQL_DUMP_CHUNK_SIZE;
    /** @var GroupOptions[] */
    private array $packageMysqldumpOptions;
    /** @var int<-1, 3> ENUM */
    public $archive_build_mode = PackageArchive::BUILD_MODE_UNCONFIGURED;
    /** @var bool */
    public $archive_compression = true;
    /** @var bool */
    public $ziparchive_validation = false;
    /** @var int<0, 1> ENUM */
    public $ziparchive_mode = PackageArchive::ZIP_MODE_MULTI_THREAD;
    /** @var int<0, max> */
    public $ziparchive_chunk_size_in_mb = Constants::DEFAULT_ZIP_ARCHIVE_CHUNK;
    /** @var bool */
    public $homepath_as_abspath = false;
    //PACKAGES::Basic::Processing
    /** @var int<0, 3> ENUM */
    public $server_load_reduction = ServerThrottle::NONE;
    /** @var int<0, max> */
    public $max_package_runtime_in_min = Constants::DEFAULT_MAX_PACKAGE_RUNTIME_IN_MIN;
    /** @var int <0, max> */
    public $max_package_transfer_time_in_min = Constants::DEFAULT_MAX_PACKAGE_TRANSFER_TIME_IN_MIN;
    /** @var int<0, max> */
    public $php_max_worker_time_in_sec = Constants::DEFAULT_MAX_WORKER_TIME;
    //PACKAGES::Basic::Cleanup
    /** @var int<0,2> ENUM */
    public $cleanup_mode = self::CLEANUP_MODE_OFF;
    /** @var string */
    public $cleanup_email = '';
    /** @var int<0,max> */
    public $auto_cleanup_hours = 24;
    //PACKAGES::Advanced
    /** @var int<0,1> ENUM LockUtil::LOCK_MODE_* */
    public $lock_mode = LockUtil::LOCK_MODE_SQL;
    /** @var string */
    public $ajax_protocol = '';
    /** @var string */
    public $custom_ajax_url = '';
    /** @var bool */
    public $clientside_kickoff = false;
    /** @var string ENUM */
    public $installer_name_mode = self::INSTALLER_NAME_MODE_SIMPLE;
    /** @var bool */
    public $skip_archive_scan = false;
    //SCHEDULES
    /** @var int<0, 2> ENUM */
    public $send_email_on_build_mode = self::EMAIL_BUILD_MODE_FAILURE;
    /** @var string */
    public $notification_email_address = '';
    //STORAGE
    /** @var bool */
    public $storage_htaccess_off = false;
    /** @var int<0, max> */
    public $max_storage_retries = 10;
    /** @var int<0, 2> ENUM AbstractStorageEntity::BACKUP_RECORDS_* */
    protected $purgeBackupRecords = AbstractStorageEntity::BACKUP_RECORDS_REMOVE_ALL;
    /** @var int[] */
    protected $manual_mode_storage_ids = [];
    /** @var int<0, max> */
    public $last_system_check_timestamp = 0;
    /** @var int<0, max> */
    public $initial_activation_timestamp = 0;
    /** @var bool */
    public $ssl_useservercerts = true;
    /** @var bool */
    public $ssl_disableverify = true;
    /** @var int<0, max> */
    public $import_chunk_size = DUPLICATOR_DEFAULT_CHUNK_UPLOAD_SIZE; // in KB, 0 no chunk
    /** @var string */
    public $import_custom_path = '';
    /** @var bool */
    public $ipv4_only = false;
    /** @var bool */
    public $unhook_third_party_js = false;
    /** @var bool */
    public $unhook_third_party_css = false;
    /** @var string if empty custom path is disabled */
    private $recoveryCustomPath = '';

    /**
     * Class constructor
     */
    protected function __construct()
    {
        $this->packageMysqldumpOptions = $this->getDefaultMysqlDumpOptions();
        add_action(
            'duplicator_after_activation',
            function ($oldVersion, $newVersion): void {
                // Schedule custom cron event for cleanup of installer files if it should be scheduled
                self::cleanupScheduleSetup();
            },
            10,
            2
        );
    }

    /**
     * Return entity type identifier
     *
     * @return string
     */
    public static function getType(): string
    {
        return 'Global_Entity';
    }

    /**
     * Will be called, automatically, when Serialize
     *
     * @return array<string,mixed>
     */
    public function __serialize(): array
    {
        $data = JsonSerialize::serializeToData($this, JsonSerialize::JSON_SKIP_MAGIC_METHODS |  JsonSerialize::JSON_SKIP_CLASS_NAME);
        $data = $this->encryptSerializedProperties($data);
        return $data;
    }

    /**
     * Unserialize
     *
     * @param array<string,mixed> $data Serialized data
     *
     * @return void
     */
    public function __unserialize(array $data): void
    {
        // Decrypt properties
        $data = $this->decryptSerializedProperties($data);

        // Convert packageMysqldumpOptions arrays to GroupOptions objects
        $loadedOptionNames = [];
        if (isset($data['packageMysqldumpOptions']) && is_array($data['packageMysqldumpOptions'])) {
            foreach ($data['packageMysqldumpOptions'] as $index => $optionData) {
                $data['packageMysqldumpOptions'][$index] = GroupOptions::getObjectFromArray($optionData); // @phpstan-ignore-line
                $loadedOptionNames[]                     = $data['packageMysqldumpOptions'][$index]->getOptionName();
            }
        }

        // Merge with default options for missing mysqldump modes
        foreach ($this->getDefaultMysqlDumpOptions() as $defOpt) {
            if (in_array($defOpt->getOptionName(), $loadedOptionNames)) {
                continue;
            }
            $data['packageMysqldumpOptions'][] = $defOpt;
        }

        // Assign properties
        foreach ($data as $pName => $val) {
            if (!property_exists($this, $pName)) {
                continue;
            }
            $this->$pName = $val;
        }
    }

    /**
     * Return default options
     *
     * @return GroupOptions[]
     */
    private function getDefaultMysqlDumpOptions(): array
    {
        return [
            new GroupOptions('quick', self::INPUT_MYSQLDUMP_OPTION_PREFIX, false),
            new GroupOptions('extended-insert', self::INPUT_MYSQLDUMP_OPTION_PREFIX, false),
            new GroupOptions('routines', self::INPUT_MYSQLDUMP_OPTION_PREFIX, true),
            new GroupOptions('disable-keys', self::INPUT_MYSQLDUMP_OPTION_PREFIX, false),
            new GroupOptions('compact', self::INPUT_MYSQLDUMP_OPTION_PREFIX, false),
        ];
    }

    /**
     * This function is called on first istance of singletion object
     * Can be used to set dynamic properties values
     *
     * @return void
     */
    protected function firstIstanceInit(): void
    {
        $result = $this->reset(
            [],
            [
                self::class,
                'getDefaultPropInitVal',
            ],
            function (): void {
                $this->setBuildMode();
            }
        );
        if ($result === false) {
            throw new Exception('Can\'t reset the user settings');
        }
    }

    /**
     * Return default prop val by system config
     *
     * @param string $name prop nam
     * @param mixed  $val  prop val
     *
     * @return mixed
     */
    protected static function getDefaultPropInitVal(string $name, $val)
    {
        switch ($name) {
            case 'cleanup_email':
                return get_option('admin_email');
            case 'lock_mode':
                return LockUtil::getDefaultLockType();
            case 'ajax_protocol':
                return strtolower(parse_url(network_admin_url(), PHP_URL_SCHEME));
            case 'php_max_worker_time_in_sec':
                // Default is just a bit under the .7 max
                return min(
                    floor(0.7 * SnapUtil::phpIniGet("max_execution_time", 30, 'int')),
                    Constants::DEFAULT_MAX_WORKER_TIME
                );
            case 'crypt':
                return CryptBlowfish::isEncryptAvailable();
            case 'custom_ajax_url':
                return admin_url('admin-ajax.php');
            case 'email_summary_recipients':
                return EmailSummary::getDefaultRecipients();
        }
        return $val;
    }

    /**
     * Reset default values
     *
     * @return bool
     */
    public function resetUserSettings(): bool
    {
        try {
            StaticGlobal::reset();
            $result = $this->reset(
                [
                    'manual_mode_storage_ids',
                    'last_system_check_timestamp',
                    'initial_activation_timestamp',
                ],
                [
                    self::class,
                    'getDefaultPropInitVal',
                ],
                function (): void {
                    $this->setBuildMode();
                }
            );

            if ($result == false) {
                throw new Exception('Can\'t reset global entity values');
            }

            if (DynamicGlobalEntity::getInstance()->resetUserSettings() == false) {
                throw new Exception('Can\'t save secure global');
            }
        } catch (Exception $e) {
            DupLog::traceError('Reset user settings error mrg: ' . $e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * Get usage tracking
     *
     * @return bool
     */
    public function getUsageTracking(): bool
    {
        return $this->usageTracking;
    }

    /**
     * Set usage tracking
     *
     * @param bool $value value
     *
     * @return void
     */
    public function setUsageTracking($value): void
    {
        if (DUPLICATOR_USTATS_DISALLOW) { // @phpstan-ignore-line
            // If usagfe tracking is hardcoded disabled, don't change the setting value
            return;
        }

        $value               = (bool) $value;
        $oldValue            = $this->usageTracking;
        $this->usageTracking = $value;

        if ($value == false && $oldValue != $value) {
            CommStats::disableUsageTracking();
        }
    }

    /**
     * Return recovery custom path
     *
     * @return string
     */
    public function getRecoveryCustomPath(): string
    {
        return $this->recoveryCustomPath;
    }

    /**
     * Return recovery custom URL
     *
     * @return string return empty URL if custom path isn't set
     */
    public function getRecoveryCustomURL(): string
    {
        if (strlen($this->recoveryCustomPath) == 0) {
            return '';
        }

        if (SnapIO::isChildPath($this->recoveryCustomPath, WpArchiveUtils::getArchiveListPaths('wpcontent'), false, true, true)) {
            $mainPath = WpArchiveUtils::getArchiveListPaths('wpcontent');
            $mainURL  = WpArchiveUtils::getOriginalUrls('wpcontent');
        } else {
            $mainPath = WpArchiveUtils::getArchiveListPaths('home');
            $mainURL  = WpArchiveUtils::getOriginalUrls('home');
        }

        return $mainURL . '/' . SnapIo::getRelativePath($this->recoveryCustomPath, $mainPath, true);
    }

    /**
     * Set recovery custom path
     *
     * @param string $path        path
     * @param string $failMessage return fail message
     *
     * @return bool
     */
    public function setRecoveryCustomPath($path, &$failMessage = ''): bool
    {
        $remove = false;

        try {
            $this->recoveryCustomPath = '';

            if (strlen($path) == 0) {
                return true;
            }

            if (file_exists($path)) {
                if (
                    !is_dir($path) ||
                    !is_writable($path)
                ) {
                    throw new Exception(__('The Recovery custom path must be a folder with write permissions.', 'duplicator-pro'));
                }
            } else {
                if (wp_mkdir_p($path) == false) {
                    throw new Exception(sprintf(__('It is not possible to create the folder %s', 'duplicator-pro'), $path));
                }
            }

            if (
                !SnapIO::isChildPath($path, WpArchiveUtils::getArchiveListPaths('home'), false, false, true) &&
                !SnapIO::isChildPath($path, WpArchiveUtils::getArchiveListPaths('wpcontent'), false, false, true)
            ) {
                throw new Exception(__('The custom Recovery path must be a child folder of the home path or wp-content', 'duplicator-pro'));
            }

            if (PathUtil::isPathInCoreDirs($path)) {
                throw new Exception(__('The Recovery custom path cannot be a wordpress core folder.', 'duplicator-pro'));
            }
        } catch (Exception $e) {
            $remove      = true;
            $failMessage = $e->getMessage();
            return false;
        } finally {
            if ($remove) {
                rmdir($path);
            }
        }

        $this->recoveryCustomPath = $path;
        return true;
    }

    /**
     * Update global settings after install
     *
     * @return bool true on success false on failure
     */
    public function updateAftreInstall(): bool
    {
        $this->lock_mode     = LockUtil::getDefaultLockType();
        $this->ajax_protocol = DUPLICATOR_DEFAULT_AJAX_PROTOCOL;
        if ($this->getBuildMode() !== PackageArchive::BUILD_MODE_DUP_ARCHIVE) {
            $this->setBuildMode();
        }
        return $this->save();
    }

    /**
     * To export data
     *
     * @return array<string, mixed>
     */
    public function settingsExport(): array
    {
        $skipProps = [
            'id',
            'last_system_check_timestamp',
            'initial_activation_timestamp',
            'manual_mode_storage_ids',
            'basic_auth_password',
            'lkp',
            'uninstall_settings',
            'uninstall_packages',
            'crypt',
        ];

        $data = JsonSerialize::serializeToData($this, JsonSerialize::JSON_SKIP_MAGIC_METHODS |  JsonSerialize::JSON_SKIP_CLASS_NAME);
        foreach ($skipProps as $prop) {
            unset($data[$prop]);
        }
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
    public function settingsImport($data, $dataVersion, array $extraData = []): bool
    {
        $skipProps = [
            'id',
            'last_system_check_timestamp',
            'initial_activation_timestamp',
            'manual_mode_storage_ids',
            'license_key_visible',
            'lkp',
            'basic_auth_password',
            'uninstall_settings',
            'uninstall_packages',
            'crypt',
        ];

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
            $prop->setValue($this, $data[$prop->getName()]);
        }
        return true;
    }

    /**
     * Set from object
     *
     * @param self $global_data global data
     *
     * @return void
     */
    public function setFromImportData(self $global_data): void
    {
        $reflect = new ReflectionClass(self::class);
        $props   = $reflect->getProperties();

        $skipProps = [
            'id',
            'last_system_check_timestamp',
            'initial_activation_timestamp',
            'manual_mode_storage_ids',
            'license_key_visible',
            'lkp',
        ];

        foreach ($props as $prop) {
            if (in_array($prop->getName(), $skipProps)) {
                continue;
            }
            if (PHP_VERSION_ID < 80100) {
                $prop->setAccessible(true);
            }
            $prop->setValue($this, $prop->getValue($global_data));
        }
    }

    /**
     * Check if build mode is available
     *
     * @param int $buildMode ENUM PackageArchive::BUILD_MODE_*
     *
     * @return bool
     */
    public static function isBuildModeAvailable(int $buildMode): bool
    {
        switch ($buildMode) {
            case PackageArchive::BUILD_MODE_UNCONFIGURED:
                return false;
            case PackageArchive::BUILD_MODE_SHELL_EXEC:
                return (ShellZipUtils::getShellExecZipPath() != null);
            case PackageArchive::BUILD_MODE_ZIP_ARCHIVE:
                return ZipArchiveExtended::isPhpZipAvailable();
            case PackageArchive::BUILD_MODE_DUP_ARCHIVE:
                return true;
            default:
                throw new Exception('Invalid engine');
        }
    }

    /**
     * Return Backup build mode
     *
     * @return int Return enum PackageArchive::BUILD_MODE_*
     */
    public function getBuildMode(): int
    {
        $archive_build_mode = $this->archive_build_mode;

        switch ($archive_build_mode) {
            case PackageArchive::BUILD_MODE_UNCONFIGURED:
                if (self::isBuildModeAvailable(PackageArchive::BUILD_MODE_SHELL_EXEC)) {
                    $archive_build_mode = PackageArchive::BUILD_MODE_SHELL_EXEC;
                } elseif (self::isBuildModeAvailable(PackageArchive::BUILD_MODE_ZIP_ARCHIVE)) {
                    $archive_build_mode = PackageArchive::BUILD_MODE_ZIP_ARCHIVE;
                } else {
                    $archive_build_mode = PackageArchive::BUILD_MODE_DUP_ARCHIVE;
                }
                break;
            case PackageArchive::BUILD_MODE_SHELL_EXEC:
                if (!self::isBuildModeAvailable(PackageArchive::BUILD_MODE_SHELL_EXEC)) {
                    if (self::isBuildModeAvailable(PackageArchive::BUILD_MODE_ZIP_ARCHIVE)) {
                        $archive_build_mode = PackageArchive::BUILD_MODE_ZIP_ARCHIVE;
                    } else {
                        $archive_build_mode = PackageArchive::BUILD_MODE_DUP_ARCHIVE;
                    }
                }
                break;
            case PackageArchive::BUILD_MODE_ZIP_ARCHIVE:
                if (!self::isBuildModeAvailable(PackageArchive::BUILD_MODE_ZIP_ARCHIVE)) {
                    if (self::isBuildModeAvailable(PackageArchive::BUILD_MODE_SHELL_EXEC)) {
                        $archive_build_mode = PackageArchive::BUILD_MODE_SHELL_EXEC;
                    } else {
                        $archive_build_mode = PackageArchive::BUILD_MODE_DUP_ARCHIVE;
                    }
                }
                break;
            case PackageArchive::BUILD_MODE_DUP_ARCHIVE:
                break;
            default:
                throw new Exception('Invalid engine');
        }
        return $archive_build_mode;
    }

    /**
     * Selt build mode and return it
     *
     * @param bool $save if true save update global entity only if build mode is changed
     *
     * @return int Return enum PackageArchive::BUILD_MODE_*
     */
    public function setBuildMode(bool $save = false): int
    {
        $newBuildMode = apply_filters('duplicator_default_archive_build_mode', $this->getBuildMode());
        if ($newBuildMode != $this->archive_build_mode) {
            $this->archive_build_mode = $newBuildMode;
            if ($save) {
                $this->save();
            }
        }
        return $this->archive_build_mode;
    }

    /**
     *
     * @return int<0,max> microsenconds
     */
    public function getMicrosecLoadReduction(): int
    {
        return ServerThrottle::microsecondsFromThrottle($this->server_load_reduction);
    }

    /**
     * set db mode ant all related params.
     * check mysqldump
     *
     * @param null|string $dbMode                if null get INPUT_POST
     * @param null|int    $phpDumpMode           if null get INPUT_POST
     * @param null|int    $dbPhpQueryLimit       if null get INPUT_POST
     * @param null|string $packageMysqldumpPath  if null get INPUT_POST
     * @param null|int    $dbMysqlDumpQueryLimit if null get INPUT_POST
     *
     * @return void
     */
    public function setDbMode(
        $dbMode = null,
        $phpDumpMode = null,
        $dbPhpQueryLimit = null,
        $packageMysqldumpPath = null,
        $dbMysqlDumpQueryLimit = null
    ): void {
        //DATABASE
        $dbMode                = is_null($dbMode) ? SnapUtil::sanitizeDefaultInput(INPUT_POST, '_package_dbmode') : $dbMode;
        $phpDumpMode           = is_null($phpDumpMode) ? filter_input(
            INPUT_POST,
            '_phpdump_mode',
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'default'   => 0,
                    'min_range' => 0,
                    'max_range' => 1,
                ],
            ]
        ) : $phpDumpMode;
        $dbMysqlDumpQueryLimit = is_null($dbMysqlDumpQueryLimit) ? filter_input(
            INPUT_POST,
            '_package_mysqldump_qrylimit',
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'default'   => Constants::DEFAULT_MYSQL_DUMP_CHUNK_SIZE,
                    'min_range' => Constants::MYSQL_DUMP_CHUNK_SIZE_MIN_LIMIT,
                    'max_range' => Constants::MYSQL_DUMP_CHUNK_SIZE_MAX_LIMIT,
                ],
            ]
        ) : $dbMysqlDumpQueryLimit;

        $packageMysqldumpPath = is_null($packageMysqldumpPath) ?
            SnapUtil::sanitizeDefaultInput(INPUT_POST, '_package_mysqldump_path') :
            $packageMysqldumpPath;
        $packageMysqldumpPath = SnapUtil::sanitizeNSCharsNewlineTabs($packageMysqldumpPath);
        $packageMysqldumpPath = preg_match('/^([A-Za-z]\:)?[\/\\\\]/', $packageMysqldumpPath) ? $packageMysqldumpPath : '';
        $packageMysqldumpPath = preg_replace('/[\'"]/m', '', $packageMysqldumpPath);
        $packageMysqldumpPath = SnapIO::safePathUntrailingslashit($packageMysqldumpPath);

        $mysqlDumpPath = empty($packageMysqldumpPath) ? WpDbUtils::getMySqlDumpPath() : $packageMysqldumpPath;
        if ($dbMode == 'mysql' && empty($mysqlDumpPath)) {
            $dbMode = 'php';
        }

        $this->package_mysqldump          = ($dbMode == 'mysql');
        $this->package_phpdump_mode       = $phpDumpMode;
        $this->package_mysqldump_path     = $packageMysqldumpPath;
        $this->package_mysqldump_qrylimit = $dbMysqlDumpQueryLimit;

        foreach ($this->getMysqldumpOptions() as $option) {
            $option->update();
        }
    }

    /**
     * Sets cleanup fields and configures WP Cron accordingly
     *
     * @param int    $cleanup_mode       Cleanup mode to set
     * @param string $cleanup_email      Email address to send cleanup notification to
     * @param int    $auto_cleanup_hours Number of hours after which cleanup should be performed
     *
     * @return void
     */
    public function setCleanupFields(
        $cleanup_mode = null,
        $cleanup_email = null,
        $auto_cleanup_hours = null
    ): void {
        $this->cleanup_mode = is_null($cleanup_mode) ? filter_input(
            INPUT_POST,
            'cleanup_mode',
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'default'   => self::CLEANUP_MODE_OFF,
                    'min_range' => 0,
                    'max_range' => 2,
                ],
            ]
        ) : $cleanup_mode;

        $email               = filter_input(INPUT_POST, 'cleanup_email', FILTER_VALIDATE_EMAIL, ['options' => ['default' => '']]);
        $email               = $email === '' ? get_option('admin_email') : $email;
        $this->cleanup_email = is_null($cleanup_email) ? $email : $cleanup_email;

        $this->auto_cleanup_hours = is_null($auto_cleanup_hours) ? filter_input(
            INPUT_POST,
            'auto_cleanup_hours',
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'default'   => 24,
                    'min_range' => 1,
                ],
            ]
        ) : $auto_cleanup_hours;

        self::cleanupScheduleSetup();
    }

    /**
     * Schedules cron event for installer files cleanup purposes,
     * and unschedules it if it's not needed anymore.
     *
     * @return void
     */
    public static function cleanupScheduleSetup(): void
    {
        DupLog::trace("CLEANUP SCHEDULE SETUP");
        $global = self::getInstance();
        CronUtils::unscheduleEvent(self::CLEANUP_HOOK);
        if ($global->cleanup_mode == self::CLEANUP_MODE_MAIL) {
            $nextRunTime = time() + self::CLEANUP_EMAIL_NOTICE_INTERVAL * 3600;
            CronUtils::scheduleEvent($nextRunTime, self::CLEANUP_INTERVAL_NAME, self::CLEANUP_HOOK);
        } elseif ($global->cleanup_mode == self::CLEANUP_MODE_AUTO) {
            $nextRunTime = time() + $global->auto_cleanup_hours * 3600;
            CronUtils::scheduleEvent($nextRunTime, self::CLEANUP_INTERVAL_NAME, self::CLEANUP_HOOK);
        }
    }

    /**
     * Customizes schedules according to current cleanup_mode. If necessary, it
     * adds a custom cron schedule that will run every N hours.
     *
     * @param array<string,array{interval:int,display:string}> $schedules An array of non-default cron schedules.
     *
     * @return array<string,array{interval:int,display:string}> Filtered array of non-default cron schedules.
     */
    public static function customCleanupCronInterval(array $schedules): array
    {
        $global = self::getInstance();

        switch ($global->cleanup_mode) {
            case self::CLEANUP_MODE_OFF:
                // No need to modify anything
                break;
            case self::CLEANUP_MODE_MAIL:
                $schedules[self::CLEANUP_INTERVAL_NAME] = [
                    'interval' => self::CLEANUP_EMAIL_NOTICE_INTERVAL * 3600, // In seconds, every N hours
                    'display'  => sprintf(esc_html__('Every %1$d hours', 'duplicator-pro'), self::CLEANUP_EMAIL_NOTICE_INTERVAL),
                ];
                break;
            case self::CLEANUP_MODE_AUTO:
                $schedules[self::CLEANUP_INTERVAL_NAME] = [
                    'interval' => $global->auto_cleanup_hours * 3600, // In seconds, every N hours
                    'display'  => sprintf(esc_html__('Every %1$d hours', 'duplicator-pro'), $global->auto_cleanup_hours),
                ];
                break;
            default:
                throw new Exception('Invalid cleanup mode:' . SnapLog::v2str($global->cleanup_mode));
        }
        return $schedules;
    }

    /**
     * The function that gets executed by WP Cron for cleanup of installer files.
     * It does different tasks based on current cleanup_mode setting.
     *
     * @return void
     */
    public static function cleanupCronJob(): void
    {
        $global = self::getInstance();
        DupLog::trace("CLEANUP CRON JOB");

        $websiteUrl = SnapURL::getCurrentUrl(false, false, 1);
        $to         = $global->cleanup_email;
        if (empty($to)) {
            $to = get_option('admin_email');
        }

        switch ($global->cleanup_mode) {
            case self::CLEANUP_MODE_MAIL:
                // Email Notice cron job routine for cleanup of installer files
                $listOfInstallerFiles = MigrationMng::checkInstallerFilesList();
                $filesToRemove        = [];

                foreach ($listOfInstallerFiles as $path) {
                    if (time() - filectime($path) > self::CLEANUP_FILE_TIME_DELAY) {
                        $filesToRemove[] = $path;
                    }
                }

                if (count($filesToRemove) > 0 && !empty($to)) {
                    // Send an Email Notice
                    $subject  = __("Action required", 'duplicator-pro');
                    $message  = sprintf(__('This email is sent by your Wordpress plugin "Duplicator Pro" from website: %1$s. ', 'duplicator-pro'), $websiteUrl);
                    $message .= __('You received this email because Cleanup mode is set to "Email Notice". ', 'duplicator-pro');
                    $message .= __('Cleanup routine discovered that some installer files (leftovers from migration) were not removed. ', 'duplicator-pro');
                    $message .= __('We strongly advise you to remove these files. ', 'duplicator-pro');
                    $message .= __('Here is the list of files found on your website that you should remove:', 'duplicator-pro') . "<br/>";
                    foreach ($filesToRemove as $path) {
                        $message .= "-> $path<br/>";
                    }
                    $message .= "<br/>";
                    $message .= __('Note: You could enable "Auto Cleanup" mode if you go to:', 'duplicator-pro') . "<br/>";
                    $message .= __('WordPress Admin > Duplicator Pro > Settings > Backups Tab > Cleanup.', 'duplicator-pro') . "<br/>";
                    $message .= __('That mode will do cleanup of those files automatically for you.', 'duplicator-pro') . "<br/>";
                    $message .= "<br/>";
                    $message .= __('Best regards,', 'duplicator-pro') . "<br/>";
                    $message .= __('Duplicator Pro', 'duplicator-pro');

                    if (wp_mail($to, $subject, $message, ['Content-Type: text/html; charset=UTF-8'])) {
                        // OK
                        DupLog::trace('wp_mail sent email notice regarding cleanup of installer files');
                    } else {
                        DupLog::trace("Problem sending email notice regarding cleanup of installer files to {$to}");
                    }
                }
                break;
            case self::CLEANUP_MODE_AUTO:
                // Auto Cleanup cron job routine for cleanup of installer files
                $installerFiles = MigrationMng::cleanMigrationFiles(false, self::CLEANUP_FILE_TIME_DELAY);
                if (count($installerFiles) == 0) {
                    // No installer files were found, so we do nothing else
                    return;
                }

                $filesFailedRemoval = [];
                foreach ($installerFiles as $path => $success) {
                    if (!$success) {
                        $filesFailedRemoval[] = $path;
                    }
                }
                if (count($filesFailedRemoval) == 0) {
                    // All found installer files were removed successfully,
                    // or they did not even need to be removed yet because of CLEANUP_FILE_TIME_DELAY
                    return;
                }

                // If this is executed that means that some of installer files
                // could not be removed for some reason (permission issues?)
                if (!empty($to)) {
                    // Send an Email Notice about files that could not be removed during auto cleanup
                    $subject  = __("Action required", 'duplicator-pro');
                    $message  = sprintf(
                        __('This email is sent by your Wordpress plugin "Duplicator Pro" from website: %1$s. ', 'duplicator-pro'),
                        $websiteUrl
                    );
                    $message .= __('"Auto Cleanup" mode is ON, ', 'duplicator-pro');
                    $message .= __(
                        'however cleanup routine discovered that some installer files (leftovers from migration) could not be removed. ',
                        'duplicator-pro'
                    );
                    $message .= __('We strongly advise you to remove those files manually. ', 'duplicator-pro');
                    $message .= __('Here is the list of files found on your website that you should remove:', 'duplicator-pro') . "<br/>";
                    foreach ($filesFailedRemoval as $path) {
                        $message .= "-> $path<br/>";
                    }
                    $message .= "<br/>";
                    $message .= __('Those files probably could not be removed due to permission issues. ', 'duplicator-pro');
                    $message .= sprintf(
                        __('You can find more info in FAQ %1$son this link%2$s.', 'duplicator-pro'),
                        "<a href='" . DUPLICATOR_DUPLICATOR_DOCS_URL . "how-to-fix-file-permissions-issues' target='_blank'>",
                        "</a>"
                    ) . "<br/>";
                    $message .= "<br/>";
                    $message .= __('Note: To edit "Cleanup" settings go to:', 'duplicator-pro') . "<br/>";
                    $message .= __('WordPress Admin > Duplicator Pro > Settings > Backups Tab > Cleanup.', 'duplicator-pro') . "<br/>";
                    $message .= "<br/>";
                    $message .= __('Best regards,', 'duplicator-pro') . "<br/>";
                    $message .= __('Duplicator Pro', 'duplicator-pro');

                    if (wp_mail($to, $subject, $message, ['Content-Type: text/html; charset=UTF-8'])) {
                        // OK
                        DupLog::trace('wp_mail sent email notice regarding failed auto cleanup of installer files');
                    } else {
                        DupLog::trace("Problem sending email notice regarding failed auto cleanup of installer files to {$to}");
                    }
                }
                break;
            case self::CLEANUP_MODE_OFF:
            default:
                break;
        }
    }

    /**
     * Set archive mode
     *
     * @param ?int  $archiveBuildMode        Archive build mode, if null get INPUT_POST
     * @param ?int  $zipArchiveMode          Zip archive mode, if null get INPUT_POST
     * @param ?bool $archiveCompression      Archive compression, if null get INPUT_POST
     * @param ?bool $ziparchiveValidation    Zip archive validation, if null get INPUT_POST
     * @param ?int  $ziparchiveChunkSizeInMb Zip archive chunk size in MB, if null get INPUT_POST
     *
     * @return void
     */
    public function setArchiveMode(
        $archiveBuildMode = null,
        $zipArchiveMode = null,
        $archiveCompression = null,
        $ziparchiveValidation = null,
        $ziparchiveChunkSizeInMb = null
    ): void {
        $isZipAvailable = (ShellZipUtils::getShellExecZipPath() != null);

        $prelimBuildMode = is_null($archiveBuildMode) ? filter_input(
            INPUT_POST,
            'archive_build_mode',
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                    'max_range' => 3,
                ],
            ]
        ) : $archiveBuildMode;

        // Something has changed which invalidates Shell exec so move it to ZA
        $this->archive_build_mode = (!$isZipAvailable && $prelimBuildMode == PackageArchive::BUILD_MODE_SHELL_EXEC) ?
            PackageArchive::BUILD_MODE_ZIP_ARCHIVE :
            $prelimBuildMode;
        $this->ziparchive_mode    = is_null($zipArchiveMode) ? filter_input(
            INPUT_POST,
            'ziparchive_mode',
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'default'   => 0,
                    'min_range' => 0,
                    'max_range' => 1,
                ],
            ]
        ) : $zipArchiveMode;

        $this->archive_compression         = is_null($archiveCompression) ?
            filter_input(INPUT_POST, 'archive_compression', FILTER_VALIDATE_BOOLEAN) :
            $archiveCompression;
        $this->ziparchive_validation       = is_null($ziparchiveValidation) ?
            filter_input(INPUT_POST, 'ziparchive_validation', FILTER_VALIDATE_BOOLEAN) :
            $ziparchiveValidation;
        $this->ziparchive_chunk_size_in_mb = is_null($ziparchiveChunkSizeInMb) ? filter_input(
            INPUT_POST,
            'ziparchive_chunk_size_in_mb',
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'default'   => Constants::DEFAULT_ZIP_ARCHIVE_CHUNK,
                    'min_range' => 1,
                ],
            ]
        ) : $ziparchiveChunkSizeInMb;
    }

    /**
     * Set clientside kickoff
     *
     * @param bool $enable enable or disable
     *
     * @return void
     */
    public function setClientsideKickoff(bool $enable): void
    {
        if ($this->clientside_kickoff != $enable) {
            $this->clientside_kickoff = $enable;

            if ($this->clientside_kickoff) {
                // Auto setting the max Backup runtime in case of client kickoff is turned on and
                // the max Backup runtime is less than 480 minutes - 8 hours
                $this->max_package_runtime_in_min = max(480, $this->max_package_runtime_in_min);
                $this->setDbMode('mysql');
            }
        }
    }

    /**
     * Get archive engine label
     *
     * @return string
     */
    public function getArchiveEngine(): string
    {
        $mode = '';
        switch ($this->archive_build_mode) {
            case PackageArchive::BUILD_MODE_ZIP_ARCHIVE:
                $mode = ($this->ziparchive_mode == PackageArchive::ZIP_MODE_MULTI_THREAD) ?
                    __("ZipArchive: multi-thread", 'duplicator-pro') :
                    __("ZipArchive: single-thread", 'duplicator-pro');
                break;

            case PackageArchive::BUILD_MODE_DUP_ARCHIVE:
                $mode = __('DupArchive', 'duplicator-pro');
                break;

            default:
                $mode = __("Shell Zip", 'duplicator-pro');
                break;
        }

        return $mode;
    }

    /**
     * Return archive extension type
     *
     * @return string
     */
    public function getArchiveExtensionType(): string
    {
        $mode = 'zip';
        if ($this->archive_build_mode == PackageArchive::BUILD_MODE_DUP_ARCHIVE) {
            $mode = 'daf';
        }
        return $mode;
    }

    /**
     * Return Mysqldump options
     *
     * @return GroupOptions[]
     */
    public function getMysqldumpOptions(): array
    {
        return $this->packageMysqldumpOptions;
    }

    /**
     * Get manual mode storage ids
     *
     * @return int[]
     */
    public function getManualModeStorageIds(): array
    {
        if (count($this->manual_mode_storage_ids) == 0) {
            $this->manual_mode_storage_ids = [StoragesUtil::getDefaultStorageId()];
        }
        return $this->manual_mode_storage_ids;
    }

    /**
     * Set manual mode storage ids
     *
     * @param int[] $storageIds Storage ids
     *
     * @return void
     */
    public function setManualModeStorageIds(array $storageIds): void
    {
        $this->manual_mode_storage_ids = [];
        foreach ($storageIds as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $this->manual_mode_storage_ids[] = $id;
        }
        if (count($this->manual_mode_storage_ids) == 0) {
            $this->manual_mode_storage_ids = [StoragesUtil::getDefaultStorageId()];
        }
    }

    /**
     * Get Email Summary Recipients
     *
     * @return string[]
     */
    public function getEmailSummaryRecipients(): array
    {
        return $this->email_summary_recipients;
    }

    /**
     * Set Email Summary Recipients
     *
     * @param string[] $recipients List of recipient email addreses
     *
     * @return void
     */
    public function setEmailSummaryRecipients(array $recipients): void
    {
        $recipients = filter_var($recipients, FILTER_VALIDATE_EMAIL, FILTER_REQUIRE_ARRAY);
        if ($recipients === false) {
            $recipients = [];
        }

        foreach ($recipients as $key => $recipient) {
            if ($recipient === false) {
                continue;
            }

            $recipients[$key] = sanitize_email($recipient);
        }

        $this->email_summary_recipients = array_values(array_unique($recipients));
    }

    /**
     * Get email summary frequency
     *
     * @return string
     */
    public function getEmailSummaryFrequency(): string
    {
        return $this->email_summary_frequency;
    }

    /**
     * Set email summary frequency
     *
     * @param string $frequency The frequency
     *
     * @return void
     */
    public function setEmailSummaryFrequency($frequency): void
    {
        if (EmailSummaryBootstrap::updateFrequency($this->email_summary_frequency, $frequency) === false) {
            DupLog::trace("Invalid email summary frequency: {$frequency}");
            return;
        }
        $this->email_summary_frequency = $frequency;
    }

    /**
     * True if AM notifications are enabled
     *
     * @return bool
     */
    public function isAmNoticesEnabled(): bool
    {
        return $this->amNotices;
    }

    /**
     * Set notifications enabled
     *
     * @param bool $enable true if enabled
     *
     * @return void
     */
    public function setAmNotices(bool $enable): void
    {
        $this->amNotices = $enable;
    }

    /**
     * Set purge backup records
     *
     * @param int<0,2> $value ENUM AbstractStorageEntity::BACKUP_RECORDS_*
     *
     * @return void
     */
    public function setPurgeBackupRecords(int $value): void
    {
        switch ($value) {
            case AbstractStorageEntity::BACKUP_RECORDS_REMOVE_ALL:
            case AbstractStorageEntity::BACKUP_RECORDS_REMOVE_NEVER:
            case AbstractStorageEntity::BACKUP_RECORDS_REMOVE_DEFAULT:
                $this->purgeBackupRecords = $value;
                break;
            default:
                throw new Exception('Invalid value');
        }
    }

    /**
     * Get option on how to handle the old backup records
     *
     * @return int<0,2> ENUM AbstractStorageEntity::BACKUP_RECORDS_*
     */
    public function getPurgeBackupRecords(): int
    {
        return $this->purgeBackupRecords;
    }
}
