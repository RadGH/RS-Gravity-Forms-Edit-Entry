<?php

class RS_Gravity_Forms_Edit_Entry_Form {
	
	// Internal variables
	protected $uploads = array();
	
	// Constructor
	public function __construct() {
		
		// Capture the 'editable' property from the shortcode and store it for later use.
		add_filter( 'gform_shortcode_form', array( $this, 'capture_shortcode_args' ), 15, 3 );
		
		// When editing an entry, change the entry ID to the edited entry instead of creating a new entry
		add_filter( "gform_entry_id_pre_save_lead", array( $this, 'change_saved_entry_id' ), 50, 2 );
		
		// Keeps file uploads unless the user actually deletes or replaces them
		add_filter( "gform_pre_process", array( $this, 'pre_restore_existing_uploads' ), 20, 1 );
		
		// Disable notifications for edited entries
		add_filter( 'gform_disable_notification', array( $this, 'disable_notifications' ), 10, 5 );
		
		// Modify confirmation after editing an entry
		add_filter( 'gform_confirmation', array( $this, 'edit_confirmation' ), 15, 4 );
		
	}
	
	// Singleton instance
	protected static $instance = null;
	
	public static function get_instance() {
		if ( !isset( self::$instance ) ) self::$instance = new static();
		return self::$instance;
	}
	
	// Utilities
	/**
	 * Get the entry id that is being edited from the url ?entry_id=100
	 *
	 * @param int $form_id
	 *
	 * @return int|false
	 */
	public function get_edited_entry_id( $form_id ) {
		// Use the entry ID that was submitted
		$entry_id = (int) rgar( $_POST, 'rs_gf_edit_entry' );
		
		// Verify the entry belongs to the form
		if ( $entry_id ) {
			$entry = GFAPI::get_entry( $entry_id );
			if ( ! $entry || $entry['form_id'] != $form_id ) {
				$entry_id = false;
			}
		}
		
		if ( $this->can_user_edit_entry( get_current_user_id(), $entry_id ) ) {
			return $entry_id;
		}else{
			return false;
		}
	}
	
	/**
	 * Get the latest entry for a user
	 *
	 * @param int $form_id
	 * @param int $user_id
	 *
	 * @return false|int
	 */
	public function get_latest_entry_id( $form_id, $user_id ) {
		$search = array(
			'status' => 'active',
			'field_filters' => array(
				array(
					'key' => 'created_by',
					'value' => $user_id,
				),
			),
		);
		
		$sort = array(
			'page_size' => 1,
			'key' => 'date_created',
			'direction' => 'DESC',
		);
		
		$user_entries = GFAPI::get_entries( $form_id, $search, $sort);
		
		if ( ! $user_entries ) return false;
		
		return (int) rgar( $user_entries[0], 'id' );
	}
	
	/**
	 * Return true if the given user is able to make edits to the entry.
	 *
	 * @param int $user_id
	 * @param array|int $entry
	 *
	 * @return bool
	 */
	public function can_user_edit_entry( $user_id, $entry ) {
		if ( is_numeric($entry) ) $entry = GFAPI::get_entry( $entry );
		if ( ! $entry || is_wp_error( $entry ) ) return false;
		
		$can_edit = true;
		
		// The user ID must match the owner of the entry
		if ( (int) $entry['created_by'] != (int) $user_id ) $can_edit = true;
		
		$can_edit = (bool) apply_filters( 'rs_gravityforms_edit_entry/can_user_edit_entry', $can_edit, $entry, $user_id );
		
		return $can_edit;
	}
	
	/**
	 * Add certain hooks only when the form is going to be displayed (based on shortcode usage)
	 *
	 * $args = array(7) {
	 *     "form_id"             => "12"
	 *     "display_title"       => true
	 *     "display_description" => true
	 *     "force_display"       => false
	 *     "field_values"        => array() (empty)
	 *     "ajax"                => false
	 *     "tabindex"            => "0"
	 * }
	 *
	 * @param int $form_id
	 *
	 * @return string
	 */
	public function prepare_editable_form( $form_id, $title = false, $description = false, $confirmation = false ) {
		// Hook before
		do_action( 'rs-gravityforms-edit-entry/editable-form/before', $form_id );
		
		// Add Hooks:
		
		// Fill default values for fields when editing an entry
		add_filter( 'gform_pre_render_' . $form_id, array( $this, 'prefill_field_values' ), 20 );
		
		// Add hidden fields to manage the submitted entry
		add_filter( 'gform_form_tag_' . $form_id, array( $this, 'add_hidden_fields' ), 10, 2 );
		
		// Get latest entry
		$entry_id = RS_Gravity_Forms_Edit_Entry_Form::get_instance()->get_latest_entry_id( $form_id, get_current_user_id() );
		
		if ( $entry_id ) {
			$entry = RS_Gravity_Forms_Edit_Entry_Form::get_instance()->prepare_editable_entry( $form_id, $entry_id );
		}else{
			$entry = false;
		}
		
		// Store confirmation
		if ( $confirmation ) {
			RS_Gravity_Forms_Edit_Entry_Form::get_instance()->set_confirmation( $form_id, $confirmation );
		}
		
		$form = gravity_form( $form_id, $title, $description, false, $entry, false, 0, false );
		
		// Hook after
		do_action( 'rs-gravityforms-edit-entry/editable-form/after', $form_id );
		
		return $form;
	}
	
	/**
	 * Load an entry and populate values to use for an editable entry
	 *
	 * @param int $form_id
	 * @param int $entry_id
	 *
	 * @return array
	 */
	public function prepare_editable_entry( $form_id, $entry_id ) {
		return $this->get_field_values( $form_id, $entry_id );
	}
	
	/**
	 * Returns true if a string is specifically not a false value: blank, 0, or "false".
	 *
	 * @param string $str
	 *
	 * @return bool
	 */
	public function is_string_true( $str ) {
		return ( $str !== '' && $str !== '0' && $str !== 'false' );
	}
	
	
	// Hooks
	/**
	 * Capture the 'allowEditingEntries' property from the block and store it for later use.
	 *
	 * @param string $shortcode_string The full shortcode string.
	 * @param array $attributes        The attributes within the shortcode.
	 * @param string $content          The content of the shortcode, if available.
	 *
	 * @return string
	 */
	public function capture_shortcode_args( $shortcode_string, $attributes, $content ) {
		$form_id = rgar( $attributes, 'id' );
		$editable = strtolower((string) rgar( $attributes, 'editable' ));
		
		if ( $form_id && $this->is_string_true( $editable ) ) {
			$title = $this->is_string_true( rgar( $attributes, 'title' ) );
			$description = $this->is_string_true( rgar( $attributes, 'description' ) );
			$confirmation = (string) rgar( $attributes, 'confirmation' );
			
			return $this->prepare_editable_form( $form_id, $title, $description, $confirmation );
		}
		
		// Return the args
		return $shortcode_string;
	}
	
	
	/**
	 * Make Gravity Forms edit an existing entry ($entry_id = int), instead of creating a new one ($entry_id = null).
	 *
	 * @param $entry_id null|int
	 * @param $form     array
	 *
	 * @return null|int
	 */
	public function change_saved_entry_id( $entry_id, $form ) {
		if ( $entry_id !== null ) return $entry_id;
		
		$user_id = get_current_user_id();
		
		// Get the entry being edited
		$existing_entry_id = $this->get_edited_entry_id( $form['id'] );
		
		// Check access, if user can edit then return the previous entry ID, instead of creating a new one (null).
		if ( $existing_entry_id && $this->can_user_edit_entry( $user_id, $existing_entry_id ) ) {
			
			// Log a note on the entry to notify that the form was edited by the user
			$user_name = get_the_author_meta( 'display_name', $user_id );
			$date = current_time('m/d/Y g:i:s a');
			$note = 'Entry updated at '.  $date .' on the page: ' . get_permalink();
			GFAPI::add_note( $existing_entry_id, $user_id, $user_name, $note );
			
			// Update existing entry
			return $existing_entry_id;
		}else{
			// Create a new entry
			return null;
		}
	}
	
	/**
	 * When rendering a form, load the default values using the entry that is being edited.
	 *
	 * @param $form
	 *
	 * @return mixed
	 */
	public function prefill_field_values( $form ) {
		$existing_entry_id = $this->get_latest_entry_id( $form['id'], get_current_user_id() );
		if ( !$existing_entry_id ) return $form;
		
		$existing_entry = GFAPI::get_entry( $existing_entry_id );
		if ( !$existing_entry ) return $form;
		
		foreach( $form['fields'] as &$field ) {
			if ( ! $field instanceof GF_Field ) continue;
			$value = GFFormsModel::get_lead_field_value( $existing_entry, $field );
			
			/*
			// Advanced inputs like Name and Address have multiple sub inputs, each with their own name and values
			// They are tricky to fill because Gravity Forms doesn't give you the key with this filter.
			if ( !empty( $field->inputs ) ) {
				foreach( $field->inputs as $key => $sub_input ) {
					if ( ! is_array($sub_input) ) continue;
					
					// Get the name, if any
					$name = rgar($sub_input, 'name' );
					if ( !$name ) {
						$name = 'sub_field_' . $sub_input['id'] . '_value';
						$sub_input['name'] = $name;
					}
					
					// Save a name for the sub input
					$field->inputs[$key]['name'] = $name;
					
					// Store the sub input field assigned to the name (first_name) so we can get field data later
					$this->sub_inputs[ $name ] = $sub_input;
					
					// Use a special filter for sub inputs
					add_filter( "gform_field_value_{$name}", array( $this, 'fill_sub_input_value' ), 20, 3 );
				}
			}
			*/
			
			
			if ( $field->type == 'checkbox' || $field->type == 'radio' ) {
				
				// For checkboxes and multi-selects, we need to set the 'isSelected' property on the choices
				if ( ! is_array($value) ) {
					$value = explode(',', $value );
				}
				
				foreach ($field->choices as &$choice) {
					if ( in_array( $choice['value'], $value, true ) ) { // Match the 'value' of the choice
						$choice['isSelected'] = true;
					}
				}
				
			}else{
				
				// For all other fields, just set the 'defaultValue' property
				$field->defaultValue = $value;
				
			}
		}
		
		return $form;
	}
	
	public function get_field_values( $form_id, $entry_id ) {
		$form = GFFormsModel::get_form_meta( $form_id );
		$entry = GFAPI::get_entry( $entry_id );
		if ( ! $form || ! $entry ) return array();
		
		$values = array();
		
		foreach ( $form['fields'] as $field ) {
			
			$value = $this->get_field_value( $field, $entry );
			$values[ $field['id'] ] = $value;
			
			switch ( GFFormsModel::get_input_type( $field ) ) {
				
				case 'list':
					$value = maybe_unserialize( $value );
					$list_values = array();
					
					if ( is_array( $value ) ) {
						foreach ( $value as $vals ) {
							if ( is_array( $vals ) ) {
								// Escape commas so the value is not split into multiple inputs.
								$vals = implode( '|', array_map( function( $value ) {
									$value = str_replace( ',', '&#44;', $value );
									return $value;
								}, $vals ) );
							}
							array_push( $list_values, $vals );
						}
						$values[ $field->id ] = implode( ',', $list_values );
					}
					break;
				
				case 'checkbox':
					$values[ $field['id'] ] = (array) $value;
					break;
				
				case 'number':
					$value         = rgar( $entry, $field->id );
					$number_format = rgar( $field, 'numberFormat' );
					
					if ( $number_format ) {
						$value = GFCommon::format_number( $value, $number_format );
					}
					
					$values[ $field['id'] ] = $value;
					break;
				
				case 'multiselect':
					$value = GFCommon::maybe_decode_json( $value );
					$values[ $field['id'] ] = $value;
					break;
				
				case 'fileupload':
					$multi = $field->multipleFiles;
					$value = rgar( $entry, $field->id );
					$return = array();
					
					if ( $multi ) {
						$files = json_decode( $value );
					} else {
						$files = array( $value );
					}
					
					if ( is_array( $files ) ) {
						foreach ( $files as $file ) {
							
							$path_info = pathinfo( $file );
							
							// Check if file has been "deleted" via form UI.
							$upload_files = json_decode( rgpost( 'gform_uploaded_files' ), ARRAY_A );
							$input_name   = "input_{$field->id}";
							
							if ( is_array( $upload_files ) && array_key_exists( $input_name, $upload_files ) && ! $upload_files[ $input_name ] ) {
								continue;
							}
							
							if ( $multi ) {
								$return[] = array(
									'uploaded_filename' => $path_info['basename'],
								);
							} else {
								$return[] = $path_info['basename'];
							}
						}
					}
					
					// if $uploaded_files array is not set for this form at all, init as array
					if ( ! isset( GFFormsModel::$uploaded_files[ $form['id'] ] ) ) {
						GFFormsModel::$uploaded_files[ $form['id'] ] = array();
					}
					
					// check if this field's key has been set in the $uploaded_files array, if not add this file (otherwise, a new image may have been uploaded so don't overwrite)
					if ( ! isset( GFFormsModel::$uploaded_files[ $form['id'] ][ "input_{$field->id}" ] ) ) {
						GFFormsModel::$uploaded_files[ $form['id'] ][ "input_{$field->id}" ] = $multi ? $return : reset( $return );
					}
			}
		}
		
		return $values;
	}
	
	/**
	 * @param GF_Field $field
	 * @param array $entry
	 *
	 * @return array|mixed
	 */
	public function get_field_value( $field, $entry ) {
		$values = array();
		
		foreach ( $entry as $input_id => $value ) {
			$fid = intval( $input_id );
			if ( $fid === (int) $field['id'] ) {
				$values[] = $value;
			}
		}
		
		return count( $values ) <= 1 ? $values[0] : $values;
	}
	
	/**
	 * Keeps file uploads unless the user actually deletes or replaces them.
	 * Gravity forms seems to do this, but fails at it.
	 * To make this work we capture values before (here) and restore them after the entry is saved.
	 *
	 * @param $form
	 *
	 * @return mixed
	 */
	public function pre_restore_existing_uploads( $form ) {
		$entry_id = $this->get_edited_entry_id( $form['id'] );
		if ( ! $entry_id ) return $form;
		
		// Get uploads from $_POST, served as JSON string, which has file upload info
		$uploads = rgpost( 'gform_uploaded_files' );
		if ( !$uploads ) return $form;
		
		// Decode the json
		$uploads = json_decode( $uploads );
		
		// Each file upload field will have the previous filename, or NULL if removing that file.
		if ( $uploads ) foreach( $uploads as $input_name => $file ) {
			
			// If file was removed by user, or replaced with new file
			if ( ! $file ) continue;
			
			// File should be kept.
			$field_id = (int) str_replace('input_', '', $input_name );
			$url = gform_get_meta( $entry_id, $field_id );
			
			$this->uploads[ $input_name ] = array(
				'entry_id' => $entry_id,
				'input_name' => $input_name,
				'field_id' => $field_id,
				'url' => $url
			);
		}
		
		add_filter( "gform_after_submission_{$form['id']}", array( $this, 'restore_existing_uploads' ), 20, 2 );
		
		return $form;
	}
	
	/**
	 * Append hidden inputs to the form to signal that the submission should be processed as an edit instead of adding a new entry.
	 *
	 * @param string $form_tag The form opening tag.
	 * @param array $form The current form.
	 *
	 * @return string
	 */
	public function add_hidden_fields( $form_tag, $form ) {
		// Edited Entry ID
		$entry_id = $this->get_latest_entry_id( $form['id'], get_current_user_id() );
		if ( ! $entry_id ) {
			return $form_tag;
		}
		
		$form_tag .= "\n" . '<input type="hidden" name="rs_gf_edit_entry" value="' . esc_attr( $entry_id ) . '" />';
		
		// Confirmation message
		$confirmation_message = $this->get_confirmation( $form['id'] );
		if ( $confirmation_message ) {
			// Encrypt the message
			$confirmation_message = acf_encrypt($confirmation_message);
			$form_tag .= "\n" . '<input type="hidden" name="rs_gf_confirmation" value="' . esc_attr( $confirmation_message ) . '" />';
		}
		
		return $form_tag;
	}
	
	/**
	 * Disable notifications for edited entries
	 *
	 * @param bool  $disabled
	 * @param array $notification
	 * @param array $form
	 * @param array $entry
	 * @param array $data
	 */
	public function disable_notifications( $disabled, $notification, $form, $entry, $data = array() ) {
		if ( $this->get_edited_entry_id( $form['id'] ) ) {
			$disabled = true;
		}
		
		return $disabled;
	}
	
	/**
	 * Modify confirmation after editing an entry
	 *
	 * @param array $confirmation
	 * @param array $form
	 * @param array $entry
	 * @param bool  $ajax
	 *
	 * @return array|string
	 */
	public function edit_confirmation( $confirmation, $form, $entry, $ajax ) {
		if ( ! $this->get_edited_entry_id( $form['id'] ) ) {
			return $confirmation;
		}
		
		// Get confirmation message from $_POST
		$message = isset($_POST['rs_gf_confirmation']) ? stripslashes( $_POST['rs_gf_confirmation'] ) : false;
		if ( ! $message ) return $confirmation;
		
		// Decrypt the message
		$message = acf_decrypt($message);
		
		// Apply gravity forms merge tags
		$message = GFCommon::replace_variables( $message, $form, $entry, false, false, false, 'text' );
		
		// Apply wpautop
		$message = wpautop($message);
		
		// Apply shortcodes
		$message = do_shortcode($message);
		
		// Allow filtering the message
		$message = apply_filters( 'rs_gravityforms_edit_entry/edit_confirmation', $message, $form, $entry );
		
		if ( ! $message ) {
			return $confirmation;
		}
		
		// Replace the confirmation
		$confirmation = array(
			'type' => 'message',
			'message' => $message,
		);
		
		$form['confirmations'] = array( $confirmation );
		
		// Handle the confirmation again, avoiding infinite loop
		remove_filter( 'gform_confirmation', array( $this, 'edit_confirmation' ), 15 );
		$confirmation = \GFFormDisplay::handle_confirmation( $form, $entry );
		add_filter( 'gform_confirmation', array( $this, 'edit_confirmation' ), 15, 4 );
		
		return $confirmation;
	}
	
	/**
	 * Store, get, and set confirmations
	 * @var array
	 */
	protected $confirmations = array();
	public function set_confirmation( $form_id, $confirmation_message ) {
		$this->confirmations[ $form_id ] = $confirmation_message;
	}
	public function get_confirmation( $form_id ) {
		return $this->confirmations[ $form_id ] ?? false;
	}
	
	
	
	// Internal Hooks
	/**
	 * Restore files preserved by pre_restore_existing_uploads() after the entry has been saved
	 *
	 * @param $entry
	 * @param $form
	 *
	 * @return mixed
	 */
	public function restore_existing_uploads( $entry, $form ) {
		$restored_any = false;
		
		if ( $this->uploads ) foreach( $this->uploads as $u ) {
			if ( $entry['id'] != $u['entry_id'] ) continue;
			
			// Get the field ID and URL of the file that should be preserved
			$field_id = $u['field_id'];     // 13
			$url = $u['url'];               // https://example.com/.../icon-zm3.png
			
			// Restore the URL to the entry
			$entry[ $field_id ] = $url;
			
			$restored_any = true;
			
			// Note: This doesn't seem to work:
			// gform_update_meta( $entry['id'], $field_id, $url );
			
		}
		
		if ( $restored_any ) {
			
			// Update the entry
			GFAPI::update_entry( $entry );
			
		}
		
		return $entry;
	}
	
	
}


// Initialize the plugin
RS_Gravity_Forms_Edit_Entry_Form::get_instance();