<?php

namespace Duplicator\Models;

use Duplicator\Utils\Crypt\CryptBlowfish;

/**
 * Static global settings manager for critical plugin options.
 *
 * This class provides direct database access for essential plugin settings that must be
 * available without instantiating full entity objects. It handles critical options like
 * encryption settings and uninstall preferences that are needed during plugin initialization
 * and deactivation processes.
 *
 * Unlike standard entities, this class uses WordPress options API directly and maintains
 * static caching for performance. It's designed for settings that require immediate access
 * during plugin bootstrap or when the full entity system isn't available.
 */
final class StaticGlobal
{
    const UNINSTALL_PACKAGE_OPTION_KEY  = 'dupli_opt_uninstall_package';
    const UNINSTALL_SETTINGS_OPTION_KEY = 'dupli_opt_uninstall_settings';
    const CRYPT_OPTION_KEY              = 'dupli_opt_crypt';
    const TRACE_LOG_ENABLED_OPTION_KEY  = 'dupli_opt_trace_log_enabled';
    const TRACE_TO_ERROR_LOG_OPTION_KEY = 'dupli_opt_trace_to_error_log';

    private static ?bool $uninstallPackageOption    = null;
    private static ?bool $uninstallSettingsOption   = null;
    private static ?bool $cryptOption               = null;
    private static ?bool $traceLogEnabledOption     = null;
    private static ?bool $sendTraceToErrorLogOption = null;

    /**
     * Reset user settings, remove all options from the database
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$uninstallPackageOption    = null;
        self::$uninstallSettingsOption   = null;
        self::$cryptOption               = null;
        self::$traceLogEnabledOption     = null;
        self::$sendTraceToErrorLogOption = null;

        delete_option(self::UNINSTALL_PACKAGE_OPTION_KEY);
        delete_option(self::UNINSTALL_SETTINGS_OPTION_KEY);
        delete_option(self::CRYPT_OPTION_KEY);
        delete_option(self::TRACE_LOG_ENABLED_OPTION_KEY);
        delete_option(self::TRACE_TO_ERROR_LOG_OPTION_KEY);
    }

    /**
     * Get the uninstall package option
     *
     * @return bool
     */
    public static function getUninstallPackageOption(): bool
    {
        if (self::$uninstallPackageOption === null) {
            self::$uninstallPackageOption = get_option(self::UNINSTALL_PACKAGE_OPTION_KEY, false);
        }
        return self::$uninstallPackageOption;
    }

    /**
     * Get the uninstall settings option
     *
     * @return bool
     */
    public static function getUninstallSettingsOption(): bool
    {
        if (self::$uninstallSettingsOption === null) {
            self::$uninstallSettingsOption = get_option(self::UNINSTALL_SETTINGS_OPTION_KEY, false);
        }
        return self::$uninstallSettingsOption;
    }

    /**
     * Set the uninstall package option
     *
     * @param bool $value The value to set
     *
     * @return void
     */
    public static function setUninstallPackageOption(bool $value): void
    {
        self::$uninstallPackageOption = $value;
        update_option(self::UNINSTALL_PACKAGE_OPTION_KEY, $value);
    }

    /**
     * Set the uninstall settings option
     *
     * @param bool $value The value to set
     *
     * @return void
     */
    public static function setUninstallSettingsOption(bool $value): void
    {
        self::$uninstallSettingsOption = $value;
        update_option(self::UNINSTALL_SETTINGS_OPTION_KEY, $value);
    }

    /**
     * Get the crypt option
     *
     * @return bool
     */
    public static function getCryptOption(): bool
    {
        if (self::$cryptOption === null) {
            self::$cryptOption = (get_option(self::CRYPT_OPTION_KEY, true) && CryptBlowfish::isEncryptAvailable());
        }
        return self::$cryptOption;
    }

    /**
     * Set the crypt option
     *
     * @param bool $value The value to set
     *
     * @return void
     */
    public static function setCryptOption(bool $value): void
    {
        self::$cryptOption = ($value && CryptBlowfish::isEncryptAvailable());
        update_option(self::CRYPT_OPTION_KEY, $value);
    }

    /**
     * Get the trace log enabled option
     *
     * @return bool
     */
    public static function getTraceLogEnabledOption(): bool
    {
        if (self::$traceLogEnabledOption === null) {
            self::$traceLogEnabledOption = get_option(self::TRACE_LOG_ENABLED_OPTION_KEY, false);
        }
        return self::$traceLogEnabledOption;
    }

    /**
     * Set the trace log enabled option
     *
     * @param bool $value The value to set
     *
     * @return void
     */
    public static function setTraceLogEnabledOption(bool $value): void
    {
        self::$traceLogEnabledOption = $value;
        update_option(self::TRACE_LOG_ENABLED_OPTION_KEY, $value);
    }

    /**
     * Get the send trace to error log option
     *
     * @return bool
     */
    public static function getSendTraceToErrorLogOption(): bool
    {
        if (self::$sendTraceToErrorLogOption === null) {
            self::$sendTraceToErrorLogOption = get_option(self::TRACE_TO_ERROR_LOG_OPTION_KEY, false);
        }
        return self::$sendTraceToErrorLogOption;
    }

    /**
     * Set the send trace to error log option
     *
     * @param bool $value The value to set
     *
     * @return void
     */
    public static function setSendTraceToErrorLogOption(bool $value): void
    {
        self::$sendTraceToErrorLogOption = $value;
        update_option(self::TRACE_TO_ERROR_LOG_OPTION_KEY, $value);
    }
}
