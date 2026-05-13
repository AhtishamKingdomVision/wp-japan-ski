<?php

use Duplicator\Libs\Shell\ShellZipUtils;
use Duplicator\Libs\Snap\SnapServer;
use Duplicator\Package\Archive\PackageArchive;

defined("ABSPATH") or die("");

/**
 * Settings > Packages, shell zip message
 *
 * Variables
 *
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 * @var bool $hasShellZip
 */
$hasShellZip = $tplData['hasShellZip'];

if ($hasShellZip) {
    esc_html_e('The "Shell Zip" mode allows Duplicator to use the server\'s internal zip command.', 'duplicator-pro');
    ?>
    <br />
    <?php
    esc_html_e('When available this mode is recommended over the PHP "ZipArchive" mode.', 'duplicator-pro');
} else {
    $scanPath = PackageArchive::getScanPaths();
    if (count($scanPath) > 1) {
        ?>

        <i style='color:maroon'>
            <i class='fa fa-exclamation-triangle'></i>
            <?php esc_html_e("This server is not configured for the Shell Zip engine - please use a different engine mode.", 'duplicator-pro'); ?>
        </i>
    <?php } else { ?>
        <i style='color:maroon'>
            <i class='fa fa-exclamation-triangle'></i>
            <?php esc_html_e("This server is not configured for the Shell Zip engine - please use a different engine mode.", 'duplicator-pro'); ?>
            <br />
            <?php
            printf(
                esc_html_x(
                    'Shell Zip is %1$srecommended%2$s when available. ',
                    '%1$s and %2$s are html anchor tags or link',
                    'duplicator-pro'
                ),
                '<a href="' . esc_url(DUPLICATOR_DUPLICATOR_DOCS_URL . 'how-to-work-with-the-different-zip-engines') . '" target="_blank">',
                '</a> '
            );
            printf(
                esc_html_x(
                    'For a list of supported hosting providers %1$sclick here%2$s.',
                    '%1$s and %2$s are html anchor tags or link',
                    'duplicator-pro'
                ),
                '<a href="' . esc_url(DUPLICATOR_DUPLICATOR_DOCS_URL . 'what-host-providers-are-recommended-for-duplicator/') . '" target="_blank">',
                '</a> '
            );
            ?>
        </i>
        <?php
        // Show possible solutions for some linux setups
        $problems = ShellZipUtils::getShellExecZipProblems();
        if (count($problems) > 0 && !SnapServer::isWindows()) {
            $shell_tooltip  = ' ';
            $shell_tooltip .= __("To make 'Shell Zip' available, ask your host to:", 'duplicator-pro');
            echo '<br/>';
            $i = 1;
            foreach ($problems as $problem) {
                $shell_tooltip .= "{$i}. {$problem['fix']}<br/>";
                $i++;
            }
            $shell_tooltip .= '<br/>';
            echo wp_kses($shell_tooltip, ['br' => []]);
        }
    }
}
?>