<?php

namespace VendorDuplicator\Aws\Endpoint\UseDualstackEndpoint\Exception;

use VendorDuplicator\Aws\HasMonitoringEventsTrait;
use VendorDuplicator\Aws\MonitoringEventsInterface;
/**
 * Represents an error interacting with configuration for useDualstackRegion
 */
class ConfigurationException extends \RuntimeException implements MonitoringEventsInterface
{
    use HasMonitoringEventsTrait;
}
