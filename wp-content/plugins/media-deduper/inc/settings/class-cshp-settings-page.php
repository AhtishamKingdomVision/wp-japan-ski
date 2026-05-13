<?php
/**
 * CSHP Settings Page class file.
 */

// Block direct access.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'No script kiddies please!' );
}

if ( ! class_exists( 'CSHP_Settings_Page' ) ) {
	/**
	 * CSHP_Settings_Page Class.
	 */
	class CSHP_Settings_Page {
		/**
		 * The Settings Page Title
		 *
		 * @var page_title string page title
		 */
		public $page_title = 'CSHP Settings Page';
		/**
		 * The settings menu title.
		 *
		 * @var menu_title string menu title
		 */
		public $menu_title = 'CSHP Settings';
		/**
		 * The settings page needed capability.
		 *
		 * @var capability string capability
		 */
		public $capability = 'manage_options';
		/**
		 * The settings page menu slug.
		 *
		 * @var menu_slug string menu slug
		 */
		public $menu_slug  = '';

		/**
		 * Cosntructing our settings page.
		 */
		public function __construct( $page_title, $menu_title, $capability, $menu_slug ) {
			// setting the classes properties.
			$this->page_title = isset( $page_title ) ? $page_title : $this->page_title;
			$this->menu_title = isset( $menu_title ) ? $menu_title : $this->menu_title;
			$this->capability = isset( $capability ) ? $capability : $this->capability;
			$this->menu_slug  = isset( $menu_slug ) ? $menu_slug : $this->menu_slug;

			// Setting our classes hooks.
			add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
		}

		/**
		 * Add the settings page.
		 */
		public function admin_menu() {
			add_options_page(
				$this->page_title,
				$this->menu_title,
				$this->capability,
				$this->menu_slug,
				array( &$this, 'settings_page' )
			);
		}

		/**
		 * Create the settings page.
		 */
		public function settings_page() {
			?>
			<div class="wrap">
				<h1><?php echo esc_html( $this->page_title ); ?></h1>
				<form method="post" action="options.php">
					<?php settings_fields( $this->menu_slug ); ?>
					<?php do_settings_sections( $this->menu_slug ); ?>
					<?php submit_button(); ?>
					<?php do_action( "cshp_settings_page_after_section_{$this->menu_slug}" ); ?>
					<?php do_action( 'cshp_settings_page_after_section' ); ?>
				</form>
			</div>
			<?php
		}

		/**
		 * Add setting sections to the page.
		 */
		public function add_settings_section( $section_id, $section_title, $section_description = '' ) {
			// set the new section.
			$section = new CSHP_Settings_Section( $section_id, $section_title, $section_description, $this->menu_slug );
			// return the created section.
			return $section;
		}
	}
}
