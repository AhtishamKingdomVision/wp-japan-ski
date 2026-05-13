<?php

/**
 * @package Duplicator
 */

defined("ABSPATH") or die("");

use Duplicator\Addons\ProBase\License\License;
use Duplicator\Controllers\SettingsPageController;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Models\BrandEntity;
use Duplicator\Models\TemplateEntity;
use Duplicator\Views\UI\UiViewState;

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var int $activeBrandId
 */
$activeBrandId = ($tplData['activeBrandId'] ?? -1);
/** @var string */
$dbHost = ($tplData['dbHost'] ?? '');
/** @var string */
$dbName = ($tplData['dbName'] ?? '');
/** @var string */
$dbUser = ($tplData['dbUser'] ?? '');
/** @var bool */
$cpnlEnable = ($tplData['cpnlEnable'] ?? false);
/** @var string */
$cpnlHost = ($tplData['cpnlHost'] ?? '');
/** @var string */
$cpnlUser = ($tplData['cpnlUser'] ?? '');
/** @var string */
$cpnlDbAction = ($tplData['cpnlDbAction'] ?? '');
/** @var string */
$cpnlDbHost = ($tplData['cpnlDbHost'] ?? '');
/** @var string */
$cpnlDbName = ($tplData['cpnlDbName'] ?? '');
/** @var string */
$cpnlDbUser = ($tplData['cpnlDbUser'] ?? '');

$ui_css_installer = (UiViewState::getValue('dupli-pack-installer-panel') ? 'display:block' : 'display:none');

?>
<div class="dup-box">
    <div class="dup-box-title">
        <i class="fa fa-bolt fa-sm"></i> <?php esc_html_e('Installer', 'duplicator-pro') ?>
        <button class="dup-box-arrow">
            <span class="screen-reader-text"><?php esc_html_e('Toggle panel:', 'duplicator-pro') ?>
                <?php esc_html_e('Installer Settings', 'duplicator-pro') ?>
            </span>
        </button>
    </div>
    <div class="dup-box-panel" id="dupli-pack-installer-panel" style="<?php echo esc_attr($ui_css_installer); ?>">
        <div class="dup-package-hdr-1">
            <?php esc_html_e("Setup", 'duplicator-pro') ?>
        </div>
        <label class="lbl-larger">
            <?php esc_html_e('Branding', 'duplicator-pro'); ?>
            <?php
            $tipContent = __(
                'This option changes the branding of the installer file. Click the preview button to see the selected style.',
                'duplicator-pro'
            );
            ?>
            <i
                class="fa-solid fa-question-circle fa-sm dark-gray-color"
                data-tooltip-title="<?php esc_attr_e("Choose Brand", 'duplicator-pro'); ?>"
                data-tooltip="<?php echo esc_attr($tipContent); ?>"></i>
        </label>
        <div>
            <?php
            if (License::can(License::CAPABILITY_BRAND)) :
                $brands = BrandEntity::getAllWithDefault();
                /** @todo remove this */
                /*
            $activeBrandId = TemplateEntity::get_manual_template()->installer_opts_brand;
            if ($activeBrandId < 0) {
                $activeBrandId = -1; // for old brand version
            }
                */
                ?>
                <select name="installer_opts_brand" id="brand" class="width-auto">
                    <?php foreach ($brands as $i => $brand) { ?>
                        <option
                            value="<?php echo (int) $brand->getId(); ?>"
                            title="<?php echo esc_attr($brand->notes); ?>"
                            <?php selected($brand->getId(), $activeBrandId); ?>>
                            <?php echo esc_html($brand->name); ?>
                        </option>
                    <?php } ?>
                </select>
                <?php
                if ($activeBrandId > 0) {
                    $preview_url = ControllersManager::getMenuLink(
                        ControllersManager::SETTINGS_SUBMENU_SLUG,
                        SettingsPageController::L2_SLUG_PACKAGE_BRAND,
                        null,
                        [
                            ControllersManager::QUERY_STRING_INNER_PAGE => SettingsPageController::BRAND_INNER_PAGE_EDIT,
                            'action'                                    => 'edit',
                            'id'                                        => intval($activeBrandId),
                        ]
                    );
                } else {
                    $preview_url = ControllersManager::getMenuLink(
                        ControllersManager::SETTINGS_SUBMENU_SLUG,
                        SettingsPageController::L2_SLUG_PACKAGE_BRAND,
                        null,
                        [
                            ControllersManager::QUERY_STRING_INNER_PAGE => SettingsPageController::BRAND_INNER_PAGE_EDIT,
                            'action'                                    => 'default',
                        ]
                    );
                }
                ?>
                &nbsp;
                <a href="<?php echo esc_url($preview_url); ?>" target="_blank" class="button hollow secondary small" id="brand-preview">
                    <?php esc_html_e("Preview", 'duplicator-pro'); ?>
                </a> &nbsp;
            <?php else :
                $link =  ControllersManager::getMenuLink(
                    ControllersManager::SETTINGS_SUBMENU_SLUG,
                    SettingsPageController::L2_SLUG_PACKAGE_BRAND
                );
                ?>
                <a href="<?php echo esc_url($link); ?>"><?php esc_html_e("Enable Branding", 'duplicator-pro'); ?></a>
            <?php endif; ?>
        </div>

        <div class="dup-package-hdr-1">
            <?php esc_html_e("Prefills", 'duplicator-pro') ?>&nbsp;
            <i class="fa-solid fa-question-circle fa-sm dark-gray-color"
                data-tooltip-title="<?php esc_attr_e("Setup/Prefills", 'duplicator-pro'); ?>"
                data-tooltip="<?php
                                esc_attr_e(
                                    'All values in this section are OPTIONAL! If you know ahead of time the database input fields the installer will use, 
                    then you can optionally enter them here and they will be prefilled at install time. 
                    Otherwise you can just enter them in at install time and ignore all these options in the Installer section.',
                                    'duplicator-pro'
                                );
                                ?>">
            </i>
        </div>

        <!-- ===================
        BASIC/CPANEL TABS -->
        <div data-dupli-tabs="true">
            <ul>
                <li id="dupli-bsc-tab-lbl"><?php esc_html_e('Basic', 'duplicator-pro') ?></li>
                <li id="dupli-cpnl-tab-lbl"><?php esc_html_e('cPanel', 'duplicator-pro') ?></li>
            </ul>

            <!-- ===================
            TAB1: Basic -->
            <div>
                <div class="dup-package-hdr-2">
                    <?php esc_html_e("MySQL Server", 'duplicator-pro') ?>
                    <div class="dup-package-hdr-usecurrent">
                        <a href="javascript:void(0)" onclick="DupliJs.Pack.ApplyDataCurrent('s1-installer-dbbasic')">
                            [<?php esc_html_e('use current', 'duplicator-pro') ?>]
                        </a>
                    </div>
                </div>

                <div id="s1-installer-dbbasic">
                    <label class="lbl-larger">
                        <?php esc_html_e("Host", 'duplicator-pro') ?>:
                    </label>
                    <div>
                        <input
                            type="text"
                            name="installer_opts_db_host"
                            id="dbhost"
                            maxlength="200"
                            placeholder="<?php esc_html_e("example: localhost (value is optional)", 'duplicator-pro') ?>"
                            data-current="<?php echo esc_attr(DB_HOST); ?>"
                            value="<?php echo esc_attr($dbHost); ?>">
                    </div>

                    <label class="lbl-larger">
                        <?php esc_html_e("Database", 'duplicator-pro') ?>:
                    </label>
                    <div>
                        <input
                            type="text"
                            name="installer_opts_db_name"
                            id="dbname"
                            maxlength="100"
                            placeholder="<?php esc_html_e("example: DatabaseName (value is optional)", 'duplicator-pro') ?>"
                            data-current="<?php echo esc_attr(DB_NAME) ?>"
                            value="<?php echo esc_attr($dbName); ?>">
                    </div>

                    <label class="lbl-larger">
                        <?php esc_html_e("User", 'duplicator-pro') ?>:
                    </label>
                    <div>
                        <input
                            type="text"
                            name="installer_opts_db_user"
                            id="dbuser"
                            maxlength="100"
                            placeholder="<?php esc_html_e("example: DatabaseUser (value is optional)", 'duplicator-pro') ?>"
                            data-current="<?php echo esc_attr(DB_USER); ?>"
                            value="<?php echo esc_attr($dbUser); ?>">
                    </div>
                </div>

            </div>

            <!-- ===================
            TAB2: cPanel -->
            <div>
                <div class="dup-package-hdr-2">
                    <?php esc_html_e("cPanel Login", 'duplicator-pro') ?>
                </div>

                <label class="lbl-larger">
                    <?php esc_html_e("Automation", 'duplicator-pro') ?>:
                </label>
                <div>
                    <?php
                    $tipContent = __(
                        'Enabling this options will automatically select the cPanel tab when step one of the installer is shown.',
                        'duplicator-pro'
                    );
                    ?>
                    <input
                        type="checkbox"
                        name="installer_opts_cpnl_enable"
                        id="cpnl-enable"
                        value="1"
                        <?php checked($cpnlEnable); ?>>
                    <label for="cpnl-enable"><?php esc_html_e('Auto Select cPanel', 'duplicator-pro') ?></label>
                    <i class="fa-solid fa-question-circle fa-sm dark-gray-color"
                        data-tooltip-title="<?php esc_attr_e('Auto Select cPanel', 'duplicator-pro'); ?>"
                        data-tooltip="<?php echo esc_attr($tipContent); ?>">
                    </i>
                </div>

                <label class="lbl-larger">
                    <?php esc_html_e("Host", 'duplicator-pro') ?>:
                </label>
                <div>
                    <input
                        type="text"
                        name="installer_opts_cpnl_host"
                        id="cpnl-host"
                        maxlength="200"
                        value="<?php echo esc_attr($cpnlHost); ?>"
                        placeholder="<?php esc_attr_e("example: cpanelHost (value is optional)", 'duplicator-pro') ?>">
                </div>

                <label class="lbl-larger">
                    <?php esc_html_e("User", 'duplicator-pro') ?>:
                </label>
                <div>
                    <input
                        type="text"
                        name="installer_opts_cpnl_user"
                        id="cpnl-user"
                        value="<?php echo esc_attr($cpnlUser); ?>"
                        maxlength="200"
                        placeholder="<?php esc_attr_e("example: cpanelUser (value is optional)", 'duplicator-pro') ?>">
                </div>

                <div class="dup-package-hdr-2">
                    <?php esc_html_e("MySQL Server", 'duplicator-pro') ?>
                    <div class="dup-package-hdr-usecurrent">
                        <a href="javascript:void(0)" onclick="DupliJs.Pack.ApplyDataCurrent('s1-installer-dbcpanel')">
                            [<?php esc_html_e('use current', 'duplicator-pro') ?>]
                        </a>
                    </div>
                </div>

                <div id="s1-installer-dbcpanel">
                    <label class="lbl-larger">
                        <?php esc_html_e("Action", 'duplicator-pro') ?>:
                    </label>
                    <div>
                        <select name="installer_opts_cpnl_db_action" id="cpnl-dbaction">
                            <option value="" <?php selected($cpnlDbAction, ''); ?>>
                                <?php esc_html_e('Default', 'duplicator-pro'); ?>
                            </option>
                            <option value="create" <?php selected($cpnlDbAction, 'create'); ?>>
                                <?php esc_html_e('Create A New Database', 'duplicator-pro'); ?>
                            </option>
                            <option value="empty" <?php selected($cpnlDbAction, 'empty'); ?>>
                                <?php esc_html_e('Connect and Delete Any Existing Data', 'duplicator-pro'); ?>
                            </option>
                            <option value="rename" <?php selected($cpnlDbAction, 'rename'); ?>>
                                <?php esc_html_e('Connect and Backup Any Existing Data', 'duplicator-pro'); ?>
                            </option>
                            <option value="manual" <?php selected($cpnlDbAction, 'manual'); ?>>
                                <?php esc_html_e('Skip Database Extraction', 'duplicator-pro'); ?>
                            </option>
                        </select>
                    </div>

                    <label class="lbl-larger">
                        <?php esc_html_e("Host", 'duplicator-pro') ?>:
                    </label>
                    <div>
                        <input
                            type="text"
                            name="installer_opts_cpnl_db_host"
                            id="cpnl-dbhost"
                            value="<?php echo esc_attr($cpnlDbHost); ?>"
                            maxlength="200"
                            placeholder="<?php esc_attr_e("example: localhost (value is optional)", 'duplicator-pro') ?>"
                            data-current="<?php echo esc_html(DB_HOST); ?>">
                    </div>

                    <label class="lbl-larger">
                        <?php esc_html_e("Database", 'duplicator-pro') ?>:
                    </label>
                    <div>
                        <input
                            type="text"
                            name="installer_opts_cpnl_db_name"
                            value="<?php echo esc_attr($cpnlDbName); ?>"
                            id="cpnl-dbname"
                            data-parsley-pattern="/^[a-zA-Z0-9-_]+$/"
                            maxlength="100"
                            placeholder="<?php esc_attr_e("example: DatabaseName (value is optional)", 'duplicator-pro') ?>"
                            data-current="<?php echo esc_html(DB_NAME); ?>">
                    </div>

                    <label class="lbl-larger">
                        <?php esc_html_e("User", 'duplicator-pro') ?>:
                    </label>
                    <div>
                        <input
                            type="text"
                            name="installer_opts_cpnl_db_user"
                            value="<?php echo esc_attr($cpnlDbUser); ?>"
                            id="cpnl-dbuser"
                            data-parsley-pattern="/^[a-zA-Z0-9-_]+$/"
                            maxlength="100"
                            placeholder="<?php esc_attr_e("example: DatabaseUserName (value is optional)", 'duplicator-pro') ?>"
                            data-current="<?php echo esc_html(DB_USER); ?>">
                    </div>
                </div>
            </div>
        </div><br />

        <small><?php esc_html_e("Additional inputs can be entered at install time.", 'duplicator-pro') ?></small>
        <br /><br />
    </div>
</div><br />

<script>
    (function($) {
        DupliJs.Pack.ApplyDataCurrent = function(id) {
            $('#' + id + ' input').each(function() {
                var attr = $(this).attr('data-current');
                if (typeof attr !== typeof undefined && attr !== false) {
                    $(this).val($(this).attr('data-current'));
                }
            });
        };
        <?php if (License::can(License::CAPABILITY_BRAND)) : ?>
            // brand-preview
            var $brand = $("#brand"),
                brandCheck = function(e) {
                    var $this = $(this) || $brand;
                    var $id = $this.val();
                    <?php
                    $prewURLs = [
                        ControllersManager::getMenuLink(
                            ControllersManager::SETTINGS_SUBMENU_SLUG,
                            SettingsPageController::L2_SLUG_PACKAGE_BRAND,
                            null,
                            [
                                ControllersManager::QUERY_STRING_INNER_PAGE => SettingsPageController::BRAND_INNER_PAGE_EDIT,
                                'action'                                    => 'default',
                            ]
                        ),
                        ControllersManager::getMenuLink(
                            ControllersManager::SETTINGS_SUBMENU_SLUG,
                            SettingsPageController::L2_SLUG_PACKAGE_BRAND,
                            null,
                            [
                                ControllersManager::QUERY_STRING_INNER_PAGE => SettingsPageController::BRAND_INNER_PAGE_EDIT,
                                'action'                                    => 'edit',
                            ]
                        ),
                    ];
                    ?>
                    var $url = <?php echo json_encode($prewURLs); ?>;
                    $url[1] += "&id=" + $id;

                    $("#brand-preview").attr('href', $url[$id > 0 ? 1 : 0]);

                    $this.find('option[value="' + $id + '"]')
                        .prop('selected', true)
                        .parent();
                };
            $brand.on('select change', brandCheck);
        <?php endif; ?>


    }(window.jQuery));
</script>