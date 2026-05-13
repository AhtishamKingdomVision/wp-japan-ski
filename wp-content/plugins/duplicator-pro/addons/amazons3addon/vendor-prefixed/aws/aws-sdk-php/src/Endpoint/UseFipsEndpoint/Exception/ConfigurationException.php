<?php

namespace VendorDuplicator\Aws\Endpoint\UseFipsEndpoint\Exception;

use VendorDuplicator\Aws\HasMonitoringEventsTrait;
use VendorDuplicator\Aws\MonitoringEventsInterface;
/**
 * Represents an error interacting with configuration for useFipsRegion
 */
class ConfigurationException extends \RuntimeException implements MonitoringEventsInterface
{
    use HasMonitoringEventsTrait;
}
