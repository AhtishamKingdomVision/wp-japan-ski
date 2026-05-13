<?php
// Review Post type
add_action('init', 'reviews_post_type');
function reviews_post_type()
{
    register_post_type('reviews', array(
        'labels' => array(
            'name' => __('Reviews'),
            'singular_name' => __('Reviews')
        ),
        'supports' => array(
            'title',
            'editor',
            //'thumbnail'
        ),
        'public' => true,
        'hierarchical' => false,
        'show_ui' => true,
        'query_var' => true,
        'menu_icon' => 'dashicons-testimonial'
    ));
    register_taxonomy('reviews_catgory', ['reviews'], [
        'label' => __('Reviews Category', 'txtdomain'),
        'hierarchical' => true,
        'rewrite' => ['slug' => 'reviews-catgory'],
        'show_admin_column' => true,
        'show_in_rest' => true,
        'labels' => [
            'singular_name' => 'Review Category',
            'all_items' => 'All Category',
            'edit_item' => 'Edit Category',
            'view_item' => 'View Category',
            'update_item' => 'Update Category',
            'add_new_item' => 'Add New Category',
            'new_item_name' => 'New Category Name',
            'search_items' => 'Search Categories',
            'parent_item' => 'Parent Category',
            'parent_item_colon' => 'Parent Category:',
            'not_found' => 'No Category found',
        ]
    ]);
    register_taxonomy_for_object_type('reviews_catgory', 'reviews');
    register_taxonomy( 'review_tags', 'reviews', [ 'hierarchical' => false ] );

}

// Review Shortcode
add_shortcode('reviews', 'codex_reviews');
function codex_reviews($atts = [])
{

    // normalize attribute keys, lowercase
    $atts = array_change_key_case( (array)$atts, CASE_LOWER );

    // override default attributes with user attributes
    $_atts = shortcode_atts(
        array(
            'tags' => [],      // optional taxonomy slug
            'category' => '',      // optional taxonomy slug
            'perpage'  => '20',    // number of reviews to fetch
        ), $atts
    );

    ob_start();

    $tags = $_atts['tags'];
    $cat = isset( $_atts['category'] ) && !empty( $_atts['category'] ) ? [ $_atts['category'] ] : [];
    $perPage = $atts[ 'perpage' ];
    // optional category filte
    if (!empty($_atts['category'])) {
        $cat = $_atts['category'];
    }
    $args = get_reviews_args( $tags, $cat, $perPage );

    $loop = new WP_Query($args);

    if ($loop->have_posts()) :
        echo '<div class="reviews-carousel">';
        while ($loop->have_posts()) : $loop->the_post();
            $date    = get_field('posted_date', get_the_ID());
            $c_title = get_field('custom_title', get_the_ID());
            $rating  = get_post_meta(get_the_ID(), 'review_rating', true) ?: 5;
            ?>
            <div class="item">
                <div class="title home_rev_title">
                    <?php
                    for ($i = 1; $i <= $rating; $i++) {
                        echo '<i class="fa fa-star"></i>';
                    }
                    ?>
                </div>
                <div class="excerpt more"><?php the_content(); ?></div>
                <div class="title home_rev_title">
                    <?php echo $c_title ? $c_title : get_the_title(); ?>
                </div>                
            </div>
            <?php
        endwhile;
        echo '</div>';
    endif;

    wp_reset_postdata();
    return ob_get_clean();
}

function company_reviews_shortcode($atts){
    // Fetch stored values
    $review_data     = get_review_data();
    $average_rating  = $review_data['average']; //kv_company_average_rating
    $total_reviews   = $review_data['total']; //'kv_company_total_reviews'

    $title           = $review_data['title'];
    $reviews_url     = 'https://www.reviews.io/company-reviews/store/japanskiexperience-com1';

    if ($total_reviews <= 0) {
        return ''; // no reviews
    }

    $average_rating = $average_rating;

    ob_start();
    ?>
    <div class="kv-reviews-summary">
        <div class="rate_star top_style">
            <img src="<?php echo home_url() ?>/wp-content/themes/kingdomvision/images/rating-star.svg"
                 class="attachment-full size-full"
                 alt="Rating stars"
                 decoding="async">
        </div>
        <!-- <span class="kv-stars" aria-label="<?php //echo esc_attr($average_rating); ?> out of 5 stars"> -->
            <?php
            //$full = floor($average_rating);
            //$half = ($average_rating - $full >= 0.5);

            //for ($i = 1; $i <= 5; $i++) {
            //    if ($i <= $full) {
            //        echo '<span class="star full">★</span>';
            //    } elseif ($half && $i === $full + 1) {
            //        echo '<span class="star half">★</span>';
            //    } else {
            //        echo '<span class="star empty">★</span>';
            //    }
           // }
            ?>
        <!-- </span> -->

        <span class="kv-rating-text">
            <?php echo esc_html($average_rating); ?> | <?php echo esc_html(number_format($total_reviews)); ?> Japan Ski Experience reviews
        </span>

        <a class="kv-reviews-link" href="<?php echo esc_url($reviews_url); ?>" target="_blank" rel="noopener">
            View All
        </a>

    </div>
    <?php

    return ob_get_clean();
}

add_shortcode('sku_reviews', 'company_reviews_shortcode');

// Review Shortcode
//add_shortcode('product_reviews', 'codex_product_reviews');
function codex_product_reviews($atts = [])
{
    // Fetch stored values
    $review_data     = get_review_data();
    $average_rating  = $review_data['average']; //kv_company_average_rating
    $total_reviews   = $review_data['total']; //'kv_company_total_reviews'
    $title           = $review_data['title'];
    $reviews_url     = 'https://www.reviews.io/company-reviews/store/japanskiexperience-com1';

    if ($total_reviews <= 0) {
        return ''; // no reviews
    }

    $average_rating = number_format((float)$average_rating, 2);

    ob_start();
    ?>
    <div class="kv-reviews-summary">

        <span class="kv-stars" aria-label="<?php echo esc_attr($average_rating); ?> out of 5 stars">
            <?php
            $full = floor($average_rating);
            $half = ($average_rating - $full >= 0.5);

            for ($i = 1; $i <= 5; $i++) {
                if ($i <= $full) {
                    echo '<span class="star full">★</span>';
                } elseif ($half && $i === $full + 1) {
                    echo '<span class="star half">★</span>';
                } else {
                    echo '<span class="star empty">★</span>';
                }
            }
            ?>
        </span>

        <span class="kv-rating-text">
            <?php echo esc_html($average_rating); ?> | <?php echo esc_html($total_reviews); ?> Reviews
        </span>

        <div class="kv-review-title"><?php echo esc_html($title); ?></div>

        <a class="kv-reviews-link" href="<?php echo esc_url($reviews_url); ?>" target="_blank" rel="noopener">
            View All
        </a>

    </div>
    <?php

    return ob_get_clean();
}