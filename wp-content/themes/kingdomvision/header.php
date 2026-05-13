<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <title><?php wp_title(); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <?php
    $favicon = get_field('favicon_image', 'option');
    if ($favicon) {
        echo '<link rel="shortcut icon" href="' . $favicon . '" type="image/x-icon" />';
    } else {
        echo '<link rel="shortcut icon" href="' . get_stylesheet_directory_uri() . '/images/favicon.png" type="image/x-icon" />';
    }

    wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<?php

echo '<div class="main_wrapper full-section">';
    
    // Main Wrapper
    $logo          = get_field('logo', 'option');
    $search        = get_field('search', 'option');
    $buttons       = get_field('header_buttons', 'option');
    $header_filter = get_field('header_filter', 'option');
    $header_option = get_field('header_option');
    // if($header_option == 'colored'):
        // echo '<div class="header_space"></div>';
    // endif;

    echo '<header class="newHeader '.esc_attr($header_option).'" role="header">';
        echo '<div class="topHeader">';
            echo '<div class="container">';
                echo '<div class="logoWrap" aria-label="Go to homepage">';
                    echo '<a href="'.esc_url( home_url('/')).'">';
                        echo wp_get_attachment_image($logo, 'full', false, array('alt' => esc_attr( get_bloginfo('name') . ' logo' )));
                    echo '</a>';
                    if( get_post_type() == 'accommodation'){
                        echo'<div class="accReviewWrapper">';
                            echo do_shortcode('[company_rating]');
                        echo'</div>'; #accReviewWrapper
                    }
                echo '</div>'; #logoWrap

                if($header_filter == true){
                    echo '<div class="resortFilterWrap">';
                      echo do_shortcode('[newResortFilters]');
                    echo '</div>'; # /resortFilterWrap
                }

                if ( ! empty($search) ){
                    echo '<div class="searchWrap">';
                        echo '<form method="get" id="searchform" action="'.esc_url( home_url('/')).'" role="search" aria-label="Site Search">';
                            echo '<div class="search-text">';
                                echo '<label for="search-input" class="screen-reader-text">Search resorts</label>';
                                echo '<input type="text" placeholder="Search..." value="'.esc_attr( get_search_query() ).'" name="s" id="search-input" autocomplete="off" />';
                                echo '<button type="submit" id="searchsubmit" aria-label="Submit Search">';
                                echo '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><path d="M21 21l-4.35-4.35"></path></svg>';
                                echo '</button>';
                            echo '</div>';
                        echo '</form>';
                    echo '</div>'; #searchWrap
                }
            echo '</div>'; #container
        echo '</div>'; #topHeader
        echo '<div class="menuHeader">';
            echo '<div class="container">';
                echo '<div class="navWrap">';
                    if (has_nav_menu('main-menu')) {
                        wp_nav_menu(array(
                            'theme_location' => 'main-menu',
                            'menu_class' => 'mainMenu'
                        ));
                    }
                echo '</div>'; #navWrap
                echo '<div class="btnWrap" aria-label="Header Quick Links">';
                    if ( ! empty($buttons) && is_array($buttons) ) {
                        echo '<ul>';
                            foreach ( $buttons as $btn ) {

                                $headlink    = $btn['headlink'] ?? [];
                                $link_url    = $headlink['url'] ?? '#';
                                $link_title  = $headlink['title'] ?? '';
                                $link_target = $headlink['target'] ?? '_self';
                               
                                echo '<li>';
                                    echo '<a class="btn" href="'.esc_url($link_url).'" target="'.esc_attr($link_target).'">';
                                        echo esc_html($link_title);
                                    echo '</a>';
                                echo '</li>';
                            }
                        echo '</ul>';
                    }
                echo '</div>'; #btnWrap
            echo '</div>'; #container
        echo '</div>'; #topHeader
        get_template_part('mobile-header');

    echo '</header>';

    if (!is_front_page()){
        echo '<div class="breadcrumb-wrapper full-section"
            role="Breadcrumb" 
            aria-label="Breadcrumb">';
            echo '<div class="container">'; 
                if ( function_exists('yoast_breadcrumb') ) {
                    yoast_breadcrumb(
                        '<nav class="yoast-breadcrumbs" aria-label="Breadcrumbs">',
                        '</nav>'
                    );
                }
            echo '</div>';
        echo '</div>';
    }

?>

