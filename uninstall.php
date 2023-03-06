<?php // fires when plugin is uninstalled via the Plugins screen


// exit if uninstall constant is not defined
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) 
{
	exit;
}

// delete the plugin options
delete_option( 'shipbubble_options' );
delete_option( 'shipbubble_shipping_services_woocommerce_settings' );
