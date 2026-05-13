<?php
/**
 * CSHP_Field class file.
 */

// Block direct access.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'No script kiddies please!' );
}


if ( ! class_exists( 'CSHP_Field' ) ) {
	/**
	 * CSHP_Field Class.
	 */
	class CSHP_Field {
		/**
		 * The fields default value.
		 *
		 * @var default_value mixed fields default value
		 */
		public $default_value;
		/**
		 * The fields arguments.
		 *
		 * @var args array fields arguments
		 */
		public $args;
		/**
		 * Constructing our field.
		 */
		public function __construct( $args ) {
			$this->args = $args;
		}
		/**
		 * Outputting our field.
		 */
		public function output() {
			// set the value.
			$value = ( ! empty( get_option( $this->args->id ) ) ) ? get_option( $this->args->id ) : $this->args->default_value;
			// echo the text input.
			echo '<input name="' . esc_attr( $this->args->id ) . '" id="' . esc_attr( $this->args->id ) . '" type="text" value="' . esc_attr( $value ) . '" class="regular-text code ' . esc_attr( $this->args->default_value ) . '" />';
			// echo the description.
			// phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
			echo '<p>' . $this->args->description . '</p>';
		}
	}
}
