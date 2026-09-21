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

/**
 * Render the Shipbubble settings page and its available tabs.
 *
 * @return void
 */
function display_shipbubble_settings_page() {
	$options = get_option(WC_SHIPBUBBLE_ID, shipbubble_wc_options_default());
	$shipbubble_init = get_option(SHIPBUBBLE_INIT);
	?>
	<div class="wrap">
		<h1>Shipbubble Settings</h1>
		<div class="shipbubble-accordion">
			<div class="shipbubble-accordion-item is-open">
				<button type="button" class="shipbubble-accordion-toggle" aria-expanded="true" aria-controls="shipbubble-settings-api-tab">
					<span>API Keys</span><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
				</button>
				<?php include_once plugin_dir_path(__FILE__) . 'templates/api-keys.php'; ?>
			</div>

			<?php if (!empty($shipbubble_init['account_status'])) { ?>
				<div class="shipbubble-accordion-item">
					<button type="button" class="shipbubble-accordion-toggle" aria-expanded="false" aria-controls="shipbubble-settings-sender-tab">
						<span>Store Information</span><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
					</button>
					<?php include_once plugin_dir_path(__FILE__) . 'templates/sender-details.php'; ?>
				</div>

				<div class="shipbubble-accordion-item">
					<button type="button" class="shipbubble-accordion-toggle" aria-expanded="false" aria-controls="shipbubble-settings-integrations">
						<span>Integrations</span><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
					</button>
					<?php include_once plugin_dir_path(__FILE__) . 'templates/integrations.php'; ?>
				</div>

				<div class="shipbubble-accordion-item">
					<button type="button" class="shipbubble-accordion-toggle" aria-expanded="false" aria-controls="shipbubble-settings-checkout">
						<span>Checkout</span><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
					</button>
					<?php include_once plugin_dir_path(__FILE__) . 'templates/checkout.php'; ?>
				</div>

				<div class="shipbubble-accordion-item">
					<button type="button" class="shipbubble-accordion-toggle" aria-expanded="false" aria-controls="shipbubble-settings-local-pickup">
						<span>Local Pickup</span><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
					</button>
					<?php include_once plugin_dir_path(__FILE__) . 'templates/local-pickup.php'; ?>
				</div>
			<?php } ?>
		</div>
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
