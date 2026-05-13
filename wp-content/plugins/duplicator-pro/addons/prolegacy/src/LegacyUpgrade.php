<?php

/**
 * Legacy upgrade functions for backward compatibility
 *
 * @package   Duplicator\Addons\ProLegacy
 * @copyright (c) 2025, Snap Creek LLC
 */

declare(strict_types=1);

namespace Duplicator\Addons\ProLegacy;

use Duplicator\Core\CapMng;
use Duplicator\Core\Models\AbstractEntity;
use Duplicator\Core\UniqueId;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Models\DynamicGlobalEntity;
use Duplicator\Models\GlobalEntity;
use Duplicator\Models\ScheduleEntity;
use Duplicator\Models\SecureGlobalEntity;
use Duplicator\Models\StaticGlobal;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\Storages\StoragesUtil;
use Duplicator\Package\AbstractPackage;
use Duplicator\Package\DupPackage;
use Duplicator\Utils\Crypt\CryptBlowfish;
use Duplicator\Utils\Logging\DupLog;
use Duplicator\Views\AdminNotices;
use Throwable;

/**
 * Handles legacy upgrade migrations from old prefixes
 *
 * Priority scheme:
 * - 10: initTables (creates database tables)
 * - 20: initCapabilities
 * - 50: initEntities (initializes default entities - plugin semi-initialized after this)
 * - 100: initSecureKey (new installations only)
 * - 200: initUniqueId
 * - 1000: updateOptionVersion
 * - 1001: setInstallInfo
 * - 10000: resaveAllEntities (always last)
 */
class LegacyUpgrade
{
    const FIRST_VERSION_WITH_NEW_TABLES              = '4.5.14-beta2';
    const FIRST_VERSION_WITH_STATIC_GLOBAL_IMPROVED  = '4.5.23-beta1';
    const FIRST_VERSION_DEFAULT_PURGE                = '4.5.20-beta1';
    const FIRST_VERSION_WITH_PACKAGE_TYPE            = '4.5.22-beta3';
    const FIRST_VERSION_WITH_DYNAMIC_GLOBAL_ENTITY   = '4.5.22-beta8';
    const FIRST_VERSION_WITH_LOGS_SUBFOLDER          = '4.5.23-beta2';
    const FIRST_VERSION_WITH_WEBSITE_IDENTIFIER_CORE = '4.5.23-RC3';
    const FIRST_VERSION_WITH_ACTIVITY_LOG            = '4.5.25-beta1';
    const FIRST_VERSION_WITH_NEW_OPTION_PREFIX       = '4.5.25-beta8';

    const LEGACY_TRACE_LOG_ENABLED_OPT       = 'duplicator_pro_trace_log_enabled';
    const LEGACY_SEND_TRACE_TO_ERROR_LOG_OPT = 'duplicator_pro_send_trace_to_error_log';
    const LEGACY_PLUGIN_DATA_OPTION_KEY      = 'duplicator_pro_plugin_data_stats';

    /**
     * List of deprecated WordPress options to be removed during upgrade
     *
     * @var string[]
     */
    const DEPRECATED_OPTIONS = ['duplicator_pro_package_active'];

    /**
     * Initialize upgrade hooks
     *
     * Core priorities: 10=initTables, 20=initCapabilities, 50=initEntities, 100+=post-init
     *
     * @return void
     */
    public static function init(): void
    {
        // AFTER initTables (10), BEFORE initCapabilities (20)
        add_action('duplicator_upgrade', [__CLASS__, 'migrateOptionPrefixes'], 12, 2);
        add_action('duplicator_upgrade', [__CLASS__, 'migrateUserMetaKeys'], 12, 2);
        add_action('duplicator_upgrade', [__CLASS__, 'migrateCapabilities'], 18, 2);

        // AFTER initCapabilities (20), BEFORE initEntities (50)
        add_action('duplicator_upgrade', [__CLASS__, 'migrateStaticGlobalOptions'], 40, 2);
        add_action('duplicator_upgrade', [__CLASS__, 'migrateEntityTypes'], 40, 2);

        // AFTER initEntities (50), BEFORE initSecureKey (100)
        add_action('duplicator_upgrade', [__CLASS__, 'storeDupSecureKey'], 101, 2);

        // BEFORE initUniqueId (200)
        add_action('duplicator_upgrade', [__CLASS__, 'migrateWebsiteIdentifier'], 150, 2);

        // AFTER initUniqueId (200)
        add_action('duplicator_upgrade', [__CLASS__, 'moveDataToDynamicGlobalEntity'], 250, 2);
        add_action('duplicator_upgrade', [__CLASS__, 'updatePackageType'], 251, 2);
        add_action('duplicator_upgrade', [__CLASS__, 'migrateLogsToSubfolder'], 252, 2);

        // Legacy storage fixes
        add_action('duplicator_upgrade', [__CLASS__, 'fixDoubleDefaultStorages'], 300, 2);
        add_action('duplicator_upgrade', [__CLASS__, 'updateBackupRecordPurgeSettings'], 301, 2);
        add_action('duplicator_upgrade', [__CLASS__, 'cleanupDeprecatedOptions'], 302, 2);
        add_action('duplicator_upgrade', [__CLASS__, 'notifyActivityLogIntegration'], 303, 2);
    }

    /**
     * Migrate options from old prefixes (duplicator_pro_*, duplicator_expire_) to new prefix (dupli_opt_*)
     *
     * @param false|string $currentVersion current Duplicator version
     * @param string       $newVersion     new Duplicator version
     *
     * @return void
     */
    public static function migrateOptionPrefixes($currentVersion, $newVersion): void
    {
        if ($currentVersion === false || version_compare($currentVersion, self::FIRST_VERSION_WITH_NEW_OPTION_PREFIX, '>=')) {
            return;
        }

        /** @var \wpdb $wpdb */
        global $wpdb;

        try {
            // Map of old option names to new option names
            $optionMapping = [
                'duplicator_pro_capabilities'                        => 'dupli_opt_capabilities',
                'duplicator_pro_notifications'                       => 'dupli_opt_notifications',
                'duplicator_pro_plugin_data_stats'                   => 'dupli_opt_plugin_data_stats',
                'duplicator_pro_recover_point'                       => 'dupli_opt_recover_point',
                'duplicator_pro_ui_view_state'                       => 'dupli_opt_ui_view_state',
                'duplicator_pro_inst_hash_notice'                    => 'dupli_opt_inst_hash_notice',
                'duplicator_pro_activate_plugins_after_installation' => 'dupli_opt_activate_plugins_after_installation',
                'duplicator_pro_migration_success'                   => 'dupli_opt_migration_success',
                'duplicator_pro_s3_contents_fetch_fail'              => 'dupli_opt_s3_contents_fetch_fail',
                'duplicator_pro_quick_fix_notice'                    => 'dupli_opt_quick_fix_notice',
                'duplicator_pro_failed_schedule_notice'              => 'dupli_opt_failed_schedule_notice',
                'duplicator_pro_failed_backup_notice'                => 'dupli_opt_failed_backup_notice',
                'duplicator_pro_activity_log_upgrade_notice'         => 'dupli_opt_activity_log_upgrade_notice',
                'duplicator_pro_first_login_after_install'           => 'dupli_opt_first_login_after_install',
                'duplicator_pro_migration_data'                      => 'dupli_opt_migration_data',
                'duplicator_pro_clean_install_report'                => 'dupli_opt_clean_install_report',
                'duplicator_pro_help_docs_expire'                    => 'dupli_opt_help_docs_expire',
                'duplicator_pro_auth_token_auto_active'              => 'dupli_opt_auth_token_auto_active',
                'duplicator_pro_frotend_delay'                       => 'dupli_opt_frotend_delay',
                'duplicator_pro_pending_cancellations'               => 'dupli_opt_pending_cancellations',
                'duplicator_pro_exe_safe_mode'                       => 'dupli_opt_exe_safe_mode',
                'duplicator_pro_settings'                            => 'dupli_opt_settings',
                // StaticGlobal options
                'duplicator_uninstall_package_option'                => 'dupli_opt_uninstall_package',
                'duplicator_uninstall_settings_option'               => 'dupli_opt_uninstall_settings',
                'duplicator_crypt_option'                            => 'dupli_opt_crypt',
                'duplicator_trace_log_enabled_option'                => 'dupli_opt_trace_log_enabled',
                'duplicator_trace_to_error_log_option'               => 'dupli_opt_trace_to_error_log',
                // UniqueId, TransferFailureHandler, DupCloud options
                'duplicator_unique_id'                               => 'dupli_opt_unique_id',
                'duplicator_failed_transfers'                        => 'dupli_opt_failed_transfers',
                'duplicator_dup_cloud_out_of_space_notice'           => 'dupli_opt_dup_cloud_out_of_space_notice',
            ];

            $migratedCount = 0;
            foreach ($optionMapping as $oldName => $newName) {
                $oldValue = get_option($oldName);
                if ($oldValue !== false) {
                    update_option($newName, $oldValue);
                    delete_option($oldName);
                    $migratedCount++;
                }
            }

            if ($migratedCount > 0) {
                DupLog::trace("LEGACY MIGRATION: Migrated {$migratedCount} options to new prefix");
            }

            // Migrate duplicator_expire_* options to dupli_opt_expire_*
            $expireOptions = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $wpdb->esc_like('duplicator_expire_') . '%'
                ),
                ARRAY_A
            );

            $expireMigratedCount = 0;
            foreach ($expireOptions as $option) {
                $oldName = $option['option_name'];
                $newName = str_replace('duplicator_expire_', 'dupli_opt_expire_', $oldName);
                update_option($newName, $option['option_value']);
                delete_option($oldName);
                $expireMigratedCount++;
            }

            if ($expireMigratedCount > 0) {
                DupLog::trace("LEGACY MIGRATION: Migrated {$expireMigratedCount} expire options to new prefix");
            }
        } catch (Throwable $e) {
            DupLog::trace("LEGACY MIGRATION: Error migrating option prefixes: " . $e->getMessage());
        }
    }

    /**
     * Migrate user meta keys from old prefixes to new prefix (dupli_opt_*)
     *
     * @param false|string $currentVersion current Duplicator version
     * @param string       $newVersion     new Duplicator version
     *
     * @return void
     */
    public static function migrateUserMetaKeys($currentVersion, $newVersion): void
    {
        if ($currentVersion === false || version_compare($currentVersion, self::FIRST_VERSION_WITH_NEW_OPTION_PREFIX, '>=')) {
            return;
        }

        /** @var \wpdb $wpdb */
        global $wpdb;

        $userMetaMapping = [
            'duplicator_pro_created_format'           => 'dupli_opt_created_format',
            'duplicator_pro_opts_per_page'            => 'dupli_opt_opts_per_page',
            'duplicator_user_ui_option'               => 'dupli_opt_user_ui_option',
            'dupli-import-view-mode'                  => 'dupli_opt_import_view_mode',
            'duplicator_recommended_plugin_dismissed' => 'dupli_opt_recommended_plugin_dismissed',
        ];

        try {
            foreach ($userMetaMapping as $oldKey => $newKey) {
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$wpdb->usermeta} SET meta_key = %s WHERE meta_key = %s",
                        $newKey,
                        $oldKey
                    )
                );
            }

            // Bulk migrate any remaining duplicator_pro_* user meta
            $wpdb->query(
                "UPDATE {$wpdb->usermeta} SET meta_key = REPLACE(meta_key, 'duplicator_pro_', 'dupli_opt_')
                 WHERE meta_key LIKE 'duplicator\_pro\_%'"
            );

            DupLog::trace("LEGACY MIGRATION: Migrated user meta keys to new prefix");
        } catch (Throwable $e) {
            DupLog::trace("LEGACY MIGRATION: Error migrating user meta keys: " . $e->getMessage());
        }
    }

    /**
     * Migrate capabilities from old prefix (duplicator_pro_*) to new prefix (duplicator_*)
     *
     * This migration must run BEFORE CapMng::getInstance() (priority 4) because:
     * 1. CapMng reads capabilities from database option
     * 2. The option contains capability names as array keys
     * 3. If we change CAP_PREFIX without migrating, keys won't match and reset() will be called
     *
     * Migration steps:
     * 1. Read current capabilities from dupli_opt_capabilities option
     * 2. Rename array keys from duplicator_pro_* to duplicator_*
     * 3. Update WordPress roles: remove old caps, add new caps
     * 4. Update WordPress users: remove old caps, add new caps
     * 5. Save updated option
     *
     * @param false|string $currentVersion current Duplicator version
     * @param string       $newVersion     new Duplicator version
     *
     * @return void
     */
    public static function migrateCapabilities($currentVersion, $newVersion): void
    {
        if ($currentVersion === false || version_compare($currentVersion, self::FIRST_VERSION_WITH_NEW_OPTION_PREFIX, '>=')) {
            return;
        }

        $oldPrefix = 'duplicator_pro_';
        $newPrefix = CapMng::CAP_PREFIX;

        try {
            $capabilities = get_option(CapMng::OPTION_KEY, false);
            if ($capabilities === false || !is_array($capabilities)) {
                return;
            }

            DupLog::trace("LEGACY MIGRATION: Starting capability prefix migration");

            // Step 1: Remove old capabilities from WordPress roles and users
            foreach ($capabilities as $oldCapName => $data) {
                if (strpos($oldCapName, $oldPrefix) !== 0) {
                    continue;
                }

                foreach ($data['roles'] as $roleName) {
                    $role = get_role($roleName);
                    if ($role) {
                        $role->remove_cap($oldCapName);
                    }
                }

                foreach ($data['users'] as $userId) {
                    $user = get_user_by('id', $userId);
                    if ($user) {
                        $user->remove_cap($oldCapName);
                    }
                }
            }

            // Step 2: Build new capabilities array with renamed keys
            $newCapabilities = [];
            foreach ($capabilities as $oldCapName => $data) {
                if (strpos($oldCapName, $oldPrefix) === 0) {
                    $newCapName = $newPrefix . substr($oldCapName, strlen($oldPrefix));
                } else {
                    $newCapName = $oldCapName;
                }
                $newCapabilities[$newCapName] = $data;
            }

            // Step 3: Save updated option and let CapMng sync with WordPress
            update_option(CapMng::OPTION_KEY, $newCapabilities);
            CapMng::getInstance()->update($newCapabilities);

            $migratedCount = count($newCapabilities);
            DupLog::trace("LEGACY MIGRATION: Migrated {$migratedCount} capabilities from {$oldPrefix}* to {$newPrefix}*");
        } catch (Throwable $e) {
            DupLog::trace("LEGACY MIGRATION: Error migrating capabilities: " . $e->getMessage());
        }
    }

    /**
     * Migrate entity type keys from old prefix (DUP_PRO_*) to new format
     *
     * Updates the `type` column in duplicator_entities table.
     * Runs AFTER initTables (priority 10) and BEFORE initEntities (priority 50).
     *
     * @param false|string $currentVersion current Duplicator version
     * @param string       $newVersion     new Duplicator version
     *
     * @return void
     */
    public static function migrateEntityTypes($currentVersion, $newVersion): void
    {
        if ($currentVersion === false || version_compare($currentVersion, self::FIRST_VERSION_WITH_NEW_OPTION_PREFIX, '>=')) {
            return;
        }

        /** @var \wpdb $wpdb */
        global $wpdb;

        $typeMapping = [
            'DUP_PRO_Schedule_Entity'         => 'Schedule_Entity',
            'DUP_PRO_Global_Entity'           => 'Global_Entity',
            'DUP_PRO_Brand_Entity'            => 'Brand_Entity',
            'DUP_PRO_Package_Template_Entity' => 'Package_Template_Entity',
            'DUP_PRO_Storage_Entity'          => 'Storage_Entity',
            'DUP_PRO_System_Global_Entity'    => 'System_Global_Entity',
            'DUP_PRO_Secure_Global_Entity'    => 'Secure_Global_Entity',
            'DUP_PRO_Staging_Entity'          => 'Staging_Entity',
        ];

        try {
            $tableName     = $wpdb->base_prefix . 'duplicator_entities';
            $migratedCount = 0;

            foreach ($typeMapping as $oldType => $newType) {
                $result = $wpdb->update(
                    $tableName,
                    ['type' => $newType],
                    ['type' => $oldType],
                    ['%s'],
                    ['%s']
                );

                if ($result !== false && $result > 0) {
                    $migratedCount += $result;
                }
            }

            if ($migratedCount > 0) {
                DupLog::trace("LEGACY MIGRATION: Migrated {$migratedCount} entity type keys");
            }
        } catch (Throwable $e) {
            DupLog::trace("LEGACY MIGRATION: Error migrating entity types: " . $e->getMessage());
        }
    }

    /**
     * Migrate static global options from old prefix
     *
     * @param false|string $currentVersion current Duplicator version
     * @param string       $newVersion     new Duplicator version
     *
     * @return void
     */
    public static function migrateStaticGlobalOptions($currentVersion, $newVersion): void
    {
        if (
            $currentVersion === false ||
            version_compare($currentVersion, self::FIRST_VERSION_WITH_STATIC_GLOBAL_IMPROVED, '>=')
        ) {
            return;
        }

        $traceLogEnabled     = get_option(self::LEGACY_TRACE_LOG_ENABLED_OPT, false);
        $sendTraceToErrorLog = get_option(self::LEGACY_SEND_TRACE_TO_ERROR_LOG_OPT, false);

        if ($traceLogEnabled !== false) {
            StaticGlobal::setTraceLogEnabledOption($traceLogEnabled);
            delete_option(self::LEGACY_TRACE_LOG_ENABLED_OPT);
            DupLog::trace("LEGACY MIGRATION: Migrated trace_log_enabled option");
        }

        if ($sendTraceToErrorLog !== false) {
            StaticGlobal::setSendTraceToErrorLogOption($sendTraceToErrorLog);
            delete_option(self::LEGACY_SEND_TRACE_TO_ERROR_LOG_OPT);
            DupLog::trace("LEGACY MIGRATION: Migrated send_trace_to_error_log option");
        }
    }

    /**
     * Remove deprecated WordPress options with old prefix
     *
     * @param false|string $currentVersion current Duplicator version
     * @param string       $newVersion     new Duplicator version
     *
     * @return void
     */
    public static function cleanupDeprecatedOptions($currentVersion, $newVersion): void
    {
        try {
            foreach (self::DEPRECATED_OPTIONS as $optionName) {
                if (delete_option($optionName)) {
                    DupLog::trace("LEGACY CLEANUP: Removed deprecated option: {$optionName}");
                }
            }
        } catch (Throwable $e) {
            DupLog::trace("LEGACY CLEANUP: Error removing deprecated options: " . $e->getMessage());
        }
    }

    /**
     * Save DUP SECURE KEY for legacy upgrades
     *
     * @param false|string $currentVersion current Duplicator version
     * @param string       $newVersion     new Duplicator version
     *
     * @return void
     */
    public static function storeDupSecureKey($currentVersion, $newVersion): void
    {
        if ($currentVersion !== false && SnapUtil::versionCompare($currentVersion, '4.5.0', '<=', 3)) {
            CryptBlowfish::createWpConfigSecureKey(true, true);
        }
    }

    /**
     * Migrate website identifier from PluginData to UniqueId core class
     *
     * @param false|string $currentVersion current Duplicator version
     * @param string       $newVersion     new Duplicator version
     *
     * @return void
     */
    public static function migrateWebsiteIdentifier($currentVersion, $newVersion): void
    {
        if ($currentVersion === false || version_compare($currentVersion, self::FIRST_VERSION_WITH_WEBSITE_IDENTIFIER_CORE, '>=')) {
            return;
        }

        try {
            $pluginDataOption = get_option(self::LEGACY_PLUGIN_DATA_OPTION_KEY, false);
            if ($pluginDataOption === false) {
                return;
            }

            $pluginDataArray = json_decode($pluginDataOption, true);
            if (!is_array($pluginDataArray) || !isset($pluginDataArray['identifier']) || strlen($pluginDataArray['identifier']) === 0) {
                return;
            }

            $oldIdentifier = $pluginDataArray['identifier'];
            update_option(UniqueId::OPTION_KEY, $oldIdentifier, true);
            DupLog::trace("LEGACY MIGRATION: Copied identifier to new location: " . UniqueId::OPTION_KEY);
        } catch (Throwable $e) {
            DupLog::trace("LEGACY MIGRATION: Error migrating website identifier: " . $e->getMessage());
        }
    }

    /**
     * Update legacy package adding type column if empty
     *
     * @param false|string $currentVersion current Duplicator version
     * @param string       $newVersion     new Duplicator version
     *
     * @return void
     */
    public static function updatePackageType($currentVersion, $newVersion): void
    {
        if ($currentVersion === false || version_compare($currentVersion, self::FIRST_VERSION_WITH_PACKAGE_TYPE, '>=')) {
            return;
        }

        /** @var \wpdb $wpdb */
        global $wpdb;

        $table = DupPackage::getTableName();
        $wpdb->query($wpdb->prepare("UPDATE `{$table}` SET type = %s WHERE type IS NULL OR type = ''", DupPackage::getBackupType()));
        DupLog::trace("LEGACY MIGRATION: Updated package type column");
    }

    /**
     * Move data to dynamic global entity from secure global entity
     *
     * @param false|string $currentVersion current Duplicator version
     * @param string       $newVersion     new Duplicator version
     *
     * @return void
     */
    public static function moveDataToDynamicGlobalEntity($currentVersion, $newVersion): void
    {
        if (
            $currentVersion === false ||
            version_compare($currentVersion, self::FIRST_VERSION_WITH_DYNAMIC_GLOBAL_ENTITY, '>=')
        ) {
            return;
        }

        $sGlobal = SecureGlobalEntity::getInstance();
        $dGlobal = DynamicGlobalEntity::getInstance();

        if (!is_null($sGlobal->lkp) && strlen($sGlobal->lkp) > 0) {
            $dGlobal->setValString('license_key_visible_pwd', $sGlobal->lkp);
            $sGlobal->lkp = null;
        }

        if (!is_null($sGlobal->basic_auth_password) && strlen($sGlobal->basic_auth_password) > 0) {
            $dGlobal->setValString('basic_auth_password', $sGlobal->basic_auth_password);
            $sGlobal->basic_auth_password = null;
        }

        $sGlobal->save();
        $dGlobal->save();
        DupLog::trace("LEGACY MIGRATION: Moved data to DynamicGlobalEntity");
    }

    /**
     * Fix double default storages bug from legacy versions
     *
     * @param false|string $currentVersion current Duplicator version
     * @param string       $newVersion     new Duplicator version
     *
     * @return void
     */
    public static function fixDoubleDefaultStorages($currentVersion, $newVersion): void
    {
        if ($currentVersion === false) {
            return;
        }

        try {
            $defaultStorageId = StoragesUtil::getDefaultStorageId();
            $doubleStorageIds = StoragesUtil::removeDoubleDefaultStorages();

            if ($doubleStorageIds === []) {
                return;
            }

            // Auto assign references to the correct default storage
            ScheduleEntity::listCallback(
                function (ScheduleEntity $schedule) use ($defaultStorageId, $doubleStorageIds): void {
                    $save = false;
                    foreach ($schedule->storage_ids as $key => $storageId) {
                        if (!in_array($storageId, $doubleStorageIds)) {
                            continue;
                        }
                        $schedule->storage_ids[$key] = $defaultStorageId;
                        $save                        = true;
                    }
                    $schedule->storage_ids = array_values(array_unique($schedule->storage_ids));
                    if ($save) {
                        $schedule->save();
                    }
                }
            );

            DupPackage::dbSelectByStatusCallback(
                function (DupPackage $package) use ($defaultStorageId, $doubleStorageIds): void {
                    $save = false;
                    if (in_array($package->active_storage_id, $doubleStorageIds)) {
                        $package->active_storage_id = $defaultStorageId;
                        $save                       = true;
                    }
                    foreach ($package->upload_infos as $key => $info) {
                        if (!in_array($info->getStorageId(), $doubleStorageIds)) {
                            continue;
                        }
                        $info->setStorageId($defaultStorageId);
                        $save = true;
                    }
                    if ($save) {
                        $package->save();
                    }
                },
                [
                    [
                        'op'     => '>=',
                        'status' => AbstractPackage::STATUS_COMPLETE,
                    ],
                ]
            );

            DupLog::trace("LEGACY MIGRATION: Fixed double default storages");
        } catch (Throwable $e) {
            DupLog::trace("LEGACY MIGRATION: Error fixing double default storages: " . $e->getMessage());
        }
    }

    /**
     * Sets the correct backup purge setting based on previous default local storage settings
     *
     * @param false|string $currentVersion current Duplicator version
     * @param string       $newVersion     new Duplicator version
     *
     * @return void
     */
    public static function updateBackupRecordPurgeSettings($currentVersion, $newVersion): void
    {
        if ($currentVersion === false || version_compare($currentVersion, self::FIRST_VERSION_DEFAULT_PURGE, '>=')) {
            return;
        }

        $global = GlobalEntity::getInstance();
        if (StoragesUtil::getDefaultStorage()->isPurgeEnabled()) {
            $global->setPurgeBackupRecords(AbstractStorageEntity::BACKUP_RECORDS_REMOVE_DEFAULT);
        } else {
            $global->setPurgeBackupRecords(AbstractStorageEntity::BACKUP_RECORDS_REMOVE_NEVER);
        }

        $global->save();
        DupLog::trace("LEGACY MIGRATION: Updated backup record purge settings");
    }

    /**
     * Migrate existing log files from root directory to logs subfolder
     *
     * @param false|string $currentVersion current Duplicator version
     * @param string       $newVersion     new Duplicator version
     *
     * @return void
     */
    public static function migrateLogsToSubfolder($currentVersion, $newVersion): void
    {
        if ($currentVersion === false || version_compare($currentVersion, self::FIRST_VERSION_WITH_LOGS_SUBFOLDER, '>=')) {
            return;
        }

        try {
            DupLog::trace("LEGACY MIGRATION: Moving log files to logs subfolder");

            // Ensure logs directory exists
            if (!file_exists(DUPLICATOR_LOGS_PATH)) {
                SnapIO::mkdirP(DUPLICATOR_LOGS_PATH);
                SnapIO::chmod(DUPLICATOR_LOGS_PATH, 'u+rwx');
                SnapIO::createSilenceIndex(DUPLICATOR_LOGS_PATH);
            }

            // Use SnapIO::regexGlob for more robust file discovery
            $logFiles = SnapIO::regexGlob(DUPLICATOR_SSDIR_PATH, [
                'regexFile'   => [
                    '/.*_log\.txt$/',
                    '/.*_log_bak\.txt$/',
                    '/.*\.log$/',
                ],
                'regexFolder' => false,
                'recursive'   => false,
            ]);

            $migratedCount = 0;
            foreach ($logFiles as $oldPath) {
                $filename = basename($oldPath);
                $newPath  = DUPLICATOR_LOGS_PATH . '/' . $filename;

                if (SnapIO::rename($oldPath, $newPath)) {
                    $migratedCount++;
                }
            }

            DupLog::trace("LEGACY MIGRATION: Moved {$migratedCount} log files to logs subfolder");
        } catch (Throwable $e) {
            DupLog::trace("LEGACY MIGRATION: Error moving log files: " . $e->getMessage());
        }
    }

    /**
     * Set transient for Activity Log integration upgrade notice if failed backups exist
     *
     * @param false|string $currentVersion current Duplicator version
     * @param string       $newVersion     new Duplicator version
     *
     * @return void
     */
    public static function notifyActivityLogIntegration($currentVersion, $newVersion): void
    {
        if ($currentVersion === false || version_compare($currentVersion, self::FIRST_VERSION_WITH_ACTIVITY_LOG, '>=')) {
            return;
        }

        try {
            $count = DupPackage::countByStatus(
                [
                    [
                        'op'     => '<',
                        'status' => AbstractPackage::STATUS_PRE_PROCESS,
                    ],
                ],
                [DupPackage::getBackupType()]
            );

            if ($count > 0) {
                set_transient(AdminNotices::ACTIVITY_LOG_UPGRADE_NOTICE, $count, 0);
                DupLog::trace("LEGACY MIGRATION: Set Activity Log upgrade notice transient with count: " . $count);
            }
        } catch (Throwable $e) {
            DupLog::trace("LEGACY MIGRATION: Error setting Activity Log upgrade notice: " . $e->getMessage());
        }
    }
}
