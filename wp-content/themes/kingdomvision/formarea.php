<?php
$form            = get_field('form');
$heading_tags    = get_field('heading_tags');
$heading_size    = get_field('heading_size');
$heading_text    = get_field('heading_text');
$heading_color   = get_field('heading_color');
$top_content     = get_field('top_content');
$bullet_points   = get_field('bullet_points');

$star_images    = get_field('star_images');
$free_text      = get_field('free_text');
$brand_images   = get_field('brand_images');

$ff_image     = get_field('form_footer_image', 'option');
$ff_content   = get_field('form_footer_content', 'option');

// Background Featured Image
$bg_image = get_field( 'page_bg_image', get_the_ID() ) ? get_field( 'page_bg_image', get_the_ID() ) : get_the_post_thumbnail_url(get_the_ID(), 'full');
// $bg_image = get_the_post_thumbnail_url(get_the_ID(), 'full');

// Overlay from backend (0–100)
$overlay_value = get_field('banner_overlay');

$overlay_opacity = '';
if ($overlay_value !== '' && $overlay_value >= 0 && $overlay_value <= 100) {
    $overlay_opacity = $overlay_value / 100;
}

// Black overlay style (rgba)
$overlay_style = '';
if ($overlay_opacity !== '') {
    $overlay_style = "background-color: rgba(0,17,31,{$overlay_opacity});";
}


if( intval( $form ) == 1 && $bg_image):?>

<section
    class="form_area full-section" 
    role="region"  
    aria-labelledby="section-heading"
    style="background-image: url('<?php echo esc_url($bg_image); ?>'); background-size: cover; background-position: center;"
>

    <?php if ($overlay_style) : ?>
        <div class="section-overlay" style="<?php echo esc_attr($overlay_style); ?>"></div>
    <?php endif; ?>

    <div class="container">

        <div class="fa_left">
            <a class="btn desktop-none quote_toggle" href="javascript:void();">Get a Quote</a>
            <?php
				echo do_shortcode('[company_rating]');
            ?>
            <?php if (!empty($heading_text) && !empty($heading_tags)) : 
                    // Build style only when values exist
                    $style = '';

                    if (!empty($heading_color)) {
                        $style .= 'color:' . esc_attr($heading_color) . ';';
                    }
                    if (!empty($heading_size)) {
                        $style .= 'font-size:' . esc_attr($heading_size) . ';';
                    }

                    $style_attr = $style ? ' style="' . $style . '"' : '';
                ?>
                <<?php echo esc_attr($heading_tags); ?>
                    id="section-heading"
                    <?php echo $style_attr; ?>
                >
                    <?php echo esc_html($heading_text); ?>
                </<?php echo esc_attr($heading_tags); ?>>
            <?php endif; ?>

            <?php if (!empty($top_content)) : ?>
                <div class="top-content">
                    <?php echo wp_kses_post($top_content); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($bullet_points)) : ?>
                <ul class="bullet-points">
                    <?php foreach ($bullet_points as $b) : ?>
                        <?php 
                            $points = trim($b['points'] ?? ''); 
                            if ($points === '') continue;
                        ?>
                        <li><?php echo esc_html($points); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif;?>
        </div> <!-- fa_left -->
    
        <?php if($form == true){?>
            <div class="fa_right">
                <a class="btn desktop-none quote_toggle" href="javascript:void();">Get a Quote</a>
                <div class="mob_quote_form">
                    <div class="mob_quote_inner">
                        <a class="btn desktop-none close_mob_quote_form" href="javascript:void();"><i class="fa-solid fa-x"></i></a>
                        <?php echo do_shortcode('[gravityform id="1" title="true" ajax="true"]'); ?>
                    </div>
                </div>
        <?php if ( $ff_image && $ff_content ) : ?>
            <div class="ff_content_image">
                <div class="ff_image">
                    <?php echo wp_get_attachment_image( $ff_image, 'full' ); ?>
                </div><!-- ff_image -->

                <div class="ff_content">
                    <?php echo $ff_content; ?>
                </div><!-- ff_content -->
            </div><!-- ff_content_image -->
        <?php endif; ?>
            </div>
        <?php }?>
    </div>
</section>
<?php endif; ?>