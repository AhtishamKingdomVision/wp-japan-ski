<?php

namespace Duplicator\Utils\ManagedHost;

use Duplicator\Package\Archive\PackageArchive;

class HostGoDaddy implements ManagedHostInterface
{
    /**
     * Get the identifier for this host
     *
     * @return string
     */
    public static function getIdentifier(): string
    {
        return ManagedHostMng::HOST_GODADDY;
    }

    /**
     * Check if the current host is GoDaddy
     *
     * @return bool true if is current host
     */
    public function isHosting(): bool
    {
        return apply_filters('duplicator_godaddy_host_check', file_exists(WPMU_PLUGIN_DIR . '/gd-system-plugin.php'));
    }

    /**
     * Initialize the host
     *
     * @return void
     */
    public function init(): void
    {
        add_filter('duplicator_default_archive_build_mode', [self::class, 'defaultArchiveBuildMode'], 20, 1);
        add_filter('duplicator_overwrite_params_data', [self::class, 'installerParams']);
    }

    /**
     * In godaddy the packag build mode must be Dup archive
     *
     * @param int $archiveBuildMode archive build mode
     *
     * @return int
     */
    public static function defaultArchiveBuildMode($archiveBuildMode): int
    {
        return PackageArchive::BUILD_MODE_DUP_ARCHIVE;
    }

    /**
     * Add installer params
     *
     * @param array<string,array{formStatus?:string,value:mixed}> $data Data
     *
     * @return array<string,array{formStatus?:string,value:mixed}>
     */
    public static function installerParams($data)
    {
        // disable wp engine plugins
        $data['fd_plugins'] = [
            'value' => [
                'gd-system-plugin.php',
                'object-cache.php',
            ],
        ];

        // generate new wp-config.php file
        $data['wp_config'] = [
            'value'      => 'new',
            'formStatus' => 'st_infoonly',
        ];

        return $data;
    }
}
