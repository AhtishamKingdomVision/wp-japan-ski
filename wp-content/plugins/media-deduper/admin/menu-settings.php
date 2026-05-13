<?php
/**
 * Menu Settings Page file.
 */

// Block direct access.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'No script kiddies please!' );
}

// The media deduper settings page.
$mdd_settings = new CSHP_Settings_Page( __( 'Media Deduper', 'media-deduper' ), __( 'Media Deduper', 'media-deduper' ), 'manage_options', 'mdd' );
