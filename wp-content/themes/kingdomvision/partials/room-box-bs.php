<?php

/**
 * Room Card Template for Booking System
 * Displays a single room with pricing, amenities, and booking options
 * 
 * Required $args keys:
 *   - property_id: Numeric property ID
 *   - room: Array of room data (RoomName, MaximumAdults, RoomImages, etc.)
 *   - rb: RoomBoss data array (optional)
 */

try {
    // ✅ STEP 1: Validate and extract arguments with defaults
    $args = is_array($args) ? $args : [];
    $property_id = intval($args['property_id'] ?? 0);
    $room = is_array($args['room'] ?? []) ? $args['room'] : [];
    $rb = is_array($args['rb'] ?? []) ? $args['rb'] : [];

    // Validate room data exists
    if (empty($room)) {
        return;
    }

    // ✅ STEP 2: Extract room identifiers with validation
    $room_id = ($room['ActualRoomId'] ?? 0);
    if (empty($room_id)) {
        return;
    }

    $room_post_id = get_post_id_by_typeId($room_id, 'room');
    if (empty($room_post_id)) {
        return;
    }


    // ✅ STEP 3: Extract and sanitize room details
    $name = sanitize_text_field($room['RoomName'] ?? '');
    $guests = intval($room['MaximumAdults'] ?? 0);
    $bedrooms = intval($room['numberBedrooms'] ?? 0);
    $bathrooms = intval($room['numberBathrooms'] ?? 0);
    $sqm = sanitize_text_field($room['squareMeter'] ?? '');

    // ✅ STEP 4: Process and validate room image URL
    $image_url = '';
    if (!empty($room['RoomImages']) && is_array($room['RoomImages']) && !empty($room['RoomImages'][0])) {
        $imageData = $room['RoomImages'][0];
        
        // Check if path is already a complete URL
        if (!empty($imageData['path']) && filter_var($imageData['path'], FILTER_VALIDATE_URL)) {
            $image_url = esc_url_raw($imageData['path']);
        } elseif (!empty($imageData['path']) && !empty($imageData['file_name'])) {
            // Construct full URL from components
            $image_url = esc_url_raw(KV_BOOKING_SYSTEM_BASE . '/storage' . $imageData['path'] . $imageData['file_name']);
        }
    }

    // Fallback to placeholder if no image found
    if (empty($image_url)) {
        $image_url = get_template_directory_uri() . '/images/placeholder-accomo.jpg';
    }

    // ✅ STEP 5: Determine unit type (RoomBoss or BedBank)
    $is_roomboss = (isset($room['RoomBossData']) && intval($room['RoomBossData']) === 1);
    $unit_type = $is_roomboss ? 'roomboss' : 'bedbank';

} catch (Exception $e) {
    // ❌ Handle unexpected errors
    error_log('Error in room-box-bs template: ' . $e->getMessage());
    return;
}

?>

<div class="room-card t1" data-bedroom="<?php echo esc_attr($bedrooms); ?>">
    <div class="room-img"
        style="background-image: url('<?php echo esc_url($image_url); ?>');"
        aria-label="<?php echo esc_attr($name); ?>"
        role="img">
    </div>

    <div class="rc_cover t1bs">
        <!-- Room Title -->
        <div class="room-title">
            <h3><?php echo esc_html($name); ?></h3>
        </div>

        <!-- Room Amenities Info -->
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

        <!-- Room Action Buttons -->
        <div class="room-btns">
            <?php if ($is_roomboss) : ?>
                <button class="btn book-btn roomboss_btn" property-id="<?php echo esc_attr($property_id); ?>">
                    Book Now
                </button>
            <?php else : ?>
                <button class="btn book-btn roomboss_btn" property-id="<?php echo esc_attr($property_id); ?>">
                    Request
                </button>
            <?php endif; ?>

            <a href="javascript:void(0)" class="btn details-btn" 
                property-id="<?php echo esc_attr($property_id); ?>" 
                room-id="<?php echo esc_attr($room_post_id); ?>">
                Details
            </a>
        </div>
    </div>
</div>