# Shipbubble for WooCommerce

Shipbubble is a WordPress/WooCommerce plugin that enables retailers to offer multiple shipping options at checkout, helping increase conversion rates with transparent delivery pricing.

## Features

- Access pre-negotiated courier rates across 20+ logistics partners
- Display real-time shipping rates and delivery times at checkout
- Support for both domestic and international shipping to 220+ countries
- Sandbox and live mode for testing and production
- Local pickup option support
- Extra charges configuration
- Courier filtering by service code
- Automatic shipment creation after order placement
- Order tracking with shipment status labels
- Currency conversion compatibility (YayCurrency, YITH Multi Currency)
- WooCommerce HPOS (High-Performance Order Storage) compatible

## Requirements

- WordPress 4.0 or higher
- PHP 5.6 or higher
- WooCommerce (required plugin)

## Installation

1. Upload the plugin files to `/wp-content/plugins/shipbubble`, or install through the WordPress plugins screen.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Create a [Shipbubble account](https://app.shipbubble.com/register) if you don't have one.
4. Go to the Shipbubble settings page and enter your API key.
5. Validate your API key and sender address.
6. Configure your WooCommerce shipping preferences.

## Configuration

After activation, navigate to **Shipbubble Settings** in the WordPress admin to configure:

- **API Keys** - Enter your live and/or sandbox API keys
- **Sender Details** - Validate your sender address
- **Shipping Options** - Set extra charges, courier preferences, and shipping price display
- **Local Pickup** - Enable and configure local pickup as a shipping option
- **Mode** - Toggle between live and sandbox environments

## How It Works

1. A customer adds items to their cart and proceeds to checkout.
2. Shipbubble fetches real-time shipping rates from available couriers based on the delivery address and cart contents.
3. The customer selects their preferred courier and rate.
4. On order completion, a shipment is automatically created via the Shipbubble API.
5. Order tracking status is updated as the shipment progresses.

## Support

- Website: [shipbubble.com](https://shipbubble.com)
- Dashboard: [app.shipbubble.com](https://app.shipbubble.com)

## License

This plugin is licensed under the [GPLv3 or later](https://www.gnu.org/licenses/gpl-3.0.html).
