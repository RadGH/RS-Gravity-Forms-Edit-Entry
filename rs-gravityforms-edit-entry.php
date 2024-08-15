<?php
/*
Plugin Name: RS Gravity Forms Edit Entry
Description: Allows users to edit their previous entry on a gravity form. Adds a new "Editable Form" block and supports the Gravity Forms shortcode by adding the <code>editable="true</code> property.
Version: 1.0.0
Author: Radley Sustaire
Author URI: https://radleysustaire.com
GitHub Plugin URI: https://github.com/RadGH/RS-Gravity-Forms-Edit-Entry
GitHub Branch: main
*/

define( 'RS_GF_Edit_Entry_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'RS_GF_Edit_Entry_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'RS_GF_Edit_Entry_VERSION', '1.0.0' );

class RS_Gravity_Forms_Edit_Entry_Plugin {
	
	// Constructor
	public function __construct() {
		
		add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
		
	}
	
	// Singleton instance
	protected static $instance = null;
	
	public static function get_instance() {
		if ( !isset( self::$instance ) ) self::$instance = new static();
		return self::$instance;
	}
	
	// Hooks
	public function init() {
		
		// Check for required plugins
		$missing_plugins = array();
		
		if ( ! class_exists('GFAPI') ) {
			$missing_plugins[] = 'Gravity Forms';
		}
		
		if ( $missing_plugins ) {
			self::add_admin_notice( '<strong>RS Gravity Forms Edit Entry:</strong> The following plugins are required: '. implode(', ', $missing_plugins) . '.', 'error' );
			return;
		}
		
		// Load the plugin files
		require_once( RS_GF_Edit_Entry_PATH . '/assets/acf-field.php' );
		require_once( RS_GF_Edit_Entry_PATH . '/includes/block.php' );
		require_once( RS_GF_Edit_Entry_PATH . '/includes/form.php' );
	}
	
	/**
	 * Adds an admin notice to the dashboard's "admin_notices" hook.
	 *
	 * @param string $message The message to display
	 * @param string $type    The type of notice: info, error, warning, or success. Default is "info"
	 * @param bool $format    Whether to format the message with wpautop()
	 *
	 * @return void
	 */
	public static function add_admin_notice( $message, $type = 'info', $format = true ) {
		add_action( 'admin_notices', function() use ( $message, $type, $format ) {
			?>
			<div class="notice notice-<?php echo $type; ?> bbearg-crm-notice">
				<?php echo $format ? wpautop($message) : $message; ?>
			</div>
			<?php
		});
	}
	
}


// Initialize the plugin
RS_Gravity_Forms_Edit_Entry_Plugin::get_instance();