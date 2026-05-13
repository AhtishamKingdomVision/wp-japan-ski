<?php

namespace VendorDuplicator\Aws\ClientSideMonitoring;

use VendorDuplicator\Aws\CommandInterface;
use VendorDuplicator\Aws\Exception\AwsException;
use VendorDuplicator\Aws\ResultInterface;
use VendorDuplicator\GuzzleHttp\Psr7\Request;
use VendorDuplicator\Psr\Http\Message\RequestInterface;
/**
 * @internal
 */
interface MonitoringMiddlewareInterface
{
    /**
     * Data for event properties to be sent to the monitoring agent.
     *
     * @param RequestInterface $request
     * @return array
     */
    public static function getRequestData(RequestInterface $request);
    /**
     * Data for event properties to be sent to the monitoring agent.
     *
     * @param ResultInterface|AwsException|\Exception $klass
     * @return array
     */
    public static function getResponseData($klass);
    public function __invoke(CommandInterface $cmd, RequestInterface $request);
}
