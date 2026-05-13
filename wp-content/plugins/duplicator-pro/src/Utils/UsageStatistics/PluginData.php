<?php

namespace Duplicator\Utils\UsageStatistics;

use Duplicator\Package\AbstractPackage;
use Duplicator\Models\TemplateEntity;
use Duplicator\Models\ScheduleEntity;
use Duplicator\Addons\ProBase\License\License;
use Duplicator\Core\CapMng;
use Duplicator\Core\MigrationMng;
use Duplicator\Core\Upgrade\UpgradePlugin;
use Duplicator\Core\UniqueId;
use Duplicator\Installer\Core\InstState;
use Duplicator\Installer\Models\MigrateData;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Libs\Snap\SnapWP;
use Duplicator\Libs\WpUtils\WpDbUtils;
use Duplicator\Models\BrandEntity;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\Storages\Local\LocalStorage;
use Duplicator\Package\Create\BuildComponents;
use Duplicator\Package\DupPackage;
use Duplicator\Package\PackageUtils;
use Duplicator\Package\Recovery\RecoveryPackage;
use VendorDuplicator\Amk\JsonSerialize\JsonSerialize;
use wpdb;

class PluginData
{
    const PLUGIN_DATA_OPTION_KEY = 'dupli_opt_plugin_data_stats';

    const PLUGIN_STATUS_ACTIVE   = 'active';
    const PLUGIN_STATUS_INACTIVE = 'inactive';

    /**
     * @var ?self
     */
    private static $instance;

    /**
     * @var int
     */
    private $lastSendTime = 0;

    /**
     * @var string
     */
    private $plugin = 'dup-pro';

    /**
     * @var string
     */
    private $pluginStatus = self::PLUGIN_STATUS_ACTIVE;

    /**
     * @var bool
     */
    private $anonymous = false;

    /**
     * @var int
     */
    private $buildCount = 0;

    /**
     * @var int
     */
    private $buildLastDate = 0;

    /**
     * @var int
     */
    private $buildFailedCount = 0;

    /**
     * @var int
     */
    private $buildFailedLastDate = 0;

    /**
     * @var int
     */
    private $packagesBuildCompFullCount = 0;

    /**
     * @var int
     */
    private $packagesBuildCompDbOnlyCount = 0;

    /**
     * @var int
     */
    private $packagesBuildCompMdOnlyCount = 0;

    /**
     * @var int
     */
    private $packagesBuildCompCustomCount = 0;

    /**
     * @var int
     */
    private $packagesBuildCompCustomOnlyActiveCount = 0;

    /**
     * @var int
     */
    private $schedulesBuildCount = 0;

    /**
     * @var int
     */
    private $schedulesBuildLastDate = 0;

    /**
     * @var int
     */
    private $schedulesBuildFailedCount = 0;

    /**
     * @var int
     */
    private $schedulesBuildFailedLastDate = 0;

    /**
     * @var int
     */
    private $schedulesBuildCompFullCount = 0;

    /**
     * @var int
     */
    private $schedulesBuildCompDbOnlyCount = 0;

    /**
     * @var int
     */
    private $schedulesBuildCompMdOnlyCount = 0;

    /**
     * @var int
     */
    private $schedulesBuildCompCustomCount = 0;

    /**
     * @var int
     */
    private $schedulesBuildCompCustomOnlyActiveCount = 0;

    /**
     * @var int
     */
    private $usedRecoveryCount = 0;

    /**
     * @var float
     */
    private $siteSizeMB = 0;

    /**
     * @var int
     */
    private $siteNumFiles = 0;

    /**
     * @var float
     */
    private $siteDbSizeMB = 0;

    /**
     * @var int
     */
    private $siteDbNumTables = 0;

    /**
     * Class constructor
     */
    private function __construct()
    {
        if (($data = get_option(self::PLUGIN_DATA_OPTION_KEY)) !== false) {
            JsonSerialize::unserializeToObj($data, $this);
        } else {
            $this->save();
        }
    }

    /**
     * Get instance
     *
     * @return self
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Save plugin data
     *
     * @return bool True if data has been saved, false otherwise
     */
    public function save()
    {
        return update_option(self::PLUGIN_DATA_OPTION_KEY, JsonSerialize::serialize($this, JSON_PRETTY_PRINT));
    }

    /**
     * Update from migrate data
     *
     * @param MigrateData $data Migration data
     *
     * @return bool
     */
    public function updateFromMigrateData(MigrateData $data)
    {
        $save = false;
        if ($data->recoveryMode) {
            $this->usedRecoveryCount++;
            $save = true;
        }

        return ($save ? $this->save() : true);
    }

    /**
     * Return usage tracking data
     *
     * @return array<string, mixed>
     */
    public function getDataToSend()
    {
        $result = $this->getBasicInfos();
        $result = array_merge($result, $this->getPluginInfos());
        $result = array_merge($result, $this->getSiteInfos());
        $result = array_merge($result, $this->getManualPackageInfos());
        $result = array_merge($result, $this->getStoragesInfos());
        $result = array_merge($result, $this->getTemplatesInfos());
        $result = array_merge($result, $this->getSchedulesInfos());
        $result = array_merge($result, $this->getGranularPermissionsInfos());
        $result = array_merge($result, $this->getSettingsInfos());
        $result = array_merge($result, $this->getOtherInfos());

        if (!$this->anonymous) {
            $result = array_merge($result, $this->getNonAnonymousInfos());
        }

        $rules = [
            'api_version'      => 'string|max:7', // 1.0
            'identifier'       => 'string|max:44',
            // BASIC INFO
            'plugin_version'   => 'string|max:25',
            'php_version'      => 'string|max:25',
            'wp_version'       => 'string|max:25',
            // PLUGIN INFO
            'pinstall_version' => '?string|max:25',
            // SITE INFO
            'servertype'       => 'string|max:25',
            'db_engine'        => 'string|max:25',
            'db_version'       => 'string|max:25',
            'timezoneoffset'   => 'string|max:10',
            'locale'           => 'string|max:10',
            'themename'        => 'string|max:255',
            'themeversion'     => 'string|max:25',
            // NON-ANONYMOUS INFO
            'email'            => '?string|max:255',
            'api_key'          => '?string|max:32',
        ];

        return StatsUtil::sanitizeFields($result, $rules);
    }

    /**
     * Get disable tracking data
     *
     * @return array<string, mixed>
     */
    public function getDisableDataToSend()
    {
        $result = $this->getBasicInfos();

        $rules = [
            'api_version'    => 'string|max:7', // 1.0
            'identifier'     => 'string|max:44',
            // BASIC INFO
            'plugin_version' => 'string|max:25',
            'php_version'    => 'string|max:25',
            'wp_version'     => 'string|max:25',
        ];

        return StatsUtil::sanitizeFields($result, $rules);
    }

    /**
     * Set status
     *
     * @param string $status Status: active, inactive or uninstalled
     *
     * @return void
     */
    public function setStatus($status): void
    {
        if ($this->pluginStatus === $status) {
            return;
        }

        switch ($status) {
            case self::PLUGIN_STATUS_ACTIVE:
            case self::PLUGIN_STATUS_INACTIVE:
                $this->pluginStatus = $status;
                $this->save();
                break;
        }
    }

    /**
     * Get status
     *
     * @return string Enum: self::PLUGIN_STATUS_ACTIVE, self::PLUGIN_STATUS_INACTIVE or self::PLUGIN_STATUS_UNINSTALLED
     */
    public function getStatus()
    {
        return $this->pluginStatus;
    }

    /**
     * Add package build count and date for manual and schedule build
     *
     * @param AbstractPackage $package Backup
     *
     * @return void
     */
    public function addPackageBuild(AbstractPackage $package): void
    {
        if ($package->getExecutionType() == AbstractPackage::EXEC_TYPE_MANUAL) {
            if ($package->getStatus() == AbstractPackage::STATUS_COMPLETE) {
                $this->buildCount++;
                $this->buildLastDate = time();

                switch (BuildComponents::getActionFromComponents($package->components)) {
                    case BuildComponents::COMP_ACTION_ALL:
                        $this->packagesBuildCompFullCount++;
                        break;
                    case BuildComponents::COMP_ACTION_DB:
                        $this->packagesBuildCompDbOnlyCount++;
                        break;
                    case BuildComponents::COMP_ACTION_MEDIA:
                        $this->packagesBuildCompMdOnlyCount++;
                        break;
                    case BuildComponents::COMP_ACTION_CUSTOM:
                        if (
                            array_intersect(
                                $package->components,
                                [
                                    BuildComponents::COMP_PLUGINS_ACTIVE,
                                    BuildComponents::COMP_THEMES_ACTIVE,
                                ]
                            )
                        ) {
                            $this->packagesBuildCompCustomOnlyActiveCount++;
                        } else {
                            $this->packagesBuildCompCustomCount++;
                        }
                        break;
                }
            } else {
                $this->buildFailedCount++;
                $this->buildFailedLastDate = time();
            }
        } else {
            if ($package->getStatus() == AbstractPackage::STATUS_COMPLETE) {
                $this->schedulesBuildCount++;
                $this->schedulesBuildLastDate = time();

                switch (BuildComponents::getActionFromComponents($package->components)) {
                    case BuildComponents::COMP_ACTION_ALL:
                        $this->schedulesBuildCompFullCount++;
                        break;
                    case BuildComponents::COMP_ACTION_DB:
                        $this->schedulesBuildCompDbOnlyCount++;
                        break;
                    case BuildComponents::COMP_ACTION_MEDIA:
                        $this->schedulesBuildCompMdOnlyCount++;
                        break;
                    case BuildComponents::COMP_ACTION_CUSTOM:
                        if (
                            array_intersect(
                                $package->components,
                                [
                                    BuildComponents::COMP_PLUGINS_ACTIVE,
                                    BuildComponents::COMP_THEMES_ACTIVE,
                                ]
                            )
                        ) {
                            $this->schedulesBuildCompCustomOnlyActiveCount++;
                        } else {
                            $this->schedulesBuildCompCustomCount++;
                        }
                        break;
                }
            } else {
                $this->schedulesBuildFailedCount++;
                $this->schedulesBuildFailedLastDate = time();
            }
        }

        $this->save();
    }

    /**
     * Set site size
     *
     * @param int $size      Site size in bytes
     * @param int $numFiles  Number of files
     * @param int $dbSize    Database size in bytes
     * @param int $numTables Number of tables
     *
     * @return void
     */
    public function setSiteSize($size, $numFiles, $dbSize, $numTables): void
    {
        $this->siteSizeMB      = round(((int) $size) / 1024 / 1024, 2);
        $this->siteNumFiles    = (int) $numFiles;
        $this->siteDbSizeMB    = round(((int) $dbSize) / 1024 / 1024, 2);
        $this->siteDbNumTables = (int) $numTables;
        $this->save();
    }

    /**
     * Update last send time
     *
     * @return void
     */
    public function updateLastSendTime(): void
    {
        $this->lastSendTime = time();
        $this->save();
    }

    /**
     * Get last send time
     *
     * @return int
     */
    public function getLastSendTime()
    {
        return $this->lastSendTime;
    }

    /**
     * Get basic infos
     *
     * @return array<string, mixed>
     */
    protected function getBasicInfos(): array
    {
        return [
            'api_version'    => CommStats::API_VERSION,
            'identifier'     => UniqueId::getInstance()->getIdentifier(),
            'plugin'         => $this->plugin,
            'plugin_status'  => $this->pluginStatus,
            'plugin_version' => DUPLICATOR_VERSION,
            'php_version'    => SnapUtil::getVersion(phpversion(), 3),
            'wp_version'     => get_bloginfo('version'),
        ];
    }

    /**
     * Return plugin infos
     *
     * @return array<string, mixed>
     */
    protected function getPluginInfos(): array
    {
        $installInfo = UpgradePlugin::getInstallInfo();

        return [
            'pinstall_date'    => (isset($installInfo['time']) ? date('Y-m-d H:i:s', $installInfo['time']) : null),
            'pinstall_version' => ($installInfo['version'] ?? null),
            'license_type'     => StatsUtil::getLicenseType(),
            'license_status'   => StatsUtil::getLicenseStatus(),
        ];
    }

    /**
     * Return non-anonymous infos
     *
     * @return array<string, mixed>
     */
    protected function getNonAnonymousInfos(): array
    {
        return [
            'email'   => get_bloginfo('admin_email'),
            'api_key' => (strlen(License::getLicenseKey()) ? License::getLicenseKey() : null),
        ];
    }

    /**
     * Return site infos
     *
     * @return array<string, mixed>
     */
    protected function getSiteInfos(): array
    {
        /** @var wpdb $wpdb */
        global $wpdb;

        $theme_data = wp_get_theme();

        return [
            'servertype'      => StatsUtil::getServerType(),
            'db_engine'       => WpDbUtils::getDbEngine(),
            'db_version'      => WpDbUtils::getVersion(),
            'is_multisite'    => is_multisite(),
            'sites_count'     => count(SnapWP::getSitesIds()),
            'user_count'      => SnapWp::getUsersCount(),
            'timezoneoffset'  => get_option('gmt_offset'),
            /** @todo evaluate use wp or server timezone offset */
            'locale'          => get_locale(),
            'am_family'       => StatsUtil::getAmFamily(),
            'themename'       => $theme_data->get('Name'),
            'themeversion'    => $theme_data->get('Version'),
            'site_size_mb'    => ($this->siteSizeMB == 0 ? null : $this->siteSizeMB),
            'site_num_files'  => ($this->siteNumFiles == 0 ? null : $this->siteNumFiles),
            'site_db_size_mb' => ($this->siteDbSizeMB == 0 ? null : $this->siteDbSizeMB),
            'site_db_num_tbl' => ($this->siteDbNumTables == 0 ? null : $this->siteDbNumTables),
        ];
    }

    /**
     * Return manal Backup infos
     *
     * @return array<string, mixed>
     */
    protected function getManualPackageInfos(): array
    {
        return [
            'packages_build_count'                         => $this->buildCount,
            'packages_build_last_date'                     => ($this->buildLastDate == 0 ? null : date('Y-m-d H:i:s', $this->buildLastDate)),
            'packages_build_failed_count'                  => $this->buildFailedCount,
            'packages_build_failed_last_date'              => ($this->buildFailedLastDate == 0 ? null : date('Y-m-d H:i:s', $this->buildFailedLastDate)),
            'packages_build_comp_full_count'               => $this->packagesBuildCompFullCount,
            'packages_build_comp_dbonly_count'             => $this->packagesBuildCompDbOnlyCount,
            'packages_build_comp_mdonly_count'             => $this->packagesBuildCompMdOnlyCount,
            'packages_build_comp_custom_count'             => $this->packagesBuildCompCustomCount,
            'packages_build_comp_custom_only_active_count' => $this->packagesBuildCompCustomOnlyActiveCount,
            'packages_count'                               => PackageUtils::getNumCompletePackages([DupPackage::getBackupType()]),
        ];
    }

    /**
     * Return storages infos
     *
     * @return array<string, mixed>
     */
    protected function getStoragesInfos()
    {
        $result = [
            'storages_local_count'         => 0,
            'storages_onedrive_count'      => 0,
            'storages_s3_count'            => 0,
            'storages_s3_compatible_count' => 0,
            'storages_backblaze_count'     => 0,
            'storages_tot_count'           => 0,
        ];

        if (($storages = AbstractStorageEntity::getAll()) === false) {
            $storages = [];
        }

        foreach ($storages as $index => $storage) {
            if ($index === 0) {
                // Skip default local storage
                continue;
            }

            if ($storage->getSType() == LocalStorage::getSType()) {
                $result['storages_local_count']++;
            }
        }

        $result = apply_filters('duplicator_usage_stats_storages_infos', $result);

        $total = 0;
        foreach ($result as $value) {
            $total += $value;
        }
        $result['storages_tot_count'] = $total;

        return $result;
    }

    /**
     * return template infos
     *
     * @return array<string, mixed>
     */
    protected function getTemplatesInfos(): array
    {
        $result = [
            'can_use_adv_components'             => License::can(License::CAPABILITY_PACKAGE_COMPONENTS_PLUS),
            'package_manual_create_component'    => 'full',
            'templates_full_count'               => 0,
            'templates_dbonly_count'             => 0,
            'templates_monly_count'              => 0,
            'templates_custom_count'             => 0,
            'templates_custom_only_active_count' => 0,
            'templates_tot_count'                => 0,
        ];

        $manualTpl = TemplateEntity::getManualTemplate();
        switch (BuildComponents::getActionFromComponents($manualTpl->components)) {
            case BuildComponents::COMP_ACTION_ALL:
                $result['package_manual_create_component'] = 'full';
                break;
            case BuildComponents::COMP_ACTION_DB:
                $result['package_manual_create_component'] = 'dbonly';
                break;
            case BuildComponents::COMP_ACTION_MEDIA:
                $result['package_manual_create_component'] = 'mediaonly';
                break;
            case BuildComponents::COMP_ACTION_CUSTOM:
                if (
                    array_intersect(
                        $manualTpl->components,
                        [
                            BuildComponents::COMP_PLUGINS_ACTIVE,
                            BuildComponents::COMP_THEMES_ACTIVE,
                        ]
                    )
                ) {
                    $result['package_manual_create_component'] = 'custom_only_active';
                } else {
                    $result['package_manual_create_component'] = 'custom';
                }
                break;
        }

        if (($templates = TemplateEntity::getAllWithoutManualMode()) === false) {
            $templates = [];
        }

        foreach ($templates as $tpl) {
            switch (BuildComponents::getActionFromComponents($tpl->components)) {
                case BuildComponents::COMP_ACTION_ALL:
                    $result['templates_full_count']++;
                    break;
                case BuildComponents::COMP_ACTION_DB:
                    $result['templates_dbonly_count']++;
                    break;
                case BuildComponents::COMP_ACTION_MEDIA:
                    $result['templates_monly_count']++;
                    break;
                case BuildComponents::COMP_ACTION_CUSTOM:
                    if (
                        array_intersect(
                            $tpl->components,
                            [
                                BuildComponents::COMP_PLUGINS_ACTIVE,
                                BuildComponents::COMP_THEMES_ACTIVE,
                            ]
                        )
                    ) {
                        $result['templates_custom_only_active_count']++;
                    } else {
                        $result['templates_custom_count']++;
                    }
                    break;
            }
            $result['templates_tot_count']++;
        }

        return $result;
    }

    /**
     * Return schedules infos
     *
     * @return array<string, mixed>
     */
    protected function getSchedulesInfos(): array
    {
        $result = [
            'can_use_adv_schedules'                         => License::can(License::CAPABILITY_SHEDULE_HOURLY),
            'schedules_hourly_count'                        => 0,
            'schedules_daily_count'                         => 0,
            'schedules_weekly_count'                        => 0,
            'schedules_monthly_count'                       => 0,
            'schedules_disabled_count'                      => 0,
            'schedules_enabled_count'                       => 0,
            'schedules_build_count'                         => $this->schedulesBuildCount,
            'schedules_build_last_date'                     => ($this->schedulesBuildLastDate == 0 ? null : date('Y-m-d H:i:s', $this->schedulesBuildLastDate)),
            'schedules_build_failed_count'                  => $this->schedulesBuildFailedCount,
            'schedules_build_failed_last_date'              => (
                $this->schedulesBuildFailedLastDate == 0 ? null : date('Y-m-d H:i:s', $this->schedulesBuildFailedLastDate)
            ),
            'schedules_build_comp_full_count'               => $this->schedulesBuildCompFullCount,
            'schedules_build_comp_dbonly_count'             => $this->schedulesBuildCompDbOnlyCount,
            'schedules_build_comp_mdonly_count'             => $this->schedulesBuildCompMdOnlyCount,
            'schedules_build_comp_custom_count'             => $this->schedulesBuildCompCustomCount,
            'schedules_build_comp_custom_only_active_count' => $this->schedulesBuildCompCustomOnlyActiveCount,
        ];

        if (($schedules = ScheduleEntity::getAll()) === false) {
            $schedules = [];
        }

        foreach ($schedules as $schedule) {
            if (!$schedule->isActive()) {
                $result['schedules_disabled_count']++;
                continue;
            }

            $result['schedules_enabled_count']++;
            switch ($schedule->repeat_type) {
                case ScheduleEntity::REPEAT_HOURLY:
                    $result['schedules_hourly_count']++;
                    break;
                case ScheduleEntity::REPEAT_DAILY:
                    $result['schedules_daily_count']++;
                    break;
                case ScheduleEntity::REPEAT_MONTHLY:
                    $result['schedules_monthly_count']++;
                    break;
                case ScheduleEntity::REPEAT_WEEKLY:
                    $result['schedules_weekly_count']++;
                    break;
            }
        }
        return $result;
    }

    /**
     * Return granular permissions infos
     *
     * @return array<string, mixed>
     */
    protected function getGranularPermissionsInfos(): array
    {
        return [
            'can_use_adv_permissions'           => License::can(License::CAPABILITY_CAPABILITIES_MNG_PLUS),
            'have_custom_adv_permissions'       => !CapMng::getInstance()->isDefault(),
            'have_custom_users_adv_permissions' => CapMng::getInstance()->hasUsersCapabilities(),
        ];
    }

    /**
     * Return granular permissions infos
     *
     * @return array<string, mixed>
     */
    protected function getSettingsInfos(): array
    {
        return [
            'settings_archive_build_mode' => StatsUtil::getArchiveBuildMode(),
            'settings_db_build_mode'      => StatsUtil::getDbBuildMode(),
            'settings_usage_enabled'      =>  StatsBootstrap::isTrackingAllowed(),
        ];
    }

    /**
     * Return other infos
     *
     * @return array<string, mixed>
     */
    protected function getOtherInfos(): array
    {
        $migrateData = MigrationMng::getMigrationData();
        if (($brands = BrandEntity::getAll()) == false) {
            $brands = [];
        }

        return [
            'is_recovery_point_set'     => (RecoveryPackage::getRecoverPackageId() !== false),
            'used_recovery_point_count' => $this->usedRecoveryCount,
            'branding_count'            => count($brands),
            'is_recovered_site'         => $migrateData->recoveryMode,
            'is_migrated_site'          => ($migrateData->installType !== InstState::TYPE_NOT_SET),
        ];
    }
}
