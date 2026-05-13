<?php

namespace VendorDuplicator\Aws\EndpointDiscovery\Exception;

use VendorDuplicator\Aws\HasMonitoringEventsTrait;
use VendorDuplicator\Aws\MonitoringEventsInterface;
/**
 * Represents an error interacting with configuration for endpoint discovery
 */
class ConfigurationException extends \RuntimeException implements MonitoringEventsInterface
{
    use HasMonitoringEventsTrait;
}
