<?php

namespace Duplicator\Utils\CachesPurge;

class CachesPurge
{
    /**
     * purge all and return purge messages
     *
     * @return string[]
     */
    public static function purgeAll(): array
    {
        $globalMessages = [];
        $items          = array_merge(
            self::getPurgePlugins(),
            self::getPurgeHosts()
        );


        foreach ($items as $item) {
            $message = '';
            $result  = $item->purge($message);
            if (strlen($message) > 0 && $result) {
                $globalMessages[] = $message;
            }
        }

        return $globalMessages;
    }

    /**
     * get list to cache items to purge
     *
     * @return CacheItem[]
     */
    protected static function getPurgePlugins(): array
    {
        $items   = [];
        $items[] = new CacheItem(
            'Elementor',
            fn(): bool => class_exists("\\Elementor\\Plugin"),
            function (): void {
                \Elementor\Plugin::$instance->files_manager->clear_cache(); // @phpstan-ignore-line
            }
        );
        $items[] = new CacheItem(
            'W3 Total Cache',
            fn(): bool => function_exists('w3tc_pgcache_flush'),
            'w3tc_pgcache_flush' // @phpstan-ignore-line
        );
        $items[] = new CacheItem(
            'WP Super Cache',
            fn(): bool => function_exists('wp_cache_clear_cache'),
            'wp_cache_clear_cache' // @phpstan-ignore-line
        );
        $items[] = new CacheItem(
            'WP Rocket',
            fn(): bool => function_exists('rocket_clean_domain'),
            'rocket_clean_domain' // @phpstan-ignore-line
        );
        $items[] = new CacheItem(
            'Fast velocity minify',
            fn(): bool => function_exists('fvm_purge_static_files'),
            'fvm_purge_static_files' // @phpstan-ignore-line
        );
        $items[] = new CacheItem(
            'Cachify',
            fn(): bool => function_exists('cachify_flush_cache'),
            'cachify_flush_cache' // @phpstan-ignore-line
        );
        $items[] = new CacheItem(
            'Comet Cache',
            fn(): bool => class_exists('\\comet_cache'),
            [
                '\\comet_cache',
                'clear',
            ] // @phpstan-ignore-line
        );
        $items[] = new CacheItem(
            'Zen Cache',
            fn(): bool => class_exists('\\zencache'),
            [
                '\\zencache',
                'clear',
            ] // @phpstan-ignore-line
        );
        $items[] = new CacheItem(
            'LiteSpeed Cache',
            fn() => has_action('litespeed_purge_all'),
            function (): void {
                do_action('litespeed_purge_all');
            }
        );
        $items[] = new CacheItem(
            'WP Cloudflare Super Page Cache',
            fn(): bool => class_exists('\\SW_CLOUDFLARE_PAGECACHE'),
            function (): void {
                do_action("swcfpc_purge_everything");
            }
        );
        $items[] = new CacheItem(
            'Hyper Cache',
            fn(): bool => class_exists('\\HyperCache'),
            function (): void {
                do_action('autoptimize_action_cachepurged');
            }
        );
        $items[] = new CacheItem(
            'Cache Enabler',
            fn() => has_action('ce_clear_cache'),
            function (): void {
                do_action('ce_clear_cache');
            }
        );
        $items[] = new CacheItem(
            'WP Fastest Cache',
            fn(): bool => function_exists('wpfc_clear_all_cache'),
            function (): void {
                wpfc_clear_all_cache(true); // @phpstan-ignore-line
            }
        );
        $items[] = new CacheItem(
            'Breeze',
            fn(): bool => class_exists("\\Breeze_PurgeCache"),
            [
                '\\Breeze_PurgeCache',
                'breeze_cache_flush',
            ] // @phpstan-ignore-line
        );
        $items[] = new CacheItem(
            'Swift Performance',
            fn(): bool => class_exists("\\Swift_Performance_Cache"),
            [
                '\\Swift_Performance_Cache',
                'clear_all_cache',
            ] // @phpstan-ignore-line
        );
        $items[] = new CacheItem(
            'Hummingbird',
            fn() => has_action('wphb_clear_page_cache'),
            function (): void {
                do_action('wphb_clear_page_cache');
            }
        );
        $items[] = new CacheItem(
            'WP-Optimize',
            fn() => has_action('wpo_cache_flush'),
            function (): void {
                do_action('wpo_cache_flush');
            }
        );
        $items[] = new CacheItem(
            'WordPress default',
            fn(): bool => function_exists('wp_cache_flush'),
            'wp_cache_flush'
        );
        $items[] = new CacheItem(
            'WordPress permalinks',
            fn(): bool => function_exists('flush_rewrite_rules'),
            'flush_rewrite_rules'
        );
        $items[] = new CacheItem(
            'NinjaForms Maintenance Mode',
            function (): bool {
                return class_exists('WPN_Helper') && is_callable('WPN_Helper', 'set_forms_maintenance_mode');  // @phpstan-ignore-line
            },
            [
                'WPN_Helper',
                'set_forms_maintenance_mode',
            ] // @phpstan-ignore-line
        );
        return $items;
    }

    /**
     * get list to cache items to purge
     *
     * @return CacheItem[]
     */
    protected static function getPurgeHosts(): array
    {
        $items   = [];
        $items[] = new CacheItem(
            'Godaddy Managed WordPress Hosting',
            function (): bool {
                return class_exists('\\WPaaS\\Plugin') && method_exists('\\WPass\\Plugin', 'vip');  // @phpstan-ignore-line
            },
            function (): void {
                $method = 'BAN';
                $url    = home_url();
                $host   = wpraiser_get_domain(); // @phpstan-ignore-line
                $url    = set_url_scheme(str_replace($host, \WPaas\Plugin::vip(), $url), 'http'); // @phpstan-ignore-line
                update_option('gd_system_last_cache_flush', time(), false); # purge apc
                wp_remote_request(
                    esc_url_raw($url),
                    [
                        'method'   => $method,
                        'blocking' => false,
                        'headers'  =>
                        ['Host' => $host],
                    ]
                );
            }
        );
        $items[] = new CacheItem(
            'SG Optimizer (Siteground)',
            fn(): bool => function_exists('sg_cachepress_purge_everything'),
            'sg_cachepress_purge_everything' // @phpstan-ignore-line
        );
        $items[] = new CacheItem(
            'WP Engine',
            fn(): bool => class_exists(\WpeCommon::class) &&
            (
                method_exists(\WpeCommon::class, 'purge_memcached') ||  // @phpstan-ignore-line
                method_exists(\WpeCommon::class, 'purge_varnish_cache')  // @phpstan-ignore-line
            ),
            function (): void {
                if (method_exists(\WpeCommon::class, 'purge_memcached')) {  // @phpstan-ignore-line
                    \WpeCommon::purge_memcached();
                }
                if (method_exists(\WpeCommon::class, 'purge_varnish_cache')) {  // @phpstan-ignore-line
                    \WpeCommon::purge_varnish_cache();
                }
            }
        );
        $items[] = new CacheItem(
            'Kinsta',
            function (): bool {
                global $kinsta_cache;
                return (
                    (isset($kinsta_cache) &&
                        class_exists('\\Kinsta\\CDN_Enabler')) &&
                    !empty($kinsta_cache->kinsta_cache_purge));
            },
            function (): void {
                global $kinsta_cache;
                $kinsta_cache->kinsta_cache_purge->purge_complete_caches();
            }
        );
        $items[] = new CacheItem(
            'Pagely',
            fn(): bool => class_exists('\\PagelyCachePurge'),
            function (): void {
                $purge_pagely = new \PagelyCachePurge(); // @phpstan-ignore-line
                $purge_pagely->purgeAll(); // @phpstan-ignore-line
            }
        );
        $items[] = new CacheItem(
            'Pressidum',
            fn(): bool => defined('WP_NINUKIS_WP_NAME') && class_exists('\\Ninukis_Plugin'),
            function (): void {
                $purge_pressidum = \Ninukis_Plugin::get_instance(); // @phpstan-ignore-line
                $purge_pressidum->purgeAllCaches();
            }
        );

        $items[] = new CacheItem(
            'Pantheon Advanced Page Cache plugin',
            fn(): bool => function_exists('pantheon_wp_clear_edge_all'),
            'pantheon_wp_clear_edge_all' // @phpstan-ignore-line
        );
        return $items;
    }
}
