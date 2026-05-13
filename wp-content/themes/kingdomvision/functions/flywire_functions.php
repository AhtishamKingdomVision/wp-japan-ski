<?php 
/*Fly wire bokings*/
add_action('init', function () {

    register_post_type('flywire_payment', [
        'label' => 'Flywire Payments',
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-money-alt',
        'supports' => ['title'],
        'capability_type' => 'post',
        'map_meta_cap' => true,
    ]);

});

add_filter('manage_flywire_payment_posts_columns', 'manage_flywire_columns');
function manage_flywire_columns($columns) {

    $columns['payment_id'] = 'Payment ID';
    $columns['amount'] = 'Amount';
    $columns['status'] = 'Status';
    $columns['expires_at'] = 'Expires At';

    return $columns;
}

add_action('manage_flywire_payment_posts_custom_column', 'manage_flywire_columns_values', 10, 2);
function manage_flywire_columns_values($column, $post_id) {

    if ($column === 'payment_id') {
        echo esc_html(get_post_meta($post_id, '_payment_id', true));
    }

    if ($column === 'amount') {
        echo esc_html(get_post_meta($post_id, '_amount', true)) . ' ' .
             esc_html(get_post_meta($post_id, '_currency', true));
    }

    if ($column === 'status') {
        echo esc_html(get_post_meta($post_id, '_status', true));
    }

    if ($column === 'expires_at') {
        echo esc_html(get_post_meta($post_id, '_expires_at', true));
    }

}

add_action('save_post_flywire_payment', 'kv_capture_on_status_update', 20, 3);
function kv_capture_on_status_update($post_id, $post, $update) {
    // Prevent autosave / revision issues
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_revision($post_id)) {
        return;
    }

    // Only run when updating existing post
    if (!$update) {
        return;
    }

    $status = get_post_meta($post_id, '_status', true);
    $captured = get_post_meta($post_id, '_capture_triggered', true);

    // Trigger capture only when status becomes capture_requested
    if ( $status === 'captured' && !$captured) {

        $payment_id = get_post_meta($post_id, '_payment_id', true);
        $amount = get_post_meta($post_id, '_amount', true);

        $result = capture_flywire_payment($payment_id, $amount);

        $charge_result = $result['charge_result'];
        $status = $charge_result['status'] ?? '';

        cf_log( $result, 'flywire_capture_log', 'txt', false, true );
        if ( $charge_result === 'received' ) {
            cf_log( 'Captured payment for session: '.$payment_id.' amount: '.$amount, 'flywire_success_log', 'txt', false, true );

            return wp_send_json_success(['message' => 'Captured successfully']);
        } else {
            cf_log( 'Failed to capture payment for session: '.$payment_id.' error: '.$data['detail'], 'flywire_capture_log', 'txt', false, true );
            return wp_send_json_error(['message' => $data['detail']]);
        }

    }
}

add_action('rest_api_init', function () {
    register_rest_route('flywire/v1', '/webhook', [
        'methods' => 'POST',
        'callback' => 'handle_flywire_webhook',
        'permission_callback' => '__return_true', // webhooks are public, secure via secret later if needed
    ]);
});

function handle_flywire_webhook($request) {

    $body = json_decode($request->get_body(), true);
    cf_log( $body, 'flywire_webhook_log', 'txt', false, true );
    
    $event_type = $body['event_type'] ?? '';

    $event_resource = $body['event_resource'] ?? '';
    $data = $body['data'] ?? [];

    $payment_id = $data['payment_id'] ?? null;

    $fields = $data['fields'] ?? [];
    $booking_reference = $fields['booking_reference'] ?? '';

    $external_reference = $data['external_reference'] ?? '';
    $amount = $data['amount_to'] ?? 0;
    $currency = $data['currency_to'] ?? '';
    $status = $data['status'] ?? '';

    if (!$payment_id) {
        return;
    }

    if( $event_type !== 'initiated'){

        $payment_post = get_flywire_payment_by_payment_id($payment_id);

        if (!$payment_post) {
            return;
        }

        $post_id = $payment_post->ID;

        /* FAILED */
        if ($event_resource === 'charges' && $event_type === 'failed') {

            $reason = $data['reason'];
            update_post_meta($post_id, '_status', $status);
            update_post_meta($post_id, '_reason', $reason);

        }

        if ($event_resource === 'payments' && $event_type === 'cancelled') {

            $reason = $data['cancellation_reason'];
            update_post_meta($post_id, '_status', $status);
            update_post_meta($post_id, '_reason', $reason);

        }

        /* AUTHORIZED */
        if ($event_resource === 'charges' && $event_type === 'authorized') {

            update_post_meta($post_id, '_status', $status);
            update_post_meta($post_id, '_authorized_at', current_time('mysql'));
            update_post_meta($post_id, '_authorized_at', current_time('mysql'));

        }

        /* CAPTURED */
        if ($event_resource === 'charges' && $event_type === 'captured') {

            update_post_meta($post_id, '_status', $status);
            update_post_meta($post_id, '_captured_at', current_time('mysql'));

        }

    }

    else if( $event_type == 'initiated'){
        // Create a new payment record for this session

        create_flywire_payment_record([
            'payment_id' => $payment_id,
            'external_reference' => $external_reference,
            'amount' => $amount,
            'currency' => $currency,
            'expires_at' => isset($data['expiration_date']) ? date('Y-m-d H:i:s', strtotime($data['expiration_date'])) : '',
            'booking_reference' => $booking_reference,
            'status' => $status,
        ]);

    }

    // Check status and session ID
    if (
        isset($data['status']) &&
        $data['status'] === 'authorized'
    )
    {

        $form_id = 3;
        $last_entry_id = get_last_gravity_form_entry_id( $form_id );

        if ( $last_entry_id ) {

            $upd_payment_id = GFAPI::update_entry_field( $last_entry_id, 17, $payment_id );
        }
    }

    return wp_send_json_error(['message' => 'Invalid webhook payload']);
}

add_action('wp_ajax_create_flywire_session', 'create_flywire_session');
add_action('wp_ajax_nopriv_create_flywire_session', 'create_flywire_session');
function create_flywire_session() {

    $payload = $_POST['payload'];

    $url = 'https://api-platform-sandbox.flywire.com/payments/v1/checkout/sessions';

    $body = [
        'type'               => 'one_off',
        'schema'             => 'cards',
        'charge_intent' => [
            'mode'          => 'one_off',     // keeps it a single payment
            'capture'       => 'manual',      // tells Flywire to authorize but not capture
            'authorization' => 'preauth',     // allows later capture & amount adjustment
        ],
        'payor'          => [
            'first_name' => sanitize_text_field($payload['firstName']),
            'last_name'  => sanitize_text_field($payload['lastName']),
            'email'      => sanitize_email($payload['email']),
            'phone'      => sanitize_text_field($payload['phone']),
            'address'    => sanitize_text_field($payload['address']),
            'city'       => sanitize_text_field($payload['city']),
            'country'    => sanitize_text_field($payload['country']),
            'zip'        => sanitize_text_field($payload['postcode']),
        ],
        'options'                    => [
            'form' => [
                'action_button'     => 'save',
                'locale'            => sanitize_text_field($payload['lang']),
                
                'show_flywire_logo' => true,
            ],
        ],
        'recipient'                  => [
            'fields' => [
                [
                    'id'    => 'booking_reference',
                    'value' => 'GF-' . time(),
                ],
            ],
        ],
        'items'                      => [
            [
                'id'     => 'default',
                'amount' => floatval( $payload['amount'] ),
            ],
        ],
        'notifications_url'          => home_url('/wp-json/flywire/v1/webhook'),
        'return_url'                 => home_url('/flywire-return/'),
        'external_reference'         =>'Booking-' . date( 'Y-M-d h:i:s A' ),
        'recipient_id'               => 'JSE',
        'payor_id'                   => 'JSE_'.time(),
        'enable_email_notifications' => true,
    ];

    $args = [
        'method'  => 'POST',
        'timeout' => 45,
        'headers' => [
            'Content-Type'         => 'application/json',
            'X-Authentication-Key' => 'Y05SSzQ5TXQwakFSaVk3TG9jNk9KZz09',
        ],
        'body'    => json_encode($body),
    ];
    // wp_send_json_success([$args]);
    $response = wp_remote_post($url, $args);

    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);

    wp_send_json_success(
        $data ?? null
    );
}


/**
 * Capture funds for a pre-authorized Flywire session.
 *
 * @param string $session_id The Flywire session ID.
 * @param int $amount Amount to capture (in the smallest currency unit, e.g., cents).
 * @return array Success status and response data or error.
 */
function capture_flywire_payment($payment_id, $amount) {

    $capture_url = "https://api-platform-sandbox.flywire.com/payments/v1/payments/{$payment_id}/captures";

    $body = [
        'amount' => floatval($amount),
    ];

    $args = [
        'method'  => 'POST',
        'timeout' => 45,
        'headers' => [
            'Content-Type'         => 'application/json',
            'X-Authentication-Key' => 'Y05SSzQ5TXQwakFSaVk3TG9jNk9KZz09', // your backend key
        ],
        'body'    => json_encode($body),
    ];

    $response = wp_remote_post($capture_url, $args);

    if (is_wp_error($response)) {
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    cf_log( $data, 'flywire_capture_response_log', 'txt', false, true );
    
    if( $data['status'] == 200 ){

        return ['success' => true, 'data' => $data];
    }
    else{
        return ['success' => false, 'data' => $data];
    }

}

function create_flywire_payment_record($args) {

    $post_id = wp_insert_post([
        'post_type'   => 'flywire_payment',
        'post_title'  => 'FW Payment - ' . $args['external_reference'],
        'post_status' => 'publish',
    ]);

    update_post_meta($post_id, '_payment_id', $args['payment_id'] ?? '');
    update_post_meta($post_id, '_session_id', $args['session_id'] ?? '');
    update_post_meta($post_id, '_external_reference', $args['external_reference']);
    update_post_meta($post_id, '_amount', $args['amount']);
    update_post_meta($post_id, '_currency', $args['currency']);
    update_post_meta($post_id, '_status', $args['status'] ?? 'initiated');
    update_post_meta($post_id, '_reason', '');
    update_post_meta($post_id, '_booking_reference', $args['booking_reference'] ?? '');
    update_post_meta($post_id, '_expires_at', $args['expires_at'] ?? '');

    return $post_id;
}

function get_flywire_payment_by_payment_id($payment_id) {

    $query = new WP_Query([
        'post_type'  => 'flywire_payment',
        'meta_query' => [
            [
                'key'   => '_payment_id',
                'value' => $payment_id
            ]
        ],
        'posts_per_page' => 1
    ]);

    return $query->have_posts() ? $query->posts[0] : null;
}

function inject_flywire_trigger_as_error( $validation_result ) {
    $form = $validation_result['form'];

        // do NOT inject the Flywire trigger. Let the user fix those first.
    if ( !$validation_result['is_valid'] ) {
        return $validation_result;
    }
    
    // Check our 'lock' field (e.g., ID 100). If empty, we fail validation.
    $lock_field_id = 17; 
    $payment_token = rgpost( "input_{$lock_field_id}" );

    if ( empty( $payment_token ) ) {
        $validation_result['is_valid'] = false;

        foreach ( $form['fields'] as &$field ) {
            if ( $field->id == $lock_field_id ) {
                $field->failed_validation = true;
                // INJECT TRIGGER HERE:
                $field->validation_message .= "<div id='flywire-trigger' style='display:none;'></div>";
            }
        }
    }

    $validation_result['form'] = $form;
    return $validation_result;
}


add_action('wp_ajax_add_other_data_in_fw', 'add_other_data_in_fw');
add_action('wp_ajax_nopriv_add_other_data_in_fw', 'add_other_data_in_fw');

function add_other_data_in_fw() {

    $payment_id = '';

    $form_id = 3;
    $last_entry_id = get_last_gravity_form_entry_id( $form_id );
    if ( $last_entry_id ) {

        // Retrieve the full entry object using the Gravity Forms API
        $entry = GFAPI::get_entry( $last_entry_id );

        // Get the value of a single-input field (e.g., a Single Line Text field with ID 1)
        $payment_id = rgar( $entry, '17' );

    }

    if( !empty( $payment_id ) ){
        /*
        * Add hotel datails in FW  
        */
        $payload = $_POST['payload'];
        $hotel_data = json_decode(stripslashes($payload['hotel_data']), true);
        $payment_post = get_flywire_payment_by_payment_id($payment_id);

        $post_id = $payment_post->ID;
        $room_rows = [];
        $items = $hotel_data['items'];

        update_field('fw_first_name' , rgar( $entry, '11' ), $post_id );
        update_field('fw_last_name' , rgar( $entry, '12' ), $post_id );
        update_field('fw_email' , rgar( $entry, '3' ), $post_id );
        update_field('fw_phone' , rgar( $entry, '13' ), $post_id );
        update_field('fw_user_country' , rgar( $entry, '5' ), $post_id );
        update_field('fw_city' , rgar( $entry, '8' ), $post_id );
        update_field('fw_postcode' , rgar( $entry, '9' ), $post_id );
        update_field('fw_language' , rgar( $entry, '6' ), $post_id );
        update_field('fw_user_address' , rgar( $entry, '10' ), $post_id );
        update_field('fw_amount' , rgar( $entry, '7' ), $post_id );

        foreach ($items as $item_key => $item) {

            update_field('fw_rb_hotel_id', $item['hotel_type_id'], $post_id);
            update_field('fw_hotel_name', $item['hotel_type_id'], $post_id);
            
            // Define the data for your rows

                $room_rows[] = 
                    array(
                        'fw_room_name'          => $item['room_name'],
                        'fw_rb_room_id'         => $item['room_type_id'],
                        'fw_rateplan_id'        => $item['rateplan_id'],
                        'fw_rateplan_name'      => $item['rateplan_name'],
                        'fw_room_price'         => $item['price'],
                        'fw_discount_price'     => $item['discount_price'] ?? 0,
                    );

        }
    }
    // Update the repeater field
    update_field('fw_room_data', $room_rows, $post_id);

    return wp_send_json_success( ['message' => 'Hotel data updated', 'post_id' => $post_id ] );
    // pre( $hotel_data );

}