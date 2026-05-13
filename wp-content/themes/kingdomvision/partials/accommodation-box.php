<?php

/**
 * Accommodation Card Template (ACF-based with RoomBoss fallback)
 * Displays a single accommodation listing with image, price, and booking options
 * 
 * Uses ACF fields for property data and RoomBoss API for live pricing
 * Requires: get_the_ID(), get_field(), get_the_terms(), get_hotel_rooms()
 */

try {
    // ✅ STEP 1: Validate post context
    $post_id = get_the_ID();
    if (empty($post_id)) {
        return;
    }

    // Ensure ACF is active
    if (!function_exists('get_field')) {
        return;
    }

    // ✅ STEP 2: Retrieve and validate ACF fields
    $is_roomboss = (bool) get_field('is_roomboss', $post_id);
    $image = has_post_thumbnail($post_id) ? get_post_thumbnail_id($post_id) : get_template_directory_uri().'/images/placeholder-featured.jpg';
    $short_desc = get_field('bs_short_description', $post_id) ? trim( sanitize_text_field( get_field('bs_short_description', $post_id) ) ) : '';
    $db_price = (float) get_field('min_room_price', $post_id) ? get_field('min_room_price', $post_id) : '';
    $overlay = (bool) get_field('overlay', $post_id) ? get_field('overlay', $post_id) : false;
    $hotel_tid = (int) get_field('acc_hotel_id', $post_id) ? get_field('acc_hotel_id', $post_id) : '';

    // ✅ STEP 3: Process resort and area information
    
    $display_categories = [];
    $parent_term_name = '';
    $child_term_names = [];
    
    $categories = get_the_terms($post_id, 'accommodation-cat');
    
    if (!empty($categories) && !is_wp_error($categories)) {
        $parent_term_id = 0;
        
        // Find the parent term (parent ID is 0)
        foreach ($categories as $category) {
            if (intval($category->parent) === 0) {
                $parent_term_name = str_replace(' Accommodation', '', sanitize_text_field($category->name));
                $parent_term_id = $category->term_id;
                break; // Found the parent, no need to continue this loop
            }
        }
    
        // Collect child terms if a parent was found
        if ($parent_term_id > 0) {
            foreach ($categories as $category) {
                if (intval($category->parent) === $parent_term_id) {
                    $child_term_names[] = sanitize_text_field($category->name);
                }
            }
        }
        
        // Assemble the display string: Parent, Child1, Child2
        if (!empty($parent_term_name)) {
            $display_categories[] = $parent_term_name;
        }
        if (!empty($child_term_names)) {
            $display_categories = array_merge($display_categories, $child_term_names);
        }
    }
    // ✅ STEP 5: Calculate pricing with fallback to RoomBoss
    $rb_price = 0;
    $rb = $GLOBALS['kv_roomboss_current'] ?? null;
    if (!empty($rb) && isset($rb['min_price'])) {
        if (function_exists('cf_log')) {
            cf_log('inside rb', 'rb_full');
        }
        $rb_price = (float) $rb['min_price'];
    }
    $price = ($db_price > 0) ? $db_price : $rb_price;

    if (function_exists('cf_log')) {
        cf_log('db_price', 'rb_full');
        cf_log($db_price, 'rb_full');
        cf_log('rb_price', 'rb_full');
        cf_log($rb_price, 'rb_full');
    }
    $areas_display = implode(', ', $display_categories);

    $image_url = '';
    if (!empty($image)) {
        if (is_numeric($image)) {
            // Image is attachment ID
            $img = wp_get_attachment_image_src((int) $image, 'large');
            if (!empty($img[0])) {
                $image_url = esc_url_raw($img[0]);
            }
        } elseif (is_array($image)) {
            // Image is array (ACF image field)
            if (!empty($image['sizes']['large'])) {
                $image_url = esc_url_raw($image['sizes']['large']);
            } elseif (!empty($image['url'])) {
                $image_url = esc_url_raw($image['url']);
            }
        } elseif (is_string($image)) {
            // Image is string URL
            $image_url = esc_url_raw($image);
        }
    }

    // ✅ STEP 6: Process accommodation image
    // Fallback to placeholder if no image found
    if (empty($image_url)) {
        $image_url = get_template_directory_uri() . '/images/placeholder-accomo.jpg';
    }

    // ✅ STEP 7: Get room count safely
    $room_count = 0;
    if (!empty($hotel_tid) && function_exists('get_hotel_rooms')) {
        $hotel_data = get_hotel_rooms($hotel_tid);
        if (is_array($hotel_data) && isset($hotel_data['rooms'])) {
            $room_count = count($hotel_data['rooms']);
        }
    }

    // ✅ STEP 8: Prepare button state
    $post_title = get_the_title($post_id);
    $post_permalink = get_permalink($post_id);

} catch (Throwable $e) {
    // ❌ Handle unexpected errors (Exceptions and PHP 7+ Errors)
    error_log('Error in accommodation-box template: ' . $e->getMessage());
    return;
}

?>

<?php if (!empty($post_title)) : ?>
<div class="result-card accom-card <?php echo $overlay ? 'has-overlay' : ''; ?>" data-hotel-id="<?php echo esc_attr($hotel_tid); ?>">

    <a href="<?php echo esc_url($post_permalink); ?>">
        <div class="accom-image" style="background-image: url('<?php echo esc_url($image_url); ?>');"
            aria-label="<?php echo esc_attr($post_title); ?>"
            role="img" title="<?php echo esc_attr($post_title); ?>">
            <?php if ($is_roomboss) : ?>
                <?php echo wp_get_attachment_image(30809, 'thumbnail', true, ['class' => 'is_roomboss-icon', 'alt' => 'Live availability']); ?>
            <?php endif; ?>
        </div>

        <div class="accom-content">
            <h3><?php echo esc_html($post_title); ?></h3>

            <!-- Price Display -->
            <?php if ($price > 0) : ?>
                <p class="price">
                    From JPY <?php echo number_format_i18n((float) $price); ?>
                    <span>/ night</span>
                </p>
            <?php endif; ?>

            <!-- Area and Resort Info -->
            <?php if (!empty($areas_display)) : ?>
                <p class="area_resort">
                    <?php echo esc_html($areas_display); ?>
                </p>
            <?php endif; ?>

            <!-- Short Description -->
            <?php if (!empty($short_desc)) : ?>
                <p class="desc"><?php echo esc_html($short_desc); ?></p>
            <?php endif; ?>

            <!-- Action Button -->
            <?php if ($is_roomboss) : ?>
                <span class="book_btn btn" data-room_id="<?php echo esc_attr($post_id); ?>">
                    Book Now
                </span>
            <?php else : ?>
                <span class="enquire_btn btn" data-room_id="<?php echo esc_attr($post_id); ?>">
                    Check Availability
                </span>
            <?php endif; ?>

            <!-- View Link -->
            <span class="view_book btn">
                View
            </span>
        </div>
    </a>

</div>
<?php endif; ?>