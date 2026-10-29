<?php
/** Run with: php tests/php/asset-enqueue-test.php */
define('ABSPATH', __DIR__ . '/');
define('WC_VERSION', '10.3.5');
$scripts = $styles = $registered_scripts = $localized_scripts = array();
$options = array('activate_shipbubble' => 'yes', 'local_pickup' => 'no');
$context = array('checkout' => false, 'blocks' => false, 'received' => false, 'pay' => false);
$screen = null;

function is_admin() { return true; }
function is_multisite() { return false; }
function is_plugin_active($plugin) { return false; }
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugins_url($path = '', $file = '') { return 'https://example.test/plugins/shipbubble/' . ltrim($path, '/'); }
function site_url() { return 'https://example.test'; }
function admin_url($path) { return site_url() . '/wp-admin/' . $path; }
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
function apply_filters($hook, $value) { return $value; }
function register_activation_hook($file, $callback) {}
function register_deactivation_hook($file, $callback) {}
function get_option($name, $default = false)
{
	if ('active_plugins' === $name) {
		return array('woocommerce/woocommerce.php');
	}
	return WC_SHIPBUBBLE_ID === $name ? $GLOBALS['options'] : $default;
}
function get_current_screen() { return $GLOBALS['screen']; }
function wc_get_page_screen_id($page) { return 'woocommerce_page_wc-orders'; }
function is_checkout() { return $GLOBALS['context']['checkout']; }
function has_block($name) { return $GLOBALS['context']['blocks']; }
function is_order_received_page() { return $GLOBALS['context']['received']; }
function is_checkout_pay_page() { return $GLOBALS['context']['pay']; }
function woocommerce_store_api_register_update_callback() {}
function wp_create_nonce($action) { return 'test-nonce'; }
function sanitize_key($value) { return strtolower($value); }
function wp_register_script($handle, $src, $deps = array(), $version = false, $footer = false)
{
	$GLOBALS['registered_scripts'][$handle] = compact('src', 'deps', 'footer');
}
function wp_enqueue_script($handle, $src = '', $deps = array(), $version = false, $footer = false)
{
	$GLOBALS['scripts'][$handle] = $deps;
}
function wp_enqueue_style($handle, $src = '', $deps = array(), $version = false, $media = 'all')
{
	$GLOBALS['styles'][] = $handle;
}
function wp_localize_script($handle, $name, $data) { $GLOBALS['localized_scripts'][] = $handle; }
function expect_same($expected, $actual, $message)
{
	if ($expected !== $actual) {
		throw new RuntimeException($message);
	}
}
function reset_assets() { $GLOBALS['scripts'] = $GLOBALS['styles'] = array(); }
function enqueue_admin_assets($hook)
{
	shipbubble_enqueue_admin_scripts($hook);
	ajax_enqueue_scripts_create_shipment($hook);
	shipbubble_enqueue_style_admin($hook);
}
function enqueue_public_assets()
{
	ajax_public_enqueue_scripts();
	shipbubble_enqueue_style_public();
}

require dirname(__DIR__, 2) . '/shipbubble.php';
require dirname(__DIR__, 2) . '/public/woocommerce/enqueue-styles.php';

shipbubble_register_shared_scripts();
expect_same(array(), $scripts, 'Registering libraries must not enqueue them');
expect_same(array('shipbubble-sweetalert2', 'shipbubble-blockui'), array_keys($registered_scripts), 'Library handles must be namespaced');
expect_same(array('jquery'), $registered_scripts['shipbubble-blockui']['deps'], 'BlockUI must depend on jQuery');

foreach (array(
	array('index.php', 'dashboard', 'dashboard', ''),
	array('customize.php', 'customize', 'customize', ''),
	array('post.php', 'post', 'post', 'post'),
	array('post.php', 'post', 'product', 'product'),
	array('post-new.php', 'post', 'shop_order', 'shop_order', 'add'),
	array('woocommerce_page_wc-orders', 'woocommerce_page_wc-orders', 'woocommerce_page_wc-orders', ''),
) as $case) {
	list($hook, $base, $id, $post_type) = $case;
	$screen = (object) array('base' => $base, 'id' => $id, 'post_type' => $post_type, 'action' => $case[4] ?? '');
	reset_assets();
	enqueue_admin_assets($hook);
	expect_same(array(), $scripts, 'Unrelated screen must not load Shipbubble JS: ' . $hook . '/' . $post_type);
	expect_same(array(), $styles, 'Unrelated screen must not load Shipbubble CSS');
}

reset_assets();
enqueue_admin_assets('toplevel_page_shipbubble-settings');
expect_same(array('jquery', 'shipbubble-sweetalert2', 'shipbubble-blockui'), $scripts['shipbubble-settings'], 'Settings must declare popup dependencies');
expect_same(array('shipbubble-admin'), $styles, 'Settings must retain admin styles');

foreach (array(false, true) as $hpos) {
	$screen = (object) array('base' => $hpos ? 'woocommerce_page_wc-orders' : 'post', 'id' => $hpos ? 'woocommerce_page_wc-orders' : 'shop_order', 'post_type' => $hpos ? '' : 'shop_order', 'action' => '');
	$_GET = $hpos ? array('action' => 'edit') : array();
	reset_assets();
	enqueue_admin_assets($hpos ? 'woocommerce_page_wc-orders' : 'post.php');
	expect_same(array('shipbubble-create-shipment' => array('jquery')), $scripts, 'Both order editors must load shipment JS without unused popup libraries');
	expect_same(array('shipbubble-admin'), $styles, 'Order editors must retain admin styles');
}

foreach (array('home', 'inactive', 'blocks', 'received', 'pay') as $case) {
	$context = array('checkout' => 'home' !== $case, 'blocks' => 'blocks' === $case, 'received' => 'received' === $case, 'pay' => 'pay' === $case);
	$options['activate_shipbubble'] = 'inactive' === $case ? 'no' : 'yes';
	reset_assets();
	enqueue_public_assets();
	expect_same(array(), $scripts, 'Legacy JS must not load for ' . $case);
	expect_same(array(), $styles, 'Legacy CSS must not load for ' . $case);
}

$context = array('checkout' => true, 'blocks' => false, 'received' => false, 'pay' => false);
reset_assets();
enqueue_public_assets();
expect_same(array('shipbubble-checkout' => array('jquery', 'shipbubble-sweetalert2')), $scripts, 'Classic checkout must declare SweetAlert dependency');
expect_same(array('shipbubble-public'), $styles, 'Classic checkout must retain its styles');
$options['local_pickup'] = 'yes';
reset_assets();
enqueue_public_assets();
expect_same(array('jquery', 'shipbubble-blockui'), $scripts['shipbubble-local-pickup'], 'Pickup must declare BlockUI dependency');
expect_same(true, in_array('shipbubble-checkout', $localized_scripts, true), 'Checkout configuration must use the new handle');
expect_same(true, in_array('shipbubble-create-shipment', $localized_scripts, true), 'Order configuration must use the new handle');

reset_assets();
shipbubble_blocks_inject_selected_courier_mount('<div>Checkout shipping methods</div>');
expect_same(array('shipbubble-checkout-blocks'), $styles, 'Rendering the Checkout shipping block must load its styles');

echo "Asset enqueue regression checks passed.\n";
