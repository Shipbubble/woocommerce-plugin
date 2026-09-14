/**
 * Read one value from WooCommerce shipping-rate metadata.
 *
 * Store API responses use an array of key/value objects. Keeping object support
 * makes the renderer tolerant of data-store normalization changes.
 *
 * @param {Object} rate Shipping rate.
 * @param {string} key  Metadata key.
 * @return {string} Metadata value.
 */
export function getRateMeta( rate, key ) {
	const metadata = rate?.meta_data || rate?.metaData || [];

	if ( Array.isArray( metadata ) ) {
		const item = metadata.find( ( entry ) => entry?.key === key );
		return typeof item?.value === 'string' ? item.value : '';
	}

	return typeof metadata?.[ key ] === 'string' ? metadata[ key ] : '';
}

/**
 * Decode safe display text returned with HTML entities by WordPress.
 *
 * @param {*} value Display value.
 * @return {string} Plain text.
 */
export function decodeDisplayText( value ) {
	if ( typeof value !== 'string' || ! value.includes( '&' ) ) {
		return typeof value === 'string' ? value.trim() : '';
	}

	const textarea = document.createElement( 'textarea' );
	textarea.innerHTML = value;
	return textarea.value.trim();
}

/**
 * Find the currently selected Shipbubble rate in WooCommerce shipping packages.
 *
 * @param {Array}  shippingRates WooCommerce cart shipping packages.
 * @param {string} methodId      Shipbubble shipping method ID.
 * @return {Object|null} Selected rate or null.
 */
export function findSelectedShipbubbleRate(
	shippingRates,
	methodId = 'shipbubble_shipping_services'
) {
	if ( ! Array.isArray( shippingRates ) ) {
		return null;
	}

	for ( const packageRates of shippingRates ) {
		const rates =
			packageRates?.shipping_rates || packageRates?.shippingRates || [];

		if ( ! Array.isArray( rates ) ) {
			continue;
		}

		const selected = rates.find(
			( rate ) =>
				rate?.selected === true &&
				( rate?.method_id === methodId || rate?.methodId === methodId )
		);

		if ( selected ) {
			return selected;
		}
	}

	return null;
}

/**
 * Normalize the safe, display-only fields consumed by the inner block.
 *
 * @param {Object|null} rate         Selected Shipbubble rate.
 * @param {string}      fallbackLogo Plugin logo used for Local Pickup.
 * @return {Object|null} Display data or null.
 */
export function getSelectedCourierDisplay( rate, fallbackLogo = '' ) {
	if ( ! rate ) {
		return null;
	}

	return {
		name: decodeDisplayText( rate.name ),
		logo: getRateMeta( rate, '_shipbubble_courier_image' ) || fallbackLogo,
		eta: decodeDisplayText( rate.delivery_time || rate.deliveryTime ),
		description: decodeDisplayText( rate.description ),
	};
}
