<?php
/**
 * Room Booking Data Template
 * Displays available rooms and rate plans from the booking system
 * 
 * Variables available:
 * - $grouped_rooms: Grouped room data
 * - $rate_plans: Rate plan information
 * - $room_descriptions: Room descriptions by room ID
 * - $property_id or $bs_rooms: Property ID from AJAX call
 */

// Ensure we have the required data
if (empty($grouped_rooms)) {
    echo '<p class="rb-error">No rooms available for the selected dates.</p>';
    return;
}
?>

<div class="rb-rooms-container">
    
    <!-- Guest Selection Controls -->
    <div class="rb-guest-controls">
        <div class="rb-control-group">
            <label for="adults">Adults:</label>
            <input type="number" id="adults" name="adults" value="2" min="1" max="20">
        </div>
        <div class="rb-control-group">
            <label for="children">Children:</label>
            <input type="number" id="children" name="children" value="0" min="0" max="10">
        </div>
        <div class="rb-control-group">
            <label for="infants">Infants:</label>
            <input type="number" id="infants" name="infants" value="0" min="0" max="5">
        </div>
        <button type="button" class="rb-update-btn" onclick="RoomsSection.validateForm()">Update</button>
        <button type="button" class="rb-back-btn back-to-rooms" onclick="RoomBookingManager.goBack()">
            <i class="fa-solid fa-arrow-left"></i> Back to Search
        </button>
    </div>

    <!-- Rooms Grid -->
    <div class="rb-rooms-grid">
        <?php foreach ($grouped_rooms as $room_name => $room_data) : ?>
            <div class="rb-room-card" data-room-id="<?php echo esc_attr($room_data['RoomId'] ?? ''); ?>">
                
                <!-- Room Header -->
                <div class="rb-room-header">
                    <h3 class="rb-room-name"><?php echo esc_html($room_name); ?></h3>
                </div>

                <!-- Room Description -->
                <?php if (!empty($room_descriptions[$room_data['RoomId'] ?? ''])) : ?>
                    <div class="rb-room-description">
                        <p><?php echo wp_kses_post($room_descriptions[$room_data['RoomId']]); ?></p>
                    </div>
                <?php endif; ?>

                <!-- Rate Plans -->
                <div class="rb-rate-plans">
                    <h4 class="rb-rate-plans-title">Available Rate Plans:</h4>
                    
                    <?php if (!empty($room_data['ratePlans'])) : ?>
                        <div class="rb-rates-list">
                            <?php foreach ($room_data['ratePlans'] as $rate_plan) : ?>
                                <div class="rb-rate-item">
                                    <!-- Rate Plan Name -->
                                    <div class="rb-rate-header">
                                        <strong><?php echo esc_html($rate_plan['name'] ?? 'Rate Plan'); ?></strong>
                                    </div>

                                    <!-- Price -->
                                    <?php if (!empty($rate_plan['priceRetail'])) : ?>
                                        <div class="rb-rate-price">
                                            <span class="rb-price">
                                                <?php echo esc_html(kv_roomboss_format_price($rate_plan['priceRetail'])); ?>
                                            </span>
                                            <span class="rb-price-period">per night</span>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Rate Description -->
                                    <?php if (!empty($rate_plan['ratePlanLongDescription'])) : ?>
                                        <div class="rb-rate-description">
                                            <small><?php echo wp_kses_post($rate_plan['ratePlanLongDescription']); ?></small>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Book Button -->
                                    <button type="button" 
                                            class="rb-book-btn book-btn"
                                            data-room-id="<?php echo esc_attr($rate_plan['RoomId'] ?? $room_data['RoomId'] ?? ''); ?>"
                                            data-rate-plan-id="<?php echo esc_attr($rate_plan['ratePlanId'] ?? ''); ?>"
                                            onclick="RoomBookingManager.selectRoom(this)">
                                        Select Room
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <p class="rb-no-rates">No rate plans available for this room.</p>
                    <?php endif; ?>
                </div>

                <!-- Details Button -->
                <button type="button" 
                        class="rb-details-btn details-btn"
                        room-id="<?php echo esc_attr($room_data['RoomId'] ?? ''); ?>"
                        hotel-id="<?php echo esc_attr($property_id ?? ''); ?>"
                        onclick="event.preventDefault();">
                    <i class="fa-solid fa-info-circle"></i> View Details
                </button>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Back Button at Bottom -->
    <div class="rb-actions-bottom">
        <button type="button" class="rb-secondary-btn back-to-rooms" onclick="RoomBookingManager.goBack()">
            <i class="fa-solid fa-arrow-left"></i> Back to Search
        </button>
    </div>
</div>

<style>
/* Inline fallback styles - should be overridden by room-booking-manager.css */
.rb-rooms-container {
    padding: 20px;
    background: #f9f9f9;
    border-radius: 8px;
}

.rb-guest-controls {
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
    padding: 15px;
    background: white;
    border-radius: 6px;
    flex-wrap: wrap;
}

.rb-control-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.rb-control-group label {
    font-size: 13px;
    font-weight: 600;
    color: #333;
}

.rb-control-group input {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.rb-rooms-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.rb-room-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s, box-shadow 0.2s;
}

.rb-room-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.rb-room-header {
    margin-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
    padding-bottom: 10px;
}

.rb-room-name {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #333;
}

.rb-room-description {
    margin-bottom: 15px;
    font-size: 13px;
    color: #666;
    line-height: 1.4;
}

.rb-room-description p {
    margin: 0;
}

.rb-rate-plans {
    margin-bottom: 15px;
}

.rb-rate-plans-title {
    font-size: 13px;
    font-weight: 600;
    color: #333;
    margin: 0 0 10px 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.rb-rates-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.rb-rate-item {
    background: #f5f5f5;
    padding: 12px;
    border-radius: 6px;
    border-left: 3px solid #3498db;
}

.rb-rate-header {
    margin-bottom: 8px;
}

.rb-rate-price {
    display: flex;
    align-items: baseline;
    gap: 5px;
    margin-bottom: 8px;
}

.rb-price {
    font-size: 18px;
    font-weight: 700;
    color: #27ae60;
}

.rb-price-period {
    font-size: 12px;
    color: #999;
}

.rb-rate-description {
    margin-bottom: 10px;
    font-size: 11px;
    color: #666;
}

.rb-book-btn,
.rb-details-btn,
.rb-update-btn,
.rb-secondary-btn {
    width: 100%;
    padding: 10px;
    margin-top: 10px;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.rb-book-btn {
    background: #3498db;
    color: white;
}

.rb-book-btn:hover:not(:disabled) {
    background: #2980b9;
}

.rb-details-btn {
    background: #34495e;
    color: white;
    margin-top: 15px;
}

.rb-details-btn:hover:not(:disabled) {
    background: #2c3e50;
}

.rb-update-btn {
    background: #27ae60;
    color: white;
    width: auto;
    padding: 8px 20px;
    margin-top: 0;
}

.rb-update-btn:hover {
    background: #229954;
}

.rb-secondary-btn {
    background: #95a5a6;
    color: white;
}

.rb-secondary-btn:hover {
    background: #7f8c8d;
}

.rb-back-btn {
    background: #e74c3c;
    color: white;
    padding: 8px 15px;
    width: auto;
    margin-top: 0;
}

.rb-back-btn:hover {
    background: #c0392b;
}

.rb-no-rates {
    color: #e74c3c;
    font-size: 12px;
    margin: 0;
    padding: 10px;
}

.rb-error {
    background: #fee;
    border: 1px solid #fcc;
    color: #c33;
    padding: 15px;
    border-radius: 4px;
}

.rb-actions-bottom {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e0e0e0;
}

/* Responsive Design */
@media (max-width: 768px) {
    .rb-rooms-grid {
        grid-template-columns: 1fr;
    }

    .rb-guest-controls {
        flex-direction: column;
    }

    .rb-control-group input {
        width: 100%;
    }
}
</style>
