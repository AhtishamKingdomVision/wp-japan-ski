<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

defined("ABSPATH") or die("");

use Duplicator\Addons\ProBase\License\License;

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */
?>
<div class="scan-item">
    <div class='title' onclick="DupliJs.Pack.toggleScanItem(this);">
        <div class="text"><i class="fa fa-caret-right"></i> <?php esc_html_e('WordPress', 'duplicator-pro'); ?></div>
        <div id="data-srv-wp-all"></div>
    </div>
    <div class="info">
        <span id="data-srv-wp-version"></span>&nbsp;
        <b><?php esc_html_e('WordPress Version', 'duplicator-pro'); ?>:</b>&nbsp;<?php echo esc_html(get_bloginfo('version')); ?> <br />
        <hr size="1" /><span id="data-srv-wp-core"></span>&nbsp;<b> <?php esc_html_e('Core Files', 'duplicator-pro'); ?></b> <br />
        <?php if (count($tplData['filteredCoreDirs']) > 0) : ?>
            <div id="data-srv-wp-core-missing-dirs">
                <?php echo wp_kses(
                    __(
                        "The core WordPress directories below will <u>not</u> be included in the archive.
                        These paths are required for WordPress to function!",
                        'duplicator-pro'
                    ),
                    ['u' => []]
                ); ?>
                <br />
                <?php foreach ($tplData['filteredCoreDirs'] as $coreDir) : ?>
                    <b class="margin-left-1"><i class="fa fa-exclamation-circle scan-warn margin-right-1"></i><?php echo esc_html($coreDir); ?></b><br />
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (count($tplData['filteredCoreFiles']) > 0) : ?>
            <div id="data-srv-wp-core-missing-dirs">
                <?php echo wp_kses(
                    __(
                        "The core WordPress files below will <u>not</u> be included in the archive.
                        These files are required for WordPress to function!",
                        'duplicator-pro'
                    ),
                    ['u' => []]
                ); ?>
                <br />
                <?php foreach ($tplData['filteredCoreFiles'] as $coreFile) : ?>
                    <b class="margin-left-1"><i class="fa fa-exclamation-circle scan-warn margin-right-1"></i><?php echo esc_html($coreFile); ?></b>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (count($tplData['filteredCoreDirs']) > 0 || count($tplData['filteredCoreFiles']) > 0) : ?>
            <div class="scan-system-subnote">
                <?php esc_html_e(
                    'Note: Please change the file and directory filters if you wish to include the WordPress core files 
                otherwise the data will have to be manually copied to the new location for the site to function properly.',
                    'duplicator-pro'
                ); ?>
            </div>
        <?php endif; ?>
        <?php if (empty($tplData['filteredCoreDirs']) && empty($tplData['filteredCoreFiles'])) : ?>
            <div class="scan-system-subnote">
                <?php esc_html_e(
                    "If the scanner is unable to locate the wp-config.php file in the root directory, 
                    then you will need to manually copy it to its new location. 
                    This check will also look for core WordPress paths that should be included in the archive for WordPress to work correctly.",
                    'duplicator-pro'
                ); ?>
            </div>
        <?php endif; ?>
        <?php if (!is_multisite()) { ?>
            <hr size="1" />
            <span>
                <div class="dup-scan-good"><i class="fa fa-check"></i></div>
            </span>
            <b> <?php esc_html_e('Multisite: N/A', 'duplicator-pro'); ?></b> <br />
            <div class="scan-system-subnote">
                <?php esc_html_e('Multisite was not detected on this site. It is currently configured as a standard WordPress site.', 'duplicator-pro'); ?>
                <i><a href='https://codex.wordpress.org/Create_A_Network' target='_blank'> [<?php esc_html_e('details', 'duplicator-pro'); ?>]</a></i>
            </div>
        <?php } elseif (License::can(License::CAPABILITY_MULTISITE_PLUS)) { ?>
            <hr size="1" />
            <span>
                <div class="dup-scan-good"><i class="fa fa-check"></i></div>
            </span>
            <b> <?php esc_html_e('Multisite: Detected', 'duplicator-pro'); ?></b> <br />
            <div class="scan-system-subnote">
                <?php esc_html_e('This license level has full access to all Multisite Plus+ features.', 'duplicator-pro'); ?>
            </div>
        <?php } else { ?>
            <hr size="1" />
            <span>
                <div class="dup-scan-warn"><i class="fa fa-exclamation-triangle fa-sm"></i></div>
            </span>
            <b><?php esc_html_e('Multisite: Detected', 'duplicator-pro'); ?> </b> <br />
            <div class="scan-system-subnote">
                <?php esc_html(
                    sprintf(
                        __(
                            'Duplicator Pro is at the %1$s license level which allows for backups and migrations of an entire Multisite network.&nbsp;',
                            'duplicator-pro'
                        ),
                        License::getLicenseToString()
                    )
                ); ?>
                <br>
                <?php echo wp_kses(
                    __(
                        "To unlock all <b>Multisite Plus</b> features please upgrade the license before building a Backup.",
                        'duplicator-pro'
                    ),
                    ['b' => []]
                ); ?>
                <br />
                <a href="<?php echo esc_url(License::getUpsellURL()); ?>" target='_blank'>
                    <?php esc_html_e('Upgrade Here', 'duplicator-pro'); ?>
                </a>&nbsp;|&nbsp;
                <a href="<?php echo esc_html(DUPLICATOR_DUPLICATOR_DOCS_URL); ?>how-does-duplicator-handle-multisite-support" target="_blank">
                    <?php esc_html_e('Multisite Plus Feature Overview', 'duplicator-pro'); ?>
                </a>
            </div>
        <?php } ?>
    </div>
</div>