<?php

/**
 * Duplicator page header
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 */

?>
<div id="dup-ajax-loader" class="dup-styles" >
    <div id="dup-ajax-loader-img-wrapper" >
        <img 
            src="<?php echo esc_url(DUPLICATOR_PLUGIN_URL . 'assets/img/duplicator-logo-icon.svg'); ?>" 
            alt="<?php esc_html_e('wait ...', 'duplicator-pro'); ?>"
        >
    </div>
</div>