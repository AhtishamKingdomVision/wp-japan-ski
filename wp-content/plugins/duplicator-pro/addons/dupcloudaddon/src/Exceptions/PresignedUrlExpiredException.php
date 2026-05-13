<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

declare(strict_types=1);

namespace Duplicator\Addons\DupCloudAddon\Exceptions;

use Exception;

/**
 * Exception thrown when a presigned URL has expired
 */
class PresignedUrlExpiredException extends Exception
{
}
