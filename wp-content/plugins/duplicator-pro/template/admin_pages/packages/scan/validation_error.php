<?php

/**
 * @package Duplicator
 */

use Duplicator\Core\Controllers\ControllersManager;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */
?>

<form 
    id="form-duplicator scan-result" 
    method="post"
    action="<?php echo esc_attr(ControllersManager::getMenuLink(ControllersManager::PACKAGES_SUBMENU_SLUG)); ?>"
>
    <!--  ERROR MESSAGE -->
    <div id="dup-msg-error">
        <div class="dup-hdr-error"><i class="fa fa-exclamation-circle"></i> <?php esc_html_e('Input fields not valid', 'duplicator-pro'); ?></div>
        <i><?php esc_html_e('Please try again!', 'duplicator-pro'); ?></i><br/>
        <div style="text-align:left">
            <b><?php esc_html_e("Server Status:", 'duplicator-pro'); ?></b> &nbsp;
            <div id="dup-msg-error-response-status" style="display:inline-block"></div><br/>
            <b><?php esc_html_e("Error Message:", 'duplicator-pro'); ?></b>
            <div id="dup-msg-error-response-text">
                <ul>
                    <?php $tplData['validator']->getErrorsFormat("<li>%s</li>"); ?>
                </ul>
            </div>
        </div>
    </div>
    <input
        type="button"
        value="&#9664; <?php esc_html_e("Back", 'duplicator-pro') ?>"
        class="button hollow secondary dup-go-back-to-new1"
    >
</form>
