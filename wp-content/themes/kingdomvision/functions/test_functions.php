<?php
// Debug/test triggers
@$_GET['hz_test'] == 'yes' ? add_action('wp_head', 'hz_testing') : '';
@$_GET['test'] == 'yes' ? add_action('wp_head', 'testing') : '';
@$_GET['del_reviews'] == 'yes' ? add_action('wp_head', 'del_reviews') : '';
@$_GET['chk_dup_reviews'] == 'yes' ? add_action('wp_head', 'chk_dup_reviews') : '';
@$_GET['update_menu_order'] == 'yes' ? add_action('wp_head', 'kv_update_accommodation_menu_order') : '';
@$_GET['get_acc_by_order'] == 'yes' ? add_action('wp_head', 'kv_get_accommodation_by_menu_order') : '';
@$_GET['get_product_reviews'] == 'yes' ? add_action('wp_head', 'kv_cron_fetch_product_reviews_fn') : '';
@$_GET['fetch_reviews'] == 'yes' ? add_action('wp_head', 'kv_cron_fetch_reviews_fn') : '';

function del_reviews(){
    $allposts = get_posts( array('post_type' => 'reviews', 'numberposts' => -1) );
    foreach ( $allposts as $eachpost ) {
        pre( 'deleting review '. $eachpost->ID );
        wp_delete_post( $eachpost->ID, true ); // Set to 'true' to bypass trash
        pre( 'deleted review '. $eachpost->ID );
    }
}

function chk_dup_reviews() {
    $allposts = get_posts(array('post_type' => 'reviews', 'numberposts' => -1));
    $store_ids = [];

    // Collect all store_review_id values with their post IDs
    foreach ($allposts as $eachpost) {
        pre( 'post id' );
        pre( $eachpost->ID );
        
        $store_id = get_field('store_review_id', $eachpost->ID);
        pre( 'store_review_id' );
        pre( $store_id );
    }
}

function hz_testing(){
    // kv_cron_fetch_reviews_fn();
    // hz_get_data_from_booking_sys_func();
    // pre( __media_sideload_image( KV_BOOKING_SYSTEM_BASE . '/storage/photos/1/Phoenix Hotel/Phoenix Hotel superior-singe-1-1536x1024.jpg', 75759) );
    // hz_get_data_from_booking_sys_func();
    // show_all_post_types();
    pre( hz_get_parent_category( 7321 ) );
}

function testing(){
    hz_get_data_from_booking_sys_func();
}

/*show all post types in wp_head*/

function test_fetch_reviews_fn() {
    
    $store_id = 'japanskiexperience-com1';
    $api_key  = 'ae0427dc29a62e2320110a6c157bc45b';

    $per_page = 100;
    $page     = 1;
    $sort     = 'date_created';

    global $wpdb;
    while ( true ) {
        $url = add_query_arg([
            'store'    => $store_id,
            'per_page' => $per_page,
            'page'     => $page,
            'sort'     => $sort,
        ], 'https://api.reviews.io/merchant/reviews');

        $response = wp_remote_get( $url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'store'        => $store_id,
                'apikey'       => $api_key,
            ],
            'timeout' => 20,
        ]);

        if ( is_wp_error( $response ) ) {
            break;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['reviews'] ) ) {
            cf_log( 'No more reviews', 'review_cron', 'txt', true, true );
            break;
        }

        /*
         * Store company-level stats ONCE (page 1)
         */
        if ( $page === 1 && ! empty( $body['stats'] ) ) {

            update_option( 'kv_company_total_reviews', (int) $body['stats']['total_reviews'], false );
            update_option( 'kv_company_average_rating', (float) $body['stats']['average_rating'], false );
        }

        pre( 'page' );
        pre( $page );

        pre( 'total_reviews' );
        pre( $body['stats']['total_reviews'] );

        pre( 'reviews count' );
        pre( count( $body['reviews'] ) );

        /*
         * Process reviews
         */
        foreach ( $body['reviews'] as $review ) {
            pre( 'review' );
            pre( $review );
            if ( empty( $review['store_review_id'] ) ) {
                continue;
            }
            // Prevent duplicates
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                    'store_review_id',
                    $review['store_review_id']
                )
            );
            pre( 'exist' );
            pre( $exists );

            if ( $exists ) {
                continue;
            }

            $author = trim(
                html_entity_decode(
                    ( $review['reviewer']['first_name'] ?? '' ) . ' ' .
                    ( $review['reviewer']['last_name'] ?? '' )
                )
            );

            $post_id = wp_insert_post([
                'post_title'   => sanitize_text_field( $author ?: 'Anonymous Review' ),
                'post_type'    => 'reviews',
                'post_status'  => 'publish',
                'post_content' => $review['comments'] ?? '',
            ]);

            pre( 'post_id' );
            pre( $post_id );

            if ( ! $post_id || is_wp_error( $post_id ) ) {

                cf_log( 'review failed to create msg: '.$post_id->get_error_message() , 'err_review_cron', 'txt', false, true );
                continue;
            }
            pre( 'rating' );
            pre( $review['rating'] );


            pre( 'date_created' );
            pre( $review['date_created'] );


            pre( 'store_review_id' );
            pre( $review['store_review_id'] );

            /*
             * Save meta
             */
            update_post_meta( $post_id, 'review_rating', (int) $review['rating'] );
            update_post_meta( $post_id, 'review_date', $review['date_created'] );
            update_post_meta( $post_id, 'store_review_id', $review['store_review_id'] );

            $posted_date = 'Posted ' . date( 'F Y', strtotime( $review['date_created'] ) );

            if ( function_exists( 'update_field' ) ) {
                update_field( 'posted_date', $posted_date, $post_id );
            } else {
                update_post_meta( $post_id, 'posted_date', $posted_date );
            }
            /*
             * Assign review tags → CPT taxonomy (slug: review_tags)
             */
            if ( ! empty( $review['tags'] ) && is_array( $review['tags'] ) ) {

                $tag_slugs = [];

                foreach ( $review['tags'] as $tag ) {
                    if ( ! empty( $tag['tag'] ) ) {
                        $tag_slugs[] = sanitize_title( $tag['tag'] );
                    }
                }

                pre( 'tags' );
                pre( $tag_slugs );

                if ( ! empty( $tag_slugs ) ) {
                    wp_set_object_terms(
                        $post_id,
                        $tag_slugs,
                        'review_tags',
                        false
                    );

                    cf_log( 'Tags: '.implode(',', $tag_slugs).' added to review: '.$post_id, 'review_cron', 'txt', true, true );
                }
            }
        }

        $page++;
    }
}

// function __media_sideload_image($file, $post_id = 0, $desc = null, $return_type = 'id') {

//     $file = str_replace(' ', '%20', $file);
//     $thumbnail_id = 0;
//     if (empty($file) || !filter_var($file, FILTER_VALIDATE_URL)) {
//         return new WP_Error('invalid_url', 'Invalid image URL');
//     }

//     // Get clean filename from URL
//     $filename = wp_basename(parse_url($file, PHP_URL_PATH));

//     // OPTIONAL: check if attachment already exists
//     $existing = get_posts([
//         'post_type'   => 'attachment',
//         'post_status'  => 'inherit',
//         'numberposts'  => 1,
//         'fields'       => 'ids',
//         'meta_query'   => [
//             [
//                 'key'     => '_source_url',
//                 'value'   => pathinfo($filename, PATHINFO_FILENAME),
//                 'compare' => 'LIKE'
//             ]
//         ]
//     ]);

//     pre( 'file name' );
//     pre( $filename );

//     if (!empty($existing)) {
//         pre( 'existing' );
//         pre( $existing );
//         $thumbnail_id = $existing[0];
//     } else {

//         pre( 'not exist' );
//         require_once ABSPATH . 'wp-admin/includes/file.php';
//         require_once ABSPATH . 'wp-admin/includes/media.php';
//         require_once ABSPATH . 'wp-admin/includes/image.php';

//         $file_array = [
//             'name'     => $filename,
//             'tmp_name' => download_url($file),
//         ];

//         pre( 'filearray' );
//         pre( $file_array );

//         if (is_wp_error($file_array['tmp_name'])) {
//             return $file_array['tmp_name'];
//         }

//         $thumbnail_id = media_handle_sideload($file_array, $post_id, $desc);

//         pre( 'id' );
//         pre( $thumbnail_id );
//         if (is_wp_error($thumbnail_id)) {
//             @unlink($file_array['tmp_name']);
//             return $thumbnail_id;
//         }

//         add_post_meta($thumbnail_id, '_source_url', $file);
//     }

//     $post_thmb_id = get_post_thumbnail_id( $post_id );

//     if( $post_thmb_id != $thumbnail_id ){
//         set_post_thumbnail( $post_id, $thumbnail_id );
//     }

//     return ($return_type === 'id') ? $thumbnail_id : wp_get_attachment_url($thumbnail_id);
// }

function kv_update_accommodation_menu_order() {

    // Step 1: Get all accommodation posts ordered alphabetically (ASC)
    $args = [
        'post_type'      => 'accommodation',
        'posts_per_page' => 100,
        'paged'          => 1,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
        'post_status'    => 'any',
        'fields'         => 'ids', // optimize query
    ];

    $posts = get_posts($args);

    if (empty($posts)) {
        return;
    }

    // Step 2: Total count
    $total = count($posts);

    // Step 3: Assign menu_order (descending from total → 1)
    foreach ($posts as $index => $post_id) {

        $menu_order = 465;

        wp_update_post([
            'ID'         => $post_id,
            'menu_order' => $menu_order,
        ]);
    }
}

function kv_get_accommodation_by_menu_order() {

    // Step 1: Get all accommodation posts ordered alphabetically (ASC)
    $args = [
        'post_type'      => 'accommodation',
        'posts_per_page' => 100,
        'paged'          => 1,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
        'post_status'    => 'any',
        'fields'         => 'ids', // optimize query
    ];

    $posts = get_posts($args);

    if (empty($posts)) {
        return;
    }

    foreach ($posts as $key => $post_id) {
        pre( get_post_field('menu_order', $post_id) );
        pre( get_the_title( $post_id ) );
    }
}

function hz_clean_string( $string ){
    $html = html_entity_decode( $string );
    pre( $html );
    $clean = preg_replace('/[^A-Za-z0-9 ]/', '', $html);
    pre( $clean );
    return $clean;
}

function get_product_reviews() {

    $store = 'japanskiexperience-com1';
    $api_key  = 'ae0427dc29a62e2320110a6c157bc45b';
    $per_page = 100;
    $sort     = 'date_created';

    global $wpdb;

    $accommodation_ids = get_posts([
        'post_type'      => 'accommodation',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => 'prd_sku',
                'compare' => 'EXISTS',
            ],
        ],
    ]);

    $sku = 13;
    // foreach ( $accommodation_ids as $acc_id ) {

    //     $sku .= get_field( 'prd_sku', $acc_id ).';';
    // }

    $page = 1; // ✅ RESET per SKU

    while ( true ) {

        $url = 'https://api.reviews.io/product/review?store=' . $store . '&sku=' . $sku . '&per_page='.$per_page.'&page='.$page.'&verified_only=true&comments_only=true';

        pre( $sku );

        $response = wp_remote_get( $url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'store'        => $store,
                'apikey'       => $api_key,
            ],
            'timeout' => 20,
        ]);

        if ( is_wp_error( $response ) ) {
            break;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        $reviews = $body['reviews'];
        $data = $reviews[ 'data' ];

        if ( empty( $data ) ) {
            cf_log( "No more reviews for SKU: {$sku}", 'review_cron', 'txt', true, true );
            break;
        }

        foreach ( $data as $review ) {

            if ( empty( $review['product_review_id'] ) ) {
                continue;
            }
            // pre( $review['reviewer']['first_name'] );
            $first_name = hz_clean_string( $review['reviewer']['first_name'] );
            // pre( $first_name );
            // pre( hz_clean_string( $review['reviewer']['last_name'] ) );
            pre( $review );
            }

        $page++; // ✅ pagination only for THIS SKU
    }
}

