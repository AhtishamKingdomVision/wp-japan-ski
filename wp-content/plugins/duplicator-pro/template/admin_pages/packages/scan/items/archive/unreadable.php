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
<div id="scan-unreadable-items" class="scan-item">
    <div class='title' onclick="DupliJs.Pack.toggleScanItem(this);">
        <div class="text"><i class="fa fa-caret-right"></i> <?php esc_html_e('Read Checks', 'duplicator-pro');?></div>
        <div id="data-arc-status-unreadablefiles"></div>
    </div>
    <div class="info">
        <?php
        echo wp_kses(
            __(
                'PHP is unable to read the following items and they will <u>not</u> be included in the Backup. 
                Please work with your host to adjust the permissions or resolve the symbolic-link(s) shown in the lists below. 
                If these items are not needed then this notice can be ignored.',
                'duplicator-pro'
            ),
            ['u' => []]
        );
        ?>
        <script id="unreadable-files" type="text/x-handlebars-template">
            <div class="container">
                <div class="data-padded">
                    <b><?php esc_html_e('Unreadable Items:', 'duplicator-pro');?></b> <br/>
                    <div class="directory">
                        {{#if ARC.UnreadableItems}}
                            {{#each ARC.UnreadableItems as |uitem|}}
                                <i class="fa fa-lock fa-sm"></i> {{uitem}} <br/>
                            {{/each}}
                        {{else}}
                            <i>
                                <?php esc_html_e('No unreadable items found.', 'duplicator-pro'); ?>
                                <br>
                            </i>
                        {{/if}}
                    </div>

                    <b><?php esc_html_e('Recursive Links:', 'duplicator-pro');?></b> <br/>
                    <div class="directory">
                        {{#if  ARC.RecursiveLinks}}
                            {{#each ARC.RecursiveLinks as |link|}}
                                <i class="fa fa-lock fa-sm"></i> {{link}} <br/>
                            {{/each}}
                        {{else}}
                            <i>
                                <?php esc_html_e('No recursive sym-links found.', 'duplicator-pro'); ?>
                                <br>
                            </i>
                        {{/if}}
                    </div>

                    <b><?php esc_html_e('Open Basedir Restricted Items:', 'duplicator-pro');?></b> <br/>
                    <div class="directory">
                        {{#if ARC.PathsOutOpenbaseDir}}
                            {{#each ARC.PathsOutOpenbaseDir as |openBaseDiritem|}}
                                <i class="fa fa-lock fa-sm"></i> {{openBaseDiritem}} <br/>
                            {{/each}}
                        {{else}}
                            <i>
                                <?php esc_html_e('No open_basedir restricted items found.', 'duplicator-pro'); ?>
                                <br>
                            </i>
                        {{/if}}
                    </div>
                </div>
            </div>
        </script>
        <div id="unreadable-files-result" class="hb-files-style"></div>
    </div>
</div>
