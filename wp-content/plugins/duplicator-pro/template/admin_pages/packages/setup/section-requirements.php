<?php

/**
 * @package Duplicator
 */

defined("ABSPATH") or die("");

use Duplicator\Controllers\ToolsPageController;
use Duplicator\Core\MigrationMng;
use Duplicator\Libs\Snap\FunctionalityCheck;
use Duplicator\Libs\WpUtils\WpArchiveUtils;
use Duplicator\Libs\WpUtils\WpDbUtils;
use Duplicator\Package\BuildRequirements;

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string,mixed> $tplData
 * @var array<string,mixed> $requirements
 */
$requirements = $tplData['requirements'];

if ($requirements['Success']) {
    return;
}
?>
<div class="dup-box dup-requirements-wrapper">
    <div class="dup-box-title">
        <i class="far fa-check-circle"></i>
        <?php esc_html_e("Requirements:", 'duplicator-pro'); ?> <div class="dup-sys-fail">Fail</div>
        <button class="dup-box-arrow">
            <span class="screen-reader-text">
                <?php esc_html_e('Toggle panel:', 'duplicator-pro') ?> <?php esc_html_e('Requirements:', 'duplicator-pro') ?>
            </span>
        </button>
    </div>
    <div class="dup-box-panel">
        <div class="dup-sys-section">
            <i><?php esc_html_e("System requirements must pass for the Duplicator to work properly.  Click each link for details.", 'duplicator-pro'); ?></i>
        </div>

        <!-- PHP SUPPORT -->
        <div class='dup-sys-req'>
            <div class='dup-sys-title'>
                <a><?php esc_html_e('PHP Support', 'duplicator-pro'); ?></a>
                <div><?php echo esc_html($requirements['PHP']['ALL']); ?></div>
            </div>
            <div class="dup-sys-info dup-info-box">
                <table class="dup-sys-info-results">
                    <tr>
                        <td>
                            <?php esc_html_e('PHP Version', 'duplicator-pro'); ?> [<?php echo esc_html(phpversion()); ?>]
                        </td>
                        <td><?php echo esc_html($requirements['PHP']['VERSION']); ?></td>
                    </tr>
                    <?php foreach (BuildRequirements::getFunctionalitiesCheckList() as $func) { ?>
                        <tr>
                            <td>
                                <?php
                                switch ($func->getType()) {
                                    case FunctionalityCheck::TYPE_FUNCTION:
                                        esc_html_e('Function', 'duplicator-pro');
                                        break;
                                    case FunctionalityCheck::TYPE_CLASS:
                                        esc_html_e('Class', 'duplicator-pro');
                                        break;
                                    default:
                                        throw new Exception('Invalid item type');
                                }
                                ?>
                                <a href="<?php echo esc_url($func->link); ?>" target="_blank">
                                    <?php echo esc_html($func->getItemKey()); ?>
                                </a>
                            </td>
                            <td>
                                <?php
                                if ($func->check()) {
                                    echo esc_html_e('Pass', 'duplicator-pro');
                                } elseif ($func->isRequired()) {
                                    echo esc_html_e('Fail', 'duplicator-pro');
                                } else {
                                    echo esc_html_e('Warning', 'duplicator-pro');
                                }
                                if (strlen($func->troubleshoot) > 0) {
                                    echo ' &nbsp; ';
                                    echo wp_kses(
                                        $func->troubleshoot,
                                        [
                                            'a' => [
                                                'href'   => [],
                                                'target' => [],
                                            ],
                                            'i' => [
                                                'class' => [],
                                                'style' => [],
                                            ],
                                        ]
                                    );
                                }
                                ?>
                            </td>
                        </tr>
                    <?php } ?>
                </table>
                <small>
                    <?php
                    printf(
                        esc_html__(
                            "PHP versions %s+ including the listed functions are required for the plugin to create a Backup. 
                            For additional information see our online technical FAQs.",
                            'duplicator-pro'
                        ),
                        esc_html(DUPLICATOR_PRO_PHP_MINIMUM_VERSION)
                    );
                    ?>
                </small>
            </div>
        </div>

        <!-- PERMISSIONS -->
        <div class='dup-sys-req'>
            <div class='dup-sys-title'>
                <a><?php esc_html_e('Permissions', 'duplicator-pro'); ?></a>
                <div><?php echo esc_html($requirements['IO']['ALL']); ?></div>
            </div>
            <div class="dup-sys-info dup-info-box">
                <b><?php esc_html_e("Required Paths", 'duplicator-pro'); ?></b>
                <div style="padding:3px 0px 0px 15px">
                    <?php
                    printf("<b>%s</b> &nbsp; [%s] <br/>", esc_html($requirements['IO']['WPROOT']), esc_html(WpArchiveUtils::getArchiveListPaths('home')));
                    printf("<b>%s</b> &nbsp; [%s] <br/>", esc_html($requirements['IO']['SSDIR']), esc_html(DUPLICATOR_SSDIR_PATH));
                    printf("<b>%s</b> &nbsp; [%s] <br/>", esc_html($requirements['IO']['SSTMP']), esc_html(DUPLICATOR_SSDIR_PATH_TMP));
                    ?>
                </div>

                <small>
                    <?php
                    esc_html_e(
                        "Permissions can be difficult to resolve on some systems. If the plugin can not read the above paths here 
                    are a few things to try. 1) Set the above paths to have permissions of 755 for directories and 644 for files. 
                    You can temporarily try 777 however, be sure you don’t leave them this way. 
                    2) Check the owner/group settings for both files and directories. 
                    The PHP script owner and the process owner are different. The script owner owns the PHP script but the process owner 
                    is the user the script is running as, thus determining its capabilities/privileges in the file system. 
                    For more details contact your host or server administrator or visit the 'Help' menu under Duplicator for additional online resources.",
                        'duplicator-pro'
                    );
                    ?>
                </small>
            </div>
        </div>

        <!-- SERVER SUPPORT -->
        <div class='dup-sys-req'>
            <div class='dup-sys-title'>
                <a><?php esc_html_e('Server Support', 'duplicator-pro'); ?></a>
                <div><?php echo esc_html($requirements['SRV']['ALL']); ?></div>
            </div>
            <div class="dup-sys-info dup-info-box">
                <table class="dup-sys-info-results">
                    <tr>
                        <td><?php printf("%s [%s]", esc_html__("MySQL Version", 'duplicator-pro'), esc_html(WpDbUtils::getVersion())); ?></td>
                        <td><?php echo esc_html($requirements['SRV']['MYSQL_VER']); ?></td>
                    </tr>
                </table>
                <small>
                    <?php esc_html_e(
                        "MySQL version 5.0+ or better is required.  Contact your server administrator and request MySQL Server 5.0+ be installed.",
                        'duplicator-pro'
                    ); ?>
                </small>
                <hr>
                <table class="dup-sys-info-results">
                    <tr>
                        <td><a href="https://www.php.net/manual/en/mysqli.real-escape-string.php" target="_blank">mysqli_real_escape_string</a></td>
                        <td><?php echo esc_html($requirements['SRV']['MYSQL_ESC']); ?></td>
                    </tr>
                </table>
                <small>
                    <?php esc_html_e(
                        "The function mysqli_real_escape_string is not working properly. 
                        Please consult host support and ask them to switch to a different PHP version or configuration.",
                        'duplicator-pro'
                    ); ?>
                </small>
            </div>
        </div>

        <!-- INSTALLATION FILES -->
        <div class='dup-sys-req'>
            <div class='dup-sys-title'>
                <a><?php esc_html_e('Installation Files', 'duplicator-pro'); ?></a>
                <div><?php echo esc_html($requirements['RES']['INSTALL']); ?></div>
            </div>
            <div class="dup-sys-info dup-info-box">
                <?php
                if ($requirements['RES']['INSTALL'] == 'Pass') :
                    esc_html_e("No reserved installation files were found from a previous install. You are clear to create a new Backup.", 'duplicator-pro');
                else :
                    ?>
                    <form method="post" action="<?php echo esc_url(ToolsPageController::getInstance()->getCleanFilesAcrtionUrl()); ?>">
                        <?php
                        esc_html_e(
                            "An installer file(s) was found in the WordPress root directory.
                            To archive your data correctly please remove any of these files and try creating your Backup again.",
                            'duplicator-pro'
                        );
                        ?><br />
                        <b><?php esc_html_e('Installer file names include', 'duplicator-pro'); ?></b>
                        <ul>
                            <?php foreach (MigrationMng::checkInstallerFilesList() as $filePath) { ?>
                                <li>
                                    <?php echo esc_html($filePath); ?>
                                </li>
                            <?php } ?>
                        </ul>
                        <input
                            type='submit'
                            class='button action'
                            value='<?php esc_attr_e('Remove Files Now', 'duplicator-pro') ?>'
                            style='font-size:10px; margin-top:5px;'>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- ONLINE SUPPORT -->
        <div class="dup-sys-contact">
            <?php
            printf(
                "<i class='fa fa-question-circle'></i> %s <a href='" . esc_attr(DUPLICATOR_TECH_FAQ_URL) . "' target='_blank'>[%s]</a>",
                esc_html__("For additional help please see the ", 'duplicator-pro'),
                esc_html__("online FAQs", 'duplicator-pro')
            );
            ?>
        </div>

    </div>
</div>
<script>
    //INIT
    jQuery(document).ready(function($) {
        DupliJs.Pack.ToggleSystemDetails = function(anchor) {
            $(anchor).parent().siblings('.dup-sys-info').toggle();
        }

        //Init: Toogle for system requirment detial links
        $('.dup-sys-title a').each(function() {
            $(this).attr('href', 'javascript:void(0)');
            $(this).click(function() {
                DupliJs.Pack.ToggleSystemDetails(this);
            });
            $(this).prepend("<span class='ui-icon ui-icon-triangle-1-e dup-toggle' />");
        });

        //Init: Color code Pass/Fail/Warn items
        $('.dup-sys-title div').each(function() {
            $(this).addClass(($(this).text() == 'Pass') ? 'dup-sys-pass' : 'dup-sys-fail');
        });

    });
</script>