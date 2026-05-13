<?php
/**
 * CSHP_Settings_Section class file.
 */

// Block direct access.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'No script kiddies please!' );
}

if ( ! class_exists( 'CSHP_Settings_Section' ) ) {
	/**
	 * CSHP_Settings_Section Class.
	 */
	class CSHP_Settings_Section {
		/**
		 * The section id.
		 *
		 * @var section_id string section id
		 */
		public $section_id;
		/**
		 * The Section title.
		 *
		 * @var section_title string section title
		 */
		public $section_title;
		/**
		 * The section description.
		 *
		 * @var section_description string section description
		 */
		public $section_description = '';
		/**
		 * The Sections menu slug.
		 *
		 * @var section_menu_slug string section menu slug
		 */
		public $section_menu_slug;

		/**
		 * Constructing our class.
		 */
		public function __construct( $section_id, $section_title, $section_description, $section_menu_slug ) {
			// setting the properties.
			$this->section_menu_slug    = isset( $section_menu_slug ) ? $section_menu_slug : '';
			$this->section_id           = isset( $section_id ) ? $this->section_menu_slug . '_' . $section_id : '';
			$this->section_title        = isset( $section_title ) ? $section_title : '';
			$this->section_description  = isset( $section_description ) ? $section_description : $this->section_description;

			// setting our classes hooks.
			add_action( 'admin_init', array( &$this, 'add_settings_section' ) );
		}

		/**
		 * Add the section.
		 */
		public function add_settings_section() {
			add_settings_section(
				$this->section_id,
				$this->section_title,
				array( &$this, 'section_description' ),
				$this->section_menu_slug
			);
		}

		/**
		 * Add our sections description.
		 */
		public function section_description() {
			// phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
			echo '<p>' . $this->section_description . '</p>';
		}

		/**
		 * Add the settings field.
		 */
		public function add_settings_field( $args = array() ) {
			// Set the new Setting.
			$setting = new CSHP_Settings_Setting( $args, $this->section_id, $this->section_menu_slug );
			// Return the setting.
			return $setting;
		}
	}
}
