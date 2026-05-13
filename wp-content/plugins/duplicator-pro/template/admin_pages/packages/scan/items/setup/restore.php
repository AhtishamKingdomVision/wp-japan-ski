<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */
?>
<div id="migration-status-scan-item" class="scan-item">
    <div class='title' onclick="DupliJs.Pack.toggleScanItem(this);">
        <div class="text"><i class="fa fa-caret-right"></i> <?php esc_html_e('Import Status', 'duplicator-pro');?></div>
        <div id="data-arc-status-migratepackage"></div>
    </div>
    <div class="info">
        <script id="hb-migrate-package-result" type="text/x-handlebars-template">
            <div class="container">
                <div class="data">
                    {{#if ARC.Status.PackageIsNotImportable}}
                        <hr>
                        <p>
                            <span class="maroon">
                            <?php esc_html_e("This Backup is not compatible with", 'duplicator-pro'); ?>
                                <i data-tooltip-title="<?php esc_attr_e("Drag and Drop Import", 'duplicator-pro'); ?>"
                                   data-tooltip="<?php esc_attr_e('The Drag and Drop import method is a way to migrate Backups.
                                                                    You can find it under Duplicator Pro > Import.', 'duplicator-pro'); ?>">
                                   <u><?php esc_html_e("Drag and Drop import", 'duplicator-pro'); ?></u>.&nbsp;
                                </i>
                                <?php esc_html_e("However it can still be used to perform a database migration.", 'duplicator-pro'); ?>
                            </span>

                            {{#if ARC.Status.IsDBOnly}}
                                <?php
                                esc_attr_e(
                                    "Database only Backups can only be installed via the installer.php file. 
                                    The Drag and Drop interface only processes Backups that have all WordPress core directories and all database tables.",
                                    'duplicator-pro'
                                );
                                ?>
                            {{else}}
                                <?php esc_attr_e(
                                    "To make the Backup compatible with Drag and Drop import don't filter any tables or core directories.",
                                    'duplicator-pro'
                                ); ?>
                            {{/if}}
                        </p>
                        {{#if ARC.Status.HasFilteredCoreFolders}}
                        <p>
                            <b><?php esc_attr_e("FILTERED CORE DIRS:", 'duplicator-pro'); ?></b>
                        </p>
                        <ol>
                            {{#each ARC.FilteredCoreDirs as |dir|}}
                            <li>{{dir}} </li>
                            {{/each}}
                        </ol>
                        {{/if}}
                        {{#if ARC.Status.HasFilteredSiteTables}}
                            <b><?php esc_attr_e("FILTERED SITE TABLES:", 'duplicator-pro'); ?></b>
                            <div class="dup-scan-files-migrae-status">
                                <ol>
                                    {{#each DB.FilteredTables as |table|}}
                                    <li>{{table}} </li>
                                    {{/each}}
                                </ol>
                            </div>
                        {{/if}}
                    {{else}}
                        <?php esc_html_e("The Backup you are about to create is compatible with Drag and Drop import.", 'duplicator-pro'); ?>
                    {{/if}}
                </div>
            </div>
        </script>
        <div id="migrate-package-result"></div>
    </div>
</div>
