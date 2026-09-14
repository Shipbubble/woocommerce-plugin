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
			array('wp-data', 'wc-blocks-data-store', 'wc-blocks-checkout')
		));

		wp_register_script(
			$this->script_handle,
			SHIPBUBBLE_PLUGIN_URL . '/build/index.js',
			$dependencies,
			$asset['version'],
			true
		);
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
	 * No editor script is necessary because Shipbubble uses native rate rows.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles()
	{
		return array();
	}

	/**
	 * No public credentials or quote data are exposed to JavaScript.
	 *
	 * @return array
	 */
	public function get_script_data()
	{
		return array();
	}
}
