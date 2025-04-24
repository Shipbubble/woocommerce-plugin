<?php

// Hook to add the main admin menu
add_action('admin_menu', 'add_shipbubble_menu');

function add_shipbubble_menu() {
	// Add the main menu in the WordPress admin dashboard
	add_menu_page(
		'Shipbubble',              // Page title
		'Shipbubble',              // Menu title
		'manage_options',          // Capability
		'shipbubble-settings',     // Menu slug
		'display_shipbubble_settings_page', // Callback function to display the page
		'dashicons-admin-settings',    // Menu icon (settings gear)
		25                         // Position
	);

	// Add the "Settings" submenu (this replaces the default main menu page)
	add_submenu_page(
		'shipbubble-settings',     // Parent slug
		'Settings',                // Page title
		'Settings',                // Menu title
		'manage_options',          // Capability
		'shipbubble-settings',     // Menu slug (same as the main menu slug)
		'display_shipbubble_settings_page' // Callback function
	);

	// Add submenu pages
//	add_submenu_page(
//		'shipbubble-settings',     // Parent slug
//		'Local Pickup Settings',   // Page title
//		'Local Pickup',            // Menu title
//		'manage_options',          // Capability
//		'shipbubble-local-pickup', // Menu slug
//		'display_shipbubble_local_pickup_page' // Callback function
//	);
}

// Callback function to render the settings page
function display_shipbubble_settings_page() {
	$options = get_option(WC_SHIPBUBBLE_ID, shipbubble_wc_options_default());
	$shipbubble_init = get_option(SHIPBUBBLE_INIT);
	?>
	<div class="wrap">
		<h1>Shipbubble Settings</h1>
		<h2 class="nav-tab-wrapper">
			<a href="#shipbubble-settings-api-tab" class="nav-tab nav-tab-active" id="tab1-link">API Keys</a>
            <?php if ($shipbubble_init['account_status']) { ?>
			<a href="#shipbubble-settings-sender-tab" class="nav-tab" id="tab2-link">Store Information</a>
			<a href="#shipbubble-settings-local-pickup" class="nav-tab" id="tab2-link">Local Pickup</a>
            <?php } ?>
		</h2>
		<?php
		include_once plugin_dir_path(__FILE__) . 'templates/api-keys.php';
        if ($shipbubble_init['account_status']) {
            include_once plugin_dir_path(__FILE__) . 'templates/sender-details.php';
            include_once plugin_dir_path(__FILE__) . 'templates/local-pickup.php';
        }
		?>
	</div>

	<?php
}


add_action('admin_enqueue_scripts', 'shipbubble_enqueue_admin_scripts');

function shipbubble_enqueue_admin_scripts($hook) {
	// Only enqueue on our plugin's settings pages
	if (strpos($hook, 'shipbubble') !== false) {
		// create nonce
		$nonce = wp_create_nonce( 'ajax_wc_admin' );

		// define script
		$script = array( 'nonce' => $nonce, 'logo' => SHIPBUBBLE_LOGO_URL );
		$version = rand(1000, 9999); // or use another method to generate a version string

		wp_enqueue_script(
			'shipbubble-settings',
			plugins_url('js/settings.js', __FILE__),
			array('jquery'),
			$version,
			true
		);

		// localize script
		wp_localize_script( 'shipbubble-settings', 'ajax_wc_admin', $script );
	}
}
