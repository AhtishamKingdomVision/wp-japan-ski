<?php

namespace VendorDuplicator\Aws\Exception;

use VendorDuplicator\Aws\HasMonitoringEventsTrait;
use VendorDuplicator\Aws\MonitoringEventsInterface;
class UnresolvedSignatureException extends \RuntimeException implements MonitoringEventsInterface
{
    use HasMonitoringEventsTrait;
}
