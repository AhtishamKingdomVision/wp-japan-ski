<?php

/**
 * Version Pro Base addon class
 *
 * Name: Duplicator PRO base
 * Version: 1
 * Author: Duplicator
 * Author URI: http://snapcreek.com
 *
 * PHP version 7.4
 *
 * @category  Duplicator
 * @package   Plugin
 * @author    Duplicator
 * @copyright 2011-2021  Snapcreek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @version   GIT: $Id$
 * @link      https://duplicator.com/
 */

namespace Duplicator\Addons\ProBase;

// phpcs:disable
require_once __DIR__ . '/vendor/edd/EDD_SL_Plugin_Updater.php';
// phpcs:enable

use Duplicator\Controllers\SchedulePageController;
use Duplicator\Addons\ProBase\License\License;
use Duplicator\Addons\ProBase\License\LicenseNotices;
use Duplicator\Addons\ProBase\Models\LicenseData;
use Duplicator\Core\Controllers\AbstractMenuPageController;
use Duplicator\Core\MigrationMng;
use Duplicator\Installer\Models\MigrateData;

/**
 * Version Pro Base addon class
 *
 * @category Duplicator
 * @package  Plugin
 * @author   Snapcreek <admin@snapcreek.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     http://snapcreek.com
 */
class ProBase extends \Duplicator\Core\Addons\AbstractAddonCore
{
    /**
     * @return void
     */
    public function init(): void
    {
        add_action('init', [$this, 'hookInit']);

        add_filter('duplicator_main_menu_label', fn(): string => 'Duplicator Pro');

        add_filter('duplicator_menu_pages', [$this, 'addScheduleMenuField']);

        add_action(MigrationMng::HOOK_FIRST_LOGIN_AFTER_INSTALL, function (MigrateData $migrationData): void {
            License::clearVersionCache(true);
            $licenseData = LicenseData::getInstance();
            if ($licenseData->getStatus() !== LicenseData::STATUS_VALID) {
                $licenseData->activate();
            }
        });

        add_action('duplicator_after_activation', [$this, 'onUpgradePlugin'], 10, 2);

        add_action('duplicator_before_update_crypt_setting', [self::class, 'beforeCryptUpdateSettings']);
        add_action('duplicator_after_update_crypt_setting', [self::class, 'afterCryptUpdateSettings']);

        add_filter('duplicator_dynamic_data_skip_reset', function (array $skipResetData): array {
            $skipResetData[] = 'license_key_visible';
            $skipResetData[] = 'license_key_visible_pwd';
            return $skipResetData;
        });

        add_filter('duplicator_dynamic_skip_data_export', function (array $skipExportData): array {
            $skipExportData[] = 'license_key_visible';
            $skipExportData[] = 'license_key_visible_pwd';
            return $skipExportData;
        });

        LicenseNotices::init();
        LicensingController::init();
    }

    /**
     * Add schedule menu page
     *
     * @param array<string, AbstractMenuPageController> $basicMenuPages menu pages
     *
     * @return array<string, AbstractMenuPageController>
     */
    public function addScheduleMenuField($basicMenuPages)
    {
        $page = SchedulePageController::getInstance();

        $basicMenuPages[$page->getSlug()] = $page;
        return $basicMenuPages;
    }

    /**
     * Function calle on duplicator_addons_loaded hook
     *
     * @return void
     */
    public function hookInit(): void
    {
        License::check();
    }

    /**
     * Function called on plugin upgrade
     *
     * @param false|string $currentVersion current version
     * @param string       $newVersion     new version
     *
     * @return void
     */
    public function onUpgradePlugin($currentVersion, $newVersion): void
    {
        if ($currentVersion !== false && version_compare($currentVersion, '4.5.16-beta1', '<')) {
            $legacyKey = get_option(LicenseData::LICENSE_OLD_KEY_OPTION_NAME, '');
            if (!empty($legacyKey)) {
                LicenseData::getInstance()->setKey($legacyKey);
            }
            delete_option(LicenseData::LICENSE_OLD_KEY_OPTION_NAME);
        }
        License::clearVersionCache(false);
    }

    /**
     * Before crypt update settings
     *
     * @return void
     */
    public static function beforeCryptUpdateSettings(): void
    {
        // make sure the license date si reade before the settings are updated
        LicenseData::getInstance();
    }

    /**
     * After crypt update settings
     *
     * @return void
     */
    public static function afterCryptUpdateSettings(): void
    {
        LicenseData::getInstance()->save();
    }

    /**
     *
     * @return string
     */
    public static function getAddonPath(): string
    {
        return __DIR__;
    }

    /**
     *
     * @return string
     */
    public static function getAddonFile(): string
    {
        return __FILE__;
    }
}
