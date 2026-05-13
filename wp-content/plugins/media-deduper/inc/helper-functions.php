<?php
/**
 * Helper Functions File
 *
 * @package Media_Deduper
 */

/**
 * Check if we are on a media deduper admin page.
 */
function is_mdd_admin() {
	// if is not wp admin return false
	if ( ! is_admin() ) {
		return false;
	}
	// get the current wp admin screen
	$screen = get_current_screen();

	// switch the current screen id
	// if the screen id matches a mdd id return true
	// default to false if no matches are found
	switch ( $screen->id ) {
		case 'media_page_media-deduper':
		case 'settings_page_mdd':
			return true;
			break;
		default:
			return false;
	}
}

/**
 * Get the basic authentication check output.
 *
 * @param bool $echo Whether to return or echo the output.
 */
function mdd_basic_auth_check_output( $echo = true ) {
	$output = esc_html( ( mdd_basic_auth_check() ) ? __( 'yes', 'media-deduper' ) : __( 'no', 'media-deduper' ) );
	if ( false === $echo ) {
		return $output;
	}

	echo $output; // phpcs:ignore
}

/**
 * Get the basic authentication check status.
 *
 * @return bool
 */
function mdd_basic_auth_check() {
	// Get any existing copy of our transient data
	$request = get_transient( 'mdd_wp_cron_request' );

	if ( ! is_array( $request ) || empty( $request['headers'] ) || ( ! is_a( $request['headers'], 'WpOrg\\Requests\\Utility\\CaseInsensitiveDictionary' ) && ! is_a( $request['headers'], 'Requests_Utility_CaseInsensitiveDictionary' ) ) ) {
		// No transient data found, or transient data unreadable.
		// Make a cron request the way WP core does, and save the response.
		// We apply WP's filters here so that plugins like WP Cron HTTP Auth can affect this test
		// request the same way they affect real cron requests.

		// This block of code is mostly lifted from the WP-CLI source:
		// https://raw.githubusercontent.com/wp-cli/cron-command/0138185/src/Cron_Command.php
		$doing_wp_cron = sprintf( '%.22F', microtime( true ) );
		$cron_request_array = array(
			'url'  => site_url( 'wp-cron.php?doing_wp_cron=' . $doing_wp_cron ),
			'key'  => $doing_wp_cron,
			'args' => array(
				'timeout'   => 3,
				'blocking'  => true,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			),
		);
		$cron_request = apply_filters( 'cron_request', $cron_request_array );
		// Enforce a blocking request in case something that's hooked onto the 'cron_request' filter
		// sets it to false.
		$cron_request['args']['blocking'] = true;
		$request = wp_remote_post( $cron_request['url'], $cron_request['args'] );

		set_transient( 'mdd_wp_cron_request', $request, 12 * HOUR_IN_SECONDS );
	}

	if ( is_wp_error( $request ) ) {
		return true;
	}

	if ( isset( $request['headers']['www-authenticate'] ) ) {
		return true;
	}

	if ( 401 === $request['response']['code'] ) {
		return true;
	}

	return false;
}

