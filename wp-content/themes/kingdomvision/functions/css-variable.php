<?php

// Css Variable
add_action('wp_head', 'ufg_dynamic_css_vars', 99);
function ufg_dynamic_css_vars() {

    $colors = get_field('colors', 'option') ?: [];
    $fonts  = get_field('font_sizes', 'option') ?: [];
    global $ufg_global_fonts;
    $ufg_global_fonts = $fonts;
    // Page Banner Opacity
    $overlay_transparency = get_field('overlay_transparency' , get_the_ID());

    if (empty($colors) && empty($fonts)) return;

    echo "<style id='acf-css-vars'>:root {\n";

    if (!empty($colors['bodybgcolor'])) {
        echo "  --body-bg-color: " . esc_attr($colors['bodybgcolor']) . ";\n";
    }
    if (!empty($colors['headerbg'])) {
        echo "  --header-bg-color: " . esc_attr($colors['headerbg']) . ";\n";
    }
    if (!empty($colors['headerbghover'])) {
        echo "  --header-bg-hover: " . esc_attr($colors['headerbghover']) . ";\n";
    }
    if (!empty($colors['footerbg'])) {
        echo "  --footer-bg-color: " . esc_attr($colors['footerbg']) . ";\n";
    }

    if (!empty($colors['body_color'])) {
        echo "  --body-color: " . esc_attr($colors['body_color']) . ";\n";
    }
    if (!empty($colors['heading_color'])) {
        echo "  --heading-color: " . esc_attr($colors['heading_color']) . ";\n";
    }
    if (!empty($colors['anchor_color'])) {
        echo "  --awb-link-color: " . esc_attr($colors['anchor_color']) . ";\n";
    }

    if (!empty($colors['anchor_hover_color'])) {
        echo "  --awb-link-hover-color: " . esc_attr($colors['anchor_hover_color']) . ";\n";
    }

    if (!empty($colors['btn_color'])) {
        echo "  --btn-color: " . esc_attr($colors['btn_color']) . ";\n";
    }

    if (!empty($colors['button_color'])) {
        echo "  --button-color: " . esc_attr($colors['button_color']) . ";\n";
    }

    if (!empty($colors['button_hover'])) {
        echo "  --button-hover-color: " . esc_attr($colors['button_hover']) . ";\n";
    }

    if (!empty($colors['nav_color'])) {
        echo "  --nav-color: " . esc_attr($colors['nav_color']) . ";\n";
    }

    if (!empty($colors['nav_hover'])) {
        echo "  --nav-hover: " . esc_attr($colors['nav_hover']) . ";\n";
    }
    
    if (!empty($colors['blog_anchor'])) {
        echo "  --blog-anchor-color: " . esc_attr($colors['blog_anchor']) . ";\n";
    }

    if (!empty($colors['blog_anchor_hover'])) {
        echo "  --blog-anchor-hover: " . esc_attr($colors['blog_anchor_hover']) . ";\n";
    }

    if (!empty($fonts['body_font_size'])) {
        echo "  --body-font-size: " . esc_attr($fonts['body_font_size']) . ";\n";
    }

    if (!empty($fonts['jfont_size'])) {
        echo "  --button-font-size: " . esc_attr($fonts['jfont_size']) . ";\n";
    }

    if (!empty($fonts['anchor_font_size'])) {
        echo "  --anchor-size: " . esc_attr($fonts['anchor_font_size']) . ";\n";
    }

    if (!empty($fonts['menu_footer'])) {
        echo "  --menu-size: " . esc_attr($fonts['menu_footer']) . ";\n";
    }

    // Page Banner Opacity
    if (!empty($overlay_transparency)) {
        echo "  --banner-opacity: 0." . esc_attr($overlay_transparency) . ";\n";
    }

    foreach (['h1','h2','h3','h4','h5','h6'] as $tag) {
        if (!empty($fonts["{$tag}_size"])) {
            echo "  --{$tag}-font-size: " . esc_attr($fonts["{$tag}_size"]) . ";\n";
        }
    }

    echo "}\n</style>";
}