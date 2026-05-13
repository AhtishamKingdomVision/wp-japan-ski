<?php

/**
 * Settings change descriptor utility
 *
 * @package   Duplicator
 * @copyright (c) 2024, Snap Creek LLC
 */

namespace Duplicator\Utils\ActivityLog;

use Duplicator\Core\CapMng;

/**
 * Utility class for generating setting change descriptions
 */
class SettingsChangeDescriptor
{
    /**
     * Get human-readable label for setting key
     *
     * @param string $key Setting key
     *
     * @return string
     */
    public static function getLabelFromKey(string $key): string
    {
        $labels = [
            // General Settings
            'uninstall_settings'               => __('Delete plugin settings on uninstall', 'duplicator-pro'),
            'uninstall_packages'               => __('Delete backups on uninstall', 'duplicator-pro'),
            'crypt_option'                     => __('Encrypt installer files', 'duplicator-pro'),
            'unhook_third_party_js'            => __('Disable third-party JavaScript', 'duplicator-pro'),
            'unhook_third_party_css'           => __('Disable third-party CSS', 'duplicator-pro'),
            'email_summary_frequency'          => __('Email summary frequency', 'duplicator-pro'),
            'usage_tracking'                   => __('Usage tracking', 'duplicator-pro'),
            'am_notices'                       => __('Advanced notices', 'duplicator-pro'),
            'activity_log_retention'           => __('Activity log retention period', 'duplicator-pro'),

            // Email Settings
            'email_summary_recipients'         => __('Email summary recipients', 'duplicator-pro'),
            'send_email_on_build_mode'         => __('Send Email On Build Mode', 'duplicator-pro'),
            'notification_email_address'       => __('Notification Email Address', 'duplicator-pro'),

            // Logging Settings
            'logging_mode'                     => __('Logging mode', 'duplicator-pro'),
            'trace_max_size'                   => __('Trace log maximum size', 'duplicator-pro'),

            // Capability Settings - use constants from CapMng
            CapMng::CAP_BASIC                  => __('Backup Read Access', 'duplicator-pro'),
            CapMng::CAP_CREATE                 => __('Create Backups', 'duplicator-pro'),
            CapMng::CAP_SCHEDULE               => __('Schedule Management', 'duplicator-pro'),
            CapMng::CAP_STORAGE                => __('Storage Management', 'duplicator-pro'),
            CapMng::CAP_IMPORT                 => __('Import Backups', 'duplicator-pro'),
            CapMng::CAP_EXPORT                 => __('Export Backups', 'duplicator-pro'),
            CapMng::CAP_BACKUP_RESTORE         => __('Backup & Restore', 'duplicator-pro'),
            CapMng::CAP_SETTINGS               => __('Settings Management', 'duplicator-pro'),
            CapMng::CAP_LICENSE                => __('License Management', 'duplicator-pro'),

            // Storage Settings
            'storage_htaccess_off'             => __('Disable .htaccess files', 'duplicator-pro'),
            'max_storage_retries'              => __('Maximum storage retries', 'duplicator-pro'),
            'ssl_useservercerts'               => __('Use server certificates', 'duplicator-pro'),
            'ssl_disableverify'                => __('Disable SSL verification', 'duplicator-pro'),
            'ipv4_only'                        => __('IPv4 only mode', 'duplicator-pro'),
            'purge_backup_records'             => __('Purge backup records', 'duplicator-pro'),

            // Package Settings
            'server_load_reduction'            => __('Server load reduction', 'duplicator-pro'),
            'installer_name_mode'              => __('Installer name mode', 'duplicator-pro'),
            'lock_mode'                        => __('Lock mode', 'duplicator-pro'),
            'ajax_protocol'                    => __('AJAX protocol', 'duplicator-pro'),
            'custom_ajax_url'                  => __('Custom AJAX URL', 'duplicator-pro'),
            'clientside_kickoff'               => __('Client-side kickoff', 'duplicator-pro'),
            'homepath_as_abspath'              => __('Use home path as absolute path', 'duplicator-pro'),
            'installer_base_name'              => __('Installer base name', 'duplicator-pro'),
            'skip_archive_scan'                => __('Skip archive scan', 'duplicator-pro'),
            'php_max_worker_time_in_sec'       => __('Maximum worker time', 'duplicator-pro'),
            'basic_auth_enabled'               => __('Basic authentication', 'duplicator-pro'),
            'basic_auth_user'                  => __('Basic authentication username', 'duplicator-pro'),
            'max_package_runtime_in_min'       => __('Max Build Time', 'duplicator-pro'),
            'max_package_transfer_time_in_min' => __('Max Transfer Time', 'duplicator-pro'),

            // Database Settings
            'package_dbmode'                   => __('Database export mode', 'duplicator-pro'),
            'package_phpdump_mode'             => __('PHP dump mode', 'duplicator-pro'),
            'package_mysqldump_path'           => __('MySQL dump path', 'duplicator-pro'),
            'package_mysqldump_qrylimit'       => __('MySQL dump query limit', 'duplicator-pro'),

            // Archive Settings
            'archive_build_mode'               => __('Archive build mode', 'duplicator-pro'),
            'ziparchive_mode'                  => __('ZipArchive mode', 'duplicator-pro'),
            'archive_compression'              => __('Archive compression', 'duplicator-pro'),
            'ziparchive_validation'            => __('ZipArchive validation', 'duplicator-pro'),
            'ziparchive_chunk_size_in_mb'      => __('ZipArchive chunk size', 'duplicator-pro'),

            // Cleanup Settings
            'cleanup_mode'                     => __('Cleanup mode', 'duplicator-pro'),
            'cleanup_email'                    => __('Cleanup notification email', 'duplicator-pro'),
            'auto_cleanup_hours'               => __('Automatic cleanup interval', 'duplicator-pro'),

            // Import/Export Settings
            'import_chunk_size'                => __('Import chunk size', 'duplicator-pro'),
            'import_custom_path'               => __('Import custom path', 'duplicator-pro'),
            'recovery_custom_path'             => __('Recovery custom path', 'duplicator-pro'),
        ];

        return $labels[$key] ?? self::formatKeyAsLabel($key);
    }

    /**
     * Get descriptive sentence from format and data
     *
     * @param string                   $format  Format identifier
     * @param scalar[]                 $data    Data for format placeholders
     * @param array<int|string, mixed> $options Optional mapping of values to labels
     *
     * @return string
     */
    public static function getSentenceFromFormat(string $format, array $data, array $options = []): string
    {
        switch ($format) {
            // Boolean state changes
            case 'enabled':
                return __('It has been enabled', 'duplicator-pro');

            case 'disabled':
                return __('It has been disabled', 'duplicator-pro');

            // Smart time changes that auto-detect increase/decrease
            case 'timeChanged':
                // Generic time change with unit conversion via $options
                // options: fromUnit (sec|min|hour|month), toUnit (sec|min|hour|month), decimals (int)
                $fromUnit = isset($options['fromUnit']) ? (string) $options['fromUnit'] : 'sec';
                $toUnit   = isset($options['toUnit']) ? (string) $options['toUnit'] : $fromUnit;
                $decimals = isset($options['decimals']) ? (int) $options['decimals'] : (($toUnit === 'hour') ? 1 : 0);

                $unitToSeconds = [
                    'sec'   => 1,
                    'min'   => 60,
                    'hour'  => 3600,
                    'month' => defined('MONTH_IN_SECONDS') ? (int) MONTH_IN_SECONDS : (30 * 24 * 3600),
                ];

                $labelMap = [
                    'sec'   => __('seconds', 'duplicator-pro'),
                    'min'   => __('minutes', 'duplicator-pro'),
                    'hour'  => __('hours', 'duplicator-pro'),
                    'month' => __('months', 'duplicator-pro'),
                ];

                $fromFactor = $unitToSeconds[$fromUnit] ?? 1;
                $toFactor   = $unitToSeconds[$toUnit] ?? 1;
                $convert    = $fromFactor / $toFactor;

                $oldValConv = round(((float) $data[0]) * $convert, $decimals);
                $newValConv = round(((float) $data[1]) * $convert, $decimals);
                $unitLabel  = $labelMap[$toUnit] ?? '';

                if ($oldValConv > $newValConv) {
                    return sprintf(
                        __('Time decreased from %1$s to %2$s %3$s', 'duplicator-pro'),
                        (string) $oldValConv,
                        (string) $newValConv,
                        $unitLabel
                    );
                } elseif ($oldValConv < $newValConv) {
                    return sprintf(
                        __('Time increased from %1$s to %2$s %3$s', 'duplicator-pro'),
                        (string) $oldValConv,
                        (string) $newValConv,
                        $unitLabel
                    );
                }
                return sprintf(
                    __('Changed from %1$s to %2$s %3$s', 'duplicator-pro'),
                    (string) $oldValConv,
                    (string) $newValConv,
                    $unitLabel
                );

            // Size changes - generic
            case 'sizeChanged':
                // Generic size change; options: fromUnit|toUnit (bytes|KB|MB|GB), decimals, iec (bool)
                $fromUnit = isset($options['fromUnit']) ? (string) $options['fromUnit'] : 'bytes';
                $toUnit   = isset($options['toUnit']) ? (string) $options['toUnit'] : $fromUnit;
                $decimals = isset($options['decimals']) ? (int) $options['decimals'] : 0;
                $iec      = isset($options['iec']) ? (bool) $options['iec'] : false;

                $base  = $iec ? 1024 : 1000;
                $units = [
                    'bytes' => 1,
                    'KB'    => pow($base, 1),
                    'MB'    => pow($base, 2),
                    'GB'    => pow($base, 3),
                ];
                $label = function (string $u) use ($iec) {
                    if ($iec) {
                        return $u === 'KB' ? 'KiB' : ($u === 'MB' ? 'MiB' : ($u === 'GB' ? 'GiB' : 'bytes'));
                    }
                    return $u === 'KB' ? 'KB' : ($u === 'MB' ? 'MB' : ($u === 'GB' ? 'GB' : __('bytes', 'duplicator-pro')));
                };

                $fromFactor = $units[$fromUnit] ?? 1;
                $toFactor   = $units[$toUnit] ?? 1;
                $convert    = $fromFactor / $toFactor;

                $old = round(((float) $data[0]) * $convert, $decimals);
                $new = round(((float) $data[1]) * $convert, $decimals);
                $ul  = $label($toUnit);

                if ($old > $new) {
                    return sprintf(__('Size decreased from %1$s to %2$s %3$s', 'duplicator-pro'), (string) $old, (string) $new, $ul);
                } elseif ($old < $new) {
                    return sprintf(__('Size increased from %1$s to %2$s %3$s', 'duplicator-pro'), (string) $old, (string) $new, $ul);
                }
                return sprintf(__('Size changed from %1$s to %2$s %3$s', 'duplicator-pro'), (string) $old, (string) $new, $ul);



            // Text/selection changes
            case 'optionChanged':
                // If options mapping is provided, use it to convert values to labels
                if (!empty($options)) {
                    $oldLabel = $options[$data[0]] ?? (string) $data[0];
                    $newLabel = $options[$data[1]] ?? (string) $data[1];
                    return sprintf(__('Changed from "%1$s" to "%2$s"', 'duplicator-pro'), $oldLabel, $newLabel);
                }

                // Default behavior for simple value changes
                return sprintf(__('Changed from "%1$s" to "%2$s"', 'duplicator-pro'), (string) $data[0], (string) $data[1]);

            case 'frequencyChanged':
                return sprintf(
                    __('Frequency changed from %1$s to %2$s', 'duplicator-pro'),
                    (string) $data[0],
                    (string) $data[1]
                );

            case 'fieldChanged':
                // Generic text field change handler with optional truncation via $options
                $oldVal   = (string) $data[0];
                $newVal   = (string) $data[1];
                $truncate = isset($options['truncate']) ? (bool) $options['truncate'] : false;
                $maxLen   = isset($options['max']) ? (int) $options['max'] : 80;
                $subject  = isset($options['subject']) && $options['subject'] !== '' ? (string) $options['subject'] : __('Value', 'duplicator-pro');

                $formatFn = function (string $value) use ($truncate, $maxLen): string {
                    return $truncate ? self::truncateText($value, $maxLen) : $value;
                };

                if ($oldVal === '' && $newVal !== '') {
                    return sprintf(__('%1$s was set to "%2$s"', 'duplicator-pro'), $subject, $formatFn($newVal));
                } elseif ($oldVal !== '' && $newVal === '') {
                    return sprintf(__('%1$s was removed (was "%2$s")', 'duplicator-pro'), $subject, $formatFn($oldVal));
                } elseif ($oldVal !== '' && $newVal !== '') {
                    return sprintf(__('%1$s changed from "%2$s" to "%3$s"', 'duplicator-pro'), $subject, $formatFn($oldVal), $formatFn($newVal));
                }
                return sprintf(__('%s was cleared', 'duplicator-pro'), $subject);

            // logging_mode now uses optionChanged with mapping

            // List/array changes

            case 'emailListChanged':
                // Alias to listChanged with sensible defaults
                $options = array_merge(
                    [
                        'labelAddedSingular'   => __('%s was added', 'duplicator-pro'),
                        'labelAddedPlural'     => __('%s were added', 'duplicator-pro'),
                        'labelRemovedSingular' => __('%s was removed', 'duplicator-pro'),
                        'labelRemovedPlural'   => __('%s were removed', 'duplicator-pro'),
                        'separator'            => ' and ',
                        'maxItems'             => 0,
                    ],
                    $options
                );
                // fallthrough intended
            case 'listChanged':
                // data[0] = old array, data[1] = new array
                $oldList = is_array($data[0]) ? $data[0] : [];
                $newList = is_array($data[1]) ? $data[1] : [];

                $added   = array_values(array_diff($newList, $oldList));
                $removed = array_values(array_diff($oldList, $newList));

                $formatItems = function (array $items, int $maxItems): string {
                    if ($maxItems > 0 && count($items) > $maxItems) {
                        $shown = array_slice($items, 0, $maxItems);
                        $more  = count($items) - $maxItems;
                        return implode(', ', $shown) . sprintf(__(' and +%d more', 'duplicator-pro'), $more);
                    }
                    return implode(', ', $items);
                };

                $sentences = [];
                if (!empty($added)) {
                    $list        = $formatItems($added, (int) ($options['maxItems'] ?? 0));
                    $tpl         = count($added) === 1 ? ($options['labelAddedSingular'] ?? '%s was added') : ($options['labelAddedPlural'] ?? '%s were added');
                    $sentences[] = sprintf($tpl, $list);
                }
                if (!empty($removed)) {
                    $list        = $formatItems($removed, (int) ($options['maxItems'] ?? 0));
                    $tpl         = count($removed) === 1 ?
                        ($options['labelRemovedSingular'] ?? '%s was removed') :
                        ($options['labelRemovedPlural'] ?? '%s were removed');
                    $sentences[] = sprintf($tpl, $list);
                }

                if (!empty($sentences)) {
                    $sep = (string) ($options['separator'] ?? '; ');
                    return implode($sep, $sentences);
                }
                return __('List unchanged', 'duplicator-pro');

            case 'singleCapabilityChanged':
                // data[0] = old capability array, data[1] = new capability array
                $oldCap = is_array($data[0]) ? $data[0] : [
                    'roles' => [],
                    'users' => [],
                ];
                $newCap = is_array($data[1]) ? $data[1] : [
                    'roles' => [],
                    'users' => [],
                ];

                $changes = [];

                // Check for role changes
                $addedRoles   = array_diff($newCap['roles'], $oldCap['roles']);
                $removedRoles = array_diff($oldCap['roles'], $newCap['roles']);

                // Check for user changes
                $addedUsers   = array_diff($newCap['users'], $oldCap['users']);
                $removedUsers = array_diff($oldCap['users'], $newCap['users']);

                if (!empty($addedRoles)) {
                    if (count($addedRoles) === 1) {
                        $changes[] = sprintf(__('%s role was added', 'duplicator-pro'), reset($addedRoles));
                    } else {
                        $changes[] = sprintf(__('%s roles were added', 'duplicator-pro'), implode(', ', $addedRoles));
                    }
                }

                if (!empty($removedRoles)) {
                    if (count($removedRoles) === 1) {
                        $changes[] = sprintf(__('%s role was removed', 'duplicator-pro'), reset($removedRoles));
                    } else {
                        $changes[] = sprintf(__('%s roles were removed', 'duplicator-pro'), implode(', ', $removedRoles));
                    }
                }

                if (!empty($addedUsers)) {
                    $userNames = array_map(function ($userId) {
                        $user = get_user_by('id', $userId);
                        return $user ? $user->user_login : "User #{$userId}";
                    }, $addedUsers);

                    if (count($userNames) === 1) {
                        $changes[] = sprintf(__('%s was added', 'duplicator-pro'), reset($userNames));
                    } else {
                        $changes[] = sprintf(__('%s were added', 'duplicator-pro'), implode(', ', $userNames));
                    }
                }

                if (!empty($removedUsers)) {
                    $userNames = array_map(function ($userId) {
                        $user = get_user_by('id', $userId);
                        return $user ? $user->user_login : "User #{$userId}";
                    }, $removedUsers);

                    if (count($userNames) === 1) {
                        $changes[] = sprintf(__('%s was removed', 'duplicator-pro'), reset($userNames));
                    } else {
                        $changes[] = sprintf(__('%s were removed', 'duplicator-pro'), implode(', ', $userNames));
                    }
                }

                if (!empty($changes)) {
                    return implode(' and ', $changes);
                } else {
                    return __('No changes detected', 'duplicator-pro');
                }

            case 'passwordChanged':
                return __('Password was updated', 'duplicator-pro');

                        // Default fallback
            default:
                return sprintf(
                    __('Unknown format "%1$s" with data: %2$s', 'duplicator-pro'),
                    $format,
                    wp_json_encode($data)
                );
        }
    }

    /**
     * Format a setting key as a readable label
     *
     * @param string $key Setting key
     *
     * @return string
     */
    private static function formatKeyAsLabel(string $key): string
    {
        // Convert snake_case to Title Case
        return ucwords(str_replace(['_', '-'], ' ', $key));
    }

    /**
     * Truncate text for display
     *
     * @param string $text      Text to truncate
     * @param int    $maxLength Maximum length
     *
     * @return string
     */
    private static function truncateText(string $text, int $maxLength = 50): string
    {
        if (strlen($text) > $maxLength) {
            return substr($text, 0, $maxLength - 3) . '...';
        }
        return $text;
    }
}
