<?php
/**
 * Run with: php tests/php/checkout-phone-field-test.php
 *
 * Exercise the option filters without requiring a WordPress database.
 */
define('ABSPATH', __DIR__ . '/');
define('WC_SHIPBUBBLE_ID', 'woocommerce_shipbubble_shipping_services_settings');
define('WC_VERSION', '10.3.5');

$filters = array();
$context = array('checkout' => false, 'cart' => false, 'blocks' => false, 'active' => 'yes');
$woocommerce = (object) array('session' => null, 'cart' => null);

function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1)
{
	$GLOBALS['filters'][$hook][] = $callback;
}
function apply_filters($hook, $value)
{
	foreach ($GLOBALS['filters'][$hook] ?? array() as $callback) {
		$value = $callback($value);
	}
	return $value;
}
function WC() { return $GLOBALS['woocommerce']; }
function is_checkout() { return $GLOBALS['context']['checkout']; }
function is_cart() { return $GLOBALS['context']['cart']; }
function has_block($name) { return $GLOBALS['context']['blocks']; }
function woocommerce_store_api_register_update_callback() {}
function shipbubble_wc_options_default() { return array(); }
function get_option($name, $default = false)
{
	return array('activate_shipbubble' => $GLOBALS['context']['active']);
}
function sanitize_key($value) { return strtolower($value); }
function wp_unslash($value) { return $value; }
function expect_same($expected, $actual, $message)
{
	if ($expected !== $actual) {
		throw new RuntimeException($message);
	}
}

require dirname(__DIR__, 2) . '/includes/blocks-checkout.php';

// WP_Customize_Setting passes this exact object as a missing-option marker.
$marker = new stdClass();
foreach (array('default_option_woocommerce_checkout_phone_field', 'option_woocommerce_checkout_phone_field') as $hook) {
	foreach (array(false, null, $marker, array(), 0) as $value) {
		expect_same($value, apply_filters($hook, $value), $hook . ' must preserve non-string values');
	}
	expect_same('optional', apply_filters($hook, 'optional'), 'Non-checkout visibility must be unchanged');
}

$context['checkout'] = true;
expect_same('required', shipbubble_blocks_require_phone_field('optional'), 'Active classic checkout must require a phone');
expect_same($marker, shipbubble_blocks_require_phone_field($marker), 'Checkout must also preserve the missing-option marker');

$context['blocks'] = true;
expect_same('required', shipbubble_blocks_require_phone_field('hidden'), 'Active Blocks checkout must require a phone');
$context['active'] = 'no';
expect_same('hidden', shipbubble_blocks_require_phone_field('hidden'), 'Inactive Shipbubble must preserve visibility');

$context['active'] = 'yes';
$context['cart'] = true;
expect_same('optional', shipbubble_blocks_require_phone_field('optional'), 'Cart must preserve visibility');
$context['cart'] = false;
$woocommerce->cart = new class {
	public function needs_shipping() { return false; }
};
expect_same('optional', shipbubble_blocks_require_phone_field('optional'), 'Virtual-only checkout must preserve visibility');
$woocommerce->cart = null;

$context['checkout'] = false;
$_REQUEST['wc-ajax'] = 'checkout';
expect_same('required', shipbubble_blocks_require_phone_field('optional'), 'Classic checkout AJAX must require a phone');
unset($_REQUEST['wc-ajax']);
$woocommerce->session = new class {
	public function get($key) { return 'shipbubble_blocks_checkout_context' === $key ? 'yes' : null; }
};
expect_same('required', shipbubble_blocks_require_phone_field('optional'), 'Blocks Store API context must require a phone');

$context['blocks'] = false;
$fields = shipbubble_require_classic_checkout_phone(array('billing' => array('billing_phone' => array('required' => false))));
expect_same(true, $fields['billing']['billing_phone']['required'], 'Classic phone validation must remain required');

echo "Checkout phone field regression checks passed.\n";
