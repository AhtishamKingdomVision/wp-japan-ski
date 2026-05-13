<?php
/**
 * Room Booking Data Template
 * Displays available rooms with rate plans for booking system
 * 
 * Required $args keys:
 *   - grouped_rooms: Array of room data from API
 *   - rate_plans: Array of rate plan data (optional)
 *   - room_descriptions: Array of room descriptions keyed by room ID
 *   - dates: Array with 'check_in' and 'check_out' date strings
 *   - property_id: Numeric property/hotel ID
 */

// accommodation setting actual data for Roomboss supplier to be used in booking terms and conditions display and calculation of deposit and balance due amounts and dates. This is just a sample data structure to understand how the accommodation setting data looks like and how to use it in the room booking data template. The actual data will be fetched from the API and passed to the template through $args['property']['AccommodationSetting'].
/*
"AccommodationSetting": [
    {
        "id": 18,
        "commission_level": 15,
        "markup_level": 0,
        "commission_level_type": "%",
        "deposits_enabled": true,
        "first_night_fee": "no",
        "night_fee_value": 1,
        "percentage_value": true,
        "deposit_amount_per_room": 25,
        "min_deposit_jpy": 0,
        "days_in_advance": 0,
        "description": null,
        "taxes_fees": 0,
        "price_mode": "",
        "price": 0,
        "allow_pro_rata_pricing": false,
        "duration_nights": 0,
        "maximum_quantity": 0,
        "refundable_type": "percentage",
        "refundable_value": 0,
        "refundable_min_days": 0,
        "date_rules": [
            {
                "check_in": "01-Jul-2026",
                "check_out": "31-Aug-2026",
                "fixed_date": "20-Jun-2026",
                "selected_type": "fixed_date",
                "balance_due_days": null,
                "due_on_confirm_booking": null
            },
            {
                "check_in": "01-Sep-2026",
                "check_out": "31-Oct-2026",
                "fixed_date": "21-Aug-2026",
                "selected_type": "fixed_date",
                "balance_due_days": null,
                "due_on_confirm_booking": null
            },
            {
                "check_in": "01-Nov-2026",
                "check_out": "31-Dec-2026",
                "fixed_date": "22-Oct-2026",
                "selected_type": "fixed_date",
                "balance_due_days": null,
                "due_on_confirm_booking": null
            },
            {
                "check_in": "01-Jan-2027",
                "check_out": "28-Feb-2027",
                "fixed_date": "18-Dec-2026",
                "selected_type": "fixed_date",
                "balance_due_days": null,
                "due_on_confirm_booking": null
            }
        ],
        "is_fixed_date_enabled": true,
        "fixed_deadline_date": "2026-12-20T00:00:00.000000Z",
        "due_on_confirm_booking_date": null,
        "due_on_confirm_booking": "no",
        "created_at": "2026-01-29T03:41:09.000000Z",
        "updated_at": "2026-05-07T15:47:33.000000Z",
        "pivot": {
            "supplier_id": 16,
            "accommodation_setting_id": 18
        }
    }
]

// room settings for bedbank suppliers are included in the room data as 'globalDiscount' and can be used to override the accommodation settings for specific rate plans or rooms. This is just a sample data structure to understand how the global discount data looks like and how to use it in the room booking data template. The actual data will be fetched from the API and passed to the template through $room['globalDiscount'].
"globalDiscount": {
    "id": 98,
    "supplier_id": 46,
    "name": "10% Early Bird Discount v2",
    "code": "HHG10EB",
    "status": 1,
    "allow_multiple_discount": false,
    "priority_types_global": true,
    "priority_types_property": false,
    "description": "Get 10% off all stays when you book and pay by 20th June.",
    "discount_priority": 0,
    "discount_type": "percentage",
    "guests": "all",
    "booking_start_date": "2027-01-04T00:00:00.000000Z",
    "booking_end_date": "2027-02-05T00:00:00.000000Z",
    "value": "10",
    "module": "per_booking",
    "apply_when_id": "1",
    "apply_when_condition": "N/A",
    "apply_when_value": null,
    "apply_when_value_one": null,
    "apply_when_value_two": null,
    "apply_when_days": [],
    "condition_type": "custom_2",
    "condition_percent_2": 25,
    "remaining_amount_date": true,
    "remaining_date": "2026-06-20T00:00:00.000000Z",
    "remaining_amount_days": false,
    "days_before_departure": null,
    "override_invoice": true,
    "apply_only_before": "2026-06-20T00:00:00.000000Z",
    "free_nights": null,
    "created_at": "2026-03-01T03:09:27.000000Z",
    "updated_at": "2026-05-08T14:28:48.000000Z",
}
*/
// ✅ STEP 1: Parse and validate arguments with defaults
$args = wp_parse_args($args ?? [], [
    'grouped_rooms'      => [],
    'rate_plans'         => [],
    'room_descriptions'  => [],
    'dates'              => ['check_in' => '', 'check_out' => ''],
    'property_id'        => 0,
]);

// ✅ STEP 2: Extract and validate room data
$property = is_array($args['property']) ? $args['property'] : [];
$rooms = is_array($args['grouped_rooms']) ? $args['grouped_rooms'] : [];
$ratePlans = is_array($args['rate_plans']) ? $args['rate_plans'] : [];
$desc = is_array($args['room_descriptions']) ? $args['room_descriptions'] : [];
$propertyId = intval($args['property_id']);
$wp_property_id = get_post_id_by_typeId($propertyId, 'accommodation');

// ✅ STEP 3: Extract and validate date information
$dates = is_array($args['dates']) ? $args['dates'] : [];
$startDisplay = sanitize_text_field($dates['check_in'] ?? '');
$endDisplay = sanitize_text_field($dates['check_out'] ?? '');
// $nights = isset($dates['nights']) ? intval($dates['nights']) : 0; nights was nopt there in dates

// getting dates difference from days

    // Create DateTime objects from a specific format (d/m/Y assumed)
    $dateStart = DateTime::createFromFormat('d/m/Y', $startDisplay);
    $dateEnd   = DateTime::createFromFormat('d/m/Y', $endDisplay);

    // Calculate the difference
    $interval = $dateStart->diff($dateEnd);

// Output the duration in days
$nights = $interval->days; // Output: 4 days

// ✅ STEP 4: Check if property uses roomboss or bedbank
$is_roomboss = !empty($propertyId) ? get_resort_id_by_property_id($propertyId) : false;
$accommodationSetting = @$property['AccommodationSetting'] ?? [];
$accommodationSetting = @$accommodationSetting[0] ?? [];
$startDisplay = date_format_readable($startDisplay, 'Y-m-d', 'd/m/Y');
// var_dump($accommodationSetting['deposits_enabled']);
// pre($property, 0);
// pre($accommodationSetting, 0);
$supplierData = [
    'isDeposit' => $accommodationSetting['deposits_enabled'] == true, // true or false
    'isPercentage' => $accommodationSetting['percentage_value'] == true || !empty($accommodationSetting['deposit_amount_per_room']), // true if deposit is percentage, false if fixed amount
    'depositPercentage' => floatval($accommodationSetting['deposit_amount_per_room'] ?? 0), // percentage value if isPercentage is true
    'depositType' => 'deposit', // default to 'deposit', can be 'full' if no deposit
    'depositAmount' => 0, // will be calculated based on percentage or fixed amount
    'balanceDueAmount' => 0, // will be calculated based on total price minus deposit
    'daysInAdv' => (int) $accommodationSetting['days_in_advance'], // eg: 70
    'daysInAdvDate' => '', // will be calculated based on check-in date minus days in advance
    'isFixedDate' => $accommodationSetting['is_fixed_date_enabled'] == true,
    'fixedDueDate' => @$accommodationSetting['fixed_deadline_date'] ?? '', // used if isFixedDate is true then balance due date is fixed to this date
    'balanceDueDate' => '', // will be calculated based on check-in date and days in advance,
    'date_rules' => is_array($accommodationSetting['date_rules']) ? $accommodationSetting['date_rules'] : [],
];
// pre($supplierData, 0);
?>

<a href="javascript:;" class="btn back-to-rooms btn-outline">← Back</a>
<div class="rb-booking-layout" data-hotel-id="<?php echo esc_attr($propertyId); ?>"
    data-start-date="<?php echo esc_attr($startDisplay); ?>"
    data-end-date="<?php echo esc_attr($endDisplay); ?>"
    data-nights="<?php echo esc_attr($nights); ?>">
    <div class="rb-left">

        <?php if (empty($rooms)) : ?>
            <p class="rb-empty">No rooms available for selected dates.</p>
        <?php else :
            foreach ($rooms as $roomName => $room) :
                // ✅ Extract room identifiers with defaults
                $RoomId = ($room['RoomId'] ?? 0);
                $roomTypeId = $room['ActualRoomId'];
                
                // Skip rooms without ID
                if (empty($RoomId)) {
                    continue;
                }

                // ✅ Process and validate room image URL
                $img = '';
                if (!empty($room['RoomImages']) && is_array($room['RoomImages']) && !empty($room['RoomImages'][0])) {
                    $imageData = $room['RoomImages'][0];
                    
                    // Check if path is already a complete URL
                    if (!empty($imageData['path']) && filter_var($imageData['path'], FILTER_VALIDATE_URL)) {
                        $img = esc_url_raw($imageData['path']);
                    } elseif (!empty($imageData['path']) && !empty($imageData['file_name'])) {
                        // Construct full URL from components
                        $img = esc_url_raw(KV_BOOKING_SYSTEM_BASE . '/storage' . $imageData['path'] . $imageData['file_name']);
                    }
                }

                // Fallback to placeholder if no image found
                if (empty($img)) {
                    $img = get_template_directory_uri() . '/images/placeholder-accomo.jpg';
                }

                // ✅ Extract room details with validation and defaults
                $maxGuests = intval($room['MaximumAdults'] ?? 0);
                $bedrooms = intval($room['numberBedrooms'] ?? 0);
                $baths = intval($room['numberBathrooms'] ?? 0);
                $maxInfants = intval($room['maxNumberInfants'] ?? 0);
                $accomPayTerm = $supplierData;
                ?>
                <div class="rb-room-card room-card"
                    data-bedroom="<?php echo esc_attr($bedrooms); ?>"
                    data-room-id="<?php echo esc_attr($RoomId); ?>"
                    >
                    <div class="rb-room-top">
                        <div class="rb-room-img">
                            <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($room['RoomName'] ?? 'Room'); ?>">
                        </div>

                        <div class="rb-room-meta">
                            <div class="tit_date">
                                <h3 class="rb-room-title"><?php echo esc_html($room['RoomName'] ?? ''); ?></h3>


                                <div class="rb-room-dates"></div>
                            </div>

                            <div class="rb-icons">
                                <span class="rb-icon guest"><?php echo esc_html($maxGuests); ?></span>
                                <span class="rb-icon bad"><?php echo esc_html($bedrooms); ?></span>
                                <span class="rb-icon bath"><?php echo esc_html($baths); ?></span>
                                <?php if (!empty($maxInfants) && $maxInfants > 0) : ?>
                                    <span class="rb-icon infant">
                                        <?php echo esc_html($maxInfants); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="rb-room-desc">
                                <!-- Std Guests: 2, Max Guests: 2; 1 Bedroom (King or Twin), 1 Bathroom; Size: 33-39 m²; Levels 2-5 -->
                                <?php echo esc_html($desc[$RoomId] ?? ''); ?>
                            </div>

                        </div>
                        <div class="rb-guests-filter">
                            <form class="rb-guests-form">

                                <!-- Adults -->
                                <div class="rb-guest-field">
                                    <label>Adults</label>
                                    <select class="rb-guest-input rb-adults" name="adults">
                                        <option value="">Adults</option>
                                        <?php for ($i = 1; $i <= $maxGuests; $i++): ?>
                                            <option value="<?php echo esc_attr($i); ?>"><?php echo esc_html($i); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>

                                <!-- Children -->
                                <div class="rb-guest-field">
                                    <label>Children</label>
                                    <select class="rb-guest-input rb-children" name="children">
                                        <option value="">Children</option>
                                        <?php for ($i = 0; $i <= $maxGuests; $i++): ?>
                                            <option value="<?php echo esc_attr($i); ?>"><?php echo esc_html($i); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>

                                <!-- Infants -->
                                <div class="rb-guest-field">
                                    <label>Infants</label>
                                    <select class="rb-guest-input rb-infants" name="infants">
                                        <option value="">Infants</option>
                                        <?php for ($i = 0; $i <= $maxGuests; $i++): ?>
                                            <option value="<?php echo esc_attr($i); ?>"><?php echo esc_html($i); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>

                            </form>
                        </div>
                    </div>

                    <div class="rb-rateplans">
                        <?php
                            $allRooms = [$room];
                            foreach($room['children'] ?? [] as $childRoom) {
                                $allRooms[] = $childRoom;
                            }

                            // pre($allRooms, 0);
                            // pre(wp_json_encode($allRooms), 0);

                            foreach ($allRooms as $room) : 
                                $isBedbank = empty(@$room['ratePlanId']);
                                // var_dump($isBedbank);
                                // ✅ Calculate prices with safe division
                                $priceRetail = (float) ($room['ActualPrice'] ?? 0);
                                // pre(wp_json_encode($room), 0);
                                $perStay = ($maxGuests > 0) ? ceil($priceRetail / $maxGuests) : $priceRetail;
                                $ratePlanName = wp_strip_all_tags($room['ratePlanName'] ?? '');
                                
                                if( $isBedbank ) {
                                    $ratePlanId = null;
                                    $ratePlanDesc = wp_strip_all_tags($room['ratePlanDescription'] ?? '');

                                    $globalDiscount = $room['globalDiscount'] ?? null;
                                    if( !empty($globalDiscount) ) {

                                        $isOverride = $globalDiscount['override_invoice'] == true;
                                        if( $isOverride ) {
                                            $daysInAdv = max(0, (int) $globalDiscount['days_before_departure']);
                                            $apply_only_before = !empty($globalDiscount['apply_only_before']) ? date('Y-m-d', strtotime($globalDiscount['apply_only_before'])) : null;
                                            $current_date = date('Y-m-d');
                                            if( !empty($apply_only_before) && $apply_only_before > $current_date ) {
                                                // if apply_only_before date is set and is in the future then we can apply the global discount and override the accommodation setting for this room and rate plan
                                                $accomPayTerm = array_merge($accomPayTerm, [
                                                        'isPercentage' => @$globalDiscount['discount_type'] == 'percentage', // percentage value if isPercentage is true
                                                        'depositPercentage' => floatval( $globalDiscount['condition_percent_2'] ?? $accomPayTerm['deposit_amount_per_room'] ), // percentage value if isPercentage is true
                                                        'daysInAdv' => $daysInAdv,
                                                        'isFixedDate' => $daysInAdv === 0,
                                                        'fixedDueDate' => $globalDiscount['remaining_date'] ?? null, // used if isFixedDate is true then balance due date is fixed to this date
                                                        'balanceDueDate' => '', // will be calculated based on check-in date and days in advance,
                                                        'date_rules' => [],
                                                    ]
                                                );
                                            }
                                        }
                                    }
                                    
                                }
                                else {
                                    $ratePlanId = $room['ratePlanId'] ?? null;
                                    $ratePlanDesc = wp_strip_all_tags($room['ratePlanLongDescription'] ?? '');

                                    $wp_rate_plan = wp_get_rate_plan_by_id($ratePlanId, $wp_property_id);
                                    if( !empty($wp_rate_plan) ) {
                                        $ratePlanId = $ratePlanId;
                                        $ratePlanName = wp_strip_all_tags($wp_rate_plan['rate_plan_name']);
                                        $ratePlanDesc = wp_strip_all_tags($wp_rate_plan['rate_plan_description']);
                                    }
                                }

                                // var_dump($room['ratePlanId']);
                                // var_dump($wp_rate_plan);
                                // pre($accomPayTerm, 0);
                        ?>
                            <div class="rb-rateplan-box"
                                data-room-type="<?php echo esc_attr(!empty($ratePlanId) ? 'roomboss' : 'bedbank'); ?>"
                                data-room-type-id="<?php echo esc_attr($roomTypeId); ?>"
                                data-room-name="<?php echo esc_attr($room['RoomName'] ?? ''); ?>"
                                data-rateplan-id="<?php echo esc_attr($ratePlanId ?? ''); ?>"
                                data-rateplan-name="<?php echo esc_attr($ratePlanName ?? ''); ?>"
                                data-price="<?php echo esc_attr($priceRetail); ?>">

                                <div class="rb-rateplan-left">

                                    <!-- TITLE + INFO ICON -->
                                    <div class="rb-rateplan-title-wrap">
                                        <div class="rb-rateplan-title">
                                            <?php echo esc_html($ratePlanName ?? ''); ?>
                                        </div>

                                        <?php if (!empty($ratePlanDesc)) : ?>
                                            <i class="rb-toggle-long-desc fa-solid fa-circle-info"
                                                title="More information"></i>
                                        <?php endif; ?>
                                    </div>

                                    <!-- SHORT DESCRIPTION -->
                                    <?php if (!empty($room['description'])) :?>
                                        <div class="rb-rateplan-desc">
                                            <?php echo esc_html($room['description']); ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- LONG DESCRIPTION (HIDDEN) -->
                                    <?php if (!empty($ratePlanDesc)) : ?>
                                        <div class="rb-long-desc" style="display:none;">
                                            <?php
                                                // ✅ Security: Sanitize HTML content to allow safe formatting
                                                $long_desc = $ratePlanDesc;
                                                echo wp_kses_post($long_desc);
                                            ?>
                                        </div>
                                    <?php endif; ?>

                                </div>


                                <div class="rb-rateplan-right">

                                    <div class="rb-price-container">

                                        <!-- per stay -->
                                        <div class="rb-per-stay">
                                            <?php echo wp_kses_post(_currency_format_new($perStay, true)); ?>
                                            <span class="label">per stay</span>
                                        </div>

                                        <!-- prices -->
                                        <div class="rb-room-price">
                                            <div class="rb-final-price"
                                                data-price="<?php echo esc_attr($priceRetail); ?>">
                                                <?php echo wp_kses_post(_currency_format_new($priceRetail, true)); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Supplier terms - Deposit, Due and Full -->
                                    <div class="rb-price-container">

                                        <div class="rb-per-stay"></div>

                                        <!-- Supplier Condition -->
                                        <div class="rb-room-terms">
                                            <span class="rb-deposit-info" style="text-align: right;">
                                                <?php
                                                    if( ! $accomPayTerm['isFixedDate'] ) {
                                                        $accomPayTerm['daysInAdvDate'] = $accomPayTerm['balanceDueDate'] = date('Y-m-d', strtotime($startDisplay . ' - ' . $accomPayTerm['daysInAdv'] . ' days'));
                                                    }
                                                    else {
                                                        // get fixed due date from accommodation setting and format it to Y-m-d
                                                        if( $accomPayTerm['fixedDueDate'] ) {
                                                            $accomPayTerm['fixedDueDate'] = date('Y-m-d', strtotime($accomPayTerm['fixedDueDate']));
                                                        }

                                                        $accomPayTerm['balanceDueDate'] = $accomPayTerm['fixedDueDate'];

                                                        // get fixed due date from date_rules if exists
                                                        if( !empty($accomPayTerm['date_rules']) ) {
                                                            // var_dump($startDisplay);
                                                            foreach( $accomPayTerm['date_rules'] as $date_rule ) {
                                                                $date_rule['check_in'] = date_format_readable($date_rule['check_in'], 'Y-m-d', 'd-M-Y');
                                                                $date_rule['check_out'] = date_format_readable($date_rule['check_out'], 'Y-m-d', 'd-M-Y');
                                                                $date_rule['fixed_date'] = date_format_readable($date_rule['fixed_date'], 'Y-m-d', 'd-M-Y');
                                                                // pre($date_rule, 0);
                                                                if( strtotime($startDisplay) >= strtotime($date_rule['check_in']) && strtotime($startDisplay) <= strtotime($date_rule['check_out']) ) {
                                                                    // var_dump($date_rule);
                                                                    if( $date_rule['selected_type'] === 'fixed_date' && !empty($date_rule['fixed_date']) ) {
                                                                        $accomPayTerm['balanceDueDate'] = date('Y-m-d', strtotime($date_rule['fixed_date']));
                                                                    }
                                                                    elseif( $date_rule['selected_type'] === 'balance_due_days' && !empty($date_rule['balance_due_days']) ) {
                                                                        $accomPayTerm['balanceDueDate'] = date('Y-m-d', strtotime($startDisplay . ' - ' . intval($date_rule['balance_due_days']) . ' days'));
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    }
                                                    
                                                    if ( $accomPayTerm['isDeposit'] ) {
                                                        if ( $accomPayTerm['isPercentage'] && $accomPayTerm['depositPercentage'] > 0 ) {
                                                            $accomPayTerm['depositAmount'] = ceil($priceRetail * ($accomPayTerm['depositPercentage'] / 100));
                                                        } elseif (!$accomPayTerm['isPercentage'] && $accomPayTerm['depositAmount'] > 0) {
                                                            $accomPayTerm['depositAmount'] = ceil($accomPayTerm['depositAmount']);
                                                        }
                                                        printf(
                                                            '<div>Deposit due on booking: <strong>%s</strong></div>',
                                                            wp_kses_post(_currency_format_new($accomPayTerm['depositAmount'], true))
                                                        );
                                                    }
                                                    
                                                    if( $accomPayTerm['depositAmount'] > 0 ) {

                                                        // Calculate balance due amount
                                                        // printf(
                                                        //     '<div>Balance Due: %s</div>',
                                                        //     wp_kses_post(_currency_format_new($priceRetail - $accomPayTerm['depositAmount'], true))
                                                        // );

                                                        // Calculate balance due date
                                                        printf(
                                                            '<div>Balance due on (%s): <strong>%s</strong></div>',
                                                            esc_html(date('d M, Y', strtotime($accomPayTerm['balanceDueDate']))),
                                                            wp_kses_post(_currency_format_new($priceRetail - $accomPayTerm['depositAmount'], true))
                                                        );
                                                    }
                                                    else {
                                                        printf(
                                                            '<div>Full amount due on booking (%s): <strong>%s</strong></div>',
                                                            esc_html(date('d M, Y', strtotime($accomPayTerm['balanceDueDate']))),
                                                            wp_kses_post(_currency_format_new($priceRetail, true))
                                                        );
                                                    }
                                                    
                                                ?>
                                            </span>
                                        </div>
                                    </div>

                                </div>
                                <button type="button" class="rb-select-btn">Select</button>
                            </div>
                            <?php $accomPayTerm = $supplierData; ?>
                        <?php endforeach; ?>
                    </div>


                </div>
            <?php endforeach; ?>

        <?php endif; ?>
    </div>

    <!-- CART -->
    <aside class="rb-cart">
            <div class="rb-cart-wrap">
                <div class="rb-cart-head">
                    <h3>Booking Summary</h3>

                    <div class="close_cart is_mobile"><a class="close"><i class="fa-solid fa-xmark"></i></a></div>
                </div>

                <div class="rb-cart-body" id="rbCartBody">
                    <p class="rb-cart-empty">No rooms selected yet.</p>
                </div>

                <div class="rb-cart-footer" id="rbCartFooter" style="display:none;">
                    <div class="rb-total-row">
                        <strong>Total</strong>
                        <span id="rbCartTotal">0</span>
                    </div>
                    <button type="button" class="rb-proceed-btn">Proceed</button>
                </div>
            </div>
        </aside>
</div>