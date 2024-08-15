<?php

class RS_Gravity_Forms_Edit_Entry_Block {
	
	// Constructor
	public function __construct() {
		
		// Register blocks
		add_action( 'init', array( $this, 'register_blocks' ) );
		
		// Populate the "Form" dropdown in the ACF block
		add_filter( 'acf/load_field/key=field_66be2c09f2afd', array( $this, 'populate_form_dropdown' ) );
		
	}
	
	// Singleton instance
	protected static $instance = null;
	
	public static function get_instance() {
		if ( !isset( self::$instance ) ) self::$instance = new static();
		return self::$instance;
	}
	
	// Hooks
	/**
	 * Register blocks
	 *
	 * @return void
	 */
	public function register_blocks() {
		
		register_block_type( RS_GF_Edit_Entry_PATH . '/blocks/editable-form/block.json');
	}
	
	/**
	 * Populate the "Form" dropdown in the ACF block
	 *
	 * @param array $field
	 *
	 * @return array
	 */
	public function populate_form_dropdown( $field ) {
		if ( acf_is_screen('acf-field-group') ) return $field;
		if ( acf_is_screen('acf_page_acf-tools') ) return $field;
		
		// Get the forms
		$forms = GFAPI::get_forms();
		
		// Populate the choices
		$field['choices'] = array();
		
		// Add a null value
		$field['choices'][0] = '&ndash; Select form &ndash;';
		
		foreach( $forms as $form ) {
			$field['choices'][ $form['id'] ] = $form['title'];
		}
		
		return $field;
	}
	
}

RS_Gravity_Forms_Edit_Entry_Block::get_instance();
