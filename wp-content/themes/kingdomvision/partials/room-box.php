<?php

/**
 * Room Card Template (ACF-based)
 * Displays a single room with amenities and booking/inquiry options
 * 
 * Required $args keys:
 *   - room: WP_Post object for the room post
 *   - rb: RoomBoss API data (optional)
 *   - property_id: Numeric property/accommodation ID
 */

try {
    // ✅ STEP 1: Validate and extract arguments
    $args = is_array($args) ? $args : [];
    $room = $args['room'] ?? null;
    $property_id = intval($args['property_id'] ?? 0);

    // Validate room object exists
    if (empty($room) || !is_object($room)) {
        return;
    }

    $room_id = ($room->ID ?? 0);
    if (empty($room_id)) {
        return;
    }

    // ✅ STEP 2: Get accommodation post ID for related data
    $acc_id = 0;
    if (!empty($property_id)) {
        $acc_id = get_post_id_by_typeId($property_id, 'accommodation');
    }

    // ✅ STEP 3: Extract and sanitize room details from ACF
    $name = get_the_title($room_id);
    $guests = intval(get_field('room_guests', $room_id) ?? 0);
    $bedrooms = intval(get_field('room_bedroom', $room_id) ?? 0);
    $bathrooms = intval(get_field('room_bathroom', $room_id) ?? 0);
    $sqm = sanitize_text_field(get_field('room_sqm', $room_id) ?? '');

    // ✅ STEP 4: Process room image with fallback
    $image_url = (string) get_the_post_thumbnail_url($room_id, 'full');
    if (empty($image_url)) {
        $image_url = get_template_directory_uri() . '/images/placeholder-accomo.jpg';
    }
    $image_url = esc_url_raw($image_url);

    // ✅ STEP 5: Get accommodation details (property type and location)
    $is_roomboss = false;
    $area_list = [];
    $resort_name = '';

    if (!empty($acc_id)) {
        $is_roomboss = (bool) get_field('is_roomboss', $acc_id);

        // Get area information
        $area_field = get_field('area', $acc_id);
        if (!empty($area_field) && is_array($area_field)) {
            $area_list = array_filter(array_map('sanitize_text_field', $area_field));
        }

        // Get resort/category name
        $categories = get_the_terms($acc_id, 'accommodation-cat');
        if (!empty($categories) && !is_wp_error($categories)) {
            $resort_name = str_replace(' Accommodation', '', sanitize_text_field($categories[0]->name ?? ''));
        }
    }

    // ✅ STEP 6: Build location display string
    $location_display = '';
    if (!empty($area_list)) {
        $location_display = implode(', ', $area_list);
    }
    if (!empty($resort_name)) {
        $location_display = !empty($location_display) ? $location_display . ', ' . $resort_name : $resort_name;
    }

} catch (Exception $e) {
    // ❌ Handle unexpected errors
    error_log('Error in room-box template: ' . $e->getMessage());
    return;
}

?>

<div class="room-card t2" data-bedroom="<?php echo esc_attr($bedrooms); ?>">
    <div class="room-img"
        style="background-image: url('<?php echo esc_url($image_url); ?>');"
        aria-label="<?php echo esc_attr($name); ?>"
        role="img">
    </div>

    <div class="rc_cover t1box">
        <!-- Room Title -->
        <div class="room-title">
            <h3><?php echo esc_html($name); ?></h3>
        </div>

        <!-- Location Info -->
        <?php if (!empty($location_display)) : ?>
            <span><?php echo esc_html($location_display); ?></span>
        <?php endif; ?>

        <!-- Room Amenities -->
        <div class="room-info">
            <?php if ($guests > 0) : ?>
                <span class="guest">Max Guests: <?php echo esc_html($guests); ?></span>
            <?php endif; ?>

            <?php if ($bedrooms > 0) : ?>
                <span class="bad"><?php echo esc_html($bedrooms); ?> Bedrooms</span>
            <?php endif; ?>

            <?php if ($bathrooms > 0) : ?>
                <span class="bath"><?php echo esc_html($bathrooms); ?> Bathrooms</span>
            <?php endif; ?>

            <?php if (!empty($sqm)) : ?>
                <span class="sqm"><?php echo esc_html($sqm); ?> sqm</span>
            <?php endif; ?>
        </div>

        <!-- Action Buttons -->
        <div class="room-btns">
            <?php if ($is_roomboss) : ?>
                <button class="btn book-btn roomboss_btn" hotel-id="<?php echo esc_attr($property_id); ?>">
                    Book Now
                </button>
            <?php else : ?>
                <button class="btn book-btn bedbank_btn" hotel-id="<?php echo esc_attr($property_id); ?>">
                    Request
                </button>
            <?php endif; ?>

            <a href="javascript:;" class="btn details-btn" 
                hotel-id="<?php echo esc_attr($property_id); ?>" 
                room-id="<?php echo esc_attr($room_id); ?>">
                Details
            </a>
        </div>
    </div>
</div>