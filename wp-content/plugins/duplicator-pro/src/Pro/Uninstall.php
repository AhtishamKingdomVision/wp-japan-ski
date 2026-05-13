<?php

namespace Duplicator\Pro;

use Error;
use Exception;
use WP_Filesystem_Direct;

/**
 * Uninstall class
 * Maintain PHP 7.4 compatibility, don't include Duplicator Libs.
 *
 * This is a standalone class used on uninstall.php
 */
class Uninstall
{
    const ENTITIES_TABLE_NAME           = 'duplicator_entities';
    const PACKAGES_TABLE_NAME           = 'duplicator_backups';
    const ACTIVITY_LOG_TABLE_NAME       = 'duplicator_activity_logs';
    const VERSION_OPTION_KEY            = 'dupli_opt_version';
    const INSTALL_INFO_OPTION_KEY       = 'dupli_opt_install_info';
    const UNINSTALL_PACKAGE_OPTION_KEY  = 'dupli_opt_uninstall_package';
    const UNINSTALL_SETTINGS_OPTION_KEY = 'dupli_opt_uninstall_settings';

    /**
     * Uninstall plugin
     *
     * @return void
     */
    public static function uninstall(): void
    {
        try {
            self::removePackages();
            self::removeSettings();
            self::removePluginVersion();
        } catch (Exception | Error $e) {
            if (function_exists('error_log')) {
                error_log('Duplicator PRO uninstall clean Backups error: ' . $e->getMessage());
            }
            // Prevent error on uninstall
        }
    }

    /**
     * Remove plugin version options
     *
     * @return void
     */
    protected static function removePluginVersion()
    {
        delete_option(self::VERSION_OPTION_KEY);
        delete_option(self::INSTALL_INFO_OPTION_KEY);
    }

    /**
     * Return duplicator PRO backup path
     *
     * @return string
     */
    protected static function getBackupPath(): string
    {
        return trailingslashit(wp_normalize_path((string) realpath(WP_CONTENT_DIR))) . 'duplicator-backups';
    }

    /**
     * Remove all Backups
     *
     * @return void
     */
    protected static function removePackages()
    {
        /** @var \wpdb */
        global $wpdb;

        if (get_option(self::UNINSTALL_PACKAGE_OPTION_KEY) != true) {
            return;
        }

        try {
            $tableName = $wpdb->base_prefix . self::PACKAGES_TABLE_NAME;
            $wpdb->query('DROP TABLE IF EXISTS ' . $tableName);

            $ssdir = self::getBackupPath();

            // Sanity check for strange setup
            $check = glob("{$ssdir}/wp-config.php");

            if (is_array($check) && count($check) == 0) {
                if (!class_exists('WP_Filesystem_Direct')) {
                    include_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php');
                }
                if (!class_exists('WP_Filesystem_Base')) {
                    include_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php');
                }
                $fsystem = new WP_Filesystem_Direct(true);
                $fsystem->rmdir($ssdir, true);
            }
        } catch (Exception | Error $e) {
            if (function_exists('error_log')) {
                error_log('Duplicator PRO uninstall clean Backups error: ' . $e->getMessage());
            }
            // Prevent error on remove Backups
        }
    }

    /**
     * Remove plugins settings
     *
     * @return void
     */
    protected static function removeSettings()
    {
        /** @var \wpdb */
        global $wpdb;

        if (get_option(self::UNINSTALL_SETTINGS_OPTION_KEY) != true) {
            return;
        }

        $tableName = $wpdb->base_prefix . self::ENTITIES_TABLE_NAME;
        $wpdb->query('DROP TABLE IF EXISTS ' . $tableName);
        $tableName = $wpdb->base_prefix . self::ACTIVITY_LOG_TABLE_NAME;
        $wpdb->query('DROP TABLE IF EXISTS ' . $tableName);

        self::removeAllCapabilities();
        self::deleteUserMetaKeys();
        self::deleteOptions(); // Deletes all dupli_opt_* options including transients
        self::deleteSiteTransients();
        self::cleanWpConfig();
    }

    /**
     * Delete all users meta key
     *
     * @return void
     */
    private static function deleteUserMetaKeys(): void
    {
        /** @var \wpdb */
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
                $wpdb->esc_like('dupli_opt_') . '%'
            )
        );
    }

    /**
     * Delete all options
     *
     * @return void
     */
    protected static function deleteOptions()
    {
        /** @var \wpdb */
        global $wpdb;

        $optionsTableName = $wpdb->base_prefix . "options";
        $dupOptionNames   = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT `option_name` FROM `{$optionsTableName}` WHERE `option_name` LIKE %s",
                $wpdb->esc_like('dupli_opt_') . '%'
            )
        );

        foreach ($dupOptionNames as $dupOptionName) {
            delete_option($dupOptionName);
        }
    }

    /**
     * wp-config.php cleanup
     *
     * @return bool false if wp-config.php not found
     */
    protected static function cleanWpConfig()
    {
        if (($wpConfigFile = self::getWPConfigPath()) === false) {
            return false;
        }

        if (($content = file_get_contents($wpConfigFile)) === false) {
            return false;
        }

        $content = preg_replace('/^.*define.+[\'"]DUPLICATOR_AUTH_KEY[\'"].*$/m', '', $content);

        return (file_put_contents($wpConfigFile, $content) !== false);
    }

    /**
     * Return wp-config path or false if not found
     *
     * @return false|string
     */
    protected static function getWPConfigPath()
    {
        static $configPath = null;
        if (is_null($configPath)) {
            $absPath   = trailingslashit(ABSPATH);
            $absParent = dirname($absPath) . '/';

            if (file_exists($absPath . 'wp-config.php')) {
                $configPath = $absPath . 'wp-config.php';
            } elseif (@file_exists($absParent . 'wp-config.php') && !@file_exists($absParent . 'wp-settings.php')) {
                $configPath = $absParent . 'wp-config.php';
            } else {
                $configPath = false;
            }
        }
        return $configPath;
    }

    /**
     * Delete site transients created by the plugin that don't use the dupli_opt_ prefix.
     *
     * @return void
     */
    private static function deleteSiteTransients(): void
    {
        /** @var \wpdb */
        global $wpdb;

        $optionsTableName = $wpdb->base_prefix . 'options';
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM `{$optionsTableName}` WHERE `option_name` LIKE %s",
                $wpdb->esc_like('_site_transient_duplicator_') . '%'
            )
        );
    }

    /**
     * Remove all capabilities
     *
     * @return void
     */
    protected static function removeAllCapabilities()
    {
        if (($capabilities = get_option('dupli_opt_capabilities')) == false) {
            return;
        }

        foreach ($capabilities as $cap => $data) {
            foreach ($data['roles'] as $role) {
                $role = get_role($role);
                if ($role) {
                    $role->remove_cap($cap);
                }
            }
            foreach ($data['users'] as $user) {
                $user = get_user_by('id', $user);
                if ($user) {
                    $user->remove_cap($cap);
                }
            }
        }
    }
}
