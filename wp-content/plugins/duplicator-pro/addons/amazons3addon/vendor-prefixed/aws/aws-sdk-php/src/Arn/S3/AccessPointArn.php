<?php

namespace VendorDuplicator\Aws\Arn\S3;

use VendorDuplicator\Aws\Arn\AccessPointArn as BaseAccessPointArn;
use VendorDuplicator\Aws\Arn\AccessPointArnInterface;
use VendorDuplicator\Aws\Arn\ArnInterface;
use VendorDuplicator\Aws\Arn\Exception\InvalidArnException;
/**
 * @internal
 */
class AccessPointArn extends BaseAccessPointArn implements AccessPointArnInterface
{
    /**
     * Validation specific to AccessPointArn
     *
     * @param array $data
     */
    public static function validate(array $data)
    {
        parent::validate($data);
        if ($data['service'] !== 's3') {
            throw new InvalidArnException("The 3rd component of an S3 access" . " point ARN represents the region and must be 's3'.");
        }
    }
}
