<?php

/**
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Installer\Package;

class DescriptorPackageInfo
{
    /** @var int */
    public $packageId = 0;
    /** @var string */
    public $packageName = '';
    /** @var string */
    public $packageHash = '';
    /** @var string */
    public $secondaryHash = '';
}
