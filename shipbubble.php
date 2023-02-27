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
		require_once plugin_dir_path( __FILE__ ) . 'admin/wordpress/async-validate-auth.php';
		
		// Woocommerce
		require_once plugin_dir_path( __FILE__ ) . 'admin/woocommerce/shipping-settings.php';
	}

	// includes
	require_once plugin_dir_path( __FILE__ ) . 'includes/constants.php';
	require_once plugin_dir_path( __FILE__ ) . 'includes/endpoints.php';
	require_once plugin_dir_path( __FILE__ ) . 'includes/core-methods.php';

	// public
	require_once plugin_dir_path( __FILE__ ) . 'public/async-checkout-couriers.php';


	// action on activation
	function shipbubble_on_activation() 
	{
		if ( ! current_user_can( 'activate_plugins' ) ) return;

		if (get_option('shipbubble_init')) {
			$data = array('initialized' => true, 'account_status' => false);
			update_option( 'shipbubble_init', $data );
		} else {
			$data = array('initialized' => true, 'account_status' => false);
			add_option( 'shipbubble_init', $data );
		}

	}

	register_activation_hook( __FILE__, 'shipbubble_on_activation' );


	// action on deactivation
	function shipbubble_on_deactivation() 
	{
		if ( ! current_user_can( 'activate_plugins' ) ) return;
		
		$data = array('initialized' => false, 'account_status' => false);
		update_option( 'shipbubble_init', $data );
	}

	register_deactivation_hook( __FILE__, 'shipbubble_on_deactivation' );

	// default plugin options
	function shipbubble_options_default(): array 
	{
		return array(
			'shipbubble_api_key'     	=> '',
		);
	}

	function shipbubble_wc_options_default(): array 
	{
		return array(
			'extra_charges' => '0',
			'courier_list' =>  array('all'),
			'shipping_price' => 'default',
		);
	}

	
    /**
	 * Initialize Courier List Container
     *
	 * @return void
     */
	add_action( 'woocommerce_after_checkout_billing_form', 'shipbubble_add_courier_methods' );

    function shipbubble_add_courier_methods()
    {
        
        $container = '<div id="courier-section">';
        $container .= '<div id="courier-list"></div>';
        $container .= '</div>';

        echo $container;
    }

