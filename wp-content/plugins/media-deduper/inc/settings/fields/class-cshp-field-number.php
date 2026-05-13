<?php
/**
 * CSHP_Field_Number class file.
 */

// Block direct access.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'No script kiddies please!' );
}

if ( ! class_exists( 'CSHP_Field_Number' ) ) {
	/**
	 * CSHP_Field_Number Class.
	 */
	class CSHP_Field_Number extends CSHP_Field {
		/**
		 * Outputting our field.
		 */
		public function output() {
			// set the value.
			$value = ( ! empty( get_option( $this->args->id ) ) ) ? get_option( $this->args->id ) : $this->args->default_value;
			// echo the number field.
			echo '<input name="' . esc_attr( $this->args->id ) . '" id="' . esc_attr( $this->args->id ) . '" type="number" value="' . esc_attr( $value ) . '" class="small-text" />';
			// echo the description.
			// phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
			echo '<p>' . $this->args->description . '</p>';
		}
	}
}
