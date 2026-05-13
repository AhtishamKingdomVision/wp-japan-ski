<?php

namespace VendorDuplicator\Aws\S3\RegionalEndpoint\Exception;

use VendorDuplicator\Aws\HasMonitoringEventsTrait;
use VendorDuplicator\Aws\MonitoringEventsInterface;
/**
 * Represents an error interacting with configuration for sts regional endpoints
 */
class ConfigurationException extends \RuntimeException implements MonitoringEventsInterface
{
    use HasMonitoringEventsTrait;
}
