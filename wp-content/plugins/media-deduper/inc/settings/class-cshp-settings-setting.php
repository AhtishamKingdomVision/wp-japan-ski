<?php
/**
 * CSHP Settings Page class file.
 */

// Block direct access.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'No script kiddies please!' );
}

if ( ! class_exists( 'CSHP_Settings_Setting' ) ) {
	/**
	 * CSHP_Settings_Setting Class.
	 */
	class CSHP_Settings_Setting {
		/**
		 * Id of setting.
		 *
		 * @var id string setting id
		 */
		public $id;
		/**
		 * /label of setting.
		 *
		 * @var label string setting label
		 */
		public $label;
		/**
		 * Description of setting.
		 *
		 * @var description string setting description
		 */
		public $description;
		/**
		 * Field type of setting.
		 *
		 * @var field_type string field type
		 */
		public $field_type;
		/**
		 * Default value of setting.
		 *
		 * @var default_value string stting default value
		 */
		public $default_value;
		/**
		 * Options of setting.
		 *
		 * @var options string setting options
		 */
		public $options;
		/**
		 * Rows of setting.
		 *
		 * @var rows string setting rows
		 */
		public $rows;
		/**
		 * Section id of setting.
		 *
		 * @var section_id string setting section id
		 */
		public $section_id;
		/**
		 * Menu id of setting.
		 *
		 * @var menu_id string setting menu id.
		 */
		public $menu_id;

		/**
		 * Constructing our class.
		 */
		public function __construct( $args, $section_id, $menu_id ) {
			// setting classes properties
			$this->section_id    = isset( $section_id ) ? $section_id : '';
			$this->menu_id       = isset( $menu_id ) ? $menu_id : '';

			$this->id            = isset( $args['id'] ) ? $this->section_id . '_' . $args['id'] : '';
			$this->label         = isset( $args['label'] ) ? $args['label'] : '';
			$this->description   = isset( $args['description'] ) ? $args['description'] : '';
			$this->field_type    = isset( $args['field_type'] ) ? $args['field_type'] : '';
			$this->default_value = isset( $args['default'] ) ? $args['default'] : '';
			$this->options       = isset( $args['options'] ) ? $args['options'] : '';
			$this->classes       = isset( $args['classes'] ) ? $args['classes'] : '';
			$this->rows          = isset( $args['rows'] ) ? $args['rows'] : 5;

			// adding hooks.
			add_action( 'admin_init', array( &$this, 'add_settings_field' ) );
		}

		/**
		 * Add the settings field.
		 */
		public function add_settings_field() {
			// add the field.
			add_settings_field(
				$this->id,
				// id
				$this->label,
				// title
				array( &$this, 'section_setting' ),
				// field printing
				$this->menu_id,
				// page id
				$this->section_id
				// section id
			);

			// register the setting.
			register_setting( $this->menu_id, $this->id );
		}

		/**
		 * Outputing the field.
		 */
		public function section_setting() {
			// check if the field class exists otherwise use the default class of text.
			if ( class_exists( 'CSHP_Field_' . ucfirst( $this->field_type ) ) && 'text' !== $this->field_type ) :
				$class = 'CSHP_Field_' . ucfirst( $this->field_type );
			else :
				$class = 'CSHP_Field';
			endif;

			// call the field class.
			$setting = new $class( $this );
			// output the field.
			$setting->output();
		}
	}
}
