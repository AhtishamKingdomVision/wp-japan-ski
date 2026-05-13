<?php

/**
 * Duplicator Backup row in table Backups list
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Libs\Snap\SnapWP;
use Duplicator\Libs\WpUtils\WpDbUtils;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var \wpdb $wpdb
 */

$dbFilterOn        = $tplData['dbFilterOn'] ?? false;
$dbPrefixFilter    = $tplData['dbPrefixFilter'] ?? false;
$dbPrefixSubFilter = $tplData['dbPrefixSubFilter'] ?? false;
$tSelected         = $tplData['tablesSlected'] ?? [];
$dbTableCount      = 1;

global $wpdb;

$toolTipPrefixFilterContent = sprintf(
    __(
        'By enabling this option all tables that do not start with the prefix <b>"%s"</b> are excluded from the Backup.',
        'duplicator-pro'
    ),
    esc_html($wpdb->prefix)
) . ' ' .
    __(
        'This option is useful for multiple WordPress installs in the same database or if several applications are installed in the same database.',
        'duplicator-pro'
    );

$toolTipSubsiteFilterContent =
    __('Enabling this option excludes all tables associated with deleted sites from the Backup.', 'duplicator-pro') . '<br><br>' .
    __(
        'When deleting a site in a multisite; WordPress deletes the tables of items related to the core, however it is not assumed that the tables of third party plugins are removed.', // phpcs:ignore Generic.Files.LineLength
        'duplicator-pro'
    ) . ' ' .
    __('With a multisite with a large number of deleted sites the database may be full of unused tables.', 'duplicator-pro') . ' ' .
    __('With this option only the tables of currently existing sites will be included in the backup.', 'duplicator-pro');

$toolTipTablesFilters = __(
    "Checked tables will be <b>excluded</b> from the database script. 
    Excluding certain tables can cause your site or plugins to not work correctly after install!",
    'duplicator-pro'
) . '<br><br>' .
    __(
        "Use caution when excluding tables! It is highly recommended to not exclude WordPress core tables in red with an *, 
    unless you know the impact.",
        'duplicator-pro'
    );
?>
<label class="lbl-larger">
    <?php esc_html_e('Database Filters:', 'duplicator-pro'); ?>
    <i class="fa-solid fa-question-circle fa-sm dark-gray-color"
        title="<?php esc_attr_e('Database filters allow you to exclude table from the backup.', 'duplicator-pro'); ?>"
        aria-expanded="false"></i>
</label>
<div class="margin-bottom-1">
    <label>
        <input
            type="checkbox"
            id="dbfilter-on"
            name="dbfilter-on" <?php checked($dbFilterOn); ?>
            class="margin-0">&nbsp;<?php esc_html_e('Enable', 'duplicator-pro'); ?>
    </label>
</div>

<div class="db-filter-section">
    <label class="lbl-larger">
        <?php esc_html_e("Table Prefixes:", 'duplicator-pro') ?>
        <i class="fa-solid fa-question-circle fa-sm dark-gray-color"
            title="<?php echo esc_attr($toolTipPrefixFilterContent); ?>"
            aria-expanded="false"></i>
    </label>
    <div class="margin-bottom-1">
        <label>
            <input
                type="checkbox"
                id="db-prefix-filter"
                name="db-prefix-filter"
                class="margin-0"
                <?php checked($dbPrefixFilter); ?>
                <?php disabled(!$dbFilterOn); ?>
                data-prefix-value="<?php echo esc_attr($wpdb->prefix); ?>" />
            <?php
            esc_html_e('Filter tables without current WordPress prefix', 'duplicator-pro');
            echo isset($wpdb->prefix) ?  '&nbsp;<i>(' . esc_html($wpdb->prefix) . ')</i>&nbsp;' : '';
            ?>
        </label>
    </div>


    <?php if (is_multisite()) { ?>
        <label class="lbl-larger">
            <?php esc_html_e("Subsites:", 'duplicator-pro') ?>
            <i class="fa-solid fa-question-circle fa-sm dark-gray-color"
                data-tooltip-title="<?php esc_attr_e("Multisite-Subsite Filters", 'duplicator-pro'); ?>"
                data-tooltip="<?php echo esc_attr($toolTipSubsiteFilterContent); ?>">
            </i>
        </label>
        <div class="margin-bottom-1">
            <label>
                <input
                    type="checkbox"
                    id="db-prefix-sub-filter"
                    name="db-prefix-sub-filter"
                    class="margin-0"
                    <?php checked($dbPrefixSubFilter); ?>
                    <?php disabled(!$dbFilterOn); ?>>
                <?php esc_html_e("Filter/Hide Tables of Deleted Multisite-Subsites", 'duplicator-pro') ?>
            </label>
        </div>
    <?php } ?>

    <label class="lbl-larger">
        <?php esc_html_e("Exclude Tables:", 'duplicator-pro') ?>
        <i class="fa-solid fa-question-circle fa-sm dark-gray-color"
            data-tooltip="<?php echo esc_attr($toolTipTablesFilters); ?>">
        </i>
    </label>
    <div id="dup-db-filter-items">
        <div class="dup-db-filter-buttons">
            <span id="dbnone" class="link-style gray dup-db-filter-none">
                <i class="fa-regular fa-square-minus fa-lg" title="<?php esc_html_e('Unfilter All Tables', 'duplicator-pro'); ?>"></i>
            </span>&nbsp;
            <span id="dball" class="link-style gray dup-db-filter-all">
                <i class="fa-regular fa-square-check fa-lg" title="<?php esc_html_e('Filter All Tables', 'duplicator-pro'); ?>"></i>
            </span>
        </div>
        <div id="dup-db-tables-exclude-wrapper">
            <div id="dup-db-tables-exclude">
                <input type="hidden" id="dup-db-tables-lists" name="dbtables-list" value="">
                <?php
                $substesIds = SnapWP::getSitesIds();
                foreach (WpDbUtils::getTablesList() as $table) {
                    $info    = SnapWP::getTableInfoByName($table, $wpdb->prefix);
                    $classes = ['table-item'];

                    if ($info['isCore']) {
                        $classes[] = 'core-table';
                        $core_note = '*';

                        if ($info['subsiteId'] > 0) {
                            $classes[] = ' subcore-table-' . ($info['subsiteId'] % 2);
                        }
                    } else {
                        $core_note = '';
                    }
                    $dbTableCount++;
                    $cboxClasses = ['dup-pseudo-checkbox'];
                    $checked     = in_array($table, $tSelected);

                    if ($info['subsiteId'] > 1 && !in_array($info['subsiteId'], $substesIds)) {
                        $classes[] = 'no-subsite-exists';
                        if ($dbPrefixSubFilter) {
                            $cboxClasses[] = 'disabled';
                            $checked       = true;
                        }
                    }

                    if ($info['havePrefix'] == false) {
                        $classes[] = 'no-prefix-table';
                        if ($dbPrefixFilter) {
                            $cboxClasses[] = 'disabled';
                            $checked       = true;
                        }
                    }

                    if ($checked) {
                        $cboxClasses[] = 'checked';
                    }
                    ?>
                    <label class="<?php echo esc_attr(implode(' ', $classes)); ?>">
                        <span
                            class="<?php echo esc_attr(implode(' ', $cboxClasses)); ?>"
                            aria-checked="<?php echo $checked ? "true" : "false"; ?>"
                            role="checkbox"
                            data-value="<?php echo esc_attr($table); ?>">
                        </span>
                        &nbsp;<span><?php echo esc_html($table . $core_note); ?></span>
                    </label>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<script>
    jQuery(function($) {
        /* METHOD: Toggle Database table filter red icon */
        DupliJs.Pack.ToggleDBFiltersRedIcon = function() {
            if (
                $("#dbfilter-on").is(':checked')
            ) {
                $('#dup-archive-filter-db-icon').show();
                $('#db-prefix-filter').prop('disabled', false);
                $('#db-prefix-sub-filter').prop('disabled', false);
            } else {
                $('#dup-archive-filter-db-icon').hide();
                $('#db-prefix-filter').prop('disabled', true);
                $('#db-prefix-sub-filter').prop('disabled', true);
            }
        }

        DupliJs.Pack.ToggleDBFilters = function() {
            var filterItems = $('#dup-db-filter-items');

            if (
                $("#dbfilter-on").is(':checked')
            ) {
                $('.db-filter-section').removeClass('no-display');
                filterItems.removeClass('disabled');
                $('#dup-db-filter-items-no-filters').hide();
            } else {
                $('.db-filter-section').addClass('no-display');
                filterItems.addClass('disabled');
                $('#dup-db-filter-items-no-filters').show();
            }

            DupliJs.Pack.ToggleDBFiltersRedIcon();
        };

        DupliJs.Pack.FillExcludeTablesList = function() {
            let values = $("#dup-db-tables-exclude .dup-pseudo-checkbox.checked")
                .map(function() {
                    return this.getAttribute('data-value');
                })
                .get()
                .join();

            $('#dup-db-tables-lists').val(values);
        };

        DupliJs.Pack.ToggleNoPrefixTables = function(removeCheckOnEnable = true) {
            let checkNode = $('#db-prefix-filter');
            let display = !checkNode.is(":checked");

            $("#dup-db-tables-exclude .no-prefix-table").each(function() {
                let checkBox = $(this).find(".dup-pseudo-checkbox").first();
                if (display) {
                    checkBox.removeClass('disabled');
                    if (removeCheckOnEnable) {
                        checkBox.removeClass("checked");
                    }
                } else {
                    checkBox
                        .addClass('disabled')
                        .addClass("checked");
                }
            });

            DupliJs.Pack.ToggleDBFiltersRedIcon();
        }

        DupliJs.Pack.ToggleNoSubsiteExistsTables = function(removeCheckOnEnable = true) {
            let checkNode = $('#db-prefix-sub-filter');
            let display = !checkNode.is(":checked");

            $("#dup-db-tables-exclude .no-subsite-exists").each(function() {
                let checkBox = $(this).find(".dup-pseudo-checkbox").first();
                if (display) {
                    checkBox.removeClass('disabled');
                    if (removeCheckOnEnable) {
                        checkBox.removeClass("checked");
                    }
                } else {
                    checkBox
                        .addClass('disabled')
                        .addClass("checked");
                }
            });

            DupliJs.Pack.ToggleDBFiltersRedIcon();
        }
    });

    jQuery(document).ready(function($) {
        let tablesToExclude = $("#dup-db-tables-exclude");

        $('.dup-db-filter-none').click(function() {
            tablesToExclude.find(".dup-pseudo-checkbox.checked").removeClass("checked");
        });

        $('.dup-db-filter-all').click(function() {
            tablesToExclude.find(".dup-pseudo-checkbox:not(.checked)").addClass("checked");
        });

        $('#db-prefix-sub-filter').change(DupliJs.Pack.ToggleNoSubsiteExistsTables);
        $('#db-prefix-filter').change(DupliJs.Pack.ToggleNoPrefixTables);
        $('#dbfilter-on').change(DupliJs.Pack.ToggleDBFilters);
        DupliJs.Pack.ToggleDBFilters();
    });
</script>