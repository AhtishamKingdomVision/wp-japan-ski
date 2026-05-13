<?php
/**
 * Plugin Name: Media Deduper
 * Version: 1.5.9
 * Description: Save disk space and bring some order to the chaos of your media library by removing and preventing duplicate files.
 * Plugin URI: https://www.mediadeduper.com/
 * Author: Cornershop Creative
 * Author URI: https://cornershopcreative.com/
 *
 * @package Media_Deduper
 */

// Check PHP version. The main MDD class uses late static bindings which are only compatible with
// PHP 5.3 and higher.
if ( version_compare( phpversion(), '5.3', '>=' ) ) {

	// Load up the plugin class.
	define( 'MDD_FILE', __FILE__ );
	define( 'MDD_PATH', plugin_dir_path( MDD_FILE ) );
	define( 'MDD_INCLUDES_DIR', MDD_PATH . '/inc/' );
	require_once( MDD_INCLUDES_DIR . 'class-media-deduper.php' );

} else { // If we're running on PHP 5.2 or lower, show an error and deactivate.

	/**
	 * Display a notice about the user's PHP version.
	 */
	function media_deduper_php53_notice() {
		echo '<div class="message error"><p>' . esc_html__( 'Sorry, but Media Deduper requires PHP version 5.3 or higher.', 'media-deduper' ) . '</p></div>';
	}
	add_action( 'admin_notices', 'media_deduper_php53_notice' );

	/**
	 * Deactivate Media Deduper.
	 */
	function media_deduper_deactivate() {
		deactivate_plugins( plugin_basename( __FILE__ ) );
	}
	add_action( 'admin_init', 'media_deduper_deactivate' );

}
