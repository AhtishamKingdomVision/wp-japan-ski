<?php

/**
 * Template for Duplicator Cloud Connect Step 1
 *
 * @package   Duplicator\Addons\DupCloudAddon
 * @copyright (c) 2024, Snap Creek LLC
 */

use Duplicator\Addons\DupCloudAddon\Utils\DupCloudClient;

defined('ABSPATH') || exit;


/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 */

$tokens = $tplMng->getDataValueArrayRequired('tokens');

switch (count($tokens)) {
    case 0:
        ?>
        <div class="dup-box">
            <div class="dup-box-title">
                <i class="fa fa-cloud-off"></i>&nbsp;
                <?php esc_html_e('No Storage Available', 'duplicator-pro'); ?>
            </div>
            <div class="dup-box-panel" >
                <p>
                    <?php esc_html_e(
                        'No Duplicator Cloud storage is available for your license. Purchase cloud storage to use this feature.',
                        'duplicator-pro'
                    ); ?>
                </p>
                <a 
                    href="<?php echo esc_url(DupCloudClient::getManageLicenseStorageUrl()); ?>" 
                    class="button button-primary" 
                    target="_blank"
                >
                    <i class="fa fa-shopping-cart"></i> <?php esc_html_e('Purchase Storage', 'duplicator-pro'); ?>
                </a>
            </div> 
        </div>
        <?php
        break;
    case 1:
        // 1 token found, auto connect - no HTML needed, handled by JS
        break;
    default:
        ?>
        <div class="dup-box">
            <div class="dup-box-title">
                <?php esc_html_e('Select Storage', 'duplicator-pro'); ?>
            </div>
            <div class="dup-box-panel" >
                <p>
                    <?php esc_html_e(
                        'Multiple storage options are available for your license. Please select one:',
                        'duplicator-pro'
                    ); ?>
                </p>
                <div id="storage-selection-list">
                    <?php foreach ($tokens as $index => $token) : ?>
                        <?php
                        $maskedLicense = substr($token['license_key'], 0, 4) . '***';
                        $expiration    = $token['is_lifetime'] ?
                            __('Lifetime', 'duplicator-pro') :
                            date_i18n(get_option('date_format'), strtotime($token['expiration']));
                        ?>
                        <div class="dup-storage-item margin-bottom-1" >
                            <strong><?php echo esc_html($token['price_name']); ?></strong>&nbsp;|&nbsp;
                            <?php esc_html_e('License:', 'duplicator-pro'); ?> <?php echo esc_html($maskedLicense); ?>&nbsp;|&nbsp;
                            <?php esc_html_e('Expires:', 'duplicator-pro'); ?> <?php echo esc_html($expiration); ?>&nbsp;&nbsp;
                            <button type="button" class="button button-primary small margin-bottom-0" data-token-select="<?php echo esc_attr($index); ?>">
                                <?php esc_html_e('Use This Storage', 'duplicator-pro'); ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div> 
        </div>
        <?php
        break;
}

