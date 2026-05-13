<?php

namespace Duplicator\Utils\UsageStatistics;

use Duplicator\Utils\Logging\DupLog;
use Duplicator\Libs\Snap\SnapLog;
use Error;
use Exception;
use WP_Error;

class CommStats
{
    const API_VERSION            = '1.0';
    const DEFAULT_REMOTE_HOST    = 'https://connect.duplicator.com';
    const END_POINT_PLUGIN_STATS = '/api/ustats/addProStats';
    const END_POINT_DISABLE      = '/api/ustats/disable';
    const END_POINT_INSTALLER    = '/api/ustats/installer';

    /**
     * Send plugin statistics
     *
     * @return bool true if data was sent successfully, false otherwise
     */
    public static function pluginSend(): bool
    {
        if (!StatsBootstrap::isTrackingAllowed()) {
            return false;
        }

        $data = PluginData::getInstance()->getDataToSend();

        if (self::request(self::END_POINT_PLUGIN_STATS, $data)) {
            PluginData::getInstance()->updateLastSendTime();
            return true;
        } else {
            return false;
        }
    }

    /**
     * Disabled usage tracking
     *
     * @return bool true if data was sent successfully, false otherwise
     */
    public static function disableUsageTracking()
    {
        if (DUPLICATOR_USTATS_DISALLOW) { // @phpstan-ignore-line
            // Don't use StatsBootstrap::isTrackingAllowed beacause on disalbe usage tracking i necessary disable the tracking on server
            return false;
        }

        // Remove usage tracking data on server
        $data = PluginData::getInstance()->getDisableDataToSend();
        return self::request(self::END_POINT_DISABLE, $data, 'Disable usage tracking error');
    }

    /**
     * Sent installer statistics
     *
     * @return bool true if data was sent successfully, false otherwise
     */
    public static function installerSend()
    {
        if (!StatsBootstrap::isTrackingAllowed()) {
            return false;
        }

        $data = InstallerData::getInstance()->getDataToSend();
        return self::request(self::END_POINT_INSTALLER, $data, 'Installer usage tracking error');
    }

    /**
     * Request to usage tracking server
     *
     * @param string               $endPoint            end point
     * @param array<string, mixed> $data                data to send
     * @param string               $traceMessagePerefix trace message prefix
     *
     * @return bool true if data was sent successfully, false otherwise
     */
    protected static function request($endPoint, $data, $traceMessagePerefix = 'Error sending usage tracking'): bool
    {
        try {
            $postParams = [
                'method'      => 'POST',
                'timeout'     => 10,
                'redirection' => 5,
                'sslverify'   => false,
                'httpversion' => '1.1',
                //'blocking'    => false,
                'user-agent'  => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
                'body'        => $data,
            ];

            $url      = self::getRemoteHost() . $endPoint . '/';
            $response = wp_remote_post($url, $postParams);

            if (is_wp_error($response)) {
                /** @var WP_Error $response */
                DupLog::trace('URL Request: ' . $url);
                DupLog::trace($traceMessagePerefix . ' code: ' . $response->get_error_code());
                DupLog::trace('Error message: ' . $response->get_error_message());
                return false;
            } elseif ($response['response']['code'] < 200 || $response['response']['code'] >= 300) {
                DupLog::trace('URL Request: ' . $url);
                DupLog::trace($traceMessagePerefix . ' code: ' . $response['response']['code']);
                DupLog::trace('Error message: ' . $response['response']['message']);
                DupLog::traceObject('Data', $data);
                return false;
            } else {
                DupLog::trace('Usage tracking updated successfully');
                return true;
            }
        } catch (Exception | Error $e) {
            DupLog::trace($traceMessagePerefix . '  trace msg: ' . $e->getMessage() . "\n" . SnapLog::getTextException($e, false));
            return false;
        }
    }

    /**
     * Get remote host
     *
     * @return string
     */
    public static function getRemoteHost()
    {
        if (DUPLICATOR_CUSTOM_STATS_REMOTE_HOST != '') {  // @phpstan-ignore-line
            return DUPLICATOR_CUSTOM_STATS_REMOTE_HOST;
        } else {
            return self::DEFAULT_REMOTE_HOST;
        }
    }
}
