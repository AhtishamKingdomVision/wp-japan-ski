<?php
/**
 * Booking System Sync Functions
 * 
 * All cron jobs, API helpers, image import, REST sync endpoints,
 * and debug triggers for syncing data from the Booking System / RoomBoss APIs.
 */

/**
 * Build HTTP request arguments for Booking System API calls
 * Constructs standard headers, timeout, and authorization configuration
 * 
 * @return array HTTP request arguments with method, headers, timeout, and cookies
 */
function booking_sys_api_args()
{
    try {
        // ✅ STEP 1: Validate authorization token is defined
        if (!defined('KV_BS_authToken') || empty(KV_BS_authToken)) {
            error_log('KV_BS_authToken is not defined or empty');
            return [];
        }

        $token = trim(KV_BS_authToken);
        if (empty($token)) {
            error_log('KV_BS_authToken is empty after trimming');
            return [];
        }

        // ✅ STEP 2: Build request configuration
        $args = [
            'method'      => 'POST',
            'timeout'     => 120,
            'redirection' => 5,
            'httpversion' => '1.1',
            'headers'     => [
                'Authorization' => 'Bearer ' . $token,
            ],
            'body'        => [],
            'cookies'     => [],
        ];

        // ✅ STEP 3: Return configured arguments
        return $args;

    } catch (Exception $e) {
        // ❌ Catch unexpected errors
        error_log('Error in booking_sys_api_args: ' . $e->getMessage());
        return [];
    }
}

/**
 * Find a post by its exact title
 *
 * @param string $title     Exact post title to search for
 * @param string $post_type Post type to search within
 * @return WP_Post|null First matching post or null if not found
 */
function get_post_by_title($title, $post_type = 'post')
{
    if (empty($title)) {
        return null;
    }

    $posts = get_posts([
        'post_type'      => sanitize_text_field($post_type),
        'title'          => sanitize_text_field($title),
        'post_status'    => 'any',
        'posts_per_page' => 1,
    ]);

    return !empty($posts) ? $posts[0] : null;
}

/**
 * Extract and sort bedroom counts from room data
 *
 * @param array $rooms Array of room data with 'no_of_bedrooms' key
 * @return array Sorted array of bedroom counts
 */
function get_hotel_num_bedrooms($rooms)
{
    if (empty($rooms) || !is_array($rooms)) {
        return [];
    }

    $bedrooms = wp_list_pluck($rooms, 'no_of_bedrooms');
    $bedrooms = array_map('intval', array_filter($bedrooms));
    sort($bedrooms, SORT_NUMERIC);

    return $bedrooms;
}

/**
 * Look up accommodation category term ID by booking system resort ID
 *
 * @param string|int $resort_id Booking system resort ID
 * @return int|null Term ID if found, null otherwise
 */
function hz_get_term_id_by_resort_id($resort_id)
{
    if (empty($resort_id)) {
        return null;
    }

    $terms = get_terms([
        'taxonomy'   => 'accommodation-cat',
        'hide_empty' => false,
        'number'     => 1,
        'meta_query' => [
            [
                'key'     => 'bs_resort_id',
                'value'   => sanitize_text_field($resort_id),
                'compare' => '=',
            ],
        ],
    ]);

    return (!is_wp_error($terms) && !empty($terms)) ? intval($terms[0]->term_id) : null;
}

/**
 * Insert or update a term in a custom taxonomy and return term ID.
 *
 * If $parent_id is provided, the function creates/updates the term
 * as a child of the given parent.
 *
 * @param string $taxonomy  Taxonomy slug.
 * @param string $term_name Human-readable term name.
 * @param int    $parent_id Optional parent term ID.
 * @param string $slug      Optional explicit slug.
 *
 * @return int|null Term ID on success, null on failure.
 */
function kv_upsert_taxonomy_term($taxonomy, $term_name, $parent_id = 0, $slug = '')
{
    $taxonomy = trim((string) $taxonomy);
    $term_name = trim(wp_strip_all_tags((string) $term_name));
    $parent_id = absint($parent_id);
    $slug = trim((string) $slug);

    if ($taxonomy === '' || $term_name === '' || !taxonomy_exists($taxonomy)) {
        return null;
    }

    if ($parent_id > 0) {
        $parent_term = term_exists($parent_id, $taxonomy);
        if (!$parent_term) {
            return null;
        }
    }

    $slug = ($slug !== '') ? sanitize_title($slug) : sanitize_title($term_name);
    $parent_lookup = $parent_id > 0 ? $parent_id : null;

    $existing = term_exists($slug, $taxonomy, $parent_lookup);
    if (!$existing) {
        $existing = term_exists($term_name, $taxonomy, $parent_lookup);
    }

    if ($existing) {
        $term_id = is_array($existing) ? intval($existing['term_id']) : intval($existing);
        if ($term_id < 1) {
            return null;
        }

        // Keep hierarchy consistent when parent is provided.
        if ($parent_id > 0) {
            $term_obj = get_term($term_id, $taxonomy);
            if (!is_wp_error($term_obj) && $term_obj && intval($term_obj->parent) !== $parent_id) {
                $updated = wp_update_term($term_id, $taxonomy, ['parent' => $parent_id]);
                if (!is_wp_error($updated) && !empty($updated['term_id'])) {
                    $term_id = intval($updated['term_id']);
                }
            }
        }

        return $term_id;
    }

    $insert_args = [
        'slug' => $slug,
    ];

    if ($parent_id > 0) {
        $insert_args['parent'] = $parent_id;
    }

    $inserted = wp_insert_term($term_name, $taxonomy, $insert_args);
    if (is_wp_error($inserted) || empty($inserted['term_id'])) {
        return null;
    }

    return intval($inserted['term_id']);
}

// function check_if_image_exists($url) {
//     global $wpdb;
//     // Set Product Image
//     $image_url = $url;
//     $image_name = @$prod['image'];
//     $ext = strtolower( pathinfo($image_name, PATHINFO_EXTENSION) );
//     $image_id = 0;
//     $image_url .= $image_name;
//     $isImageCDN = false;

//     if ( ! in_array($ext, ['jpg', 'jpeg', 'png']) ) {
//         $isImageCDN = true;
//         $image_url = $image_name;
//     }
    
//     if( !empty($image_name) ) {

//         $thumbnail = $wpdb->get_row("SELECT * FROM $wpdb->postmeta 
//                         WHERE meta_key = '_thumbnail_name' AND meta_value = '{$image_name}' ");
        
//         if( !empty($thumbnail) ) {
//             $image_id = get_post_meta($thumbnail->post_id, '_thumbnail_id', true);
//         }

//         if( ! $image_id ) {
//             if( $isImageCDN ) {
//                 // pre('CDN');
//                 $image_id = __media_sideload_image( $image_url, 0, null, 'id');
//             }
//             else {
//                 // pre('JPEG');
//                 $image_id = media_sideload_image( $image_url, 0, null, 'id');
//             }
//         }

//         return $image_id;
//     }
// }

function __media_sideload_image( $file, $post_id = 0, $desc = null, $return_type = 'html' ) {
    if ( ! empty( $file ) ) {

        $segments = explode('/', $file);
        $numSegments = count($segments);
        $matches = $segments[$numSegments - 1];

        $file_array         = array();
        // $file_array['name'] = wp_basename( $matches[0] );
        $file_array['name'] = wp_basename( $matches . '.jpg' );

        // Download file to temp location.
        $file_array['tmp_name'] = download_url( $file );

        // If error storing temporarily, return the error.
        if ( is_wp_error( $file_array['tmp_name'] ) ) {
            return $file_array['tmp_name'];
        }

        // Do the validation and storage stuff.
        $id = media_handle_sideload( $file_array, $post_id, $desc );

        // If error storing permanently, unlink.
        if ( is_wp_error( $id ) ) {
            @unlink( $file_array['tmp_name'] );
            return $id;
        }

        // Store the original attachment source in meta.
        add_post_meta( $id, '_source_url', $file );

        // If attachment ID was requested, return it.
        if ( 'id' === $return_type ) {
            return $id;
        }

    }
}

/**
 * Find an existing media attachment by filename (name + extension).
 * Matches against the _wp_attached_file meta to avoid re-uploading duplicates.
 *
 * @param string $filename Filename including extension, e.g. "photo.jpg"
 * @return int|null Attachment post ID if found, null otherwise
 */
function kv_find_attachment_by_filename($filename) {
    if (empty($filename)) {
        return null;
    }

    $results = get_posts([
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => '_wp_attached_file',
                'value'   => $filename,
                'compare' => 'LIKE',
            ],
        ],
    ]);

    return !empty($results) ? intval($results[0]) : null;
}

/**
 * Return existing attachment ID matched by filename, or sideload and return new ID.
 *
 * @param string $url     Image URL to sideload
 * @param int    $post_id Parent post ID for the attachment
 * @return int|null Attachment ID on success, null on failure
 */
function kv_sideload_or_find_image($url, $post_id) {
    $path     = parse_url($url, PHP_URL_PATH);
    $filename = $path ? basename($path) : '';

    if (empty($filename)) {
        return null;
    }

    $existing_id = kv_find_attachment_by_filename($filename);
    if ($existing_id) {
        return $existing_id;
    }

    $ext = strtolower( pathinfo($filename, PATHINFO_EXTENSION) );
    if( empty($ext) ) {
        $attachment_id = __media_sideload_image($url, $post_id, null, 'id');
    }
    else {
        $attachment_id = media_sideload_image($url, $post_id, null, 'id');
    }

    return is_wp_error($attachment_id) ? null : intval($attachment_id);
}

/**
 * Register admin meta boxes to display pending (non-sideloaded) image URLs as a gallery.
 */
add_action('add_meta_boxes', 'kv_register_pending_images_meta_boxes');
function kv_register_pending_images_meta_boxes() {
    add_meta_box(
        'kv_acco_pending_images',
        'Additional Images (from Booking System)',
        'kv_render_acco_pending_images_meta_box',
        'accommodation',
        'normal',
        'low'
    );
    add_meta_box(
        'kv_room_pending_images',
        'Additional Images (from Booking System)',
        'kv_render_room_pending_images_meta_box',
        'japan_rooms',
        'normal',
        'low'
    );
}

// Helper function to decode JSON gallery and return as array (if needed elsewhere)
function kv_get_meta_images_gallery($post_id, $meta_key) {
    $json = get_post_meta($post_id, $meta_key, true);
    return json_decode((string) $json, true);
}

function kv_render_acco_pending_images_meta_box($post) {
    $json = kv_get_meta_images_gallery($post->ID, 'acco_pending_images');
    kv_render_pending_images_gallery($json);
}

function kv_render_room_pending_images_meta_box($post) {
    $json = kv_get_meta_images_gallery($post->ID, 'room_pending_images');
    kv_render_pending_images_gallery($json);
}

/**
 * Render a simple image gallery from a JSON-encoded array of image URLs.
 *
 * @param string $json JSON-encoded array of image URLs
 */
function kv_render_pending_images_gallery($urls) {
    // $urls = json_decode((string) $json, true);

    if (empty($urls) || !is_array($urls)) {
        echo '<p>' . esc_html__('No additional images saved.') . '</p>';
        return;
    }

    echo '<div style="display:flex;flex-wrap:wrap;gap:8px;padding:8px 0;">';
    foreach ($urls as $url) {
        $url = esc_url((string) $url);
        if (empty($url)) {
            continue;
        }
        echo '<a href="' . $url . '" target="_blank" rel="noopener">'
            . '<img src="' . $url . '" style="width:120px;height:90px;object-fit:cover;border:1px solid #ddd;border-radius:3px;" loading="lazy" />'
            . '</a>';
    }
    echo '</div>';
}

/**
 * Import images from booking system API for accommodations or rooms.
 *
 * - Checks if an image already exists in the media library by filename before sideloading.
 * - Uploads/reuses ONLY the first valid image and sets it as the featured image.
 * - Remaining image URLs are NOT sideloaded; they are stored as a JSON array in post meta
 *   (acco_pending_images / room_pending_images) and rendered in a custom admin meta box.
 *
 * @param array    $images  Array of image data with 'url' key
 * @param int      $post_id WordPress post ID to attach images to
 * @param string   $type    'accommodation' or 'room'
 * @return string|void Success message or void on invalid input
 */
function hz_add_img_from_booking_sys($images, $post_id, $type) {
    // ✅ STEP 1: Validate inputs and load dependencies
    if (empty($images) || !is_array($images)) {
        return;
    }

    $post_id = intval($post_id);
    if ($post_id < 1) {
        return;
    }

    if (!in_array($type, ['accommodation', 'room'], true)) {
        return;
    }

    // ✅ STEP 2: Collect and validate all image URLs upfront
    $valid_urls = [];
    foreach ($images as $image) {
        $url = isset($image['url']) ? esc_url_raw(trim($image['url'])) : '';
        if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
            $valid_urls[] = $url;
        }
    }

    if (empty($valid_urls)) {
        return;
    }

    // ✅ STEP 4: Sideload or find first image → set as featured
    $first_url     = array_shift($valid_urls);
    $attachment_id = kv_sideload_or_find_image($first_url, $post_id);

    if ($type === 'accommodation') {

        if ($attachment_id) {
            set_post_thumbnail($post_id, $attachment_id);
        }

        // ✅ STEP 5: Save remaining URLs as JSON in post meta (no sideloading)
        update_post_meta(
            $post_id,
            'acco_pending_images',
            !empty($valid_urls) ? wp_json_encode(array_values($valid_urls)) : ''
        );

    } else {

        if ($attachment_id) {
            set_post_thumbnail($post_id, $attachment_id);
        }

        // ✅ STEP 5: Save remaining URLs as JSON in post meta (no sideloading)
        update_post_meta(
            $post_id,
            'room_pending_images',
            !empty($valid_urls) ? wp_json_encode(array_values($valid_urls)) : ''
        );
    }

    return 'Images successfully refreshed for accommodations and rooms';
}

/**
 * Fetch paginated list of properties from Booking System API
 * Retrieves accommodation properties with pagination metadata
 * 
 * @param int $page Page number to fetch (default: 1)
 * @param int $perPage Items per page (default: 1)
 * @return array|false Array with 'properties', 'total_pages', and 'pagination' keys, or false on error
 */
function hz_get_limited_properties($page = 1, $perPage = 1)
{
    try {
        // ✅ STEP 1: Validate and sanitize pagination parameters
        $page = intval($page);
        $perPage = intval($perPage);

        if ($page < 1) {
            $page = 1;
        }
        if ($perPage < 1) {
            $perPage = 1;
        }
        $apiUrl = add_query_arg([
            'page' => $page,
            'per_page' => $perPage,
        ], KV_BOOKING_SYSTEM_BASE . '/api/get-all-properties');

        if (isset( $_GET['is_test_cron'] ) && !empty( $_GET['is_test_cron'] ) ) {
            $property_id = $_GET['is_test_cron'];

            $apiUrl = add_query_arg([
                'propertyIds' => $property_id,
            ], KV_BOOKING_SYSTEM_BASE . '/api/get-properties-by-ids');
        }

        // ✅ STEP 3: Get API request arguments
        $args = booking_sys_api_args();

        if (empty($args)) {
            error_log('Failed to get booking system API args in hz_get_limited_properties');
            return false;
        }

        // ✅ STEP 4: Make API request
        $response = wp_remote_post($apiUrl, $args);

        // Validate response
        if (is_wp_error($response)) {
            cf_log( 'API Error: ' . $response->get_error_message(), 'err_api', 'txt', false, true );
            return false;
        }

        // ✅ STEP 5: Validate HTTP status code
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            cf_log( 'API returned HTTP ' . $http_code, 'err_api', 'txt', false, true );
            return false;
        }

        // ✅ STEP 6: Parse JSON response
        $body = wp_remote_retrieve_body($response);
        // pre( $body, 1);
        if (empty($body)) {
            cf_log('Empty response body from API', 'err_api', 'txt', false, true);
            return false;
        }

        $result = json_decode($body, true);

        if (!is_array($result)) {
            cf_log('Invalid JSON in API response', 'err_api', 'txt', false, true);
            return false;
        }

        // ✅ STEP 7: Validate properties in response
        if (empty($result['properties']) || !is_array($result['properties'])) {
            return [
                'properties' => [],
                'total_pages' => 0,
                'pagination' => [],
            ];
        }

        // ✅ STEP 8: Extract pagination data
        $total_pages = isset($result['pagination']['last_page']) ? intval($result['pagination']['last_page']) : 1;
        $pagination = is_array($result['pagination'] ?? null) ? $result['pagination'] : [];

        // ✅ STEP 9: Return structured response
        return [
            'properties' => $result['properties'],
            'total_pages' => $total_pages,
            'pagination' => $pagination,
        ];

    } catch (Exception $e) {
        // ❌ Catch unexpected errors
        error_log('Error in hz_get_limited_properties: ' . $e->getMessage());
        return false;
    }
}


/**
 * Fetch available hotels from RoomBoss API with pagination support
 *
 * @param string $hotelId    Query string of hotelId params (e.g. "hotelId=123&hotelId=456")
 * @param string $checkIn    Check-in date
 * @param string $checkOut   Check-out date
 * @param int    $guests     Number of guests
 * @param int    $offset     Pagination offset
 * @param int    $limit      Number of results to return
 * @return array{status: string, response: array, number_posts: int}
 */
function hz_get_limited_available_hotels($hotelId, $checkIn, $checkOut, $guests, $offset = 0, $limit = 5)
{
    try {
        // ✅ STEP 1: Validate required parameters
        if (empty($hotelId) || empty($checkIn) || empty($checkOut)) {
            return ['status' => 'fail', 'response' => 'Missing required parameters', 'number_posts' => 0];
        }

        $guests = intval($guests);
        $offset = intval($offset);
        $limit  = max(1, intval($limit));

        // ✅ STEP 2: Build API URL safely
        $apiUrl = add_query_arg([
            'checkIn'                => sanitize_text_field($checkIn),
            'checkOut'               => sanitize_text_field($checkOut),
            'numberGuests'           => $guests,
            'excludeConditionsNotMet' => 'true',
            'rate'                   => 'ota',
        ], 'https://api.roomboss.com/extws/hotel/v1/listAvailable?' . $hotelId);

        // ✅ STEP 3: Get API args and make request
        $args = booking_sys_api_args();
        $response = wp_remote_get($apiUrl, $args);

        // ✅ STEP 4: Validate response
        if (is_wp_error($response)) {
            cf_log($response->get_error_message(), 'err_api', 'txt', false, true);
            return ['status' => 'fail', 'response' => $response->get_error_message(), 'number_posts' => 0];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            cf_log('RoomBoss API returned HTTP ' . $status_code, 'err_api', 'txt', false, true);
            return ['status' => 'fail', 'response' => 'API returned HTTP ' . $status_code, 'number_posts' => 0];
        }

        // ✅ STEP 5: Parse and validate JSON response
        $responseBody = wp_remote_retrieve_body($response);
        if (empty($responseBody)) {
            return ['status' => 'fail', 'response' => 'Empty API response', 'number_posts' => 0];
        }

        $result = json_decode($responseBody, true);
        if (!is_array($result) || !isset($result['availableHotels'])) {
            cf_log('Invalid JSON structure from RoomBoss API', 'err_api', 'txt', false, true);
            return ['status' => 'fail', 'response' => 'Invalid API response structure', 'number_posts' => 0];
        }

        // ✅ STEP 6: Extract and paginate results
        $availableHotels = $result['availableHotels'];
        $number_posts = count($availableHotels);
        $hotels = array_slice($availableHotels, $offset, $limit);

        return ['status' => 'success', 'response' => $hotels, 'number_posts' => $number_posts];

    } catch (Exception $e) {
        error_log('Error in hz_get_limited_available_hotels: ' . $e->getMessage());
        return ['status' => 'fail', 'response' => $e->getMessage(), 'number_posts' => 0];
    }
}

// Cron Scheduling
add_filter('cron_schedules', 'hz_cron_schedule');
function hz_cron_schedule($schedules)
{
    $schedules['every_two_mins'] = array(
        'interval' => (60 * 2), // Every 2 mins
        'display'  => 'Every 2 Mins',
    );
    $schedules['every_three_mins'] = array(
        'interval' => (60 * 3), // Every 3 mins
        'display'  => 'Every 3 Mins',
    );
    $schedules['every_five_mins'] = array(
        'interval' => (60 * 5), // Every 5 mins
        'display'  => 'Every 5 Mins',
    );
    $schedules['every_12_hours'] = array(
        'interval' => (60 * 60 * 12), // Every 5 mins
        'display'  => 'Every 12 Hours',
    );
    return $schedules;
}

// cronjob every 15 minutes
add_action('init', 'hooks_for_crons');
function hooks_for_crons()
{
    if (! wp_next_scheduled('hz_get_data_from_booking_sys')) {
        // wp_schedule_event( time(), 'fifteen_minutes', 'hz_get_data_from_booking_sys' );
        wp_schedule_event(time(), 'every_12_hours', 'hz_get_data_from_booking_sys');
    }

    if ( ! wp_next_scheduled( 'kv_cron_fetch_reviews' ) ) {
        wp_schedule_event( time(), 'daily', 'kv_cron_fetch_reviews' );
    }

    if ( ! wp_next_scheduled( 'kv_cron_fetch_product_reviews' ) ) {
        wp_schedule_event( time(), 'daily', 'kv_cron_fetch_product_reviews' );
    }
}

function kv_map_icon_slug($label) {
    /*get data from theme options*/
    $icons = get_field('amm_icons', 'option');

    foreach ( $icons as $key => $icon ) {
         return strpos(strtolower( $label ), strtolower( $icon) ) ? $icons[$key] : null;
    }

}

/**
 * Sync accommodation and room data from Booking System API
 * Runs as WordPress cron job to fetch and update properties, rooms, and images
 * Iterates through paginated API results and creates/updates posts with metadata
 * 
 * @return void Updates WordPress posts and metadata via options-based pagination
 */

add_action('init', 'mycf_init_func');
function mycf_init_func() {
    if( @$_GET['run'] == 'dev' ){
        hz_get_data_from_booking_sys_func();
    }
}

add_action('hz_get_data_from_booking_sys', 'hz_get_data_from_booking_sys_func');
function hz_get_data_from_booking_sys_func()
{
    try {
        // ✅ STEP 1: Fetch and validate accommodation categories
        $accommodation_cat = get_terms([
            'taxonomy' => 'accommodation-cat',
            'hide_empty' => false,
            'exclude' => [61],
        ]);

        if (empty($accommodation_cat) || is_wp_error($accommodation_cat)) {
            cf_log('No accommodation categories found', 'err_key', 'txt', false, true);
            return;
        }

        // ✅ STEP 2: Initialize pagination variables from WordPress options
        $page_num = intval(get_option('hz_page', 1));
        if( @$_GET['hz_page'] && $_GET['hz_page'] > 0 ){
            $page_num = intval( $_GET['hz_page'] );
        }
        $total_pages = intval(get_option('hz_total_pages', 5));
        $per_page = 3;

        // ✅ STEP 3: Check if pagination is complete
        if ($page_num > $total_pages) {
            // Reset pagination for next cycle
            update_option('hz_page', 1, false);
            update_option('hz_post_order', 1, false);
            update_option('hz_total_pages', 5, false);
            cf_log('Cron cycle complete - pagination reset', 'cron_complete', 'txt', false, true);
            return;
        }

        // ✅ STEP 4: Fetch properties from Booking System API
        $limited_hotels = hz_get_limited_properties($page_num, $per_page);

        // ✅ STEP 5: Validate API response and extract properties
        if ($limited_hotels === false || !is_array($limited_hotels)) {
            cf_log( 'Failed to fetch properties from API for page ' . $page_num, 'api_fetch_failed', 'txt', true, true );
            return;
        }

        $properties = $limited_hotels['properties'] ?? [];
        // Handle empty properties
        if (empty($properties) || !is_array($properties)) {
            cf_log( 'No properties found at page ' . $page_num . ' of ' . $total_pages, 'empty_properties', 'txt', true, true );

            // Reset for next sync cycle
            update_option('hz_page', 1, false);
            update_option('hz_post_order', 1, false);
            update_option('hz_total_pages', 5, false);
            return;
        }

        // ✅ STEP 6: Update total pages from API response
        $hz_total_pages = $limited_hotels['total_pages'] ?? 0;
        if ($hz_total_pages > 0) {
            update_option('hz_total_pages', $hz_total_pages, false);
            $total_pages = $hz_total_pages;
        }

        sq_mapping_properties($properties);

        // ✅ STEP 8: Increment page number for next cron execution
        update_option('hz_page', intval($page_num) + 1, false);
        cf_log( 'Cron page updated to: ' . ($page_num + 1) . ' of ' . $total_pages, 'cron_progress', 'txt', true, true );

    } catch (Exception $e) {
        // ❌ Catch unexpected errors
        error_log('Error in hz_get_data_from_booking_sys_func: ' . $e->getMessage());
        cf_log(
            'Cron error: ' . $e->getMessage(),
            'cron_error',
            'txt',
            false,
            true
        );
    }
}

function sq_mapping_properties($properties) {
    if (empty($properties) || !is_array($properties)) {
        cf_log( 'No properties to process', 'no_properties', 'txt', false, true );
        return;
    }
    // pre($properties, 1);

    // ✅ STEP 7: Process each property
    foreach ($properties as $property) {
        // ✅ STEP 7a: Validate property structure
        if (!is_array($property) || empty($property['id'])) {
            continue;
        }

        // ✅ STEP 7b: Extract and validate basic property data
        $property_id = trim((string) $property['id']);
        if ($property_id === '') {
            continue;
        }

        $is_bed_bank_raw =  intval($property['isBedBank'] ?? 0);
        $is_roomboss = ( $is_bed_bank_raw === 0 );
        $is_enabled = $property['is_enabled'] == 1;

        $hotel_name = trim( wp_strip_all_tags( empty( $property['client_property_name'] ) ? $property['name'] : $property['client_property_name'] ));
        if ($hotel_name === '') {
            continue;
        }

        // Initialize metadata array
        $hotel_tid = '';
        $meta_input = [];
        $property_types = [];

        // ✅ STEP 7c: Set up property-specific metadata
        if ($is_roomboss) {
            $hotel_tid = trim((string) ($property['room_boss_hotel_id'] ?? ''));
            if ($hotel_tid !== '') {
                $meta_input['acc_hotel_id'] = $hotel_tid;
            }
            $meta_input['is_roomboss'] = 1;
        } else {
            // BedBank property
            $meta_input['is_roomboss'] = 0;
        }

        // ✅ STEP 7d: Extract accommodation details with safe defaults
        $detail = isset($property['detail']) && is_array($property['detail']) ? $property['detail'] : [];

        $hotel_desc          = trim((string) strip_tags($detail['long_description'] ?? ''));
        $client_hotel_desc   = trim((string) strip_tags($detail['client_long_description'] ?? ''));
        $list_desc           = trim((string) strip_tags($detail['list_description'] ?? ''));
        $unit_count          = intval($detail['unit_count'] ?? 0);
        $property_code       = trim((string) ($detail['property_code'] ?? ''));
        $phone_number        = trim((string) ($detail['phone_number'] ?? ''));
        $fax_number          = trim((string) ($detail['fax_number'] ?? ''));
        $media_code          = ($detail['media_code'] ?? 0);
        $email               = trim((string) ($detail['email'] ?? ''));
        $property_type       = trim((string) ($detail['property_type'] ?? ''));
        $no_of_bedrooms      = intval($detail['no_of_bedrooms'] ?? 0);
        $max_child_age       = intval($detail['max_child_age'] ?? 0);
        $trip_advisor_url    = trim((string) ($detail['trip_advisor_url'] ?? ''));
        $deposit_amount      = ($detail['deposit_amount'] ?? 0);
        $supplier_deposit    = ($detail['supplier_deposit'] ?? 0);
        $supplier_commission = ($detail['supplier_commission'] ?? 0);
        $supplier_markup     = ($detail['supplier_markup'] ?? 0);
        $sku_code            = ($detail['sku_code'] ?? 0);

        // ✅ STEP 7e: Extract rate plans and room types
        $extra_properties = (!empty($property['extra_properties']) && is_array($property['extra_properties'])) ? $property['extra_properties'] : [];

        $rateplans = is_array($property['ratePlanDescriptions'] ?? null) ? $property['ratePlanDescriptions'] : [];
        $roomTypes = is_array($property['rooms'] ?? null) ? $property['rooms'] : [];

        $resort_id = trim((string) ($property['resort_id'] ?? ''));
        $status = $is_enabled ? 'publish' : 'draft';
        $post_order = wp_count_posts( 'accommodation' );
        $post_order = $post_order ? $post_order->publish: 0;
        $address = trim((string) ($property['address_one'] ?? '') . (string) ($property['address_two'] ?? ''));
        $country = trim((string) ($property['country'] ?? ''));
        $latitude = trim((string) ($property['latitude'] ?? ''));
        $longitude = trim((string) ($property['longitude'] ?? ''));

        $resort = (isset($property['resort']) && is_array($property['resort'])) ? $property['resort'] : [];

        // ✅ STEP 7f: Check if accommodation post already exists
        $hotel = get_post_id_by_typeId($property_id, 'accommodation');
        $hotelid = !empty($hotel) ? $hotel : 0;

        // ✅ STEP 7g: Build accommodation metadata
        $num_bedrooms = get_hotel_num_bedrooms($roomTypes);
        $bedrooms = array_values(array_unique($num_bedrooms));

        $meta_input['bedroom_number'] = $bedrooms;
        $meta_input['property_id'] = $property_id;
        $meta_input['post_order'] = $post_order;
        $meta_input['accomodation_details_address'] = $address;
        $meta_input['_header_option'] = 'field_695261be5649d ';
        $meta_input['header_option'] = 'transparent ';

        if ($unit_count > 0) {
            $meta_input['unit_count'] = $unit_count;
        }

        if ($list_desc !== '') {
            $meta_input['bs_short_description'] = $list_desc;
        }

        if ($hotel_desc !== '') {
            $meta_input['quote_desc'] = $hotel_desc;
        }

        if ($client_hotel_desc !== '') {
            $meta_input['client_quote_desc'] = $client_hotel_desc;
        }

        if ($country !== '') {
            $meta_input['accomodation_details_acc_country'] = $country;
        }

        if ($latitude !== '') {
            $meta_input['accomodation_details_acc_latitude'] = $latitude;
        }

        if ($longitude !== '') {
            $meta_input['accomodation_details_acc_longitude'] = $longitude;
        }

        if ($property_code !== '') {
            $meta_input['acc_property_code'] = $property_code;
        }

        if ($phone_number !== '') {
            $meta_input['acc_phone_number'] = $phone_number;
        }

        if ($fax_number !== '') {
            $meta_input['acc_fax_number'] = $fax_number;
        }

        if ( !empty($media_code) ) {
            $meta_input['acc_media_code'] = $media_code;
        }

        if ($email !== '') {
            $meta_input['acc_email'] = $email;
        }

        if ($property_type !== '') {
            $property_types[] =$property_type;
        }

        if ($no_of_bedrooms > 0) {
            $meta_input['acc_no_of_bedrooms'] = $no_of_bedrooms;
        }

        if ($max_child_age > 0) {
            $meta_input['acc_max_child_age'] = $max_child_age;
        }

        if ($deposit_amount > 0) {
            $meta_input['acc_deposit_amount'] = $deposit_amount;
        }

        if ($trip_advisor_url !== '') {
            $meta_input['acc_trip_advisor_url'] = $trip_advisor_url;
        }

        if ($supplier_deposit > 0) {
            $meta_input['acc_supplier_deposit'] = $supplier_deposit;
        }

        if ($supplier_commission > 0) {
            $meta_input['acc_supplier_commission'] = $supplier_commission;
        }

        if ($supplier_markup > 0) {
            $meta_input['acc_supplier_markup'] = $supplier_markup;
        }

        if ( !empty($sku_code) ) {
            $meta_input['acc_sku_code'] = $sku_code;
        }

        $supplier_fields = 
        [
            'supplier_id'                         => 'id',
            'supplier_type'                       => 'type',
            'room_boss_vendor_id'                 => 'room_boss_vendor_id',
            'arrival_departure'                   => 'arrival_departure',
            'vendor_type_id'                      => 'vendor_type_id',
            'booking_permission_id'               => 'booking_permission_id',
            'supplier_code'                       => 'code',
            'supplier_name'                       => 'name',
            'supplier_notes'                      => 'notes',
            'supplier_email'                      => 'email',
            'supplier_address'                    => 'address',
            'supplier_telephone'                  => 'telephone_number',
            'backoffice_ref'                      => 'backoffice_ref',
            'auto_confirm'                        => 'auto_confirm',
            'account_notes'                       => 'account_notes',
            'travel_e_doc_notes'                  => 'travel_e_document_notes',
            'booking_coaches_strategy'            => 'booking_coaches_strategy',
            'product_type'                        => 'product_type',
            'check_in_time'                       => 'check_in_time',
            'check_out_time'                      => 'check_out_time',
            'terms_conditions'                    => 'terms_conditions',
            'supplier_description'                => 'description',
            'image_url'                           => 'image_url',
            'supplier_url'                        => 'url',
            'book_and_pay'                        => 'book_and_pay_enabled',
            'hide_request_if_book_and_pay_enabled'=> 'hide_request_if_book_and_pay_enabled',
            'supplier_latitude'                   => 'latitude',
            'supplier_longitude'                  => 'longitude',
            'supplier_currency_code'              => 'currency_code',
            'supplier_country_code'               => 'country_code',
            'supplier_location_code'              => 'location_code',
            'price_mode'                          => 'price_mode',
            'supplier_price'                      => 'price',
            'sup_is_bb'                           => 'is_bed_bank',
            'supp_created_at'                     => 'created_at',
            'supp_updated_at'                     => 'updated_at',
        ];

        $supplier = isset($detail['supplier']) && is_array($detail['supplier']) ? $detail['supplier'] : [];

        if (!empty($supplier)) {

            foreach ($supplier_fields as $acf_field => $api_key) {
                if (isset($supplier[$api_key]) && !empty( $supplier[$api_key] )) {
                    $meta_input[$acf_field] = $supplier[$api_key];
                }
            }
        }

        // ✅ STEP 7h: Prepare accommodation post data for insert/update
        $hotel_data = [
            'ID' => $hotelid,
            'post_title' => $hotel_name,
            'post_content' => $hotel_desc,
            'post_type' => 'accommodation',
            'post_status' => $status,
            'menu_order' => $post_order,
        ];

        // ✅ STEP 7i: Insert or update accommodation post
        $upd_hotel_id = wp_insert_post($hotel_data, true);

        if (is_wp_error($upd_hotel_id)) {
            cf_log( 'Error creating/updating accommodation: ' . $upd_hotel_id->get_error_message(), 'roomboss_hotels', 'txt', false, true );
            continue;
        }

       /* if not empty property_type add it in term "property_types" */
       
       if( !empty($property_types) ){

            $property_type_ids = [];
            foreach( $property_types as $ptype ){
                $term_id = kv_upsert_taxonomy_term('property_types', $ptype);
                if( !empty($term_id) ){
                    $property_type_ids[] = intval($term_id);
                }
            }

            if( !empty($property_type_ids) ){
                wp_set_object_terms($upd_hotel_id, $property_type_ids, 'property_types');
            }
         }

        if (!empty($resort) && !empty($resort_id) && !empty($resort['name'])) {

            $taxonomy = 'accommodation-cat'; // change if using custom taxonomy

            $resort_name = ucwords( $resort['name'].' Accommodation' );
            // $resort_slug = strtolower( $resort['name'].'-accommodation' );
            $resort_cat_ids = [];

            // 1. Create/Get Parent Term (Resort)
            $parent_id = kv_upsert_taxonomy_term($taxonomy, $resort_name, 0);
            if (empty($parent_id)) {
                cf_log('Failed to resolve parent term for resort: ' . $resort_name, 'roomboss_hotels', 'txt', false, true);
                continue;
            }
            $resort_cat_ids[] = intval($parent_id);
            
            // 2. Loop Locations → Create Child Terms
            $locations = isset($resort['locations']) && is_array($resort['locations']) ? $resort['locations'] : [];

            if (!empty($locations)) {
                $selected_locations = @$property['resort_location_id'] ?? [];

                foreach ($locations as $location) {
                    if( ! in_array($location['id'], $selected_locations) ) {
                        continue; // Skip locations not associated with this property
                    }

                    if (!is_array($location) || empty($location['location']) ) {
                        continue;
                    }

                    $child_cat_id = kv_upsert_taxonomy_term($taxonomy, $location['location'], $parent_id);
                    if (empty($child_cat_id)) {
                        continue;
                    }
                    $resort_cat_ids[] = intval($child_cat_id);

                }
            }

            // 3. Assign accommodation to parent/child resort category
            wp_set_object_terms($upd_hotel_id, $resort_cat_ids, $taxonomy);
            // pre($upd_hotel_id, 0);
            // pre($resort_cat_ids, 0);
            // pre($property, 1);
        }

        cf_log('Accommodation ' . $hotel_name . ' synced with ID: ' . $upd_hotel_id, 'roomboss_hotels');

        foreach ($meta_input as $meta_key => $meta_value) {
            update_post_meta($upd_hotel_id, $meta_key, $meta_value);
        }

        $unit_ids = [];

        if( !empty( $extra_properties ) ){

            foreach ( $extra_properties as $extra_property ) {
                if (!is_array($extra_property) || !isset($extra_property['data']) || !is_array($extra_property['data'])) {
                    continue;
                }

                $data = $extra_property['data'];
                
                if (!empty($data['options']) && is_array($data['options'])) {

                    $options = $data['options'];

                    if (!empty($data) && !empty($data['name'])) {

                        if ($data['name'] === 'Property Facilities' || $data['name'] === 'Unit Facilities') {
                            $aminity_ids = [];

                            foreach ($options as $op_key => $option) {
                                if (!is_array($option) || empty($option['name'])) {
                                    continue;
                                }

                                if (
                                    (!empty($option['value'])) ||
                                    (!empty($option['checked']) && $option['checked'] === true)
                                ) {
                                    $icon = isset($option['icon']) ? $option['icon'] : '';
                                    $label = $option['name'];

                                    // Convert label to icon slug (match your dropdown values)
                                    
                                    if( $data['name'] == 'Property Facilities' ){

                                        $term_id = kv_upsert_taxonomy_term('property_ammenites', $label);
                                        $aminity_ids[] = intval($term_id);
                                        update_field('field_69fb87ef2b007', $icon, 'property_ammenites_' . $term_id);
                                    }

                                    if( $data['name'] == 'Unit Facilities' ){

                                        $term_id = kv_upsert_taxonomy_term('unit_ammenites', $label);
                                        $unit_ids[] = intval($term_id);
                                        update_field('field_69fb8993792ae', $icon, 'unit_ammenites_' . $term_id);
                                    }
                                }
                            }

                            // 3. Assign accommodation to unit and aminity category
                            if( $aminity_ids ) {
                                wp_set_object_terms($upd_hotel_id, $aminity_ids, 'property_ammenites');
                            }
                            
                        }

                    }
                }
            }
        }

        // ✅ STEP 7l: Process rate plans if available
        if (!empty($rateplans) && is_array($rateplans)) {
            $rate_plan_rows = [];

            foreach ($rateplans as $rateplan) {
                /*check if client_rateplan_name contains keyword "discount" mark the accomdation as discount*/

                if (!empty($rateplan['client_rateplan_name']) && stripos($rateplan['client_rateplan_name'], 'discount') !== false) {
                    update_post_meta($upd_hotel_id, 'is_discount', 1);
                }

                $rate_plan_rows[] = [
                    'rate_plan_id' => $rateplan['rate_plan_id'] ?? '',
                    'rate_plan_name' => !empty(trim($rateplan['client_rateplan_name'] ?? '')) ?
                        trim(html_entity_decode($rateplan['client_rateplan_name'])) :
                        ($rateplan['names'] ?? ''),
                    'rate_plan_description' => !empty(trim($rateplan['client_description'] ?? '')) ?
                        trim(html_entity_decode($rateplan['client_description'])) :
                        ($rateplan['descriptions'] ?? ''),
                    'rate_plan_long_descriptions' => !empty(trim($rateplan['client_long_description'] ?? '')) ?
                        trim(html_entity_decode($rateplan['client_long_description'])) :
                        ($rateplan['long_descriptions'] ?? ''),
                ];
            }

            update_field('rate_plan', $rate_plan_rows, $upd_hotel_id);
        }

        // ✅ STEP 7m: Process room types for this accommodation
        $room_ids = [];
        $room_links = '';

        if (!empty($roomTypes) && is_array($roomTypes)) {
            foreach ($roomTypes as $key => $roomType) {
                // Validate room data structure
                if (!is_array($roomType) || empty($roomType['id'])) {
                    continue;
                }

                // Extract room basic info
                $room_name = !empty($roomType['client_unit_name']) ? trim( htmlentities($roomType['client_unit_name']) ) : trim( htmlentities($roomType['name']) );
                if (empty($room_name)) {
                    continue;
                }

                $room_id = trim($roomType['id']);

                // Extract room configuration
                $roomboss_room_id = isset( $roomType['room_boss_room_id'] ) && !empty( $roomType['room_boss_room_id'] ) ? intval($roomType['room_boss_room_id'] ) : 0;
                $pricing_model = isset( $roomType['pricing_model'] ) && !empty( $roomType['pricing_model'] ) ? intval($roomType['pricing_model'] ) : 0;
                $maxNumberGuests = isset( $roomType['no_of_guest'] ) && !empty( $roomType['no_of_guest'] ) ? intval($roomType['no_of_guest'] ) : 0;
                $numberBedrooms = isset( $roomType['room_per_unit'] ) && !empty( $roomType['room_per_unit'] ) ? intval($roomType['room_per_unit'] ) : 0;
                $bedding_options = isset( $roomType['bedding_options'] ) && !empty( $roomType['bedding_options'] ) ? json_decode($roomType['bedding_options'] ) : 0;
                $guest_types = isset( $roomType['guest_types'] ) && !empty( $roomType['guest_types'] ) ? json_decode($roomType['guest_types'] ) : 0;
                $numberBathrooms = isset( $roomType['no_of_bathrooms'] ) && !empty( $roomType['no_of_bathrooms'] ) ? intval($roomType['no_of_bathrooms'] ) : 0;
                $maxNumberAdults = isset( $roomType['maximum_adults'] ) && !empty( $roomType['maximum_adults'] ) ? intval($roomType['maximum_adults'] ) : 0;
                $minNumberAdults = isset( $roomType['minimum_adults'] ) && !empty( $roomType['minimum_adults'] ) ? intval( $roomType['minimum_adults'] ) : 0;
                $maxNumberChildren = isset( $roomType['additional_children'] ) && !empty( $roomType['additional_children'] ) ? intval($roomType['additional_children'] ) : 0;
                $maxNumberInfants = isset( $roomType['additional_infants'] ) && !empty( $roomType['additional_infants'] ) ? intval($roomType['additional_infants'] ) : 0;
                $room_sqm = isset( $roomType['square_meters'] ) && !empty( $roomType['square_meters'] ) ? intval($roomType['square_meters'] ) : 0;
                $standard_adults = isset( $roomType['standard_adults'] ) && !empty( $roomType['standard_adults'] ) ? trim($roomType['standard_adults']) : '';
                $no_of_units = isset( $roomType['no_of_units'] ) && !empty( $roomType['no_of_units'] ) ? intval($roomType['no_of_units']) : 0;
                $additional_children = isset( $roomType['additional_children'] ) && !empty( $roomType['additional_children'] ) ? intval($roomType['additional_children']) : 0;
                $include_children = isset( $roomType['include_children'] ) && !empty( $roomType['include_children'] ) ? intval($roomType['include_children']) : 0;
                $additional_infants = isset( $roomType['additional_infants'] ) && !empty( $roomType['additional_infants'] ) ? intval($roomType['additional_infants']) : 0;
                $include_infants = isset( $roomType['include_infants'] ) && !empty( $roomType['include_infants'] ) ? intval($roomType['include_infants']) : 0;
                $desc = isset( $roomType['description'] ) && !empty( $roomType['description'] ) ? trim($roomType['description']) : '';
                $client_desc = isset( $roomType['client_description'] ) && !empty( $roomType['client_description'] ) ? trim($roomType['client_description']) : '';

                $status = isset( $roomType['global_status'] ) && $roomType['global_status'] == 1 ? 'publish' :'draft';

                // Get or create room post
                $rooms = get_hotel_rooms($property_id, [$room_id]);
                if (!is_array($rooms) || !isset($rooms['rooms']) || !is_array($rooms['rooms'])) {
                    continue;
                }

                if (empty($rooms['rooms']) || !isset($rooms['rooms'][0])) {
                    continue;
                }

                $roomid = $rooms['rooms'][0]->ID;

                // Build room post data
                $room_data = [
                    'ID' => $roomid,
                    'post_parent' => $upd_hotel_id,
                    'post_title' => $room_name,
                    'post_type' => 'japan_rooms',
                    'post_status' => $status,
                    // 'post_status' => 'publish'
                ];

                // Initialize room metadata
                $room_meta_input = [
                    'property_id'       => $property_id,
                    'pricing_model'     => $pricing_model,
                    'roomboss_room_id'  => $roomboss_room_id,
                    'actual_room_id'    => $room_id,
                    'room_guests'       => $maxNumberGuests,
                    'room_bedroom'      => $numberBedrooms,
                    'room_bathroom'     => $numberBathrooms,
                    'room_adults'       => $maxNumberAdults,
                    'room_children'     => $maxNumberChildren,
                    'room_infants'      => $maxNumberInfants,
                    'minimum_adults'    => $minNumberAdults,
                    'maximum_adults'    => $maxNumberAdults,
                    'standard_adults'   => $standard_adults,
                    'no_of_units'       => $no_of_units,
                    'additional_children' => $additional_children,
                    'include_children' => $include_children,
                    'additional_infants' => $additional_infants,
                    'include_infants' => $include_infants,
                    'room_desc' => $desc,
                    'client_description' => $client_desc,
                    'jp_hotel_link' => [
                        'title' => $hotel_name,
                        'url' => get_permalink($upd_hotel_id),
                        'target' => '_blank',
                    ],
                    'jp_hotel' => [$upd_hotel_id],
                ];

                // Add RoomBoss-specific metadata if applicable
                if ($is_roomboss) {
                    $room_desc = isset($roomType['description']) ? trim($roomType['description']) : '';
                    $room_tid = trim($roomType['room_boss_room_id'] ?? '');

                    $room_data['post_content'] = $room_desc;
                    $room_meta_input['room_hotel_id'] = $hotel_tid;
                    $room_meta_input['room_type_id'] = $room_tid;
                    $room_meta_input['is_roomboss'] = 1;
                } else {
                    // BedBank room
                    $room_meta_input['is_roomboss'] = 0;
                }

                // Insert or update room post
                $upd_room_id = wp_insert_post($room_data);

                if (is_wp_error($upd_room_id)) {
                    cf_log( 'Error creating/updating room: ' . $upd_room_id->get_error_message(), 'roomboss_rooms', 'txt', false, true );
                    continue;
                }

                if (!empty($bedding_options)) {
                    $bed_term_ids = [];
                    foreach ($bedding_options as $bed_key => $bedding_option) {
                        
                        if( !is_array( $bedding_option ) ){
                            $bedding_option[] = $bedding_option;
                        }

                        foreach ($bedding_option as $key => $option) {
                            /*add terms even if th*/
                            if ( is_int( $option ) ) {
                                continue;
                            }
                            $bed_term_id = kv_upsert_taxonomy_term('bedding_options', $option);
                            $bed_term_ids[] = intval($bed_term_id);
                        }
                    }

                    if($bed_term_ids){
                        wp_set_object_terms($upd_room_id, $bed_term_ids, 'bedding_options');
                    }
                }

                if (!empty($guest_types) && isset($guest_types[0])) {

                    $new_guest_types = $guest_types[0];
                    if( !empty( $new_guest_types ) ){
                        $guest_term_ids = [];

                        foreach ($new_guest_types as $guest_type) {
                        
                            if( !is_array( $guest_type ) ){
                                $guest_type[] = $guest_type;
                            }

                            foreach ($guest_type as $type) {

                                if (is_numeric($type)) continue;

                                $guest_term_id = kv_upsert_taxonomy_term('guest_types', $type);
                                $guest_term_ids[] = intval($guest_term_id);
                            }
                        }

                        if($guest_term_ids){
                            wp_set_object_terms($upd_room_id, $guest_term_ids, 'guest_types');
                        }
                    }
                }

                
                if( $unit_ids ) {
                    wp_set_object_terms($upd_hotel_id, $unit_ids, 'unit_ammenites');
                }

                $room_ids[] = $upd_room_id;
                $room_links .= '<a href="' . admin_url('post.php?post=' . $upd_room_id . '&action=edit') . '">' . esc_html($room_name) . '</a>' . "\n";

                cf_log('Room ' . $room_name . ' synced with ID: ' . $upd_room_id, 'roomboss_rooms');

                // Add room images
                $room_imgs = $roomType['images'] ?? [];
                hz_add_img_from_booking_sys($room_imgs, $upd_room_id, 'room');

                // Update room metadata
                foreach ($room_meta_input as $meta_key => $meta_value) {
                    update_post_meta($upd_room_id, $meta_key, $meta_value);
                }
            }
        }

        // ✅ STEP 7n: Update accommodation with room relationships
        update_field('jp_rooms_link', $room_links, $upd_hotel_id);
        update_field('jp_rooms', $room_ids, $upd_hotel_id);
        update_option('hz_post_order', $post_order + 1, false);

        // ✅ STEP 7j: Add accommodation images and update metadata
        hz_add_img_from_booking_sys(@$property['images'], $upd_hotel_id, 'accommodation');
    }
}

// REST Sync Endpoints
add_action('rest_api_init', function () {
    register_rest_route('kv/v1', '/accommodation/sync', [
        'methods'  => 'POST',
        'callback' => 'kv_sync_accommodation',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * REST API endpoint: Sync accommodation from booking system payload
 * Creates or updates an accommodation post with metadata based on property data
 *
 * @param WP_REST_Request $request JSON payload with property_location and property_details
 * @return array|WP_Error Success response with post info, or WP_Error on failure
 *
 * 7-step flow:
 * 1. Validate request payload
 * 2. Determine property source (RoomBoss vs BedBank)
 * 3. Resolve hotel ID and validate
 * 4. Look up existing accommodation post
 * 5. Prepare and insert/update post
 * 6. Update property metadata
 * 7. Return success response
 */
function kv_sync_accommodation(WP_REST_Request $request)
{
    // ✅ STEP 1: Validate request payload
    $property_data = $request->get_json_params();

    if (empty($property_data)) {
        return new WP_Error('invalid_payload', 'property_data is required', ['status' => 400]);
    }

    $property_location = @$property_data['property_location'] ?? null;
    if (empty($property_location) || !is_array($property_location)) {
        return new WP_Error('invalid_payload', 'property_location is required', ['status' => 400]);
    }

    // ✅ STEP 2: Determine property source (RoomBoss vs BedBank)
    $is_bedbank = empty($property_location['room_boss_hotel_id']);

    // ✅ STEP 3: Resolve hotel ID and validate
    if ($is_bedbank) {
        $hotel_id = sanitize_text_field($request->get_param('property_id'));
    } else {
        $hotel_id = sanitize_text_field($property_location['room_boss_hotel_id'] ?? '');
    }

    if (empty($hotel_id)) {
        return new WP_Error('missing_hotel_id', 'Unable to resolve hotel_id', ['status' => 400]);
    }

    $is_enabled = (bool) ($property_location['is_enabled'] ?? false);
    $status     = $is_enabled ? 'publish' : 'draft';

    // ✅ STEP 4: Look up existing accommodation post
    $existing = get_post_id_by_typeId($hotel_id, 'accommodation');
    $post_id  = $existing ? intval($existing) : 0;
    $property_details = @$property_data['property_details'] ?? [];

    // ✅ STEP 5: Prepare and insert/update post
    $title = $property_location['name'] ?? 'Accommodation';

    $content = $property_details['client_long_description']
        ?? $property_details['long_description']
        ?? '';

    $post_args = [
        'ID'           => $post_id,
        'post_type'    => 'accommodation',
        'post_content' => $content,
        'post_title'   => wp_strip_all_tags($title),
        'post_status'  => $status,
    ];

    $upd_post_id = wp_insert_post($post_args, true);
    $action      = $post_id > 0 ? 'updated' : 'created';

    if (is_wp_error($upd_post_id)) {
        return new WP_Error('post_error', $upd_post_id->get_error_message(), ['status' => 500]);
    }

    // ✅ STEP 6: Update property metadata
    if ($is_bedbank) {
        update_post_meta($upd_post_id, 'property_id', $hotel_id);
    } else {
        update_post_meta($upd_post_id, 'acc_hotel_id', $hotel_id);
    }

    // $bedrooms = (array) $property_details['no_of_bedrooms'];
    // $area = (array) $property_details['no_of_bedrooms'];
    // $type = (array) $property_details['no_of_bedrooms'];

    // update_post_meta($upd_post_id, 'property_id', $property_id ? 1 : 0);
    // update_post_meta($post_id, 'property_location', $property_location ?? []);
    // update_post_meta($post_id, 'property_details', $property_details);
    // update_post_meta($post_id, 'important_info', $property_data['important_info'] ?? []);
    // update_post_meta($post_id, 'extra_properties', $property_data['extra_properties'] ?? []);
    // update_post_meta($post_id, 'property_gallery', $property_data['property_gallery'] ?? []);
    // update_post_meta($upd_post_id, 'area', $area );
    // update_post_meta($upd_post_id, 'type', $type );
    update_post_meta($upd_post_id, 'is_roomboss', $is_bedbank ? 0 : 1);

    // ✅ STEP 7: Return success response
    return [
        'success'  => true,
        'action'   => $action,
        'post_id'  => $upd_post_id,
        'hotel_id' => $hotel_id,
        'source'   => $is_bedbank ? 'bedbank' : 'roomboss',
    ];
}

add_action('rest_api_init', function () {
    register_rest_route('kv/v1', '/rooms/sync', [
        'methods'  => 'POST',
        'callback' => 'kv_sync_japan_rooms',
        'permission_callback' => '__return_true',
    ]);
});

function kv_sync_japan_rooms( WP_REST_Request $request ) {

    $payload = $request->get_json_params();

    if ( empty($payload['property_id']) || empty($payload['rooms']) ) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'property_id and rooms are required'
        ], 400);
    }

    $property_id = intval($payload['property_id']);

    $results = [];

    foreach ( $payload['rooms'] as $room ) {

        $is_roomboss  = empty($room['room_boss_room_id']) ? false : true;
        if ( empty($room['room_boss_room_id']) && empty($room['id']) ) {
            continue;
        }

        $external_id = $is_roomboss ? sanitize_text_field($room['room_boss_room_id']) : sanitize_text_field($room['id']);

        // ---- Find existing room ----
        $existing = get_post_id_by_typeId($external_id, 'room');

        $post_id = $existing ? $existing : 0;
        $room_args = [
            'ID'           => $post_id,
            'post_type'    => 'japan_rooms',
            'post_title'   => sanitize_text_field($room['name']),
            'post_content' => wp_kses_post($room['description'] ?? ''),
            'post_status'  => 'publish',
        ];

        $updated_post_id = wp_insert_post($room_args, true);
        $action  = $existing ? 'updated' : 'created';

        if ( is_wp_error($updated_post_id) ) {
            $room_type = $is_roomboss == true ? 'RoomBoss' : 'Bedbank';
            cf_log( 'Error '.$action.' '.$room_type.' room with id: '.$external_id, 'err_room_log', 'txt', false );
        }

        update_post_meta($updated_post_id, 'property_id', $property_id);
        update_post_meta($updated_post_id, 'is_roomboss', (int)$is_roomboss);

        // ---- Core meta (same for both cases) ----
        if( !$is_roomboss ){
            update_post_meta($updated_post_id, 'bb_room_id', $external_id);
        }
        elseif( $is_roomboss ){
            update_post_meta($updated_post_id, 'room_type_id', sanitize_text_field($room['room_boss_room_id']) );
        }

        $meta_map = [
            'standard_adults'     => intval($room['standard_adults'] ?? 0),
            'room_per_unit'       => sanitize_text_field($room['room_per_unit'] ?? ''),
            'no_of_units'         => intval($room['no_of_units'] ?? 0),
            'room_guests'         => intval($room['no_of_guest'] ?? 0),
            'room_bedroom'      => intval($room['no_of_bedrooms'] ?? 0),
            'room_bathroom'     => intval($room['no_of_bathrooms'] ?? 0),
            'room_sqm'       => intval($room['square_meters'] ?? 0),
            'room_adults'      => intval($room['maximum_adults'] ?? 0),
            'additional_children' => intval($room['additional_children'] ?? 0),
            'room_infants'  => intval($room['additional_infants'] ?? 0),
        ];

        foreach ( $meta_map as $key => $value ) {
            update_post_meta($updated_post_id, $key, $value);
        }

        $results[] = [
            'post_id' => $updated_post_id,
            'action'  => $action,
        ];
    }

    return new WP_REST_Response([
        'success'  => true,
        'synced'   => count($results),
        'rooms'    => $results,
    ], 200);
}

add_action( 'kv_cron_fetch_reviews', 'kv_cron_fetch_reviews_fn' );

function kv_cron_fetch_reviews_fn() {
    
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
        cf_log( 'reviews found on page: '.$page, 'review_cron', 'txt', true, true );

        /*
         * Store company-level stats ONCE (page 1)
         */
        if ( $page === 1 && ! empty( $body['stats'] ) ) {
            cf_log( 'updating total and average', 'review_cron', 'txt', true, true );

            update_option( 'kv_company_total_reviews', (int) $body['stats']['total_reviews'], false );
            update_option( 'kv_company_average_rating', (float) $body['stats']['average_rating'], false );
        }

        /*
         * Process reviews
         */
        foreach ( $body['reviews'] as $review ) {

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

            cf_log( 'exists' , 'err_review_cron', 'txt', true, true );
            cf_log( $exists , 'err_review_cron', 'txt', true, true );

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
                'post_title'   => !empty( $author ) ? sanitize_text_field( str_replace('&quot;', '', $author) ) : 'Anonymous Review',
                'post_type'    => 'reviews',
                'post_status'  => 'publish',
                'post_content' => $review['comments'] ?? '',
            ]);

            cf_log( 'post_id' , 'err_review_cron', 'txt', true, true );
            cf_log( $post_id , 'err_review_cron', 'txt', true, true );
            if ( ! $post_id || is_wp_error( $post_id ) ) {

                cf_log( 'review failed to create msg: '.$post_id->get_error_message() , 'err_review_cron', 'txt', false, true );
                continue;
            }
            /*
             * Save meta
             */
            update_post_meta( $post_id, 'review_rating', (int) $review['rating'] );
            update_post_meta( $post_id, 'review_date', $review['date_created'] );
            update_post_meta( $post_id, 'store_review_id', $review['store_review_id'] );

            cf_log( 'review created with post id: '.$post_id.' and store_review_id: '.$review['store_review_id'], 'review_cron', 'txt', true, true );

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

                if ( ! empty( $tag_slugs ) ) {
                    wp_set_object_terms( $post_id, $tag_slugs, 'review_tags' );

                    cf_log( 'Tags: '.implode(',', $tag_slugs).' added to review: '.$post_id, 'review_cron', 'txt', true, true );
                }
            }
        }

        $page++;
    }
}

add_action( 'kv_cron_fetch_product_reviews', 'kv_cron_fetch_product_reviews_fn' );
function kv_cron_fetch_product_reviews_fn() {
    $store = 'japanskiexperience-com1';
    $per_page = 100;
    $sort     = 'date_created';

    global $wpdb;

    // Get all unique SKUs from accommodations to fetch reviews per product
    $skus = $wpdb->get_col("SELECT DISTINCT meta_value FROM $wpdb->postmeta WHERE meta_key = 'prd_sku' AND meta_value != ''");

    if ( empty( $skus ) ) {
        return;
    }

    foreach ( $skus as $sku ) {
        $page = 1;

        while ( true ) {
            $url = 'https://api.reviews.io/product/review?store=' . $store . '&sku=' . $sku . '&per_page=' . $per_page . '&page=' . $page . '&verified_only=true&comments_only=true';

            $response = wp_remote_get( $url, [
                'headers' => [ 'Content-Type' => 'application/json' ],
                'timeout' => 20,
            ]);

            if ( is_wp_error( $response ) ) {
                break;
            }

            $body = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( empty( $body['reviews']['data'] ) ) {
                cf_log( "No more reviews for SKU: {$sku}", 'review_cron', 'txt', true, true );
                break;
            }

            $data = $body['reviews']['data'];

            foreach ( $data as $review ) {
                if ( empty( $review['product_review_id'] ) ) {
                    continue;
                }

                // Prevent duplicates
                $exists = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                        'product_review_id',
                        $review['product_review_id']
                    )
                );

                pre( 'exists' );
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
                    'post_title'   => $author ? sanitize_text_field( str_replace('&quot;', '', $author) ) : 'Anonymous Review',
                    'post_type'    => 'reviews',
                    'post_status'  => 'publish',
                    'post_content' => $review['review'] ?? '',
                ]);
                pre( 'post_id' );
                
                if ( ! $post_id || is_wp_error( $post_id ) ) {
                    continue;
                    }
                    
                pre( $post_id );

                update_post_meta( $post_id, 'review_rating', (int) $review['rating'] );
                update_post_meta( $post_id, 'review_date', $review['date_created'] );
                update_post_meta( $post_id, 'product_review_id', $review['product_review_id'] );
                update_post_meta( $post_id, 'product_sku', $sku ); // Store specific SKU

                $posted_date = 'Posted ' . date( 'F Y', strtotime( $review['date_created'] ) );

                if ( function_exists( 'update_field' ) ) {
                    update_field( 'posted_date', $posted_date, $post_id );
                } else {
                    update_post_meta( $post_id, 'posted_date', $posted_date );
                }

                wp_set_object_terms( $post_id, 494, 'reviews_catgory' );

                if ( ! empty( $review['tags'] ) && is_array( $review['tags'] ) ) {
                    $tag_slugs = [];
                    foreach ( $review['tags'] as $tag ) {
                        if ( ! empty( $tag['tag'] ) ) {
                            $tag_slugs[] = sanitize_title( $tag['tag'] );
                        }
                    }
                    if ( $tag_slugs ) {
                        wp_set_object_terms( $post_id, $tag_slugs, 'review_tags' );
                    }
                }
            }

            $page++;
        }
    }
}

function kv_display_product_reviews_shortcode($atts) {
    // Get SKU from attribute, or fallback to current post field
    $sku = isset($atts['sku']) ? sanitize_text_field($atts['sku']) : '';

    if ( empty($sku) ) {
        $sku = get_field('prd_sku');
    }

    if (empty($sku)) {
        return;
    }

    $query_args = [
        'post_type'      => 'reviews',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'tax_query'      => [
            [
                'taxonomy' => 'reviews_catgory',
                'field'    => 'term_id',
                'terms'    => 494,
            ],
        ],
        'meta_query'     => [
            [
                'key'     => 'product_sku',
                'value'   => $sku, // No need for semicolons if only one SKU is stored
                'compare' => '=',  // Use exact comparison
            ],
        ],
    ];

    $review_query = new WP_Query($query_args);
    $review_count = $review_query->found_posts;
    $is_slider    = ($review_count > 3);

    ob_start();

    if ($review_query->have_posts()) {

        echo '<div class="prop-reviews-track' . ($is_slider ? ' js-slick-reviews' : '') . '">';
        while ($review_query->have_posts()) {
            $review_query->the_post();
            $rating      = get_post_meta(get_the_ID(), 'review_rating', true);
            $posted_date = get_post_meta(get_the_ID(), 'posted_date', true);
            $stars       = intval($rating);
            ?>
            <div class="review-card">
                <div class="review-card-stars">
                    <?php for ($i = 1; $i <= 5; $i++) : 
                        $fill = ($i <= $stars) ? '#FFCC02' : 'rgba(255,255,255,0.15)';
                    ?>
                        <svg viewBox="0 0 20 20" fill="<?php echo $fill; ?>" width="14" height="14">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                    <?php endfor; ?>
                </div>
                <p class="review-quote"><?php echo wp_trim_words(get_the_content(), 50); ?></p>
                <div class="review-author">
                    <span class="review-author-name"><?php the_title(); ?></span>
                    <span><?php echo esc_html($posted_date); ?></span>
                </div>
            </div>
            <?php
        }
        echo '</div>';

        // if ($is_slider) : ?>
            <script>
                jQuery(document).ready(function($) {
                    $('.reviewCounts').html('<?php echo $review_count; ?>');
                    $('.js-slick-reviews:not(.slick-initialized)').slick({
                        infinite: true,
                        slidesToShow: 3,
                        slidesToScroll: 1,
                        arrows: true,
                        dots: false,
                        prevArrow: '<button type="button" class="slick-prev"><img src="' + kingdomVision.themeUrl + '/images/left_arrow.svg" alt="Previous"></button>',
                        nextArrow: '<button type="button" class="slick-next"><img src="' + kingdomVision.themeUrl + '/images/right_arrow.svg" alt="Next"></button>',
                        responsive: [{
                            breakpoint: 1024,
                            settings: {
                                slidesToShow: 2
                            }
                        }, {
                            breakpoint: 768,
                            settings: {
                                slidesToShow: 1
                            }
                        }]
                    });
                });
            </script>
        <?php //endif;
        wp_reset_postdata();
    } else {
        echo '<p style="color: rgba(255,255,255,0.5); text-align: center;">' . esc_html__('No verified guest reviews found for this property.', 'generatepress') . '</p>';
    }

    return ob_get_clean();
}
add_shortcode('kv_product_reviews', 'kv_display_product_reviews_shortcode');