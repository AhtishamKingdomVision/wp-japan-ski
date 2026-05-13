<?php

namespace Duplicator\Addons\OneDriveAddon;

use Duplicator\Models\Storages\StoragePathInfo;

class OneDriveStoragePathInfo extends StoragePathInfo
{
    /** @var string */
    public $id = '';

    /** @var string */
    public $name = '';

    /** @var string */
    public $webUrl = '';

    /**
     * @var ?array{mimeType: string, hashes: array{sha1Hash: string, quickXorHash: string, sha256Hash: string}}
     */
    public $file;

    /**
     * @var array{id: string, displayName: string}|null
     */
    public $user;
}
