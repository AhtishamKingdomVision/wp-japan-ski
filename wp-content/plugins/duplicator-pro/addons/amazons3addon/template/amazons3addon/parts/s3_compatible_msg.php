<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Addons\AmazonS3Addon\Models\AmazonS3CompatibleStorage;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var AbstractStorageEntity $storage
 */
$storage = $tplData["storage"];
?>

<p> 
    <?php
    esc_html_e(
        'The Amazon S3 compatible storage option allows you to connect to any object storage that is compatible with the S3 API.',
        'duplicator-pro'
    );
    ?>
</p>
<p>
    <?php
    printf(
        esc_attr_x(
            'Examples of compatible providers are %s.',
            '%s is a comma seperated list of providers',
            'duplicator-pro'
        ),
        '<b>' . esc_html(implode(', ', AmazonS3CompatibleStorage::getCompatibleProviders())) . '</b>'
    );
    ?>
</p>
