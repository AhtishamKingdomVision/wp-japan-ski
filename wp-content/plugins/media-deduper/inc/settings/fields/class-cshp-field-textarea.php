<?php
/**
 * CSHP_Field_Textarea class file.
 */

// Block direct access.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'No script kiddies please!' );
}

if ( ! class_exists( 'CSHP_Field_Textarea' ) ) {
	/**
	 * CSHP_Field_Textarea Class.
	 */
	class CSHP_Field_Textarea extends CSHP_Field {
		/**
		 * Outputting our field.
		 */
		public function output() {
			$allowed_html = wp_kses_allowed_html();
			// set the value.
			$value = ( ! empty( get_option( $this->args->id ) ) ) ? get_option( $this->args->id ) : $this->args->default_value;
			// echo the textarea field.
			echo '<textarea name="' . esc_attr( $this->args->id ) . '" id="' . esc_attr( $this->args->id ) . '" rows="' . esc_attr( $this->args->rows ) . '" class="large-text code" >' . esc_textarea( $value ) . '</textarea>';
			// echo the description.
			// phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
			echo '<p>' . $this->args->description . '</p>';
		}
	}
}