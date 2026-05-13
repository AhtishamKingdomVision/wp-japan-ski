<?php

/**
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Installer\Package;

/**
 * The file descriptor is used to store the file meta data
 */
class DescriptorFileInfo
{
    /** @var int */
    public $dirCount = 0;
    /** @var int */
    public $fileCount = 0;
    /** @var int */
    public $size = 0;
}
