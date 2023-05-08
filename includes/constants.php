<?php // predefined constants

define('SHIPBUBBLE_BASE_URL', 'https://api.shipbubble.com/v1/shipping');
define('SHIPBUBBLE_ID', 'shipbubble_shipping_services');
define('WC_SHIPBUBBLE_ID', 'woocommerce_' . SHIPBUBBLE_ID . '_settings');
define('SHIPBUBBLE_REQUEST_TOKEN_EXPIRY', 48);
define('SHIPBUBBLE_EP_REQUEST_TIMEOUT', 60);
define('SHIPBUBBLE_RESPONSE_IS_OK', 200);
define('SHIPBUBBLE_EXT_BASE_URL', site_url());
