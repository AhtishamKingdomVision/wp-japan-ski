<?php

namespace VendorDuplicator\Aws\S3;

use VendorDuplicator\Aws\CacheInterface;
use VendorDuplicator\Aws\CommandInterface;
use VendorDuplicator\Aws\LruArrayCache;
use VendorDuplicator\Aws\MultiRegionClient as BaseClient;
use VendorDuplicator\Aws\Exception\AwsException;
use VendorDuplicator\Aws\S3\Exception\PermanentRedirectException;
use VendorDuplicator\GuzzleHttp\Promise;
/**
 * **Amazon Simple Storage Service** multi-region client.
 *
 * @method \VendorDuplicator\Aws\Result abortMultipartUpload(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise abortMultipartUploadAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result completeMultipartUpload(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise completeMultipartUploadAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result copyObject(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise copyObjectAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result createBucket(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise createBucketAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result createBucketMetadataTableConfiguration(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise createBucketMetadataTableConfigurationAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result createMultipartUpload(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise createMultipartUploadAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result createSession(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise createSessionAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result deleteBucket(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise deleteBucketAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result deleteBucketAnalyticsConfiguration(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise deleteBucketAnalyticsConfigurationAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result deleteBucketCors(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise deleteBucketCorsAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result deleteBucketEncryption(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise deleteBucketEncryptionAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result deleteBucketIntelligentTieringConfiguration(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise deleteBucketIntelligentTieringConfigurationAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result deleteBucketInventoryConfiguration(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise deleteBucketInventoryConfigurationAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result deleteBucketLifecycle(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise deleteBucketLifecycleAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result deleteBucketMetadataTableConfiguration(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise deleteBucketMetadataTableConfigurationAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result deleteBucketMetricsConfiguration(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise deleteBucketMetricsConfigurationAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result deleteBucketOwnershipControls(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise deleteBucketOwnershipControlsAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result deleteBucketPolicy(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise deleteBucketPolicyAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result deleteBucketReplication(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise deleteBucketReplicationAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result deleteBucketTagging(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise deleteBucketTaggingAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result deleteBucketWebsite(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise deleteBucketWebsiteAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result deleteObject(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise deleteObjectAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result deleteObjectTagging(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise deleteObjectTaggingAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result deleteObjects(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise deleteObjectsAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result deletePublicAccessBlock(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise deletePublicAccessBlockAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getBucketAccelerateConfiguration(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getBucketAccelerateConfigurationAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getBucketAcl(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getBucketAclAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getBucketAnalyticsConfiguration(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getBucketAnalyticsConfigurationAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getBucketCors(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getBucketCorsAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getBucketEncryption(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getBucketEncryptionAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getBucketIntelligentTieringConfiguration(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getBucketIntelligentTieringConfigurationAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getBucketInventoryConfiguration(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getBucketInventoryConfigurationAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getBucketLifecycle(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getBucketLifecycleAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getBucketLifecycleConfiguration(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getBucketLifecycleConfigurationAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getBucketLocation(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getBucketLocationAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getBucketLogging(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getBucketLoggingAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getBucketMetadataTableConfiguration(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getBucketMetadataTableConfigurationAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getBucketMetricsConfiguration(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getBucketMetricsConfigurationAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getBucketNotification(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getBucketNotificationAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getBucketNotificationConfiguration(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getBucketNotificationConfigurationAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getBucketOwnershipControls(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getBucketOwnershipControlsAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getBucketPolicy(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getBucketPolicyAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getBucketPolicyStatus(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getBucketPolicyStatusAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getBucketReplication(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getBucketReplicationAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getBucketRequestPayment(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getBucketRequestPaymentAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getBucketTagging(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getBucketTaggingAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getBucketVersioning(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getBucketVersioningAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getBucketWebsite(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getBucketWebsiteAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getObject(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getObjectAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getObjectAcl(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getObjectAclAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getObjectAttributes(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getObjectAttributesAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getObjectLegalHold(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getObjectLegalHoldAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getObjectLockConfiguration(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getObjectLockConfigurationAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getObjectRetention(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getObjectRetentionAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getObjectTagging(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getObjectTaggingAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getObjectTorrent(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getObjectTorrentAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result getPublicAccessBlock(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getPublicAccessBlockAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result headBucket(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise headBucketAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result headObject(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise headObjectAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result listBucketAnalyticsConfigurations(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise listBucketAnalyticsConfigurationsAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result listBucketIntelligentTieringConfigurations(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise listBucketIntelligentTieringConfigurationsAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result listBucketInventoryConfigurations(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise listBucketInventoryConfigurationsAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result listBucketMetricsConfigurations(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise listBucketMetricsConfigurationsAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result listBuckets(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise listBucketsAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result listDirectoryBuckets(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise listDirectoryBucketsAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result listMultipartUploads(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise listMultipartUploadsAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result listObjectVersions(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise listObjectVersionsAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result listObjects(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise listObjectsAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result listObjectsV2(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise listObjectsV2Async(array $args = [])
 * @method \VendorDuplicator\Aws\Result listParts(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise listPartsAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result putBucketAccelerateConfiguration(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise putBucketAccelerateConfigurationAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result putBucketAcl(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise putBucketAclAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result putBucketAnalyticsConfiguration(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise putBucketAnalyticsConfigurationAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result putBucketCors(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise putBucketCorsAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result putBucketEncryption(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise putBucketEncryptionAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result putBucketIntelligentTieringConfiguration(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise putBucketIntelligentTieringConfigurationAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result putBucketInventoryConfiguration(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise putBucketInventoryConfigurationAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result putBucketLifecycle(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise putBucketLifecycleAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result putBucketLifecycleConfiguration(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise putBucketLifecycleConfigurationAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result putBucketLogging(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise putBucketLoggingAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result putBucketMetricsConfiguration(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise putBucketMetricsConfigurationAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result putBucketNotification(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise putBucketNotificationAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result putBucketNotificationConfiguration(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise putBucketNotificationConfigurationAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result putBucketOwnershipControls(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise putBucketOwnershipControlsAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result putBucketPolicy(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise putBucketPolicyAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result putBucketReplication(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise putBucketReplicationAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result putBucketRequestPayment(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise putBucketRequestPaymentAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result putBucketTagging(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise putBucketTaggingAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result putBucketVersioning(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise putBucketVersioningAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result putBucketWebsite(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise putBucketWebsiteAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result putObject(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise putObjectAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result putObjectAcl(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise putObjectAclAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result putObjectLegalHold(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise putObjectLegalHoldAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result putObjectLockConfiguration(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise putObjectLockConfigurationAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result putObjectRetention(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise putObjectRetentionAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result putObjectTagging(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise putObjectTaggingAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result putPublicAccessBlock(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise putPublicAccessBlockAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result restoreObject(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise restoreObjectAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result selectObjectContent(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise selectObjectContentAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result uploadPart(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise uploadPartAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result uploadPartCopy(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise uploadPartCopyAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result writeGetObjectResponse(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise writeGetObjectResponseAsync(array $args = [])
 */
class S3MultiRegionClient extends BaseClient implements S3ClientInterface
{
    use S3ClientTrait;
    /** @var CacheInterface */
    private $cache;
    public static function getArguments()
    {
        $args = parent::getArguments();
        $regionDef = $args['region'] + ['default' => function (array &$args) {
            $availableRegions = array_keys($args['partition']['regions']);
            return end($availableRegions);
        }];
        unset($args['region']);
        return $args + ['bucket_region_cache' => ['type' => 'config', 'valid' => [CacheInterface::class], 'doc' => 'Cache of regions in which given buckets are located.', 'default' => function () {
            return new LruArrayCache();
        }], 'region' => $regionDef];
    }
    public function __construct(array $args)
    {
        parent::__construct($args);
        $this->cache = $this->getConfig('bucket_region_cache');
        $this->getHandlerList()->prependInit($this->determineRegionMiddleware(), 'determine_region');
    }
    private function determineRegionMiddleware()
    {
        return function (callable $handler) {
            return function (CommandInterface $command) use ($handler) {
                $cacheKey = $this->getCacheKey($command['Bucket']);
                if (empty($command['@region']) && $region = $this->cache->get($cacheKey)) {
                    $command['@region'] = $region;
                }
                return Promise\Coroutine::of(function () use ($handler, $command, $cacheKey) {
                    try {
                        yield $handler($command);
                    } catch (PermanentRedirectException $e) {
                        if (empty($command['Bucket'])) {
                            throw $e;
                        }
                        $result = $e->getResult();
                        $region = null;
                        if (isset($result['@metadata']['headers']['x-amz-bucket-region'])) {
                            $region = $result['@metadata']['headers']['x-amz-bucket-region'];
                            $this->cache->set($cacheKey, $region);
                        } else {
                            $region = yield $this->determineBucketRegionAsync($command['Bucket']);
                        }
                        $command['@region'] = $region;
                        yield $handler($command);
                    } catch (AwsException $e) {
                        if ($e->getAwsErrorCode() === 'AuthorizationHeaderMalformed') {
                            $region = $this->determineBucketRegionFromExceptionBody($e->getResponse());
                            if (!empty($region)) {
                                $this->cache->set($cacheKey, $region);
                                $command['@region'] = $region;
                                yield $handler($command);
                            } else {
                                throw $e;
                            }
                        } else {
                            throw $e;
                        }
                    }
                });
            };
        };
    }
    public function createPresignedRequest(CommandInterface $command, $expires, array $options = [])
    {
        if (empty($command['Bucket'])) {
            throw new \InvalidArgumentException('The S3\MultiRegionClient' . ' cannot create presigned requests for commands without a' . ' specified bucket.');
        }
        /** @var S3ClientInterface $client */
        $client = $this->getClientFromPool($this->determineBucketRegion($command['Bucket']));
        return $client->createPresignedRequest($client->getCommand($command->getName(), $command->toArray()), $expires, $options);
    }
    public function getObjectUrl($bucket, $key)
    {
        /** @var S3Client $regionalClient */
        $regionalClient = $this->getClientFromPool($this->determineBucketRegion($bucket));
        return $regionalClient->getObjectUrl($bucket, $key);
    }
    public function determineBucketRegionAsync($bucketName)
    {
        $cacheKey = $this->getCacheKey($bucketName);
        if ($cached = $this->cache->get($cacheKey)) {
            return Promise\Create::promiseFor($cached);
        }
        /** @var S3ClientInterface $regionalClient */
        $regionalClient = $this->getClientFromPool();
        return $regionalClient->determineBucketRegionAsync($bucketName)->then(function ($region) use ($cacheKey) {
            $this->cache->set($cacheKey, $region);
            return $region;
        });
    }
    private function getCacheKey($bucketName)
    {
        return "aws:s3:{$bucketName}:location";
    }
}
