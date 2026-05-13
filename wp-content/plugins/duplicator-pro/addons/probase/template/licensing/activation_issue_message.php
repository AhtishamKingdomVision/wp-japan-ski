<?php

/**
 * @package Duplicator
 */

use Duplicator\Addons\ProBase\License\License;
use Duplicator\Addons\ProBase\License\LicenseNotices;
use Duplicator\Addons\ProBase\Models\LicenseData;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

switch (LicenseData::getInstance()->getStatus()) {
    case LicenseData::STATUS_VALID:
    case LicenseData::STATUS_INACTIVE:
    case LicenseData::STATUS_SITE_INACTIVE:
    case LicenseData::STATUS_EXPIRED:
        return;
    default:
        break;
}

esc_html_e('If license activation fails please wait ~ one hour and retry.', 'duplicator-pro');
?>
<div class="dup-license-status-notes ">
    <?php
    printf(
        esc_html_x(
            '- Failure to activate after several attempts please review %1$sfaq activation steps%2$s.',
            '1 and 2 represent opening and closing anchor tags (<a> and </a>)',
            'duplicator-pro'
        ),
        '<a target="_blank" href="' . esc_url(DUPLICATOR_DUPLICATOR_DOCS_URL . 'how-to-resolve-license-activation-issues/') . '">',
        '</a>'
    );
    ?>
    <br/>
    <?php
    printf(
        esc_html_x(
            '- To upgrade or renew your license visit %1$sduplicator.com%2$s.',
            '1 and 2 represent opening and closing anchor tags (<a> and </a>)',
            'duplicator-pro'
        ),
        '<a target="_blank" href="' . esc_url(DUPLICATOR_BLOG_URL) . '">',
        '</a>'
    );
    ?>
    <br/>
    <?php esc_html_e('- A valid key is needed for plugin updates but not for functionality.', 'duplicator-pro'); ?>
</div>
