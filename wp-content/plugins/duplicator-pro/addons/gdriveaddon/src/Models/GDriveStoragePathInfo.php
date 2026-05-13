<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Addons\GDriveAddon\Models;

use Duplicator\Models\Storages\StoragePathInfo;
use VendorDuplicator\Google\Service\Drive\DriveFile;

class GDriveStoragePathInfo extends StoragePathInfo
{
    /** @var string */
    public $id = '';

    /** @var string */
    public $name = '';

    /** @var string */
    public $mimeType = '';

    /** @var string */
    public $webUrl = '';

    /** @var string */
    public $md5Checksum = '';

    /** @var DriveFile */
    public $file;
}
