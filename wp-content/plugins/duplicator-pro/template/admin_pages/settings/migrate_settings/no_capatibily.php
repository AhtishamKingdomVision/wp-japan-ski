<?php

/**
 * @package Duplicator
 */

defined("ABSPATH") or die("");

use Duplicator\Addons\ProBase\License\License;
use Duplicator\Views\ViewHelper;

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */
?>
<div class="width-large" >
    <p>
        <?php esc_html_e(
            "The migrate settings screen allows you to import or export Duplicator Pro settings from one site to another.",
            'duplicator-pro'
        ); ?>
    </p>
    <p>
        <?php esc_html_e(
            "For example, if you have several storage locations that you use on multiple WordPress sites such as Google Drive or 
            Dropbox and you simply want to copy the profiles from this instance of Duplicator Pro to another instance then simply 
            export the data here and import it on the other instance of Duplicator Pro.",
            'duplicator-pro'
        ); ?>
    </p>
        <p>
        <?php
        echo wp_kses(
            sprintf(
                __(
                    'This option isn\'t available at the <b>%1$s</b> license level.',
                    'duplicator-pro'
                ),
                esc_html(License::getLicenseToString())
            ),
            ViewHelper::GEN_KSES_TAGS
        );
        ?>
        <b>
        <?php
        echo wp_kses(
            sprintf(
                _x(
                    'To enable this option %1$supgrade%2$s the License.',
                    '%1$s and %2$s represents the opening and closing HTML tags for an anchor or link',
                    'duplicator-pro'
                ),
                '<a href="' . esc_url(License::getUpsellURL()) . '" target="_blank">',
                '</a>'
            ),
            ViewHelper::GEN_KSES_TAGS
        );
        ?>
        </b>
    </p>
</div>