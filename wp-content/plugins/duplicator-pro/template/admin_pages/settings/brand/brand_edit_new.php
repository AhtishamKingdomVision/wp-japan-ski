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
    <input type="text" name="name" id="brand-name" value="<?php echo esc_attr($brand->name); ?>" data-parsley-required>
    <p class="description"><?php esc_html_e("Displayed as the page title of the installer.", 'duplicator-pro'); ?></p>
</div>

<label class="lbl-larger" >
    <?php esc_html_e("Notes", 'duplicator-pro'); ?>
</label>
<div class="margin-bottom-1" >
    <textarea name="notes" id="brand-notes"><?php echo esc_html($brand->notes); ?></textarea>
</div>

<label class="lbl-larger" >
    <?php esc_html_e("Logo", 'duplicator-pro'); ?>
</label>
<div class="margin-bottom-1" >
    <div class="width-xlarge" >
        <div class="style-guide-link">
            <a href="javascript:void(0)" class="button secondary hollow small" onclick="DupliJs.Brand.ShowStyleGuide();">
                <?php esc_html_e("Style Guide", 'duplicator-pro'); ?>
            </a>
        </div>
        <div class="brand-logo-editor">
            <?php
            wp_editor(
                $brand->logo,
                'brand-logo',
                [
                    'wpautop'           => true,
                    'media_buttons'     => true,
                    'textarea_name'     => 'logo',
                    'textarea_rows'     => 50,
                    'tabindex'          => '',
                    'tabfocus_elements' => ':prev,:next',
                    'editor_css'        => '',
                    'editor_class'      => 'required',
                    'teeny'             => false,
                    'dfw'               => false,
                    'tinymce'           => false,
                    'quicktags'         => ['buttons' => 'strong,em,i,ins,close,img,link'],
                ]
            );
            ?>
        </div>
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
