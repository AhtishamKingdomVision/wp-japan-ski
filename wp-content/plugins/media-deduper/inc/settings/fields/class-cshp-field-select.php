<?php
/**
 * CSHP_Field_Select class file.
 */

// Block direct access.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'No script kiddies please!' );
}

if ( ! class_exists( 'CSHP_Field_Select' ) ) {
	/**
	 * CSHP_Field_Select Class.
	 */
	class CSHP_Field_Select extends CSHP_Field {
		/**
		 * Outputting our field.
		 */
		public function output() {
			// $this->args = $args;
			// switch the fields options to check if we should output a select of pages or a default field.
			switch ( $this->args->options ) :
				case 'pages':
					$value = ( ! empty( get_option( $this->args->id ) ) ) ? get_option( $this->args->id ) : $this->args->default_value;
					$args = array(
						'depth'                 => 0,
						'child_of'              => 0,
						'selected'              => $value,
						'echo'                  => 1,
						'name'                  => $this->args->id,
						'id'                    => $this->args->id,
						'class'                 => 'regular-text code swpe-select ' . $this->args->classes,
						'show_option_none'      => 'none',
						'show_option_no_change' => null,
						'option_none_value'     => null,
					);
					// phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
					wp_dropdown_pages( $args );
					break;
				default:
					echo '<label for="' . esc_attr( $this->args['id'] ) . '">' . esc_html( $this->args['label'] ) . '</label>';
					echo '<select name="' . esc_attr( $this->args->id ) . '" id="' . esc_attr( $this->args->id ) . '" value="' . esc_attr( $value ) . '" class="regular-text code swpe-select ' . esc_attr( $this->args->classes ) . '" >';
					echo '</select>';
			endswitch;
			// echo the description.
			// phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
			echo '<p>' . $this->args->description . '</p>';
		}
	}
}
