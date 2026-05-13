<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Addons\ProBase\License;

use Duplicator\Utils\Logging\DupLog;
use Duplicator\Addons\ProBase\Vendor\EDD\EDD_SL_Plugin_Updater;
use Duplicator\Addons\ProBase\DrmHandler;
use Duplicator\Addons\ProBase\Models\LicenseData;
use Duplicator\Core\CapMng;
use Duplicator\Installer\Addons\ProBase\AbstractLicense;
use Duplicator\Utils\ExpireOptions;

final class License extends AbstractLicense
{
    /**
     * GENERAL SETTINGS
     */
    const EDD_DUP_ITEM_NAME               = 'Duplicator Pro';
    const FRONTEND_CHECK_DELAY            = 61; // Seconds, different fromgeneral frontend check to unsync
    const FRONTEND_CHECK_DELAY_OPTION_KEY = 'license_check';

    const VISIBILITY_INFO = 0;
    const VISIBILITY_ALL  = 1;
    const VISIBILITY_NONE = 2;


    /** @var ?EDD_SL_Plugin_Updater */
    private static $edd_updater;

    /**
     * License check
     *
     * @return void
     */
    public static function check(): void
    {
        if (
            !is_admin() &&
            !(defined('WP_CLI') && WP_CLI) &&
            ExpireOptions::getUpdate(
                self::FRONTEND_CHECK_DELAY_OPTION_KEY,
                true,
                self::FRONTEND_CHECK_DELAY
            ) !== false
        ) {
            return;
        }

        if (
            in_array(
                LicenseData::getInstance()->getStatus(),
                [
                    LicenseData::STATUS_INVALID,
                    LicenseData::STATUS_UNKNOWN,
                ]
            )
        ) {
            return;
        }

        self::initEddUpdater();
    }

    /**
     * Return true if multisite is enabled
     *
     * @return bool
     */
    protected static function isMultisite()
    {
        return is_multisite();
    }

    /**
     * Return true if license have the capability
     *
     * @param int  $capability capability key
     * @param ?int $license    ENUM license type, if null Get currnt licnse type
     *
     * @return bool
     */
    public static function can($capability, $license = null)
    {
        if (is_null($license)) {
            $license = static::getType();
        }

        switch ($capability) {
            case self::CAPABILITY_SCHEDULE:
                if (parent::can($capability, $license)) {
                    return true;
                }
                if (DrmHandler::getDaysTillScheduleDRM() >= 0) {
                    return true;
                }
                return false;
            default:
                return parent::can($capability, $license);
        }
    }

    /**
     * Force upgrade check
     *
     * @return void
     */
    public static function forceUpgradeCheck(): void
    {
        if (
            in_array(
                LicenseData::getInstance()->getStatus(),
                [
                    LicenseData::STATUS_INVALID,
                    LicenseData::STATUS_UNKNOWN,
                ]
            )
        ) {
            return;
        }

        self::clearVersionCache(true);
    }

    /**
     * Return latest version of the plugin
     *
     * @return string|false
     */
    public static function getLatestVersion()
    {
        $version_info = null;
        $edd_updater  = self::getEddUpdater();

        /** @var false|object */
        $version_info = $edd_updater->get_cached_version_info();

        if (is_object($version_info) && isset($version_info->new_version)) {
            return $version_info->new_version;
        } else {
            return false;
        }
    }

    /**
     * Clear version cache
     *
     * @param bool $clearLicenseData Clear license data cache
     *
     * @return void
     */
    public static function clearVersionCache(bool $clearLicenseData): void
    {
        DupLog::trace("CLEAR EDD VERSION CACHE");
        if ($clearLicenseData) {
            LicenseData::getInstance()->clearCache();
        }
        self::getEddUpdater()->set_version_info_cache(false);
    }

    /**
     * Return license key
     *
     * @return string
     */
    public static function getLicenseKey()
    {
        return LicenseData::getInstance()->getKey();
    }

    /**
     * Get license status
     *
     * @return int
     */
    public static function getLicenseStatus()
    {
        return LicenseData::getInstance()->getStatus();
    }

    /**
     * Return license type
     *
     * @return int ENUM AbstractLicense::TYPE_*
     */
    public static function getType()
    {
        return (
            LicenseData::getInstance()->getStatus() == LicenseData::STATUS_VALID ?
            LicenseData::getInstance()->getLicenseType() :
            self::TYPE_UNLICENSED
        );
    }

    /**
     * Initialize the EDD Updater
     *
     * @return void
     */
    private static function initEddUpdater(): void
    {
        if (self::$edd_updater !== null) {
            return;
        }

        $eddOpts = [
            'version'     => DUPLICATOR_VERSION,
            'license'     => self::getLicenseKey(),
            'item_name'   => self::EDD_DUP_ITEM_NAME,
            'author'      => 'Snap Creek Software',
            'wp_override' => true,
        ];

        self::$edd_updater = new EDD_SL_Plugin_Updater(
            DUPLICATOR_STORE_URL,
            DUPLICATOR____FILE,
            $eddOpts
        );
    }

    /**
     * Accessor that returns the EDD Updater singleton
     *
     * @return EDD_SL_Plugin_Updater
     */
    private static function getEddUpdater()
    {
        if (self::$edd_updater === null) {
            self::initEddUpdater();
        }

        return self::$edd_updater;
    }

    /**
     * Return upsell URL
     *
     * @return string
     */
    public static function getUpsellURL(): string
    {
        return DUPLICATOR_BLOG_URL . 'my-account/';
    }

    /**
     * Return EDD license upgrade checkout page URL
     *
     * @return string
     */
    public static function getLicenseCheckoutURL(): string
    {
        if (
            !CapMng::can(CapMng::CAP_LICENSE, false) ||
            empty(self::getLicenseKey())
        ) {
            return DUPLICATOR_BLOG_URL . 'my-account/';
        } else {
            return DUPLICATOR_BLOG_URL . 'checkout?edd_license_key=' . self::getLicenseKey();
        }
    }

    /**
     * Return no activation left message
     *
     * @return string
     */
    public static function getNoActivationLeftMessage()
    {
        if (self::isUnlimited()) {
            $result  = sprintf(__('%1$s site licenses are granted in batches of 500.', 'duplicator-pro'), License::getLicenseToString());
            $result .= ' ';
            $result .= sprintf(
                _x(
                    'Please submit a %1$sticket request%2$s and we will grant you another batch.',
                    '%1$s and %2$s represents the opening and closing HTML tags for an anchor or link',
                    'duplicator-pro'
                ),
                '<a href="' . DUPLICATOR_BLOG_URL . 'my-account/support/" target="_blank">',
                '</a>'
            );
            $result .= '<br>';
            return $result . __('This process helps to ensure that licenses are not stolen or abused for users.', 'duplicator-pro');
        } else {
            return __(
                'Use the link above to login to your duplicator.com dashboard to manage your licenses or upgrade to a higher license.',
                'duplicator-pro'
            );
        }
    }
}
