<?php

namespace VendorDuplicator\Aws\Arn\S3;

use VendorDuplicator\Aws\Arn\ArnInterface;
/**
 * @internal
 */
interface BucketArnInterface extends ArnInterface
{
    public function getBucketName();
}
