<?php

namespace VendorDuplicator\Aws\Arn\S3;

use VendorDuplicator\Aws\Arn\ArnInterface;
/**
 * @internal
 */
interface OutpostsArnInterface extends ArnInterface
{
    public function getOutpostId();
}
