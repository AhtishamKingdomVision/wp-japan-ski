<?php

namespace Duplicator\Utils\UsageStatistics;

use Duplicator\Models\GlobalEntity;
use Duplicator\Package\AbstractPackage;
use Duplicator\Utils\Logging\DupLog;
use Duplicator\Package\Create\BuildComponents;
use Duplicator\Utils\CronUtils;

/**
 * StatsBootstrap
 */
class StatsBootstrap
{
    const USAGE_TRACKING_CRON_HOOK = 'duplicator_usage_tracking_cron';

    /**
     * Init WordPress hooks
     *
     * @return void
     */
    public static function init(): void
    {
        add_action('duplicator_package_transfer_completed', [self::class, 'addPackageBuild']);
        add_action('duplicator_build_fail', [self::class, 'addPackageBuild']);
        add_action('duplicator_after_scan_report', [self::class, 'addSiteSizes'], 10, 2);
    }

    /**
     * Activation action
     *
     * @return void
     */
    public static function activationAction(): void
    {
        if (PluginData::getInstance()->getStatus() !== PluginData::PLUGIN_STATUS_ACTIVE) {
            PluginData::getInstance()->setStatus(PluginData::PLUGIN_STATUS_ACTIVE);
            CommStats::pluginSend();
        }
    }

    /**
     * Cron activation action
     *
     * @return void
     */
    public static function cronActivatie(): void
    {
        // Set cron
        if (!wp_next_scheduled(self::USAGE_TRACKING_CRON_HOOK)) {
            $randomTracking = wp_rand(0, WEEK_IN_SECONDS);
            $timeToStart    = strtotime('next sunday') + $randomTracking;
            wp_schedule_event($timeToStart, CronUtils::INTERVAL_WEEKLY, self::USAGE_TRACKING_CRON_HOOK);
        }
    }

    /**
     * Deactivation action
     *
     * @return void
     */
    public static function deactivationAction(): void
    {
        PluginData::getInstance()->setStatus(PluginData::PLUGIN_STATUS_INACTIVE);
        CommStats::pluginSend();
    }

    /**
     * Cron deactivate
     *
     * @return void
     */
    public static function cronDeactivate(): void
    {
        // Unschedule custom cron event for cleanup if it's scheduled
        if (wp_next_scheduled(self::USAGE_TRACKING_CRON_HOOK)) {
            $timestamp = wp_next_scheduled(self::USAGE_TRACKING_CRON_HOOK);
            wp_unschedule_event($timestamp, self::USAGE_TRACKING_CRON_HOOK);
        }
    }

    /**
     * Add Backup build,
     * don't use PluginData::getInstance()->addPackageBuild() directly in hook to avoid useless init
     *
     * @param AbstractPackage $package Backup
     *
     * @return void
     */
    public static function addPackageBuild(AbstractPackage $package): void
    {
        PluginData::getInstance()->addPackageBuild($package);
    }

    /**
     * Add site size statistics
     *
     * @param AbstractPackage      $package Backup
     * @param array<string, mixed> $report  Scan report
     *
     * @return void
     */
    public static function addSiteSizes(AbstractPackage $package, $report): void
    {
        $minComponents = [
            BuildComponents::COMP_DB,
            BuildComponents::COMP_CORE,
            BuildComponents::COMP_PLUGINS,
            BuildComponents::COMP_THEMES,
            BuildComponents::COMP_UPLOADS,
            BuildComponents::COMP_OTHER,
        ];

        $componentes = array_intersect($minComponents, $package->components);
        if (array_diff($minComponents, $componentes) !== []) {
            return;
        }

        PluginData::getInstance()->setSiteSize(
            $report['ARC']['USize'],
            $report['ARC']['UFullCount'],
            $report['DB']['SizeInBytes'],
            $report['DB']['TableCount']
        );
    }

    /**
     * Is tracking allowed
     *
     * @return bool
     */
    public static function isTrackingAllowed()
    {
        if (DUPLICATOR_USTATS_DISALLOW) { // @phpstan-ignore-line
            return false;
        }

        return GlobalEntity::getInstance()->getUsageTracking();
    }

    /**
     * Send plugin statistics
     *
     * @return void
     */
    public static function sendPluginStatCron(): void
    {
        DupLog::trace("CRON: Sending plugin statistics");
        if (!self::isTrackingAllowed()) {
            DupLog::trace("CRON: Tracking not allowed");
            return;
        }

        CommStats::pluginSend();
    }
}
