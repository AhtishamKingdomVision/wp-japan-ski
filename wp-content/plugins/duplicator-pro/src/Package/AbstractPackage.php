<?php

namespace Duplicator\Package;

use Duplicator\Core\MigrationMng;
use Duplicator\Installer\Package\InstallerDescriptors;
use Duplicator\Libs\WpUtils\WpDbUtils;
use Duplicator\Models\ScheduleEntity;
use Duplicator\Models\Storages\StoragesUtil;
use Duplicator\Models\TemplateEntity;
use Duplicator\Package\Archive\PackageArchive;
use Duplicator\Package\Create\BuildComponents;
use Duplicator\Package\Create\BuildProgress;
use Duplicator\Package\Create\DbBuildProgress;
use Duplicator\Package\Create\PackInstaller;
use Duplicator\Package\Database\DatabasePkg;
use Duplicator\Package\Storage\UploadInfo;
use Exception;

abstract class AbstractPackage
{
    use TraitCreateActiviyLog;
    use TraitPackageBuild;
    use TraitPackageCancellation;
    use TraitPackageFiles;
    use TraitPackagePersistence;
    use TraitPackageProgress;
    use TraitPackageQuery;
    use TraitPackageScan;
    use TraitPackageSchedule;
    use TraitPackageStorage;
    use TraitPackageTemplate;

    const EXEC_TYPE_NOT_SET   = -1; // User for legacy packages load, never used for new packages
    const EXEC_TYPE_MANUAL    = 0;
    const EXEC_TYPE_SCHEDULED = 1;
    const EXEC_TYPE_RUN_NOW   = 2;

    const FLAG_MANUAL                = 'MANUAL';
    const FLAG_SCHEDULE              = 'SCHEDULE';
    const FLAG_SCHEDULE_RUN_NOW      = 'SCHEDULE_RUN_NOW';
    const FLAG_DB_ONLY               = 'DB_ONLY';
    const FLAG_MEDIA_ONLY            = 'MEDIA_ONLY';
    const FLAG_HAVE_LOCAL            = 'HAVE_LOCAL';
    const FLAG_HAVE_REMOTE           = 'HAVE_REMOTE';
    const FLAG_DISASTER_AVAIABLE     = 'DISASTER_AVAIABLE';
    const FLAG_DISASTER_SET          = 'DISASTER_SET';
    const FLAG_CREATED_AFTER_RESTORE = 'CREATED_AFTER_RESTORE';
    const FLAG_ZIP_ARCHIVE           = 'ZIP_ARCHIVE';
    const FLAG_DUP_ARCHIVE           = 'DUP_ARCHIVE';
    const FLAG_ACTIVE                = 'ACTIVE'; // For future use
    const FLAG_TEMPLATE              = 'TEMPLATE'; // For future use
    const FLAG_TEMPORARY             = 'TEMPORARY'; // Temporary package for creation initial package

    const STATUS_REQUIREMENTS_FAILED = -6;
    const STATUS_STORAGE_FAILED      = -5;
    const STATUS_STORAGE_CANCELLED   = -4;
    const STATUS_PENDING_CANCEL      = -3;
    const STATUS_BUILD_CANCELLED     = -2;
    const STATUS_ERROR               = -1;
    const STATUS_PRE_PROCESS         = 0;
    const STATUS_SCANNING            = 3;
    const STATUS_SCAN_VALIDATION     = 4;
    const STATUS_AFTER_SCAN          = 5;
    const STATUS_START               = 10;
    const STATUS_DBSTART             = 20;
    const STATUS_DBDONE              = 39;
    const STATUS_ARCSTART            = 40;
    const STATUS_ARCVALIDATION       = 60;
    const STATUS_ARCDONE             = 65;
    const STATUS_COPIEDPACKAGE       = 70;
    const STATUS_STORAGE_PROCESSING  = 75;
    const STATUS_COMPLETE            = 100;

    const FILE_TYPE_INSTALLER = 0;
    const FILE_TYPE_ARCHIVE   = 1;
    const FILE_TYPE_LOG       = 3;

    const PACKAGE_HASH_DATE_FORMAT = 'YmdHis';

    /** @var int<-1,max> */
    protected $ID = -1;
    /** @var string */
    public $VersionWP = '';
    /** @var string */
    public $VersionDB = '';
    /** @var string */
    public $VersionPHP = '';
    /** @var string */
    public $VersionOS = '';
    /** @var string */
    protected $name = '';
    /** @var string */
    protected $hash = '';
    /** @var int Enum self::EXEC_TYPE_* */
    protected $execType = self::EXEC_TYPE_NOT_SET;
    /** @var string */
    public $notes = '';
    /** @var string */
    public $StorePath = DUPLICATOR_SSDIR_PATH_TMP;
    /** @var string */
    public $StoreURL = DUPLICATOR_SSDIR_URL . '/';
    /** @var string */
    public $ScanFile = '';
    /** @var float */
    public $timer_start = -1;
    /** @var array<int, array{start: float, end: float}> Per-status timing data keyed by STATUS_* enum */
    protected array $state_times = [];
    /** @var string */
    public $Runtime = '';
    /** @var string */
    public $ExeSize = '0';
    /** @var string */
    public $ZipSize = '0';
    /** @var string */
    public $Brand = '';
    /** @var int<-2,max> */
    public $Brand_ID = -2;
    /** @var int ENUM PackageArchive::ZIP_MODE_* */
    public $ziparchive_mode = PackageArchive::ZIP_MODE_MULTI_THREAD;
    /** @var PackageArchive */
    public $Archive;
    /** @var PackMultisite */
    public $Multisite;
    /** @var PackInstaller */
    public $Installer;
    /** @var DatabasePkg */
    public $Database;
    /** @var string[] */
    public $components = [];

    /** @var int self::STATUS_* enum */
    protected int $status = self::STATUS_PRE_PROCESS;
    /** @var int<-1,max> */
    protected $schedule_id = -1;
    // Schedule ID that created this
    // Chunking progress through build and storage uploads

    /** @var InstallerDescriptors */
    protected $descriptorsMng;
    /** @var BuildProgress */
    public $build_progress;
    /** @var DbBuildProgress */
    public $db_build_progress;
    /** @var UploadInfo[] */
    public $upload_infos = [];
    /** @var int<-1,max> */
    public $active_storage_id = -1;
    /** @var int<-1,max> */
    public $template_id = -1;
    /** @var bool */
    protected $buildEmailSent = false;

    /** @var string */
    protected $version        = DUPLICATOR_VERSION;
    protected string $created = '';
    /** @var string */
    protected $updated = '';
    /** @var string[] list ENUM self::FLAG_* */
    protected $flags = [];
    /** @var bool */
    protected $flagUpdatedAfterLoad = true;

    /**
     * Class contructor
     * The constructor is final to prevent PHP stan error
     * Unsafe usage of new static(). See: https://phpstan.org/blog/solving-phpstan-error-unsafe-usage-of-new-static
     * For now I have solved it this way but if in the future it is necessary to expand the builders there are other ways to handle this
     *
     * @param int            $execType   self::EXEC_TYPE_* ENUM
     * @param int[]          $storageIds Storages id
     * @param TemplateEntity $template   Template for Backup or null
     * @param ScheduleEntity $schedule   Schedule for Backup or null
     */
    final public function __construct(
        $execType = self::EXEC_TYPE_MANUAL,
        $storageIds = [],
        ?TemplateEntity $template = null,
        ?ScheduleEntity $schedule = null
    ) {
        global $wp_version;

        switch ($execType) {
            case self::EXEC_TYPE_MANUAL:
                $this->execType = self::EXEC_TYPE_MANUAL;
                break;
            case self::EXEC_TYPE_SCHEDULED:
                $this->execType = self::EXEC_TYPE_SCHEDULED;
                break;
            case self::EXEC_TYPE_RUN_NOW:
                $this->execType = self::EXEC_TYPE_RUN_NOW;
                break;
            default:
                throw new Exception("Package type $execType not supported");
        }

        $this->VersionOS  = defined('PHP_OS') ? PHP_OS : 'unknown';
        $this->VersionWP  = $wp_version;
        $this->VersionPHP = phpversion();
        $dbversion        = WpDbUtils::getVersion();
        $this->VersionDB  = (empty($dbversion) ? '- unknown -' : $dbversion);

        if ($schedule !== null) {
            $this->schedule_id = $schedule->getId();
        }

        $timestamp     = time();
        $this->created = gmdate("Y-m-d H:i:s", $timestamp);
        $this->name    = $this->getNameFromFormat($template, $timestamp);
        $this->hash    = $this->makeHash();

        $this->components = BuildComponents::COMPONENTS_DEFAULT;

        $this->Database          = new DatabasePkg($this);
        $this->Archive           = new PackageArchive($this);
        $this->Multisite         = new PackMultisite();
        $this->Installer         = new PackInstaller($this);
        $this->build_progress    = new BuildProgress();
        $this->db_build_progress = new DbBuildProgress();

        $this->build_progress->setBuildMode();

        $this->setByTemplate($template);
        if (empty($storageIds)) {
            $storageIds = [StoragesUtil::getDefaultStorageId()];
        }
        $this->addUploadInfos($storageIds);
        $this->updatePackageFlags();
        $this->switchStateTimer(null, self::STATUS_PRE_PROCESS);
    }

    /**
     * Clone
     *
     * @return void
     */
    public function __clone()
    {
        $this->Database          = clone $this->Database;
        $this->Archive           = clone $this->Archive;
        $this->Multisite         = clone $this->Multisite;
        $this->Installer         = clone $this->Installer;
        $this->build_progress    = clone $this->build_progress;
        $this->db_build_progress = clone $this->db_build_progress;
        $cloneInfo               = [];
        foreach ($this->upload_infos as $key => $obj) {
            $cloneInfo[$key] = clone $obj;
        }
        $this->upload_infos = $cloneInfo;
    }

    /**
     * Get package id
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->ID;
    }

    /**
     * Get package status
     *
     * @return int self::STATUS_* enum
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * Get backup type
     *
     * @return string
     */
    abstract public static function getBackupType(): string;

    /**
     * Register package type, in this function must add filter duplicator_package_type_classes_map
     *
     * @return void
     */
    public static function registerType(): void
    {
        add_filter('duplicator_package_type_classes_map', function (array $classesMap): array {
            $classesMap[static::getBackupType()] = static::class;
            return $classesMap;
        });
    }

    /**
     * Return Backup flags
     *
     * @return string[] ENUM self::FLAG_*
     */
    protected function getFlags()
    {
        if ($this->flagUpdatedAfterLoad == false) {
            $this->updatePackageFlags();
            $this->flagUpdatedAfterLoad = true;
        }
        return $this->flags;
    }

    /**
     * Check if package have flag
     *
     * @param string $flag flag to check, ENUM self::FLAG_*
     *
     * @return bool
     */
    public function hasFlag($flag)
    {
        return in_array($flag, $this->getFlags());
    }

    /**
     * Update the Backup migration flag
     *
     * @return void
     */
    public function updateMigrateAfterInstallFlag(): void
    {
        $this->updatePackageFlags();
        $this->flags = array_diff(
            $this->flags,
            [self::FLAG_CREATED_AFTER_RESTORE]
        );
        $data        = MigrationMng::getMigrationData();
        // check if package id is set for old versions before 4.5.14
        if ($data->restoreBackupMode && $data->packageId > 0) {
            $installTime = strtotime($data->installTime);
            $created     = strtotime($this->created);
            if (
                $this->getId() > $data->packageId && // If Backup is create after installer Backup
                $created < $installTime // But berore the installer time
            ) {
                $this->flags[] = self::FLAG_CREATED_AFTER_RESTORE;
            }
        }
        $this->flags = array_values($this->flags);
    }


    /**
     * Returns true if this is a DB only Backup
     *
     * @return bool
     */
    public function isDBOnly()
    {
        return BuildComponents::isDBOnly($this->components) || $this->Archive->ExportOnlyDB;
    }

    /**
     * Returns true if this is a File only Backup
     *
     * @return bool
     */
    public function isDBExcluded()
    {
        return BuildComponents::isDBExcluded($this->components);
    }


    /**
     * Get execution type
     *
     * @return int
     */
    public function getExecutionType(): int
    {
        return $this->execType;
    }

    /**
     *  Sets the status to log the state of the build and save in database
     *
     *  @param int $status The status self::STATUS_* enum
     *
     *  @return void
     */
    final public function setStatus(int $status): void
    {
        if (
            $status < self::STATUS_REQUIREMENTS_FAILED ||
            $status > self::STATUS_COMPLETE
        ) {
            throw new Exception("Package SetStatus did not receive a proper code.");
        }

        $previousStatus = $this->status;
        $hasChanged     = ($previousStatus != $status);
        if ($hasChanged) {
            // Execute hooks only if status has changed
            do_action('duplicator_package_before_set_status', $this, $status);
            $this->switchStateTimer($previousStatus, $status);
            $this->status = $status;
        }

        $this->update(); // Always update Backup

        if ($hasChanged) {
            do_action('duplicator_package_after_set_status', $this, $status);
            // Add log event after update only if status has changed
            $this->addLogEvent($previousStatus);
        }
    }

    /**
     * Close the timer of the previous status (if any) and open the timer for the new status.
     *
     * A single microtime() is used for both the previous end and the new start so
     * durations are contiguous with no gap between consecutive states.
     * Terminal statuses (COMPLETE or any failure/cancel state) are recorded with
     * start == end since no time is spent in them.
     *
     * @param ?int $previousStatus self::STATUS_* enum, or null on initial registration
     * @param int  $newStatus      self::STATUS_* enum
     *
     * @return void
     */
    private function switchStateTimer(?int $previousStatus, int $newStatus): void
    {
        $now = microtime(true);

        if ($previousStatus !== null && isset($this->state_times[$previousStatus])) {
            $this->state_times[$previousStatus]['end'] = $now;
        }

        $isTerminal                    = ($newStatus === self::STATUS_COMPLETE || $newStatus < self::STATUS_PRE_PROCESS);
        $this->state_times[$newStatus] = [
            'start' => $now,
            'end'   => $isTerminal ? $now : 0.0,
        ];
    }

    /**
     * Get how long the package has spent in the given status, in seconds
     *
     * - Returns -1 if no start time was recorded for the status.
     * - If start is recorded but end is not, returns elapsed seconds since start (ongoing).
     * - If both are recorded, returns end - start.
     *
     * @param int $status self::STATUS_* enum
     *
     * @return float Duration in seconds, or -1 if no start time recorded
     */
    public function getStateDuration(int $status): float
    {
        if (!isset($this->state_times[$status]) || $this->state_times[$status]['start'] <= 0) {
            return -1;
        }

        $start = $this->state_times[$status]['start'];
        $end   = $this->state_times[$status]['end'];
        return $end <= 0 ? microtime(true) - $start : $end - $start;
    }

    /**
     * Set progress
     *
     * @param float $progressPercent Progress percentage
     *
     * @return void
     *
     * @deprecated This method is deprecated and no longer updates progress.
     *             Progress is now calculated dynamically via getProgress() from TraitPackageProgress.
     *             The progressPercent property is kept for backward compatibility but not updated.
     */
    public function setProgressPercent(float $progressPercent): void
    {
        // No-op: Progress is now calculated dynamically via getProgress()
        // The progressPercent property is maintained for backward compatibility but not updated
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
     * Get hash
     *
     * @return string
     */
    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * Get the backup's descriptor manager
     *
     * @return InstallerDescriptors The descriptor manager
     */
    public function getDescriptorMng()
    {
        if (is_null($this->descriptorsMng)) {
            $this->descriptorsMng = new InstallerDescriptors(
                $this->getPrimaryInternalHash(),
                date(self::PACKAGE_HASH_DATE_FORMAT, strtotime($this->created))
            );
        }

        return $this->descriptorsMng;
    }

    /**
     * Get version of Backups stored in DB
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Get scheudle id
     *
     * @return int
     */
    public function getScheduleId()
    {
        return $this->schedule_id;
    }

    /**
     * Validates the inputs from the UI for correct data input
     *
     * @return InputValidator
     */
    public function validateInputs()
    {
        $validator = new InputValidator();

        if ($this->Archive->FilterOn) {
            $validator->explodeFilterCustom(
                $this->Archive->FilterDirs,
                ';',
                InputValidator::FILTER_VALIDATE_FOLDER_WITH_COMMENT,
                [
                    'valkey' => 'FilterDirs',
                    'errmsg' => __(
                        'Directory: <b>%1$s</b> is an invalid path.
                        Please remove the value from the Archive > Files Tab > Folders input box and apply only valid paths.',
                        'duplicator-pro'
                    ),
                ]
            );

            $validator->explodeFilterCustom(
                $this->Archive->FilterExts,
                ';',
                InputValidator::FILTER_VALIDATE_FILE_EXT,
                [
                    'valkey' => 'FilterExts',
                    'errmsg' => __(
                        'File extension: <b>%1$s</b> is an invalid extension name.
                        Please remove the value from the Archive > Files Tab > File Extensions input box and apply only valid extensions. For example \'jpg\'',
                        'duplicator-pro'
                    ),
                ]
            );

            $validator->explodeFilterCustom(
                $this->Archive->FilterFiles,
                ';',
                InputValidator::FILTER_VALIDATE_FILE_WITH_COMMENT,
                [
                    'valkey' => 'FilterFiles',
                    'errmsg' => __(
                        'File: <b>%1$s</b> is an invalid file name.
                        Please remove the value from the Archive > Files Tab > Files input box and apply only valid file names.',
                        'duplicator-pro'
                    ),
                ]
            );
        }

        //FILTER_VALIDATE_DOMAIN throws notice message on PHP 5.6
        if (defined('FILTER_VALIDATE_DOMAIN')) {
            // phpcs:ignore PHPCompatibility.Constants.NewConstants.filter_validate_domainFound
            $validator->filterVar($this->Installer->OptsDBHost, FILTER_VALIDATE_DOMAIN, [
                'valkey'   => 'OptsDBHost',
                'errmsg'   => __('MySQL Server Host: <b>%1$s</b> isn\'t a valid host', 'duplicator-pro'),
                'acc_vals' => [
                    '',
                    'localhost',
                ],
            ]);
        }

        return $validator;
    }


    /**
     * Get created date
     *
     * @return string
     */
    public function getCreated(): string
    {
        return $this->created;
    }
}
