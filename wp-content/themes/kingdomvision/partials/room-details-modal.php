<?php

/**
 * Room Details Modal Template
 * Displays detailed room information including gallery, amenities, and features
 * Used in modal popups for room details pages
 * 
 * Required $args keys:
 *   - room_id: Numeric room post ID
 *   - hotel_id: Numeric hotel/property ID
 */

try {
    // ✅ STEP 1: Validate and extract arguments
    $args = is_array($args) ? $args : [];
    $room_id = ($args['room_id'] ?? 0);
    $hotel_id = ($args['hotel_id'] ?? 0);

    // Validate required parameters
    if (empty($room_id)) {
        return;
    }

    // ✅ STEP 2: Get and validate room post
    $post = get_post($room_id);
    if (empty($post) || !is_object($post)) {
        return;
    }

    $room_title = get_the_title($room_id);

    // ✅ STEP 3: Extract and validate ACF room details
    $guests = intval(get_field('room_guests', $room_id) ?? 0);
    $bedrooms = intval(get_field('room_bedroom', $room_id) ?? 0);
    $bathrooms = intval(get_field('room_bathroom', $room_id) ?? 0);
    $size = sanitize_text_field(get_field('room_size', $room_id) ?? '');
    $features = get_field('room_features', $room_id);

    // ✅ STEP 4: Get accommodation details for property type
    $acc_id = 0;
    if (!empty($hotel_id)) {
        $acc_id = get_post_id_by_typeId($hotel_id, 'accommodation');
    }

    $is_roomboss = false;
    if (!empty($acc_id)) {
        $is_roomboss = (bool) get_field('is_roomboss', $acc_id);
    }

    // ✅ STEP 5: Get and validate room gallery
    $gallery = kv_get_meta_images_gallery( $room_id, 'room_pending_images');
    $gallery = (!empty($gallery) && is_array($gallery)) ? $gallery : [];

    // ✅ STEP 6: Validate features array
    if (!empty($features) && !is_array($features)) {
        $features = [];
    }

} catch (Exception $e) {
    // ❌ Handle unexpected errors
    error_log('Error in room-details-modal template: ' . $e->getMessage());
    return;
}

?>

<div class="room-modal-header">
    <h3><?php echo esc_html($room_title); ?></h3>
    
    <!-- Action Button -->
    <?php if ($is_roomboss) : ?>
        <button class="btn book-btn roomboss_btn" 
            hotel-id="<?php echo esc_attr($hotel_id); ?>" 
            data-room-id="<?php echo esc_attr($room_id); ?>">
            Book Now
        </button>
    <?php else : ?>
        <button class="btn book-btn bedbank_btn" 
            hotel-id="<?php echo esc_attr($hotel_id); ?>">
            Request
        </button>
    <?php endif; ?>
</div>

<!-- Room Gallery -->
<?php if (!empty($gallery)) : 
    $gallery_count = count($gallery);
    $use_bg_images = ($gallery_count > 3);
?>
    <div class="room-modal-gallery js-room-gallery">
        <?php foreach ($gallery as $key => $img_url) : 

            if (empty($img_url)) {
                continue;
            }
        ?>
            <div class="room-slide">
                <div class="img <?php echo $use_bg_images ? 'bg-slide' : ''; ?>"
                    <?php if ($use_bg_images) : ?>
                        style="background-image: url('<?php echo esc_url($img_url); ?>');"
                    <?php endif; ?>>
                    <?php if (!$use_bg_images) : ?>
                        <img src="<?php echo esc_url($img_url); ?>" alt="<?php echo esc_attr($room_title); ?>" loading="lazy">
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Room Overview Info -->
<div class="room-modal-meta">
    <div class="meta-title">Overview</div>
    <div class="room-info">
        <?php if ($guests > 0) : ?>
            <span class="guest"><?php echo esc_html($guests); ?> guests</span>
        <?php endif; ?>

        <?php if ($bedrooms > 0) : ?>
            <span class="bed"><?php echo esc_html($bedrooms); ?> Bed</span>
        <?php endif; ?>

        <?php if ($bathrooms > 0) : ?>
            <span class="bath"><?php echo esc_html($bathrooms); ?> Bath</span>
        <?php endif; ?>

        <?php if (!empty($size)) : ?>
            <span class="sqm"><?php echo esc_html($size); ?> sqm</span>
        <?php endif; ?>
    </div>
</div>

<!-- Room Features -->
<?php if (!empty($features) && is_array($features)) : ?>
    <div class="room-features">
        <div class="meta-title">Room Features</div>
        <ul class="features-list">
            <?php foreach ($features as $feature) : 
                // ✅ Validate feature data
                if (!is_array($feature) || empty($feature['title'])) {
                    continue;
                }
                
                $feature_title = sanitize_text_field($feature['title']);
                if (empty($feature_title)) {
                    continue;
                }
            ?>
                <li><?php echo esc_html($feature_title); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>