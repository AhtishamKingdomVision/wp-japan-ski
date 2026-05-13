<?php

/**
 * @package Duplicator
 */

use Duplicator\Addons\ProBase\License\License;
use Duplicator\Core\Constants;
use Duplicator\Libs\Snap\SnapOpenBasedir;
use Duplicator\Libs\Snap\SnapServer;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Models\DynamicGlobalEntity;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

$webServers       = implode(', ', Constants::SERVER_LIST);
$serverSoftware   = SnapUtil::sanitizeTextInput(INPUT_SERVER, 'SERVER_SOFTWARE', 'unknown');
$fopenEnabled     = SnapServer::isURLFopenEnabled() ? '1' : '0';
$isCurlEnabled    = SnapUtil::isCurlEnabled() ? __('True', 'duplicator-pro') : __('False', 'duplicator-pro');
$openBaseDir      = SnapOpenBasedir::isEnabled() ? esc_html__('on', 'duplicator-pro') : esc_html__('off', 'duplicator-pro');
$maxExecutionTime = set_time_limit(0) === true ? 0 : @ini_get('max_execution_time');
$memoryLimit      = @ini_get('memory_limit');
$architecture     = SnapUtil::getArchitectureString();
?>

<div class="scan-item scan-item-first">
    <div class='title' onclick="DupliJs.Pack.toggleScanItem(this);">
        <div class="text"><i class="fa fa-caret-right"></i> <?php esc_html_e('System', 'duplicator-pro'); ?></div>
        <div id="data-srv-php-all"></div>
    </div>
    <div class="info">
        <div class="scan-system-divider"><i class="fa fa-list"></i>&nbsp; <?php esc_html_e('General Checks', 'duplicator-pro'); ?></div>
        <?php if (License::can(License::CAPABILITY_BRAND)) : ?>
            <span id="data-srv-brand-check"></span>&nbsp;
            <b><?php esc_html_e('Brand', 'duplicator-pro'); ?>: </b>
            <span id="data-srv-brand-name"><?php esc_html_e('Default', 'duplicator-pro'); ?></span>
            <br />
            <div class="scan-system-subnote" id="data-srv-brand-note">
                <?php esc_html_e('The default content used when a brand is not defined.', 'duplicator-pro'); ?>
            </div>
            <hr size="1" />
        <?php endif; ?>
        <span id="data-srv-php-websrv"></span>
        &nbsp;<b><?php esc_html_e('Web Server', 'duplicator-pro') ?>:</b>
        &nbsp; <?php echo esc_html($serverSoftware); ?><br />
        <div class="scan-system-subnote">
            <?php esc_html_e("Supported Web Servers:", 'duplicator-pro'); ?>&nbsp;<?php echo esc_html($webServers); ?>
        </div>
        <hr size="1" />
        <span id="data-srv-php-mysqli"></span>&nbsp;<b><?php esc_html_e('MySQLi', 'duplicator-pro'); ?></b><br />
        <div class="scan-system-subnote">
            <?php esc_html_e(
                'Creating the Backup does not require the mysqli module. However the installer file requires
                that the PHP module mysqli be installed on the server it is deployed on.',
                'duplicator-pro'
            ); ?>
            <i><a href="http://php.net/manual/en/mysqli.installation.php" target="_blank">[<?php esc_html_e('details', 'duplicator-pro'); ?>]</a></i>
        </div>
        <div class="scan-system-divider margin-top-1"><i class="fa fa-list"></i>&nbsp;<?php esc_html_e('PHP Checks', 'duplicator-pro'); ?></div>
        <span id="data-srv-php-version"></span>&nbsp;<b><?php esc_html_e('PHP Version: ', 'duplicator-pro'); ?> </b> <?php echo PHP_VERSION; ?> <br />
        <div class="scan-system-subnote">
            <?php
            echo esc_html(
                sprintf(
                    __(
                        'The minimum PHP version supported by Duplicator is %1$s, however it is highly 
                        recommended to use PHP %2$s or higher for improved stability.',
                        'duplicator-pro'
                    ),
                    DUPLICATOR_PRO_PHP_MINIMUM_VERSION,
                    DUPLICATOR_PRO_PHP_SUGGESTED_VERSION
                )
            ); ?>
        </div>
        <hr size="1" />
        <span id="data-srv-php-openbase"></span>&nbsp;
        <b><?php esc_html_e('PHP Open Base Dir', 'duplicator-pro'); ?>:</b>&nbsp;<?php echo esc_html($openBaseDir); ?>
        <br />
        <div class="scan-system-subnote">
            <?php esc_html_e(
                'When [open_basedir] is enabled, issues may arise if there are symbolic links pointing outside the open_basedir restrictions. 
                To view a list of unreadable files, please consult the Read Checks section. 
                If you encounter problems while building a Backup, 
                consider working with your server admin or hosting provider to disable this setting in the php.ini file.',
                'duplicator-pro'
            ); ?>
            &nbsp;
            <i>
                <a href="http://php.net/manual/en/ini.core.php#ini.open-basedir" target="_blank">[<?php esc_html_e('details', 'duplicator-pro'); ?>]</a>
            </i>
            <br />
        </div>

        <hr size="1" />
        <span id="data-srv-php-maxtime"></span>&nbsp;
        <b><?php esc_html_e('PHP Max Execution Time', 'duplicator-pro'); ?>:</b>&nbsp; <?php echo esc_html($maxExecutionTime); ?>
        <br />
        <div class="scan-system-subnote">
            <?php
            esc_html(
                sprintf(
                    __(
                        'Issues might occur for larger Backups when the [max_execution_time] value in the php.ini is too low. 
                        The minimum recommended timeout is "%1$s" seconds or higher. 
                        An attempt is made to override this value if the server allows it. A value of 0 (recommended) indicates that PHP has no time limits.',
                        'duplicator-pro'
                    ),
                    DUPLICATOR_SCAN_TIMEOUT
                )
            ); ?>
            &nbsp;
            <i>
                <a href="http://www.php.net/manual/en/info.configuration.php#ini.max-execution-time" target="_blank">
                    [<?php esc_html_e('details', 'duplicator-pro'); ?>]
                </a>
            </i>
        </div>

        <hr size="1" />
        <span id="data-srv-php-minmemory"></span>&nbsp;
        <b><?php esc_html_e('PHP Memory Limit', 'duplicator-pro'); ?>:</b>&nbsp; <?php echo esc_html($memoryLimit); ?>
        <br />
        <div class="scan-system-subnote">
            <?php
            echo wp_kses(
                sprintf(
                    _x(
                        'Issues might occur for larger Backups when the [memory_limit] value in the php.ini is too low.  
                    The minimum recommended memory limit is "%1$s" or higher. An attempt is made to override this value if the server allows it. 
                    To manually increase the memory limit have a look at this %2$s[FAQ item]%3$s',
                        '1: memory limit, 2: link start, 3: link end',
                        'duplicator-pro'
                    ),
                    DUPLICATOR_MIN_MEMORY_LIMIT,
                    "<i><a href='" . DUPLICATOR_DUPLICATOR_DOCS_URL . "how-to-manage-server-resources-cpu-memory-disk' target='_blank'>",
                    "</a></i>"
                ),
                [
                    'a' => [
                        'href'   => [],
                        'target' => [],
                    ],
                    'i' => [],
                ]
            ); ?>
        </div>

        <hr size="1" />
        <span id="data-srv-php-arch64bit"></span>&nbsp;
        <b><?php esc_html_e('PHP 64 Bit Architecture', 'duplicator-pro'); ?>:</b>&nbsp; <?php echo esc_html($architecture); ?><br />
        <div class="scan-system-subnote">
            <?php
            echo wp_kses(
                sprintf(
                    _x(
                        'Servers that run a PHP 32-bit architecture are not capable of creating Backups larger than 2GB.   
                    If you need to create a Backup that is larger than 2GB in size talk with your host or server admin 
                    to change your version of PHP to 64-bit. %1$s[FAQ item]%2$s',
                        '1: link start, 2: link end',
                        'duplicator-pro'
                    ),
                    "<i><a href='" . DUPLICATOR_DUPLICATOR_DOCS_URL . "how-to-resolve-file-io-related-build-issues' target='_blank'>",
                    "</a></i>"
                ),
                [
                    'a' => [
                        'href'   => [],
                        'target' => [],
                    ],
                    'i' => [],
                ]
            ); ?>
        </div>
        <br />
    </div>
</div>