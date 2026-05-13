<?php

/**
 * @package Duplicator
 */

defined("ABSPATH") or die("");
use Duplicator\Models\BrandEntity;

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 * @var Duplicator\Models\BrandEntity $brand
 */
$brand = $tplData['brand'];

?>
<label class="lbl-larger" >
    <?php esc_html_e("Name", 'duplicator-pro'); ?>
</label>
<div class="margin-bottom-1" >
    <?php echo esc_html($brand->name); ?>
</div>

<label class="lbl-larger" >
    <?php esc_html_e("Notes", 'duplicator-pro'); ?>
</label>
<div class="margin-bottom-1" >
    <?php echo esc_html($brand->notes); ?>
</div>

<label class="lbl-larger" >
    <?php esc_html_e("Logo", 'duplicator-pro'); ?>
</label>
<div class="margin-bottom-1" >
    <div class="width-xlarge" >
        <div class="style-guide-link float-right">
            <a href="javascript:void(0)" class="button secondary hollow small" onclick="DupliJs.Brand.ShowStyleGuide();">
                <?php esc_html_e("Style Guide", 'duplicator-pro'); ?>
            </a>
        </div>
        <textarea id="brand-default-logo" readonly="true"><?php echo esc_html($brand->logo); ?></textarea>
    </div>
</div>

<label class="lbl-larger" >
    <?php esc_html_e("Activation", 'duplicator-pro'); ?>
</label>
<div class="margin-bottom-1 width-xlarge" >
    <?php esc_html_e(
        "This brand can be activated by using the installer brand drop-down during the Backup creation process. 
        It can also be set via a template.",
        'duplicator-pro'
    ); ?>
</div>

<i><?php esc_html_e("The default brand cannot be changed", 'duplicator-pro'); ?></i>
