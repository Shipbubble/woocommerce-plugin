<?php

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

defined('ABSPATH') || exit;

/**
 * Registers Shipbubble's checkout controller with WooCommerce Checkout Blocks.
 */
final class Shipbubble_Blocks_Integration implements IntegrationInterface
{
	/**
	 * Script handle.
	 *
	 * @var string
	 */
	private $script_handle = 'shipbubble-checkout-blocks';

	/**
	 * Return the unique integration name.
	 *
	 * @return string
	 */
	public function get_name()
	{
		return 'shipbubble';
	}

	/**
	 * Register the compiled checkout controller.
	 *
	 * @return void
	 */
	public function initialize()
	{
		$asset_path = dirname(__DIR__) . '/build/index.asset.php';
		$asset = file_exists($asset_path)
			? require $asset_path
			: array(
				'dependencies' => array('wp-data', 'wc-blocks-data-store', 'wc-blocks-checkout'),
				'version' => defined('SHIPBUBBLE_PLUGIN_VERSION_NUMBER') ? SHIPBUBBLE_PLUGIN_VERSION_NUMBER : '2.7.0',
			);
		$dependencies = array_unique(array_merge(
			(array) ($asset['dependencies'] ?? array()),
			array('wp-blocks', 'wp-data', 'wp-element', 'wc-settings', 'wc-blocks-data-store', 'wc-blocks-checkout')
		));

		wp_register_script(
			$this->script_handle,
			SHIPBUBBLE_PLUGIN_URL . '/build/index.js',
			$dependencies,
			$asset['version'],
			true
		);

		$style_path = dirname(__DIR__) . '/build/style-index.css';
		if (file_exists($style_path)) {
			wp_enqueue_style(
				'shipbubble-checkout-blocks',
				SHIPBUBBLE_PLUGIN_URL . '/build/style-index.css',
				array(),
				(string) filemtime($style_path)
			);
		}
	}

	/**
	 * Return frontend script handles.
	 *
	 * @return string[]
	 */
	public function get_script_handles()
	{
		return array($this->script_handle);
	}

	/**
	 * Register the same small inner block in the Checkout editor.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles()
	{
		return array($this->script_handle);
	}

	/**
	 * Return display-only configuration. Private quote credentials remain in the
	 * WooCommerce session and are never exposed to the Store API.
	 *
	 * @return array
	 */
	public function get_script_data()
	{
		return array(
			'methodId' => SHIPBUBBLE_ID,
			'logoUrl' => esc_url_raw(SHIPBUBBLE_LOGO_URL),
			'selectedLabel' => __('Selected delivery', 'shipbubble'),
		);
	}
}
