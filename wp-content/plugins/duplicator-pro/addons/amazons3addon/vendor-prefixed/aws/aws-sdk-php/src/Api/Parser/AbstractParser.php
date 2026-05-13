<?php

namespace VendorDuplicator\Aws\Api\Parser;

use VendorDuplicator\Aws\Api\Service;
use VendorDuplicator\Aws\Api\StructureShape;
use VendorDuplicator\Aws\CommandInterface;
use VendorDuplicator\Aws\ResultInterface;
use VendorDuplicator\Psr\Http\Message\ResponseInterface;
use VendorDuplicator\Psr\Http\Message\StreamInterface;
/**
 * @internal
 */
abstract class AbstractParser
{
    /** @var \VendorDuplicator\Aws\Api\Service Representation of the service API*/
    protected $api;
    /** @var callable */
    protected $parser;
    /**
     * @param Service $api Service description.
     */
    public function __construct(Service $api)
    {
        $this->api = $api;
    }
    /**
     * @param CommandInterface  $command  Command that was executed.
     * @param ResponseInterface $response Response that was received.
     *
     * @return ResultInterface
     */
    abstract public function __invoke(CommandInterface $command, ResponseInterface $response);
    abstract public function parseMemberFromStream(StreamInterface $stream, StructureShape $member, $response);
}
