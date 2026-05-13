<?php

/**
 * @package Duplicator
 */

use Duplicator\Addons\ProBase\License\License;
use Duplicator\Libs\WpUtils\WpUtilsMultisite;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 */

if (!is_multisite()) {
    return;
}
?>
<div class="filter-mu-tab-content <?php echo (License::can(License::CAPABILITY_MULTISITE_PLUS) ? '' : 'disabled'); ?>">
    <div style="max-width:900px">
        <?php if (!License::can(License::CAPABILITY_MULTISITE_PLUS)) { ?>
            <div class="dupli-panel-optional-txt alert-disabled" style="text-align: center">
                <b><?php esc_html_e("Notice:", 'duplicator-pro'); ?></b>
                <?php
                printf(
                    esc_html__(
                        'This option isn\'t available at the %1$s license level.',
                        'duplicator-pro'
                    ),
                    esc_html(License::getLicenseToString())
                );
                ?>
                <br>
                <?php
                printf(
                    esc_html_x(
                        'To enable this option %1$supgrade%2$s the License.',
                        '%1$s and %2$s represents the opening and closing HTML tags for an anchor or link',
                        'duplicator-pro'
                    ),
                    '<a href="' . esc_url(License::getUpsellURL()) . '" target="_blank">',
                    '</a>'
                );
                ?>
            </div>
        <?php } ?>
        <?php
        echo '<b>' . esc_html__("Overview:", 'duplicator-pro') . '</b><br/>';
        esc_html_e(
            "When you want to move a full multisite network or convert a subsite to a standalone site just 
            create a standard Backup like you would with a single site. 
            Then browse to the installer and choose either 'Restore entire multisite network' or 'Convert subsite into a standalone site'. 
            These options will be present on Step 1 of the installer when restoring a Multisite Backup.",
            'duplicator-pro'
        );
        ?>
    </div>
    <table class="mu-opts">
        <tr>
            <td>
                <b><?php esc_html_e("Included Sub-Sites", 'duplicator-pro'); ?>:</b><br />
                <select name="mu-include[]" id="mu-include" multiple="true" class="mu-selector">
                    <?php
                    $subsites = License::can(License::CAPABILITY_MULTISITE_PLUS) ? WpUtilsMultisite::getSubsites() : [];
                    foreach ($subsites as $site) {
                        echo "<option value='" . (int) $site->id . "'>" . esc_html($site->domain . $site->path) . "</option>";
                    }
                    ?>
                </select>
            </td>
            <td>
                <button type="button" id="mu-exclude-btn" class="mu-push-btn"><i class="fa fa-chevron-right"></i></button>
                <br />
                <button type="button" id="mu-include-btn" class="mu-push-btn"><i class="fa fa-chevron-left"></i></button>
            </td>
            <td>
                <b><?php esc_html_e("Excluded Sub-Sites", 'duplicator-pro'); ?>:</b><br />
                <select name="mu-exclude[]" id="mu-exclude" multiple="true" class="mu-selector"></select>
            </td>
        </tr>
    </table>

    <div class="dupli-panel-optional-txt" style="text-align: left">
        <?php
        echo wp_kses(
            __(
                "<u><b>Important:</b></u> Full network restoration is an installer option only if you include <b>all</b> subsites.
                    If any subsites are filtered then you may only restore individual subsites as standalones sites at install-time.",
                'duplicator-pro'
            ),
            [
                'b' => [],
                'u' => [],
            ]
        );
        ?>
        <br />
        <br />
        <?php
        esc_html_e(
            "This section allows you to control which sub-sites of a multisite network you want to include within your Backup. 
            The 'Included Sub-Sites' will also be available to choose from at install time.",
            'duplicator-pro'
        );
        ?>
        <br />
        <?php
        esc_html_e(
            "By default all Backups are included. The ability to exclude sub-sites are intended to help shrink your Backup if needed.",
            'duplicator-pro'
        );
        ?>
    </div>
</div>