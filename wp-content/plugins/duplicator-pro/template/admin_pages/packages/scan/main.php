<?php

/**
 * @package Duplicator
 */

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

use Duplicator\Controllers\PackagesPageController;
use Duplicator\Core\Controllers\ControllersManager;

$blur             = $tplData['blur'];
$package_list_url = ControllersManager::getMenuLink(ControllersManager::PACKAGES_SUBMENU_SLUG);
?>
<form 
    id="form-duplicator" 
    class="<?php echo ($blur ? 'dup-mock-blur' : ''); ?>  scan-result" 
    method="post" 
    action="<?php echo esc_attr($package_list_url); ?>"
>
    <?php PackagesPageController::getInstance()->getActionByKey(PackagesPageController::ACTION_CREATE_FROM_TEMP)->getActionNonceFileds(); ?>
    <div id="dup-progress-area">
        <!--  PROGRESS BAR -->
        <div class="dup-progress-bar-area">
            <div class="dupli-title" >
                <?php esc_html_e('Scanning Site', 'duplicator-pro'); ?>
            </div>
            <div class="dupli-meter-wrapper" >
                <div class="dupli-meter green dupli-fullsize">
                    <span></span>
                </div>
                <span class="text"></span>
            </div>
            <b><?php esc_html_e('Please Wait...', 'duplicator-pro'); ?></b><br/><br/>
            <i><?php esc_html_e('Keep this window open during the scan process.', 'duplicator-pro'); ?></i><br/>
            <i><?php esc_html_e('This can take several minutes.', 'duplicator-pro'); ?></i><br/>
        </div>

        <!--  SCAN DETAILS REPORT -->
        <div id="dup-msg-success" style="display:none">
            <div style="text-align:center">
                <div class="dup-hdr-success">
                    <i class="far fa-check-square fa-nr"></i> <?php esc_html_e('Scan Complete', 'duplicator-pro'); ?>
                </div>
                <div id="dup-msg-success-subtitle">
                    <?php esc_html_e("Process Time:", 'duplicator-pro'); ?> <span id="data-rpt-scantime"></span>
                </div>
            </div>
            <div class="details">
                <?php $tplMng->render('admin_pages/packages/scan/items/setup/main'); ?>
                <br/>
                <?php $tplMng->render('admin_pages/packages/scan/items/archive/main'); ?>
                <?php $tplMng->render('admin_pages/packages/scan/items/database/main'); ?>
            </div>
        </div>

        <!--  ERROR MESSAGE -->
        <div id="dup-msg-error" style="display:none">
            <div class="dup-hdr-error"><i class="fa fa-exclamation-circle"></i> <?php esc_html_e('Scan Error', 'duplicator-pro'); ?></div>
            <i><?php esc_html_e('Please try again!', 'duplicator-pro'); ?></i><br/>
            <div style="text-align:left">
                <b><?php esc_html_e("Server Status:", 'duplicator-pro'); ?></b> &nbsp;
                <div id="dup-msg-error-response-status" style="display:inline-block"></div><br/>
                <b><?php esc_html_e("Error Message:", 'duplicator-pro'); ?></b>
                <div id="dup-msg-error-response-text"></div>
            </div>
        </div>
    </div>

    <!-- WARNING CONTINUE -->
    <div id="dupli-scan-warning-continue">
        <div class="msg2">
            <?php esc_html_e("Scan checks are not required to pass, however they could cause issues on some systems.", 'duplicator-pro'); ?>
            <br/>
            <?php esc_html_e("Please review the details for each section by clicking on the detail title.", 'duplicator-pro'); ?>
        </div>
    </div>

    <div id="dupli-confirm-area">
        <?php esc_html_e('Do you want to continue?', 'duplicator-pro'); ?>
        <br/>
        <?php esc_html_e('At least one or more checkboxes were checked in "Quick Filters".', 'duplicator-pro') ?>
        <br/>
        <i style="font-weight:normal">
            <?php esc_html_e('To apply a "Quick Filter" click the "Add Filters & Rescan" button', 'duplicator-pro') ?>
        </i><br/>
        <input 
            type="checkbox" 
            id="dupli-confirm-check" 
            onclick="jQuery('#dup-build-button').removeAttr('disabled');"
            class="margin-bottom-0"
        >
        <?php esc_html_e('Yes. Continue without applying any file filters.', 'duplicator-pro') ?>
    </div>
    <div class="dup-button-footer" style="display:none">
        <input
            type="button"
            class="button hollow secondary small dup-go-back-to-new1"
            value="&#9664; <?php esc_html_e("Back", 'duplicator-pro') ?>"
        >
        <input 
            type="button" 
            class="button hollow secondary small"
            value="<?php esc_attr_e("Rescan", 'duplicator-pro') ?>" 
            onclick="DupliJs.Pack.reRunScanner()"
        >
        <input 
            type="button" 
            onclick="DupliJs.Pack.startBuild();" 
            class="button primary small" 
            id="dup-build-button" 
            value='<?php esc_attr_e("Create Backup", 'duplicator-pro') ?> &#9654'
        >
    </div>
</form>
<?php $tplMng->render('admin_pages/packages/scan/scripts'); ?>
