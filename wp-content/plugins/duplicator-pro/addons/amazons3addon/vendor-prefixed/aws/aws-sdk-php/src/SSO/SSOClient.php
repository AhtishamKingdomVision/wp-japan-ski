<?php

namespace VendorDuplicator\Aws\SSO;

use VendorDuplicator\Aws\AwsClient;
/**
 * This client is used to interact with the **AWS Single Sign-On** service.
 * @method \VendorDuplicator\Aws\Result getRoleCredentials(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise getRoleCredentialsAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result listAccountRoles(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise listAccountRolesAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result listAccounts(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise listAccountsAsync(array $args = [])
 * @method \VendorDuplicator\Aws\Result logout(array $args = [])
 * @method \VendorDuplicator\GuzzleHttp\Promise\Promise logoutAsync(array $args = [])
 */
class SSOClient extends AwsClient
{
}
