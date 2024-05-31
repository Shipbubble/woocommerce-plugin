<?php // fires when plugin is uninstalled via the Plugins screen


// exit if uninstall constant is not defined
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) 
{
	exit;
}

// includes
require_once plugin_dir_path( __FILE__ ) . 'includes/constants.php';



// delete the plugin options
delete_option( WC_SHIPBUBBLE_ID );
delete_option( 'shipbubble_options' );
delete_option( SHIPBUBBLE_INIT );
