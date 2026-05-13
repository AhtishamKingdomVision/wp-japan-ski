<?php
/**
 * Room Details Modal Template
 * Displays detailed information about a selected room
 * 
 * Variables available:
 * - $room_id: ID of the room
 * - $hotel_id: ID of the hotel/property
 */

// Get room ID and hotel ID from parameters
$room_id = isset($args['room_id']) ? intval($args['room_id']) : 0;
$hotel_id = isset($args['hotel_id']) ? intval($args['hotel_id']) : 0;

// Basic room details (in a real implementation, you'd fetch from your database)
$room_details = array(
    'name' => 'Deluxe Room',
    'description' => 'A comfortable and spacious room with modern amenities.',
    'amenities' => array(
        'Free WiFi',
        'Air Conditioning',
        'Flat Screen TV',
        'Private Bathroom',
        'Complimentary Toiletries',
        'Mini Bar',
        'Work Desk',
        'City View'
    ),
    'images' => array(
        'https://via.placeholder.com/400x300?text=Room+1',
        'https://via.placeholder.com/400x300?text=Room+2',
        'https://via.placeholder.com/400x300?text=Room+3',
    )
);
?>

<div class="rb-room-details-modal">
    
    <!-- Room Gallery -->
    <div class="rb-room-gallery js-room-gallery">
        <?php if (!empty($room_details['images'])) : ?>
            <div class="rb-gallery-main">
                <img src="<?php echo esc_url($room_details['images'][0]); ?>" 
                     alt="<?php echo esc_attr($room_details['name']); ?>" 
                     class="rb-main-image">
            </div>
            <?php if (count($room_details['images']) > 1) : ?>
                <div class="rb-gallery-thumbnails">
                    <?php foreach ($room_details['images'] as $index => $image) : ?>
                        <img src="<?php echo esc_url($image); ?>" 
                             alt="<?php echo esc_attr($room_details['name']); ?> - Image <?php echo $index + 1; ?>"
                             class="rb-thumbnail"
                             data-full-src="<?php echo esc_url($image); ?>">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Room Information -->
    <div class="rb-room-info">
        
        <!-- Room Title -->
        <h2 class="rb-room-title"><?php echo esc_html($room_details['name']); ?></h2>

        <!-- Room Description -->
        <p class="rb-room-desc">
            <?php echo esc_html($room_details['description']); ?>
        </p>

        <!-- Room Amenities -->
        <div class="rb-amenities-section">
            <h3 class="rb-amenities-title">Room Amenities</h3>
            <div class="rb-amenities-grid">
                <?php if (!empty($room_details['amenities'])) : ?>
                    <?php foreach ($room_details['amenities'] as $amenity) : ?>
                        <div class="rb-amenity-item">
                            <i class="fa-solid fa-check-circle"></i>
                            <span><?php echo esc_html($amenity); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Room Features -->
        <div class="rb-features-section">
            <h3 class="rb-features-title">Room Features</h3>
            <div class="rb-features-grid">
                <div class="rb-feature-item">
                    <div class="rb-feature-icon">👥</div>
                    <div class="rb-feature-text">
                        <strong>Occupancy</strong>
                        <p>Up to 2 Guests</p>
                    </div>
                </div>
                <div class="rb-feature-item">
                    <div class="rb-feature-icon">📐</div>
                    <div class="rb-feature-text">
                        <strong>Size</strong>
                        <p>32 m²</p>
                    </div>
                </div>
                <div class="rb-feature-item">
                    <div class="rb-feature-icon">🛏️</div>
                    <div class="rb-feature-text">
                        <strong>Bedding</strong>
                        <p>Queen Bed</p>
                    </div>
                </div>
                <div class="rb-feature-item">
                    <div class="rb-feature-icon">🚿</div>
                    <div class="rb-feature-text">
                        <strong>Bathroom</strong>
                        <p>Private with Shower</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Policies Section -->
        <div class="rb-policies-section">
            <h3 class="rb-policies-title">Policies</h3>
            <div class="rb-policies-list">
                <div class="rb-policy-item">
                    <strong>Check-in:</strong>
                    <p>From 15:00</p>
                </div>
                <div class="rb-policy-item">
                    <strong>Check-out:</strong>
                    <p>Until 11:00</p>
                </div>
                <div class="rb-policy-item">
                    <strong>Cancellation:</strong>
                    <p>Free cancellation until 24 hours before arrival</p>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="rb-detail-actions">
            <button type="button" class="rb-book-btn book-btn" disabled>
                Select This Room
            </button>
            <button type="button" class="rb-close-btn" onclick="jQuery('#room-modal').fadeOut(200)">
                Close
            </button>
        </div>
    </div>
</div>

<style>
/* Room Details Modal Styles */
.rb-room-details-modal {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    padding: 20px;
    background: white;
    border-radius: 8px;
    max-height: 600px;
    overflow-y: auto;
}

.rb-room-gallery {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.rb-gallery-main {
    width: 100%;
    background: #f5f5f5;
    border-radius: 8px;
    overflow: hidden;
}

.rb-main-image {
    width: 100%;
    height: auto;
    display: block;
    cursor: pointer;
}

.rb-gallery-thumbnails {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}

.rb-thumbnail {
    width: 100%;
    height: 80px;
    object-fit: cover;
    border-radius: 4px;
    cursor: pointer;
    border: 2px solid transparent;
    transition: border-color 0.3s;
}

.rb-thumbnail:hover,
.rb-thumbnail.active {
    border-color: #3498db;
}

.rb-room-info {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.rb-room-title {
    margin: 0;
    font-size: 24px;
    font-weight: 700;
    color: #333;
}

.rb-room-desc {
    margin: 0;
    font-size: 14px;
    color: #666;
    line-height: 1.6;
}

.rb-amenities-section,
.rb-features-section,
.rb-policies-section {
    border-top: 1px solid #e0e0e0;
    padding-top: 15px;
}

.rb-amenities-title,
.rb-features-title,
.rb-policies-title {
    margin: 0 0 12px 0;
    font-size: 14px;
    font-weight: 600;
    color: #333;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.rb-amenities-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
}

.rb-amenity-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #555;
}

.rb-amenity-item i {
    color: #27ae60;
    flex-shrink: 0;
}

.rb-features-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

.rb-feature-item {
    display: flex;
    gap: 10px;
    padding: 10px;
    background: #f9f9f9;
    border-radius: 6px;
}

.rb-feature-icon {
    font-size: 24px;
    flex-shrink: 0;
}

.rb-feature-text {
    font-size: 12px;
}

.rb-feature-text strong {
    display: block;
    color: #333;
    margin-bottom: 2px;
}

.rb-feature-text p {
    margin: 0;
    color: #666;
}

.rb-policies-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.rb-policy-item {
    font-size: 12px;
}

.rb-policy-item strong {
    color: #333;
    display: block;
}

.rb-policy-item p {
    margin: 4px 0 0 0;
    color: #666;
}

.rb-detail-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 15px;
}

.rb-close-btn {
    padding: 10px;
    background: #95a5a6;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s;
}

.rb-close-btn:hover {
    background: #7f8c8d;
}

/* Responsive Design */
@media (max-width: 768px) {
    .rb-room-details-modal {
        grid-template-columns: 1fr;
        gap: 20px;
        max-height: none;
    }

    .rb-features-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Interactive gallery thumbnail switching
document.addEventListener('DOMContentLoaded', function() {
    const thumbnails = document.querySelectorAll('.rb-thumbnail');
    const mainImage = document.querySelector('.rb-main-image');

    if (thumbnails.length && mainImage) {
        thumbnails.forEach((thumb, index) => {
            thumb.addEventListener('click', function() {
                mainImage.src = this.getAttribute('data-full-src');
                
                // Remove active class from all thumbnails
                thumbnails.forEach(t => t.classList.remove('active'));
                
                // Add active class to clicked thumbnail
                this.classList.add('active');
            });

            // Mark first thumbnail as active
            if (index === 0) {
                thumb.classList.add('active');
            }
        });
    }
});
</script>
