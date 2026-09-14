/**
 * Convert an unknown checkout field value into a trimmed string.
 *
 * @param {*} value Field value.
 * @return {string} Normalized value.
 */
export function normalizeField( value ) {
	return typeof value === 'string' ? value.trim() : '';
}

/**
 * Build the stable, minimal request sent to Shipbubble's Store API extension.
 * Address objects in wc/store/cart keep their Store API snake_case fields.
 *
 * @param {Object} cartData             WooCommerce cart store data.
 * @param {string} deliveryInstructions Checkout order notes.
 * @return {Object} Quote or clear command.
 */
export function buildQuoteRequest( cartData, deliveryInstructions = '' ) {
	if ( ! cartData || cartData.needsShipping === false ) {
		return { action: 'clear' };
	}

	const shipping = cartData.shippingAddress || {};
	const billing = cartData.billingAddress || {};
	const firstName = normalizeField(
		shipping.first_name || billing.first_name
	);
	const lastName = normalizeField( shipping.last_name || billing.last_name );
	const email = normalizeField( billing.email || shipping.email );
	const phone = normalizeField( shipping.phone || billing.phone );
	const destination = {
		address_1: normalizeField( shipping.address_1 ),
		city: normalizeField( shipping.city ),
		state: normalizeField( shipping.state ),
		country: normalizeField( shipping.country ),
		postcode: normalizeField( shipping.postcode ),
	};

	if (
		! firstName ||
		! lastName ||
		! email ||
		! phone ||
		! destination.address_1 ||
		! destination.city ||
		! destination.country
	) {
		return { action: 'clear' };
	}

	return {
		action: 'quote',
		recipient: {
			first_name: firstName,
			last_name: lastName,
			email,
			phone,
		},
		destination,
		delivery_instructions: normalizeField( deliveryInstructions ),
	};
}

/**
 * Return a stable key for a request whose properties are created in a fixed order.
 *
 * @param {Object} request Quote request.
 * @return {string} Request key.
 */
export function getRequestKey( request ) {
	return JSON.stringify( request );
}

/**
 * Debounce updates and serialize Store API calls so an older request cannot win.
 *
 * @param {Object}   dependencies            Runtime dependencies.
 * @param {Function} dependencies.send       Send one request.
 * @param {Function} dependencies.onError    Handle a failed request.
 * @param {number}   dependencies.delay      Debounce delay.
 * @param {Function} dependencies.setTimer   Timer implementation.
 * @param {Function} dependencies.clearTimer Timer cancellation implementation.
 * @return {{queue: Function, flush: Function}} Scheduler.
 */
export function createQuoteScheduler( {
	send,
	onError = () => {},
	delay = 350,
	setTimer = window.setTimeout,
	clearTimer = window.clearTimeout,
} ) {
	let timer = null;
	let pending = null;
	let running = false;
	let lastAttemptedKey = null;

	const flush = async () => {
		if ( running || ! pending ) {
			return;
		}

		const next = pending;
		pending = null;
		running = true;
		lastAttemptedKey = next.key;

		try {
			await send( next.request );
		} catch ( error ) {
			onError( error );
		} finally {
			running = false;

			if ( pending && pending.key !== lastAttemptedKey ) {
				await flush();
			}
		}
	};

	const queue = ( request ) => {
		const key = getRequestKey( request );

		if ( key === lastAttemptedKey || ( pending && pending.key === key ) ) {
			return;
		}

		pending = { key, request };
		if ( timer ) {
			clearTimer( timer );
		}

		timer = setTimer( () => {
			timer = null;
			void flush();
		}, delay );
	};

	return { queue, flush };
}
