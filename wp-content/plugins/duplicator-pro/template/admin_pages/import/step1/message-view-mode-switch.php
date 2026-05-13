<?php

/**
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

?><div class="notice notice-success is-dismissible dupli-admin-notice">
    <p>
        <?php
        printf(
            esc_html_x(
                'The mode has %1$sswitched to advanced%2$s because more backups have been detected in the import folder.',
                '%1$s and %2$s are opening and closing bold tags (<b> and </b>)',
                'duplicator-pro'
            ),
            '<b>',
            '</b>'
        );
        ?>
    </p>
</div>