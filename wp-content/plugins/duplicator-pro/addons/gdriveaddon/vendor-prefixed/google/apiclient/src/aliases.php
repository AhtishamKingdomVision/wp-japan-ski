<?php

namespace VendorDuplicator;

if (\class_exists('VendorDuplicator\Google_Client', \false)) {
    // Prevent error with preloading in PHP 7.4
    // @see https://github.com/googleapis/google-api-php-client/issues/1976
    return;
}
$classMap = ['VendorDuplicator\Google\Client' => 'VendorDuplicator\\Google_Client', 'VendorDuplicator\Google\Service' => 'VendorDuplicator\\Google_Service', 'VendorDuplicator\Google\AccessToken\Revoke' => 'VendorDuplicator\\Google_AccessToken_Revoke', 'VendorDuplicator\Google\AccessToken\Verify' => 'VendorDuplicator\\Google_AccessToken_Verify', 'VendorDuplicator\Google\Model' => 'VendorDuplicator\\Google_Model', 'VendorDuplicator\Google\Utils\UriTemplate' => 'VendorDuplicator\\Google_Utils_UriTemplate', 'VendorDuplicator\Google\AuthHandler\Guzzle6AuthHandler' => 'VendorDuplicator\\Google_AuthHandler_Guzzle6AuthHandler', 'VendorDuplicator\Google\AuthHandler\Guzzle7AuthHandler' => 'VendorDuplicator\\Google_AuthHandler_Guzzle7AuthHandler', 'VendorDuplicator\Google\AuthHandler\AuthHandlerFactory' => 'VendorDuplicator\\Google_AuthHandler_AuthHandlerFactory', 'VendorDuplicator\Google\Http\Batch' => 'VendorDuplicator\\Google_Http_Batch', 'VendorDuplicator\Google\Http\MediaFileUpload' => 'VendorDuplicator\\Google_Http_MediaFileUpload', 'VendorDuplicator\Google\Http\REST' => 'VendorDuplicator\\Google_Http_REST', 'VendorDuplicator\Google\Task\Retryable' => 'VendorDuplicator\\Google_Task_Retryable', 'VendorDuplicator\Google\Task\Exception' => 'VendorDuplicator\\Google_Task_Exception', 'VendorDuplicator\Google\Task\Runner' => 'VendorDuplicator\\Google_Task_Runner', 'VendorDuplicator\Google\Collection' => 'VendorDuplicator\\Google_Collection', 'VendorDuplicator\Google\Service\Exception' => 'VendorDuplicator\\Google_Service_Exception', 'VendorDuplicator\Google\Service\Resource' => 'VendorDuplicator\\Google_Service_Resource', 'VendorDuplicator\Google\Exception' => 'VendorDuplicator\\Google_Exception'];
foreach ($classMap as $class => $alias) {
    \class_alias($class, $alias);
}
/**
 * This class needs to be defined explicitly as scripts must be recognized by
 * the autoloader.
 */
class Google_Task_Composer extends \VendorDuplicator\Google\Task\Composer
{
}
/**
 * This class needs to be defined explicitly as scripts must be recognized by
 * the autoloader.
 */
\class_alias('VendorDuplicator\Google_Task_Composer', 'VendorDuplicator\\Google_Task_Composer', \false);
/** @phpstan-ignore-next-line */
if (\false) {
    class Google_AccessToken_Revoke extends \VendorDuplicator\Google\AccessToken\Revoke
    {
    }
    \class_alias('VendorDuplicator\Google_AccessToken_Revoke', 'VendorDuplicator\\Google_AccessToken_Revoke', \false);
    class Google_AccessToken_Verify extends \VendorDuplicator\Google\AccessToken\Verify
    {
    }
    \class_alias('VendorDuplicator\Google_AccessToken_Verify', 'VendorDuplicator\\Google_AccessToken_Verify', \false);
    class Google_AuthHandler_AuthHandlerFactory extends \VendorDuplicator\Google\AuthHandler\AuthHandlerFactory
    {
    }
    \class_alias('VendorDuplicator\Google_AuthHandler_AuthHandlerFactory', 'VendorDuplicator\\Google_AuthHandler_AuthHandlerFactory', \false);
    class Google_AuthHandler_Guzzle6AuthHandler extends \VendorDuplicator\Google\AuthHandler\Guzzle6AuthHandler
    {
    }
    \class_alias('VendorDuplicator\Google_AuthHandler_Guzzle6AuthHandler', 'VendorDuplicator\\Google_AuthHandler_Guzzle6AuthHandler', \false);
    class Google_AuthHandler_Guzzle7AuthHandler extends \VendorDuplicator\Google\AuthHandler\Guzzle7AuthHandler
    {
    }
    \class_alias('VendorDuplicator\Google_AuthHandler_Guzzle7AuthHandler', 'VendorDuplicator\\Google_AuthHandler_Guzzle7AuthHandler', \false);
    class Google_Client extends \VendorDuplicator\Google\Client
    {
    }
    \class_alias('VendorDuplicator\Google_Client', 'VendorDuplicator\\Google_Client', \false);
    class Google_Collection extends \VendorDuplicator\Google\Collection
    {
    }
    \class_alias('VendorDuplicator\Google_Collection', 'VendorDuplicator\\Google_Collection', \false);
    class Google_Exception extends \VendorDuplicator\Google\Exception
    {
    }
    \class_alias('VendorDuplicator\Google_Exception', 'VendorDuplicator\\Google_Exception', \false);
    class Google_Http_Batch extends \VendorDuplicator\Google\Http\Batch
    {
    }
    \class_alias('VendorDuplicator\Google_Http_Batch', 'VendorDuplicator\\Google_Http_Batch', \false);
    class Google_Http_MediaFileUpload extends \VendorDuplicator\Google\Http\MediaFileUpload
    {
    }
    \class_alias('VendorDuplicator\Google_Http_MediaFileUpload', 'VendorDuplicator\\Google_Http_MediaFileUpload', \false);
    class Google_Http_REST extends \VendorDuplicator\Google\Http\REST
    {
    }
    \class_alias('VendorDuplicator\Google_Http_REST', 'VendorDuplicator\\Google_Http_REST', \false);
    class Google_Model extends \VendorDuplicator\Google\Model
    {
    }
    \class_alias('VendorDuplicator\Google_Model', 'VendorDuplicator\\Google_Model', \false);
    class Google_Service extends \VendorDuplicator\Google\Service
    {
    }
    \class_alias('VendorDuplicator\Google_Service', 'VendorDuplicator\\Google_Service', \false);
    class Google_Service_Exception extends \VendorDuplicator\Google\Service\Exception
    {
    }
    \class_alias('VendorDuplicator\Google_Service_Exception', 'VendorDuplicator\\Google_Service_Exception', \false);
    class Google_Service_Resource extends \VendorDuplicator\Google\Service\Resource
    {
    }
    \class_alias('VendorDuplicator\Google_Service_Resource', 'VendorDuplicator\\Google_Service_Resource', \false);
    class Google_Task_Exception extends \VendorDuplicator\Google\Task\Exception
    {
    }
    \class_alias('VendorDuplicator\Google_Task_Exception', 'VendorDuplicator\\Google_Task_Exception', \false);
    interface Google_Task_Retryable extends \VendorDuplicator\Google\Task\Retryable
    {
    }
    \class_alias('VendorDuplicator\Google_Task_Retryable', 'VendorDuplicator\\Google_Task_Retryable', \false);
    class Google_Task_Runner extends \VendorDuplicator\Google\Task\Runner
    {
    }
    \class_alias('VendorDuplicator\Google_Task_Runner', 'VendorDuplicator\\Google_Task_Runner', \false);
    class Google_Utils_UriTemplate extends \VendorDuplicator\Google\Utils\UriTemplate
    {
    }
    \class_alias('VendorDuplicator\Google_Utils_UriTemplate', 'VendorDuplicator\\Google_Utils_UriTemplate', \false);
}
