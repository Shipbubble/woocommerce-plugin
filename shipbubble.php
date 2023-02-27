<?php 

	/**
	 * Plugin Name:  ShipBubble
	 * Description:  Plugin for enabling & managing logistics & deliveries for orders on eCommerce platforms
	 * Plugin URI:   https://profiles.wordpress.org/oghenemavo
	 * Contributors: oghenemavo
	 * Author:       Mavi Onogomuho
	 * Author URI:   https://linkedin.com/in/mavi-onogomuho
	 * Tags:         delivery, logistics, eCommerce
	 * Version:      1.0
	 * Stable tag:   1.0
	 * Requires at least: 5.6
	 * Tested up to:      8.1
	 * Text Domain:  shipbubble
	 * Domain Path:  /languages
	 * License:      GPL v3 or later
	 * License URI:  https://www.gnu.org/licenses/gpl-3.0.txt
	 */

	// exit if file is called directly
	if ( ! defined( 'ABSPATH' ) ) 
	{
		exit;
	}

	// if  admin area
	if ( is_admin() ) 
	{
		// include dependencies
		require_once plugin_dir_path( __FILE__ ) . 'admin/wordpress/settings-menu.php';
		require_once plugin_dir_path( __FILE__ ) . 'admin/wordpress/settings-page.php';
		require_once plugin_dir_path( __FILE__ ) . 'admin/wordpress/settings-register.php';
		require_once plugin_dir_path( __FILE__ ) . 'admin/wordpress/settings-callback.php';
	}

	// default plugin options
	function shipbubble_options_default() 
	{
		return array(
			'shipbubble_api_key'     	=> '',
		);
	}

