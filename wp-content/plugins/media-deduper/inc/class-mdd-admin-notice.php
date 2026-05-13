<?php
/**
 * MDD_Admin_Notice file.
 */

// Disable direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'MDD_Admin_Notice' ) ) {
	/**
	 * MDD_Admin_Notice notice class, to easily handle admin notices
	 */
	class MDD_Admin_Notice {
		/**
		 * Notice text.
		 *
		 * @var $notice
		 */
		public $notice;
		/**
		 * Notice type.
		 *
		 * @var $type
		 */
		public $type;
		/**
		 * Constructor
		 *
		 * @param   string $notice the notice text.
		 * @param   string $type   the notice type.
		 */
		function __construct( $notice, $type = 'updated' ) {
			$this->notice   = $notice;
			$this->type     = $type;
			add_action( 'admin_notices', array( &$this, 'add_admin_notice' ) );
		}
		/**
		 * Adds the admin notice
		 */
		function add_admin_notice() {
			echo '<div class="' . esc_attr( $this->type ) . '">';
			echo '<p>' . wp_kses( $this->notice, wp_kses_allowed_html() ) . '</p>';
			echo '</div>';
		}
	}

}//end if
