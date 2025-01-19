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

	// Add the "Local Pickup" submenu
	add_submenu_page(
		'shipbubble-settings',     // Parent slug
		'Local Pickup Settings',   // Page title
		'Local Pickup',            // Menu title
		'manage_options',          // Capability
		'shipbubble-local-pickup', // Menu slug
		'display_shipbubble_local_pickup_page' // Callback function
	);
}

// Callback function to render the settings page
function display_shipbubble_settings_page() {
	$options = get_option(WC_SHIPBUBBLE_ID, shipbubble_wc_options_default());
	?>
	<div class="wrap">
		<h1>Shipbubble Settings</h1>
		<h2 class="nav-tab-wrapper">
			<a href="#shipbubble-settings-api-tab" class="nav-tab nav-tab-active" id="tab1-link">API Keys</a>
			<a href="#shipbubble-settings-sender-tab" class="nav-tab" id="tab2-link">Sender Details</a>
		</h2>
		<?php
		include_once plugin_dir_path(__FILE__) . 'templates/api-keys.php';
		include_once plugin_dir_path(__FILE__) . 'templates/sender-details.php';
		?>
	</div>

	<?php
}

// Callback function to render the local pickup page
function display_shipbubble_local_pickup_page() {
	echo '<h1>Shipbubble Local Pickup Settings</h1>';
	echo '<p>Manage the local pickup settings for the Shipbubble plugin here.</p>';
}


add_action('admin_enqueue_scripts', 'shipbubble_enqueue_admin_scripts');

function shipbubble_enqueue_admin_scripts($hook) {
	// Only enqueue on our plugin's settings pages
	if (strpos($hook, 'shipbubble') !== false) {
		wp_enqueue_script(
			'shipbubble-settings',
			plugins_url('js/settings.js', __FILE__),
			array('jquery'),
			'1.0.0',
			true
		);
	}
}
