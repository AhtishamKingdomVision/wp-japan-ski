<?php

/**
 * Settings page controller
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Controllers;

use Duplicator\Models\GlobalEntity;
use Duplicator\Utils\Logging\DupLog;
use Duplicator\Utils\ActivityLog\SettingsChangeTracker;
use Duplicator\Models\ActivityLog\LogEventSettingsChange;
use Duplicator\Models\ActivityLog\LogEventBrandCreateUpdate;
use Duplicator\Models\ActivityLog\LogEventBrandDelete;
use Duplicator\Core\CapMng;
use Duplicator\Core\Constants;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Core\Controllers\AbstractMenuPageController;
use Duplicator\Core\Controllers\PageAction;
use Duplicator\Core\Controllers\SubMenuItem;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Shell\Shell;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Libs\WpUtils\WpDbUtils;
use Duplicator\Models\BrandEntity;
use Duplicator\Models\DynamicGlobalEntity;
use Duplicator\Models\StaticGlobal;
use Duplicator\Utils\Logging\TraceLogMng;
use Duplicator\Utils\Settings\MigrateSettings;
use Duplicator\Utils\Settings\ServerThrottle;
use Exception;

class SettingsPageController extends AbstractMenuPageController
{
    const NONCE_ACTION = 'dupli-settings-package';

    /**
     * tabs menu
     */
    const L2_SLUG_GENERAL         = 'general';
    const L2_SLUG_GENERAL_MIGRATE = 'migrate';
    const L2_SLUG_PACKAGE_BRAND   = 'brand';
    const L2_SLUG_PACKAGE         = 'package';
    const L2_SLUG_SCHEDULE        = 'schedule';
    const L2_SLUG_STORAGE         = 'storage';
    const L2_SLUG_IMPORT          = 'import';
    const L2_SLUG_CAPABILITIES    = 'capabilities';

    const BRAND_INNER_PAGE_LIST = 'list';
    const BRAND_INNER_PAGE_EDIT = 'edit';

    /*
     * action types
     */
    const ACTION_GENERAL_SAVE          = 'save';
    const ACTION_GENERAL_TRACE         = 'trace';
    const ACTION_CAPABILITIES_SAVE     = 'cap-save';
    const ACTION_CAPABILITIES_RESET    = 'cap-reset';
    const ACTION_IMPORT_SAVE_SETTINGS  = 'import-save-set';
    const ACTION_PACKAGE_ADVANCED_SAVE = 'pack-adv-save';
    const ACTION_PACKAGE_BASIC_SAVE    = 'pack-basic-save';
    const ACTION_RESET_SETTINGS        = 'reset-settings';
    const ACTION_SAVE_SCHEDULE         = 'save-schedule';
    const ACTION_SAVE_STORAGE          = 'save-storage';
    const ACTION_SAVE_STORAGE_SSL      = 'save-storage-ssl';
    const ACTION_SAVE_STORAGE_OPTIONS  = 'save-storage-options';
    const ACTION_IMPORT_SETTINGS       = 'import-settings';
    const ACTION_BRAND_SAVE            = 'save-brand';
    const ACTION_BRAND_DELETE          = 'delete-brand';

    /**
     * Class constructor
     */
    protected function __construct()
    {
        $this->parentSlug   = ControllersManager::MAIN_MENU_SLUG;
        $this->pageSlug     = ControllersManager::SETTINGS_SUBMENU_SLUG;
        $this->pageTitle    = __('Settings', 'duplicator-pro');
        $this->menuLabel    = __('Settings', 'duplicator-pro');
        $this->capatibility = CapMng::CAP_SETTINGS;
        $this->menuPos      = 60;

        add_filter('duplicator_sub_menu_items_' . $this->pageSlug, [$this, 'getBasicSubMenus']);
        add_filter('duplicator_sub_level_default_tab_' . $this->pageSlug, [$this, 'getSubMenuDefaults'], 10, 2);
        add_action('duplicator_render_page_content_' . $this->pageSlug, [$this, 'renderContent'], 10, 2);
        add_filter('duplicator_page_actions_' . $this->pageSlug, [$this, 'pageActions']);
    }

    /**
     * Return sub menus for current page
     *
     * @param SubMenuItem[] $subMenus sub menus list
     *
     * @return SubMenuItem[]
     */
    public function getBasicSubMenus($subMenus)
    {
        $subMenus[] = new SubMenuItem(self::L2_SLUG_GENERAL, __('General', 'duplicator-pro'));
        $subMenus[] = new SubMenuItem(self::L2_SLUG_PACKAGE, __('Backups', 'duplicator-pro'));
        $subMenus[] = new SubMenuItem(self::L2_SLUG_PACKAGE_BRAND, __('Installer Branding', 'duplicator-pro'));
        $subMenus[] = new SubMenuItem(self::L2_SLUG_SCHEDULE, __('Schedules', 'duplicator-pro'));
        $subMenus[] = new SubMenuItem(self::L2_SLUG_STORAGE, __('Storage', 'duplicator-pro'));
        $subMenus[] = new SubMenuItem(self::L2_SLUG_IMPORT, __('Import', 'duplicator-pro'));
        $subMenus[] = new SubMenuItem(self::L2_SLUG_GENERAL_MIGRATE, __('Import/Export Settings', 'duplicator-pro'));
        $subMenus[] = new SubMenuItem(self::L2_SLUG_CAPABILITIES, __('Access', 'duplicator-pro'));

        return $subMenus;
    }

    /**
     * Return slug default for parent menu slug
     *
     * @param string $slug   current default
     * @param string $parent parent for default
     *
     * @return string default slug
     */
    public function getSubMenuDefaults($slug, $parent)
    {
        switch ($parent) {
            case '':
                return self::L2_SLUG_GENERAL;
            default:
                return $slug;
        }
    }

    /**
     * Return actions for current page
     *
     * @param PageAction[] $actions actions lists
     *
     * @return PageAction[]
     */
    public function pageActions($actions)
    {
        $actions[] = new PageAction(
            self::ACTION_GENERAL_SAVE,
            [
                $this,
                'saveGeneral',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_GENERAL,
            ]
        );
        $actions[] = new PageAction(
            self::ACTION_GENERAL_TRACE,
            [
                $this,
                'traceGeneral',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_GENERAL,
            ]
        );
        $actions[] = new PageAction(
            self::ACTION_CAPABILITIES_SAVE,
            [
                $this,
                'saveCapabilities',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_CAPABILITIES,
            ]
        );
        $actions[] = new PageAction(
            self::ACTION_CAPABILITIES_RESET,
            [
                $this,
                'resetCapabilities',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_CAPABILITIES,
            ]
        );
        $actions[] = new PageAction(
            self::ACTION_PACKAGE_BASIC_SAVE,
            [
                $this,
                'savePackage',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_PACKAGE,
            ]
        );
        $actions[] = new PageAction(
            self::ACTION_IMPORT_SAVE_SETTINGS,
            [
                $this,
                'saveImportSettngs',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_IMPORT,
            ]
        );
        $actions[] = new PageAction(
            self::ACTION_RESET_SETTINGS,
            [
                $this,
                'resetSettings',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_GENERAL,
            ]
        );
        $actions[] = new PageAction(
            self::ACTION_SAVE_STORAGE,
            [
                $this,
                'saveStorageGeneral',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_STORAGE,
            ]
        );
        $actions[] = new PageAction(
            self::ACTION_SAVE_SCHEDULE,
            [
                $this,
                'saveSchedule',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_SCHEDULE,
            ]
        );
        $actions[] = new PageAction(
            self::ACTION_IMPORT_SETTINGS,
            [
                $this,
                'importSettings',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_GENERAL_MIGRATE,
            ]
        );
        $actions[] = new PageAction(
            self::ACTION_BRAND_SAVE,
            [
                $this,
                'brandSave',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_PACKAGE_BRAND,
            ],
            self::BRAND_INNER_PAGE_EDIT
        );
        $actions[] = new PageAction(
            self::ACTION_BRAND_DELETE,
            [
                $this,
                'brandDelete',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_PACKAGE_BRAND,
            ]
        );

        return $actions;
    }


    /**
     * Render page content
     *
     * @param string[] $currentLevelSlugs current menu slugs
     * @param string   $innerPage         current inner page, empty if not set
     *
     * @return void
     */
    public function renderContent($currentLevelSlugs, $innerPage): void
    {
        switch ($currentLevelSlugs[1]) {
            case self::L2_SLUG_GENERAL:
                TplMng::getInstance()->render('admin_pages/settings/general/general');
                break;
            case self::L2_SLUG_PACKAGE_BRAND:
                switch ($innerPage) {
                    case self::BRAND_INNER_PAGE_EDIT:
                        $brandId = TplMng::getInstance()->getGlobalValue(
                            'actionBrandId',
                            SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'id', -1)
                        );

                        if ($brandId === -1) {
                            $brand = new BrandEntity();
                        } elseif ($brandId === 0) {
                            $brand = BrandEntity::getDefaultBrand();
                        } else {
                            $brand = BrandEntity::getById($brandId);
                        }
                        DupLog::trace("IS DEFAULT: " . $brand->isDefault());
                        TplMng::getInstance()->render(
                            'admin_pages/settings/brand/brand_edit',
                            ['brand' => $brand]
                        );
                        break;
                    case self::BRAND_INNER_PAGE_LIST:
                    default:
                        TplMng::getInstance()->render('admin_pages/settings/brand/brand_list');
                        break;
                }
                break;
            case self::L2_SLUG_GENERAL_MIGRATE:
                TplMng::getInstance()->render('admin_pages/settings/migrate_settings/migrate_page');
                break;
            case self::L2_SLUG_PACKAGE:
                TplMng::getInstance()->render('admin_pages/settings/backup/backup_settings');
                break;
            case self::L2_SLUG_IMPORT:
                TplMng::getInstance()->render('admin_pages/settings/import/import');
                break;
            case self::L2_SLUG_SCHEDULE:
                TplMng::getInstance()->render('admin_pages/settings/schedule/schedule');
                break;
            case self::L2_SLUG_STORAGE:
                TplMng::getInstance()->render('admin_pages/settings/storage/storage_settings');
                break;
            case self::L2_SLUG_CAPABILITIES:
                TplMng::getInstance()->render('admin_pages/settings/capabilities/capabilites');
                break;
        }
    }

    /**
     * Save general settings
     *
     * @return array<string, mixed>
     */
    public function saveGeneral(): array
    {
        $result        = ['saveSuccess' => false];
        $changesTraker = new SettingsChangeTracker();
        $global        = GlobalEntity::getInstance();

        // Track uninstall settings changes
        $newUninstallSettings     = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'uninstall_settings');
        $currentUninstallSettings = StaticGlobal::getUninstallSettingsOption();
        $changesTraker->addChange(
            'uninstall_settings',
            $currentUninstallSettings,
            $newUninstallSettings,
            $newUninstallSettings ? 'enabled' : 'disabled'
        );
        StaticGlobal::setUninstallSettingsOption($newUninstallSettings);

        // Track uninstall packages changes
        $newUninstallPackages     = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'uninstall_packages');
        $currentUninstallPackages = StaticGlobal::getUninstallPackageOption();
        $changesTraker->addChange(
            'uninstall_packages',
            $currentUninstallPackages,
            $newUninstallPackages,
            $newUninstallPackages ? 'enabled' : 'disabled'
        );
        StaticGlobal::setUninstallPackageOption($newUninstallPackages);

        // Track crypt option changes
        $newCryptOption      = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'crypt');
        $currentCryptOption  = StaticGlobal::getCryptOption();
        $cryptSettingChanged = ($newCryptOption != $currentCryptOption);
        $changesTraker->addChange(
            'crypt_option',
            $currentCryptOption,
            $newCryptOption,
            $newCryptOption ? 'enabled' : 'disabled'
        );

        if ($cryptSettingChanged) {
            do_action('duplicator_before_update_crypt_setting');
        }
        StaticGlobal::setCryptOption($newCryptOption);

        // Track third party JS/CSS unhook changes
        $newUnhookJs = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, '_unhook_third_party_js');
        $changesTraker->addChange(
            'unhook_third_party_js',
            $global->unhook_third_party_js,
            $newUnhookJs,
            $newUnhookJs ? 'enabled' : 'disabled'
        );
        $global->unhook_third_party_js = $newUnhookJs;

        $newUnhookCss = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, '_unhook_third_party_css');
        $changesTraker->addChange(
            'unhook_third_party_css',
            $global->unhook_third_party_css,
            $newUnhookCss,
            $newUnhookCss ? 'enabled' : 'disabled'
        );
        $global->unhook_third_party_css = $newUnhookCss;

        // Track trace log mode changes
        $newLoggingMode = SnapUtil::sanitizeStrictInput(SnapUtil::INPUT_REQUEST, '_logging_mode');
        if (!empty($newLoggingMode)) {
            // Determine current logging mode before changes
            $currentLoggingMode = 'off';
            if (StaticGlobal::getTraceLogEnabledOption()) {
                $currentLoggingMode = StaticGlobal::getSendTraceToErrorLogOption() ? 'enhanced' : 'on';
            }

            $loggingModeOptions = [
                'off'      => __('Off', 'duplicator-pro'),
                'on'       => __('On', 'duplicator-pro'),
                'enhanced' => __('Enhanced', 'duplicator-pro'),
            ];
            $changesTraker->addChange(
                'logging_mode',
                $currentLoggingMode,
                $newLoggingMode,
                'optionChanged',
                $loggingModeOptions
            );
        }

        $this->updateLoggingModeOptions();

        // Track email summary frequency changes
        $newEmailFrequency = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, '_email_summary_frequency');
        $changesTraker->addChange(
            'email_summary_frequency',
            $global->getEmailSummaryFrequency(),
            $newEmailFrequency,
            'frequencyChanged'
        );
        $global->setEmailSummaryFrequency($newEmailFrequency);
        // Track email recipients changes
        $emailRecipients = filter_input(INPUT_POST, '_email_summary_recipients', FILTER_SANITIZE_EMAIL, [
            'flags'   => FILTER_REQUIRE_ARRAY,
            'options' => [
                'default' => [],
            ],
        ]);
        if ($emailRecipients !== []) {
            $emailRecipients = array_map('sanitize_email', $emailRecipients);
        }



        $changesTraker->addChange(
            'email_summary_recipients',
            $global->getEmailSummaryRecipients(),
            $emailRecipients,
            'emailListChanged'
        );
        $global->setEmailSummaryRecipients($emailRecipients);

        // Track usage tracking changes (only if not hardcoded disabled)
        if (!DUPLICATOR_USTATS_DISALLOW) { // @phpstan-ignore-line
            $newUsageTracking = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'usage_tracking');
            $changesTraker->addChange(
                'usage_tracking',
                $global->getUsageTracking(),
                $newUsageTracking,
                $newUsageTracking ? 'enabled' : 'disabled'
            );
            $global->setUsageTracking($newUsageTracking);
        }

        // Track AM notices changes
        $newAmNotices = !SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'dup_am_notices');
        $changesTraker->addChange(
            'am_notices',
            $global->isAmNoticesEnabled(),
            $newAmNotices,
            $newAmNotices ? 'enabled' : 'disabled'
        );
        $global->setAmNotices($newAmNotices);

        // Track trace log max size changes
        $newMaxSizeMB = SnapUtil::sanitizeIntInput(INPUT_POST, 'trace_max_size', TraceLogMng::DEFAULT_MAX_TOTAL_SIZE / MB_IN_BYTES);
        $changesTraker->addChange(
            'trace_max_size',
            TraceLogMng::getInstance()->getMaxTotalSize() / MB_IN_BYTES,
            $newMaxSizeMB,
            'sizeChanged',
            [
                'fromUnit' => 'MB',
                'toUnit'   => 'MB',
            ]
        );
        TraceLogMng::getInstance()->setMaxTotalSize($newMaxSizeMB * MB_IN_BYTES);

        if (($result['saveSuccess'] = $global->save()) == false) {
            $result['errorMessage'] = __('Can\'t update general settings', 'duplicator-pro');
            return $result;
        } else {
            $result['successMessage'] = __("General settings updated.", 'duplicator-pro');
        }

        // Save activity log retention setting in DynamicGlobalEntity
        if ($result['saveSuccess']) {
            $dGlobal                     = DynamicGlobalEntity::getInstance();
            $activityLogRetentionMonths  = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'activity_log_retention_months', 0);
            $activityLogRetentionSeconds = $activityLogRetentionMonths * MONTH_IN_SECONDS;

                    // Track activity log retention changes
            $changesTraker->addChange(
                'activity_log_retention',
                $dGlobal->getValInt('activity_log_retention', 0),
                $activityLogRetentionSeconds,
                'timeChanged',
                [
                    'fromUnit' => 'sec',
                    'toUnit'   => 'month',
                ]
            );
            $dGlobal->setValInt('activity_log_retention', $activityLogRetentionSeconds);

            if (($result['saveSuccess'] = $dGlobal->save()) == false) {
                $result['errorMessage'] = __('Can\'t update activity log retention settings', 'duplicator-pro');
            } else {
                $changesTraker->createLog(LogEventSettingsChange::SUB_TYPE_GENERAL);
            }
        }

        if ($cryptSettingChanged) {
            do_action('duplicator_after_update_crypt_setting');
        }

        return $result;
    }

    /**
     * Save capabilities settings
     *
     * @return array<string, mixed>
     */
    public function saveCapabilities()
    {
        $result        = ['saveSuccess' => false];
        $changesTraker = new SettingsChangeTracker();

        $capabilities = [];
        foreach (CapMng::getCapsList() as $capName) {
            $capabilities[$capName] = [
                'roles' => [],
                'users' => [],
            ];

            $inputName = TplMng::getInputName('cap', $capName);
            $result    = filter_input(INPUT_POST, $inputName, FILTER_UNSAFE_RAW, [
                'flags'   => FILTER_REQUIRE_ARRAY,
                'options' => [
                    'default' => [],
                ],
            ]);
            if ($result === []) {
                continue;
            }

            foreach ($result as $roles) {
                $roles = SnapUtil::sanitizeNSCharsNewlineTrim($roles);
                if (is_numeric($roles)) {
                    $capabilities[$capName]['users'][] = (int) $roles;
                } else {
                    $capabilities[$capName]['roles'][] = $roles;
                }
            }
        }

        // Track detailed capability changes (after we have the new capabilities)
        $currentCapabilities = CapMng::getInstance()->getAllCapabilities();

        // Create individual change entries for each capability that changed
        foreach (CapMng::getCapsList() as $capName) {
            $oldCap = $currentCapabilities[$capName] ?? [
                'roles' => [],
                'users' => [],
            ];
            $newCap = $capabilities[$capName] ?? [
                'roles' => [],
                'users' => [],
            ];

            // Check if this specific capability actually changed
            if ($oldCap !== $newCap) {
                $changesTraker->addChange(
                    $capName, // Use the actual capability name as the key
                    $oldCap,
                    $newCap,
                    'singleCapabilityChanged'
                );
            }
        }

        if (CapMng::getInstance()->update($capabilities) == false) {
            $result['saveSuccess']  = false;
            $result['errorMessage'] = __('Can\'t update capabilities.', 'duplicator-pro');
            return $result;
        } else {
            $result['successMessage'] = __('Capabilities updated.', 'duplicator-pro');
            $result['saveSuccess']    = true;

            $changesTraker->createLog(LogEventSettingsChange::SUB_TYPE_CAPABILITIES);
        }

        return $result;
    }

    /**
     * Reset capabilities settings
     *
     * @return array<string, mixed>
     */
    public function resetCapabilities(): array
    {
        $result        = ['saveSuccess' => false];
        $changesTraker = new SettingsChangeTracker();

        $capabilities = CapMng::getDefaultCaps();
        if (!CapMng::can(CapMng::CAP_LICENSE)) {
            // Can't reset license capability if current user can't manage license
            unset($capabilities[CapMng::CAP_LICENSE]);
        }

        // Track detailed capability reset changes
        $currentCapabilities = CapMng::getInstance()->getAllCapabilities();

        // Create individual change entries for each capability that changed
        foreach (CapMng::getCapsList() as $capName) {
            $oldCap = $currentCapabilities[$capName] ?? [
                'roles' => [],
                'users' => [],
            ];
            $newCap = $capabilities[$capName] ?? [
                'roles' => [],
                'users' => [],
            ];

            $changesTraker->addChange(
                $capName, // Use the actual capability name as the key
                $oldCap,
                $newCap,
                'singleCapabilityChanged'
            );
        }

        if (CapMng::getInstance()->update($capabilities) == false) {
            $result['saveSuccess']  = false;
            $result['errorMessage'] = __('Can\'t update capabilities.', 'duplicator-pro');
        } else {
            $result['successMessage'] = __('Capabilities updated.', 'duplicator-pro');
            $result['saveSuccess']    = true;

            $changesTraker->createLog(LogEventSettingsChange::SUB_TYPE_CAPABILITIES, 'settings_reset');
        }

        return $result;
    }

    /**
     * Save storage general settings
     *
     * @return array<string, mixed>
     */
    public function saveStorageGeneral(): array
    {
        $result        = ['saveSuccess' => false];
        $changesTraker = new SettingsChangeTracker();
        $global        = GlobalEntity::getInstance();

        // Track storage htaccess setting changes
        $newHtaccessOff = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, '_storage_htaccess_off');
        $changesTraker->addChange(
            'storage_htaccess_off',
            $global->storage_htaccess_off,
            $newHtaccessOff,
            $newHtaccessOff ? 'enabled' : 'disabled'
        );
        $global->storage_htaccess_off = $newHtaccessOff;

        // Track max storage retries changes
        $newMaxRetries = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'max_storage_retries', 10);
        $changesTraker->addChange(
            'max_storage_retries',
            $global->max_storage_retries,
            $newMaxRetries,
            'optionChanged'
        );
        $global->max_storage_retries = $newMaxRetries;

        // Track SSL server certificates setting
        $newSslServerCerts = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'ssl_useservercerts');
        $changesTraker->addChange(
            'ssl_useservercerts',
            $global->ssl_useservercerts,
            $newSslServerCerts,
            $newSslServerCerts ? 'enabled' : 'disabled'
        );
        $global->ssl_useservercerts = $newSslServerCerts;

        // Track SSL verify disable setting
        $newSslDisableVerify = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'ssl_disableverify');
        $changesTraker->addChange(
            'ssl_disableverify',
            $global->ssl_disableverify,
            $newSslDisableVerify,
            $newSslDisableVerify ? 'enabled' : 'disabled'
        );
        $global->ssl_disableverify = $newSslDisableVerify;

        // Track IPv4 only setting
        $newIpv4Only = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'ipv4_only');
        $changesTraker->addChange(
            'ipv4_only',
            $global->ipv4_only,
            $newIpv4Only,
            $newIpv4Only ? 'enabled' : 'disabled'
        );
        $global->ipv4_only = $newIpv4Only;

        // Track purge backup records setting
        $newPurgeRecords           = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'purge_backup_records', 0);
        $purgeBackupRecordsOptions = [
            0 => __('When the backup archive is removed from all storages', 'duplicator-pro'),
            1 => __('When maximum is reached for Default Local Storage', 'duplicator-pro'),
            2 => __('Never', 'duplicator-pro'),
        ];
        $changesTraker->addChange(
            'purge_backup_records',
            $global->getPurgeBackupRecords(),
            $newPurgeRecords,
            'optionChanged',
            $purgeBackupRecordsOptions
        );
        $global->setPurgeBackupRecords($newPurgeRecords);

        if (($result['saveSuccess'] = $global->save()) == false) {
            $result['errorMessage'] = __('Can\'t update storage settings.', 'duplicator-pro');
        } else {
            $result['successMessage'] = __('Storage settings updated.', 'duplicator-pro');
        }

        if ($result['saveSuccess']) {
            do_action('duplicator_update_global_storage_settings');

            $dGlobal = DynamicGlobalEntity::getInstance();
            if (($result['saveSuccess'] = $dGlobal->save()) == false) {
                $result['errorMessage'] = __('Can\'t update storage settings.', 'duplicator-pro');
            } else {
                $result['successMessage'] = __('Storage settings updated.', 'duplicator-pro');
                $changesTraker->createLog(LogEventSettingsChange::SUB_TYPE_STORAGE);
            }
        }

        return $result;
    }

    /**
     * Save schedule general settings
     *
     * @return array<string, mixed>
     */
    public function saveSchedule()
    {
        $result        = ['saveSuccess' => false];
        $changesTraker = new SettingsChangeTracker();
        $global        = GlobalEntity::getInstance();

        // Track email build mode changes
        $newEmailBuildMode     = (int)$_REQUEST['send_email_on_build_mode'];
        $emailBuildModeOptions = [
            0 => __('Never', 'duplicator-pro'),
            1 => __('On Failure', 'duplicator-pro'),
            2 => __('Always', 'duplicator-pro'),
        ];
        $changesTraker->addChange(
            'send_email_on_build_mode',
            $global->send_email_on_build_mode,
            $newEmailBuildMode,
            'optionChanged',
            $emailBuildModeOptions
        );
        $global->send_email_on_build_mode = $newEmailBuildMode;

        // Track notification email address changes
        $newEmailAddress = stripslashes($_REQUEST['notification_email_address']);
        $changesTraker->addChange(
            'notification_email_address',
            $global->notification_email_address,
            $newEmailAddress,
            'fieldChanged'
        );
        $global->notification_email_address = $newEmailAddress;

        if ($global->save()) {
            $result['saveSuccess']    = true;
            $result['successMessage'] = __('Schedule settings updated.', 'duplicator-pro');

            $changesTraker->createLog(LogEventSettingsChange::SUB_TYPE_SCHEDULE);
        } else {
            $result['errorMessage'] = __('Can\'t update schedule settings.', 'duplicator-pro');
        }

        return $result;
    }

    /**
     * Migrate settings
     *
     * @return array<string, mixed>
     */
    public function importSettings(): array
    {
        $inputData = filter_input_array(INPUT_POST, [
            'import-opts' => [
                'filter'  => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags'   => FILTER_REQUIRE_ARRAY,
                'options' => [
                    'default' => [],
                ],
            ],
        ]);

        if (empty($inputData['import-opts'])) {
            return ['errorMessage' => __('No import options selected.', 'duplicator-pro')];
        }

        if (!isset($_FILES['import-file']['tmp_name'])) {
            return ['errorMessage' => __('No file uploaded.', 'duplicator-pro')];
        }

        $filePath = SnapUtil::sanitizeNSCharsNewlineTabs($_FILES["import-file"]["tmp_name"]);
        try {
            if (MigrateSettings::import($filePath, $inputData['import-opts']) == false) {
                return ['errorMessage' => __('Couldn\'t import settings.', 'duplicator-pro')];
            }
        } catch (Exception $ex) {
            return ['errorMessage' => sprintf(__('Couldn\'t import settings. Error: %s', 'duplicator-pro'), $ex->getMessage())];
        }

        // Log the settings import action
        LogEventSettingsChange::create(
            LogEventSettingsChange::SUB_TYPE_IMPORT_EXPORT,
            [
                'changes'     => [], // Import doesn't track individual changes
                'action_type' => 'settings_import',
            ]
        );

        return ['successMessage' => __('Settings imported.', 'duplicator-pro')];
    }

    /**
     * Brand save
     *
     * @return array<string, mixed>
     */
    public function brandSave(): array
    {
        $id    = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'id', -1);
        $brand = $id === -1 ? new BrandEntity() : BrandEntity::getById($id);

        $result = [];
        $brand->setFromInput(SnapUtil::INPUT_REQUEST);
        if ($brand->save()) {
            $result['successMessage'] = __('Brand saved.', 'duplicator-pro');
            $result['actionBrandId']  = $brand->getId();

            // Log brand save action
            LogEventBrandCreateUpdate::create(
                $id === -1 ? LogEventBrandCreateUpdate::ACTION_CREATE : LogEventBrandCreateUpdate::ACTION_UPDATE,
                $brand
            );
        } else {
            $result['errorMessage'] = __('Couldn\'t save brand.', 'duplicator-pro');
        }

        return $result;
    }

    /**
     * Brand delete
     *
     * @return array<string, mixed>
     */
    public function brandDelete(): array
    {
        if (!isset($_REQUEST['selected_id'])) {
            return ['errorMessage' => __("No Brand selected.", 'duplicator-pro')];
        }

        $brandIds = filter_var_array($_REQUEST, [
            'selected_id' => [
                'filter'  => FILTER_VALIDATE_INT,
                'flags'   => FILTER_REQUIRE_ARRAY,
                'options' => ['default' => false],
            ],
        ])['selected_id'];

        if ($brandIds === false) {
            return ['errorMessage' => __("No Brand selected.", 'duplicator-pro')];
        }

        foreach ($brandIds as $id) {
            BrandEntity::deleteById($id);
        }

        // Log brand delete action
        LogEventBrandDelete::create($brandIds);

        return ['successMessage' => __("Brand deleted.", 'duplicator-pro')];
    }

    /**
     * Reset all user settings and redirects to the settings page
     *
     * @return array<string, mixed>
     */
    public function resetSettings(): array
    {
        $result = ['saveSuccess' => false];

        $global = GlobalEntity::getInstance();

        // Capture before values for logging (before reset)
        $beforeValues = ['all_standard_settings' => 'existing_values'];

        if ($global->resetUserSettings() && $global->save()) {
            $result['successMessage'] = __('Settings reset to defaults successfully', 'duplicator-pro');
            $result['saveSuccess']    = true;

            // Log the settings reset action
            LogEventSettingsChange::create(
                LogEventSettingsChange::SUB_TYPE_GENERAL,
                [
                    'changes'     => [], // Reset doesn't track individual changes
                    'action_type' => 'settings_reset',
                ]
            );
        } else {
            $result['errorMessage'] = __('Failed to reset settings.', 'duplicator-pro');
            $result['saveSuccess']  = false;
        }

        TraceLogMng::getInstance()->setMaxTotalSize(TraceLogMng::DEFAULT_MAX_TOTAL_SIZE);
        return $result;
    }

    /**
     * Update trace mode
     *
     * @return array<string, mixed>
     */
    public function traceGeneral(): array
    {
        $result = ['saveSuccess' => false];

        switch (SnapUtil::sanitizeStrictInput(SnapUtil::INPUT_REQUEST, '_logging_mode')) {
            case 'off':
                $this->updateLoggingModeOptions();
                $result = [
                    'saveSuccess'    => true,
                    'successMessage' => __("Trace settings have been turned off.", 'duplicator-pro'),
                ];
                break;
            case 'on':
                $this->updateLoggingModeOptions();
                $result = [
                    'saveSuccess'    => true,
                    'successMessage' => __("Trace settings have been turned on.", 'duplicator-pro'),
                ];
                break;
            default:
                $result = [
                    'saveSuccess'  => false,
                    'errorMessage' => __("Trace mode not valid.", 'duplicator-pro'),
                ];
                break;
        }

        return $result;
    }

    /**
     * Upate loggin modes options
     *
     * @return void
     */
    protected function updateLoggingModeOptions()
    {
        switch (SnapUtil::sanitizeStrictInput(SnapUtil::INPUT_REQUEST, '_logging_mode')) {
            case 'off':
                StaticGlobal::setTraceLogEnabledOption(false);
                StaticGlobal::setSendTraceToErrorLogOption(false);
                break;
            case 'on':
                if (StaticGlobal::getTraceLogEnabledOption() == false) {
                    DupLog::deleteTraceLog();
                }
                StaticGlobal::setTraceLogEnabledOption(true);
                StaticGlobal::setSendTraceToErrorLogOption(false);
                break;
            case 'enhanced':
                if (
                    StaticGlobal::getTraceLogEnabledOption() == false ||
                    StaticGlobal::getSendTraceToErrorLogOption() == false
                ) {
                    DupLog::deleteTraceLog();
                }

                StaticGlobal::setTraceLogEnabledOption(true);
                StaticGlobal::setSendTraceToErrorLogOption(true);
                break;
            default:
                break;
        }
    }



    /**
     * Save Backup basic settings
     *
     * @return array<string, mixed>
     */
    public function savePackage(): array
    {
        $result          = ['saveSuccess' => false];
        $changesTraker   = new SettingsChangeTracker();
        $global          = GlobalEntity::getInstance();
        $dGlobal         = DynamicGlobalEntity::getInstance();
        $packageBuild    = Constants::DEFAULT_MAX_PACKAGE_RUNTIME_IN_MIN;
        $defaultTransfer = Constants::DEFAULT_MAX_PACKAGE_TRANSFER_TIME_IN_MIN;

        // Track database mode settings before they change
        $newDbMode     = SnapUtil::sanitizeDefaultInput(INPUT_POST, '_package_dbmode');
        $currentDbMode = $global->package_mysqldump ? 'mysql' : 'php';
        $dbModeOptions = [
            'mysql' => __('MySQL', 'duplicator-pro'),
            'php'   => __('PHP', 'duplicator-pro'),
        ];
        $changesTraker->addChange(
            'package_dbmode',
            $currentDbMode,
            $newDbMode,
            'optionChanged',
            $dbModeOptions
        );

        $newPhpDumpMode     = filter_input(INPUT_POST, '_phpdump_mode', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
        $phpDumpModeOptions = [
            0 => __('Multi-Threaded', 'duplicator-pro'),
            1 => __('Single-Threaded', 'duplicator-pro'),
        ];
        $changesTraker->addChange(
            'package_phpdump_mode',
            $global->package_phpdump_mode,
            $newPhpDumpMode,
            'optionChanged',
            $phpDumpModeOptions
        );

        $newMysqldumpPath = SnapUtil::sanitizeDefaultInput(INPUT_POST, '_package_mysqldump_path');
        $changesTraker->addChange(
            'package_mysqldump_path',
            $global->package_mysqldump_path,
            $newMysqldumpPath,
            'fieldChanged',
            [
                'truncate' => true,
                'max'      => 80,
            ]
        );

        $newMysqldumpQryLimit     = filter_input(
            INPUT_POST,
            '_package_mysqldump_qrylimit',
            FILTER_VALIDATE_INT,
            ['options' => ['default' => Constants::DEFAULT_MYSQL_DUMP_CHUNK_SIZE]]
        );
        $mysqldumpQryLimitOptions = Constants::MYSQL_DUMP_CHUNK_SIZES;
        $changesTraker->addChange(
            'package_mysqldump_qrylimit',
            $global->package_mysqldump_qrylimit,
            $newMysqldumpQryLimit,
            'optionChanged',
            $mysqldumpQryLimitOptions
        );

        // Track archive mode settings before they change
        $newArchiveBuildMode     = filter_input(INPUT_POST, 'archive_build_mode', FILTER_VALIDATE_INT);
        $archiveBuildModeOptions = [
            1  => __('Shell Exec (fastest)', 'duplicator-pro'),
            2  => __('ZipArchive (reliable)', 'duplicator-pro'),
            3  => __('DupArchive (compatible)', 'duplicator-pro'),
            -1 => __('Unconfigured', 'duplicator-pro'),
        ];
        $changesTraker->addChange(
            'archive_build_mode',
            $global->archive_build_mode,
            $newArchiveBuildMode,
            'optionChanged',
            $archiveBuildModeOptions
        );

        $newZipArchiveMode     = filter_input(INPUT_POST, 'ziparchive_mode', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
        $zipArchiveModeOptions = [
            0 => __('Multi-Threaded', 'duplicator-pro'),
            1 => __('Single-Threaded', 'duplicator-pro'),
        ];
        $changesTraker->addChange(
            'ziparchive_mode',
            $global->ziparchive_mode,
            $newZipArchiveMode,
            'optionChanged',
            $zipArchiveModeOptions
        );

        $newArchiveCompression = filter_input(INPUT_POST, 'archive_compression', FILTER_VALIDATE_BOOLEAN);
        $changesTraker->addChange(
            'archive_compression',
            $global->archive_compression,
            $newArchiveCompression,
            $newArchiveCompression ? 'enabled' : 'disabled'
        );

        $newZipValidation = filter_input(INPUT_POST, 'ziparchive_validation', FILTER_VALIDATE_BOOLEAN);
        $changesTraker->addChange(
            'ziparchive_validation',
            $global->ziparchive_validation,
            $newZipValidation,
            $newZipValidation ? 'enabled' : 'disabled'
        );

        $newZipChunkSize = filter_input(
            INPUT_POST,
            'ziparchive_chunk_size_in_mb',
            FILTER_VALIDATE_INT,
            ['options' => ['default' => Constants::DEFAULT_ZIP_ARCHIVE_CHUNK]]
        );
        $changesTraker->addChange(
            'ziparchive_chunk_size_in_mb',
            $global->ziparchive_chunk_size_in_mb,
            $newZipChunkSize,
            'sizeChanged',
            [
                'fromUnit' => 'MB',
                'toUnit'   => 'MB',
            ]
        );

        $global->setDbMode();
        $global->setArchiveMode();

        // Track max package runtime changes
        $newPackageRuntime = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'max_package_runtime_in_min', $packageBuild);
        $changesTraker->addChange(
            'max_package_runtime_in_min',
            $global->max_package_runtime_in_min,
            $newPackageRuntime,
            'timeChanged',
            [
                'fromUnit' => 'min',
                'toUnit'   => 'min',
            ]
        );
        $global->max_package_runtime_in_min = $newPackageRuntime;

        // Track server load reduction changes
        $newServerLoad     = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'server_load_reduction', 0);
        $serverLoadOptions = [
            0 => __('Off', 'duplicator-pro'),
            1 => __('Low', 'duplicator-pro'),
            2 => __('Medium', 'duplicator-pro'),
            3 => __('High', 'duplicator-pro'),
        ];
        $changesTraker->addChange(
            'server_load_reduction',
            $global->server_load_reduction,
            $newServerLoad,
            'optionChanged',
            $serverLoadOptions
        );
        $global->server_load_reduction = $newServerLoad;

        // Track max transfer time changes
        $newTransferTime = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'max_package_transfer_time_in_min', $defaultTransfer);
        $changesTraker->addChange(
            'max_package_transfer_time_in_min',
            $global->max_package_transfer_time_in_min,
            $newTransferTime,
            'timeChanged',
            [
                'fromUnit' => 'min',
                'toUnit'   => 'min',
            ]
        );
        $global->max_package_transfer_time_in_min = $newTransferTime;

        // Handle installer name mode
        $newInstallerMode = SnapUtil::sanitizeDefaultInput(INPUT_POST, 'installer_name_mode');
        switch ($newInstallerMode) {
            case GlobalEntity::INSTALLER_NAME_MODE_WITH_HASH:
                $installerNameMode = GlobalEntity::INSTALLER_NAME_MODE_WITH_HASH;
                break;
            case GlobalEntity::INSTALLER_NAME_MODE_SIMPLE:
            default:
                $installerNameMode = GlobalEntity::INSTALLER_NAME_MODE_SIMPLE;
                break;
        }
        $installerNameModeOptions = [
            'simple'    => __('Standard (installer.php)', 'duplicator-pro'),
            'with_hash' => __('Hashed (more secure)', 'duplicator-pro'),
        ];
        $changesTraker->addChange(
            'installer_name_mode',
            $global->installer_name_mode,
            $installerNameMode,
            'optionChanged',
            $installerNameModeOptions
        );
        $global->installer_name_mode = $installerNameMode;

        // Track other key settings
        $newLockMode     = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'lock_mode', 0);
        $lockModeOptions = [
            0 => __('File locking', 'duplicator-pro'),
            1 => __('SQL locking', 'duplicator-pro'),
        ];
        $changesTraker->addChange(
            'lock_mode',
            $global->lock_mode,
            $newLockMode,
            'optionChanged',
            $lockModeOptions
        );
        $global->lock_mode = $newLockMode;

        $newAjaxProtocol     = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'ajax_protocol', 'admin');
        $ajaxProtocolOptions = [
            'admin'  => __('Admin AJAX', 'duplicator-pro'),
            'public' => __('Public AJAX', 'duplicator-pro'),
            'https'  => __('Secure HTTPS', 'duplicator-pro'),
        ];
        $changesTraker->addChange(
            'ajax_protocol',
            $global->ajax_protocol,
            $newAjaxProtocol,
            'optionChanged',
            $ajaxProtocolOptions
        );
        $global->ajax_protocol = $newAjaxProtocol;

        $newCustomAjaxUrl = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'custom_ajax_url', $global->custom_ajax_url);
        $changesTraker->addChange(
            'custom_ajax_url',
            $global->custom_ajax_url,
            $newCustomAjaxUrl,
            'fieldChanged',
            [
                'truncate' => true,
                'max'      => 80,
            ]
        );
        $global->custom_ajax_url = $newCustomAjaxUrl;

        $newClientSideKickoff = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, '_clientside_kickoff', false);
        $changesTraker->addChange(
            'clientside_kickoff',
            $global->clientside_kickoff,
            $newClientSideKickoff,
            $newClientSideKickoff ? 'enabled' : 'disabled'
        );
        $global->setClientsideKickoff($newClientSideKickoff);

        $newHomepathAsAbspath = SnapUtil::sanitizeBoolInput(INPUT_POST, 'homepath_as_abspath', false);
        $changesTraker->addChange(
            'homepath_as_abspath',
            $global->homepath_as_abspath,
            $newHomepathAsAbspath,
            $newHomepathAsAbspath ? 'enabled' : 'disabled'
        );
        $global->homepath_as_abspath = $newHomepathAsAbspath;

        $newSkipArchiveScan = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, '_skip_archive_scan', false);
        $changesTraker->addChange(
            'skip_archive_scan',
            $global->skip_archive_scan,
            $newSkipArchiveScan,
            $newSkipArchiveScan ? 'enabled' : 'disabled'
        );
        $global->skip_archive_scan = $newSkipArchiveScan;

        $newWorkerTime = SnapUtil::sanitizeIntInput(
            SnapUtil::INPUT_REQUEST,
            'php_max_worker_time_in_sec',
            Constants::DEFAULT_MAX_WORKER_TIME
        );
        $changesTraker->addChange(
            'php_max_worker_time_in_sec',
            $global->php_max_worker_time_in_sec,
            $newWorkerTime,
            'timeChanged',
            [
                'fromUnit' => 'sec',
                'toUnit'   => 'sec',
            ]
        );
        $global->php_max_worker_time_in_sec = $newWorkerTime;

        // Track basic auth settings in DynamicGlobalEntity
        $basicAuthEnabled = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, '_basic_auth_enabled');
        $changesTraker->addChange(
            'basic_auth_enabled',
            $dGlobal->getValBool('basic_auth_enabled', false),
            $basicAuthEnabled,
            $basicAuthEnabled ? 'enabled' : 'disabled'
        );

        if ($basicAuthEnabled == true) {
            $basicAuthUser = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'basic_auth_user', '');
        } else {
            $basicAuthUser = '';
        }

        $basicAuthPassword = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'basic_auth_password', '');
        $basicAuthPassword = stripslashes(SnapUtil::sanitizeNSCharsNewlineTrim($basicAuthPassword));

        // Track basic auth user changes
        $changesTraker->addChange(
            'basic_auth_user',
            $dGlobal->getValString('basic_auth_user', ''),
            $basicAuthUser,
            'optionChanged'
        );

        // Track basic auth password changes (show only if changed, not the actual password)
        $currentPassword = $dGlobal->getValString('basic_auth_password', '');
        if ($currentPassword !== $basicAuthPassword) {
            $changes[] = [
                'key'    => 'basic_auth_password',
                'format' => 'passwordChanged',
                'data'   => [],// No password data for security
            ];
        }

        // CLEANUP - Track cleanup settings before they change
        $newCleanupMode     = filter_input(
            INPUT_POST,
            'cleanup_mode',
            FILTER_VALIDATE_INT,
            ['options' => ['default' => GlobalEntity::CLEANUP_MODE_OFF]]
        );
        $cleanupModeOptions = [
            GlobalEntity::CLEANUP_MODE_OFF  => __('Off', 'duplicator-pro'),
            GlobalEntity::CLEANUP_MODE_MAIL => __('Email notification', 'duplicator-pro'),
            GlobalEntity::CLEANUP_MODE_AUTO => __('Automatic cleanup', 'duplicator-pro'),
        ];
        $changesTraker->addChange(
            'cleanup_mode',
            $global->cleanup_mode,
            $newCleanupMode,
            'optionChanged',
            $cleanupModeOptions
        );

        $newCleanupEmail = filter_input(INPUT_POST, 'cleanup_email', FILTER_VALIDATE_EMAIL, ['options' => ['default' => '']]);
        $newCleanupEmail = $newCleanupEmail === '' ? get_option('admin_email') : $newCleanupEmail;
        $changesTraker->addChange(
            'cleanup_email',
            $global->cleanup_email,
            $newCleanupEmail,
            'fieldChanged'
        );

        $newAutoCleanupHours = filter_input(
            INPUT_POST,
            'auto_cleanup_hours',
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'default'   => 24,
                    'min_range' => 1,
                ],
            ]
        );
        $changesTraker->addChange(
            'auto_cleanup_hours',
            $global->auto_cleanup_hours,
            $newAutoCleanupHours,
            'timeChanged',
            [
                'fromUnit' => 'hour',
                'toUnit'   => 'hour',
            ]
        );

        // Log accumulated changes BEFORE attempting to save
        $changesTraker->createLog(LogEventSettingsChange::SUB_TYPE_BACKUP);

        $global->setCleanupFields();

        if (($result['saveSuccess'] = $global->save()) == false) {
            $result['errorMessage'] = __('Can\'t Save Backup Settings', 'duplicator-pro');
            return $result;
        } else {
            $result['successMessage'] = __("Backup Settings Saved.", 'duplicator-pro');
        }

        $dGlobal->setValBool('basic_auth_enabled', $basicAuthEnabled);
        $dGlobal->setValString('basic_auth_user', $basicAuthUser);
        $dGlobal->setValString('basic_auth_password', $basicAuthPassword);

        $dGlobal->save();

        return $result;
    }

    /**
     * Save import settings
     *
     * @return array<string, mixed>
     */
    public function saveImportSettngs(): array
    {
        $result        = ['saveSuccess' => false];
        $changesTraker = new SettingsChangeTracker();
        $global        = GlobalEntity::getInstance();

        // Track import chunk size changes
        $newChunkSize           = filter_input(
            INPUT_POST,
            'import_chunk_size',
            FILTER_VALIDATE_INT,
            [
                'options' => ['default' => DUPLICATOR_DEFAULT_CHUNK_UPLOAD_SIZE],
            ]
        );
        $importChunkSizeOptions = [
            128   => __('100k [Slowest]', 'duplicator-pro'),
            256   => __('200k', 'duplicator-pro'),
            512   => __('500k', 'duplicator-pro'),
            1024  => __('1M', 'duplicator-pro'),
            2048  => __('2M', 'duplicator-pro'),
            5120  => __('5M', 'duplicator-pro'),
            10240 => __('10M [Very Fast]', 'duplicator-pro'),
            0     => __('Disabled [Fastest, BUT php.ini limits archive size]', 'duplicator-pro'),
        ];
        $changesTraker->addChange(
            'import_chunk_size',
            $global->import_chunk_size,
            $newChunkSize,
            'optionChanged',
            $importChunkSizeOptions
        );
        $global->import_chunk_size = $newChunkSize;

        // Track import custom path changes
        $newCustomPath = filter_input(
            INPUT_POST,
            'import_custom_path',
            FILTER_CALLBACK,
            [
                'options' => [
                    SnapUtil::class,
                    'sanitizeNSCharsNewlineTrim',
                ],
            ]
        );
        $changesTraker->addChange(
            'import_custom_path',
            $global->import_custom_path,
            $newCustomPath,
            'fieldChanged',
            [
                'truncate' => true,
                'max'      => 80,
            ]
        );
        $global->import_custom_path = $newCustomPath;

        $newRecoveryCustomPath = filter_input(
            INPUT_POST,
            'recovery_custom_path',
            FILTER_CALLBACK,
            [
                'options' => [
                    SnapUtil::class,
                    'sanitizeNSCharsNewlineTrim',
                ],
            ]
        );

        if (
            strlen($global->import_custom_path) > 0 &&
            (
                !is_dir($global->import_custom_path) ||
                !is_readable($global->import_custom_path)
            )
        ) {
            $result['errorMessage']     = __(
                'The custom path isn\'t a valid directory. Check that it exists or that access to it is not restricted by PHP\'s open_basedir setting.',
                'duplicator-pro'
            );
            $global->import_custom_path = '';
            $result['saveSuccess']      = false;
            return $result;
        }

        // Track recovery custom path changes
        $changesTraker->addChange(
            'recovery_custom_path',
            $global->getRecoveryCustomPath(),
            $newRecoveryCustomPath,
            'fieldChanged',
            [
                'truncate' => true,
                'max'      => 80,
            ]
        );

        $failMessage = '';
        if ($global->setRecoveryCustomPath($newRecoveryCustomPath, $failMessage) == false) {
            $result['saveSuccess']  = false;
            $result['errorMessage'] = $failMessage;
            return $result;
        }

        if (($result['saveSuccess'] = $global->save()) == false) {
            $result['errorMessage'] = __('Can\'t save settings data', 'duplicator-pro');
        } else {
            $result['successMessage'] = __('Settings updated.', 'duplicator-pro');
            $changesTraker->createLog(LogEventSettingsChange::SUB_TYPE_IMPORT);
        }

        return $result;
    }

    /**
     * Mysql dump message
     *
     * @param bool   $mysqlDumpFound Found
     * @param string $mysqlDumpPath  mysqldump path
     *
     * @return void
     */
    public static function getMySQLDumpMessage($mysqlDumpFound = false, $mysqlDumpPath = ''): void
    {
        ?>
        <?php if ($mysqlDumpFound) :
            ?>
            <span class="dup-feature-found success-color">
                <?php echo esc_html($mysqlDumpPath) ?> &nbsp;
                <small>
                    <i class="fa fa-check-circle"></i>&nbsp;<i><?php esc_html_e("Successfully Found", 'duplicator-pro'); ?></i>
                </small>
            </span>
            <?php
        else :
            ?>
            <span class="dup-feature-notfound alert-color">
                <i class="fa fa-exclamation-triangle fa-sm" aria-hidden="true"></i>
                <?php
                self::getMySqlDumpPathProblems($mysqlDumpPath, !empty($mysqlDumpPath));
                ?>
            </span>
            <?php
        endif;
    }

    /**
     * Return purge orphan Backups action URL
     *
     * @param bool $on true turn on, false turn off
     *
     * @return string
     */
    public function getTraceActionUrl($on)
    {
        $action = $this->getActionByKey(self::ACTION_GENERAL_TRACE);
        return $this->getMenuLink(
            self::L2_SLUG_GENERAL,
            null,
            [
                'action'        => $action->getKey(),
                '_wpnonce'      => $action->getNonce(),
                '_logging_mode' => ($on ? 'on' : 'off'),
            ]
        );
    }

    /**
     * Display mysql dump path problems
     *
     * @param string $path      mysqldump path
     * @param bool   $is_custom is custom path
     *
     * @return void
     */
    public static function getMySqlDumpPathProblems($path = '', $is_custom = false): void
    {
        $available = WpDbUtils::getMySqlDumpPath();
        $default   = false;
        if ($available) {
            if ($is_custom) {
                if (!Shell::isExecutable($path)) {
                    printf(
                        esc_html_x(
                            'The mysqldump program at custom path exists but is not executable. Please check file permission
                            to resolve this problem. Please check this %1$sFAQ page%2$s for possible solution.',
                            '%1$s and %2$s are html anchor tags or link',
                            'duplicator-pro'
                        ),
                        '<a href="' . esc_url(DUPLICATOR_DUPLICATOR_DOCS_URL . 'how-to-resolve-dependency-checks') . '" target="_blank">',
                        '</a>'
                    );
                } else {
                    $default = true;
                }
            } else {
                if (!Shell::isExecutable($available)) {
                    printf(
                        esc_html_x(
                            'The mysqldump program at its default location exists but is not executable.
                            Please check file permission to resolve this problem. Please check this %1$sFAQ page%2$s for possible solution.',
                            '%1$s and %2$s are html anchor tags or link',
                            'duplicator-pro'
                        ),
                        '<a href="' . esc_url(DUPLICATOR_DUPLICATOR_DOCS_URL . 'how-to-resolve-dependency-checks') . '" target="_blank">',
                        '</a>'
                    );
                } else {
                    $default = true;
                }
            }
        } else {
            if ($is_custom) {
                printf(
                    esc_html_x(
                        'The mysqldump program was not found at its custom path location.
                        Please check is there some typo mistake or mysqldump program exists on that location.
                        Also you can leave custom path empty to force automatic settings. If the problem persist
                        contact your server admin for the correct path. For a list of approved providers that support mysqldump %1$sclick here%2$s.',
                        '%1$s and %2$s are html anchor tags or links',
                        'duplicator-pro'
                    ),
                    '<a href="' . esc_url(DUPLICATOR_DUPLICATOR_DOCS_URL . 'what-host-providers-are-recommended-for-duplicator/') . '" target="_blank">',
                    '</a>'
                );
            } else {
                esc_html_e(
                    'The mysqldump program was not found at its default location.
                    To use mysqldump, ask your host to install it or for a custom mysqldump path.',
                    'duplicator-pro'
                );
            }
        }

        if ($default) {
            printf(
                esc_html_x(
                    'The mysqldump program was not found at its default location or the custom path below.
                    Please enter a valid path where mysqldump can run. If the problem persist contact your
                    server admin for the correct path. For a list of approved providers that support mysqldump %1$sclick here%2$s.',
                    '%1$s and %2$s are html anchor tags or links',
                    'duplicator-pro'
                ),
                '<a href="' . esc_url(DUPLICATOR_DUPLICATOR_DOCS_URL . 'what-host-providers-are-recommended-for-duplicator/') . '" target="_blank">',
                '</a>'
            );
        }
    }
}
