<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Controllers\SettingsPageController;
use Duplicator\Core\Controllers\ControllersManager;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 */

$importSettingsUrl = $ctrlMng->getMenuLink(
    ControllersManager::SETTINGS_SUBMENU_SLUG,
    SettingsPageController::L2_SLUG_IMPORT
);

?>
<div class="dupli-import-upload-message" >
    <p class="import-upload-reset-message-error">
        <i class="fa fa-exclamation-triangle"></i> <b><?php esc_html_e('UPLOAD FILE PROBLEM', 'duplicator-pro'); ?></b>
    </p>
    <p>
        <?php esc_html_e('Error message:', 'duplicator-pro'); ?>&nbsp;
        <b><span class="import-upload-error-message"><!-- here is set the message received from the server --></span></b>
    </p>
    <div><?php esc_html_e('Possible solutions:', 'duplicator-pro'); ?></div>
    <ul class="dupli-simple-style-list" >
        <li>
            <?php esc_html_e('If you are using Server to Server transfer function make sure the URL is a valid URL', 'duplicator-pro'); ?>
        </li>
        <li>
            <?php
                printf(
                    esc_html_x(
                        'If you are using the upload function try to change the chunk size in %1$ssettings%2$s and try again',
                        '%1$s and %2$s represents the opening and closing HTML tags for an anchor or link',
                        'duplicator-pro'
                    ),
                    '<a href="' . esc_url($importSettingsUrl) . '">',
                    '</a>'
                );
                ?>
        </li>
        <li>
            <?php
                printf(
                    esc_html__('Upload the file via FTP/file manager to the "%s" folder and reload the page.', 'duplicator-pro'),
                    esc_html(DUPLICATOR_IMPORTS_PATH)
                );
                ?>
        </li>
    </ul>
    <p>
        <b>
        <?php
        printf(
            esc_html_x(
                'For more information see %1$s[this FAQ item]%2$s',
                '%1$s and %2$s represents the opening and closing HTML tags for an anchor or link',
                'duplicator-pro'
            ),
            '<a href="' . esc_url(DUPLICATOR_DUPLICATOR_DOCS_URL . 'how-to-handle-import-install-upload-launch-issues') . '" target="_blank">',
            '</a>'
        );
        ?>
        </b>
    </p>
</div>
