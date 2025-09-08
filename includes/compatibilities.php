<?php

if (is_plugin_active('funnel-builder/funnel-builder.php')) {
	add_filter('wfacp_show_shipping_options', '__return_true' ,9999);
	add_filter('wfacp_show_shipping_package_name', '__return_true' ,9999);
	add_filter('wfacp_display_shipping_content_at_top', '__return_true',9999);

	add_action('wfacp_before_process_checkout_template_loader', 'wfacp_actions');
	add_action('wfacp_after_checkout_page_found', 'wfacp_actions');

	add_action( 'wfacp_internal_css', 'wfacp_actions_add_css');

	function wfacp_actions() {
		if (!function_exists('shipbubble_courier_list_container')) return;

		remove_action('woocommerce_checkout_before_order_review', 'shipbubble_courier_list_container');

		add_action('wfacp_before_shipping_calculator_field', function() {
			echo '<div class="woocommerce-shipping-totals shipping"></div>';
		},9);

		add_action('wfacp_after_shipping_calculator_field', 'shipbubble_courier_list_container');
	}

	function wfacp_actions_add_css() {
		?>
		<style>
			.container-card {    margin-left: 7px;}
			.container-delivery-card-list-item-top img{margin-right:15px !important;}

			#wfacp-sec-wrapper .shipbubble-delivery-method-container .container-card button#request_courier_rates:after {
				display: block;
			}
			div#courier-section {
				float: none;
				width: 100%;
			}
			#wfacp-sec-wrapper .wfacp_main_form.woocommerce .shipbubble-delivery-method-container .container-card input[type="radio"] {
				position: relative;
				left: auto;
				right: auto;
				top: auto;
				opacity:0;
			}

		</style>
		<?php
	}
}