<?php // predefined constants

define('SHIPBUBBLE_BASE_URL', 'https://staging-api.shipbubble.com/v1/shipping');
define('SHIPBUBBLE_SANDBOX_URL', 'https://staging-api.shipbubble.com/v1/shipping');
define('SHIPBUBBLE_PROD_URL', 'https://staging-api.shipbubble.com/v1/shipping');
define('SHIPBUBBLE_ID', 'shipbubble_shipping_services');
define('WC_SHIPBUBBLE_ID', 'woocommerce_' . SHIPBUBBLE_ID . '_settings');
define('SHIPBUBBLE_REQUEST_TOKEN_EXPIRY', 120); // hours
define('SHIPBUBBLE_EP_REQUEST_TIMEOUT', 60);
define('SHIPBUBBLE_RESPONSE_IS_OK', 200);
define('SHIPBUBBLE_WC_BAD_ORDER_STATUS_ARR', ['pending payment', 'on hold', 'cancelled', 'refunded', 'failed']);
define('SHIPBUBBLE_EXT_BASE_URL', site_url());
