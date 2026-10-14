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
 * Build a stable signature from cart data that can affect a courier quote.
 * Shipping rates and cart totals are intentionally excluded to avoid update
 * loops after WooCommerce recalculates shipping.
 *
 * @param {Object} cartData WooCommerce cart store data.
 * @return {string} Stable cart signature.
 */
export function getCartSignature( cartData ) {
	const items = Array.isArray( cartData?.items )
		? cartData.items
				.map( ( item ) => ( {
					key: normalizeField( item?.key ),
					id: String( item?.id ?? '' ),
					quantity: Number( item?.quantity ?? 0 ),
					variation: Array.isArray( item?.variation )
						? item.variation
								.map( ( attribute ) => ( {
									attribute: normalizeField(
										attribute?.attribute
									),
									value: normalizeField( attribute?.value ),
								} ) )
								.sort( ( first, second ) =>
									first.attribute.localeCompare(
										second.attribute
									)
								)
						: [],
					price: String( item?.prices?.price ?? '' ),
					lineTotal: String( item?.totals?.line_total ?? '' ),
				} ) )
				.sort( ( first, second ) =>
					first.key.localeCompare( second.key )
				)
		: [];
	const coupons = Array.isArray( cartData?.coupons )
		? cartData.coupons
				.map( ( coupon ) => ( {
					code: normalizeField( coupon?.code ),
					discountType: normalizeField( coupon?.discount_type ),
				} ) )
				.sort( ( first, second ) =>
					first.code.localeCompare( second.code )
				)
		: [];

	return JSON.stringify( { items, coupons } );
}

/**
 * Return the recipient fields Shipbubble still needs before requesting rates.
 *
 * @param {Object} cartData WooCommerce cart store data.
 * @param {Object} options  Checkout context.
 * @return {string[]} Stable identifiers for missing fields.
 */
export function getMissingQuoteFields( cartData, options = {} ) {
	if ( ! cartData || cartData.needsShipping === false ) {
		return [];
	}

	const shipping = cartData.shippingAddress || {};
	const billing = cartData.billingAddress || {};
	const fields = [
		[
			'first_name',
			normalizeField( shipping.first_name || billing.first_name ),
		],
		[
			'last_name',
			normalizeField( shipping.last_name || billing.last_name ),
		],
		[ 'email', normalizeField( billing.email || shipping.email ) ],
		[
			'phone',
			normalizeField(
				options.useShippingAsBilling === true
					? shipping.phone
					: shipping.phone || billing.phone
			),
		],
		[ 'address_1', normalizeField( shipping.address_1 ) ],
		[ 'city', normalizeField( shipping.city ) ],
		[ 'country', normalizeField( shipping.country ) ],
	];

	return fields
		.filter( ( [ , value ] ) => ! value )
		.map( ( [ label ] ) => label );
}

/**
 * Build the stable, minimal request sent to Shipbubble's Store API extension.
 * Address objects in wc/store/cart keep their Store API snake_case fields.
 *
 * @param {Object} cartData             WooCommerce cart store data.
 * @param {string} deliveryInstructions Checkout order notes.
 * @param {Object} options              Checkout context.
 * @return {Object} Quote or clear command.
 */
export function buildQuoteRequest(
	cartData,
	deliveryInstructions = '',
	options = {}
) {
	if ( ! cartData || cartData.needsShipping === false ) {
		return { action: 'clear' };
	}

	if ( getMissingQuoteFields( cartData, options ).length ) {
		return { action: 'clear' };
	}

	const shipping = cartData.shippingAddress || {};
	const billing = cartData.billingAddress || {};
	const firstName = normalizeField(
		shipping.first_name || billing.first_name
	);
	const lastName = normalizeField( shipping.last_name || billing.last_name );
	const email = normalizeField( billing.email || shipping.email );
	// When billing uses the shipping address, WooCommerce may retain an old
	// billing phone in cart data even though the visible shipping phone is empty.
	// Only the visible shipping phone satisfies the required field in that mode.
	const phone = normalizeField(
		options.useShippingAsBilling === true
			? shipping.phone
			: shipping.phone || billing.phone
	);
	const destination = {
		address_1: normalizeField( shipping.address_1 ),
		city: normalizeField( shipping.city ),
		state: normalizeField( shipping.state ),
		country: normalizeField( shipping.country ),
		postcode: normalizeField( shipping.postcode ),
	};

	return {
		action: 'quote',
		cart_signature: getCartSignature( cartData ),
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
 * Retry an observation while WooCommerce hydrates its cart/customer stores.
 * Some checkout sessions restore a complete saved address without emitting a
 * later store change, so relying on wp.data.subscribe alone can miss the first
 * quote request. Retries are bounded and stop as soon as the observer reports
 * that the checkout data is stable.
 *
 * @param {Object}   dependencies             Runtime dependencies.
 * @param {Function} dependencies.observe     Return true when data is stable.
 * @param {number}   dependencies.delay       Delay between observations.
 * @param {number}   dependencies.maxAttempts Maximum consecutive retries.
 * @param {Function} dependencies.setTimer    Timer implementation.
 * @param {Function} dependencies.clearTimer  Timer cancellation implementation.
 * @return {{run: Function, cancel: Function}} Retrying observer.
 */
export function createHydrationRetry( {
	observe,
	delay = 250,
	maxAttempts = 40,
	setTimer = window.setTimeout,
	clearTimer = window.clearTimeout,
} ) {
	let timer = null;
	let attempts = 0;

	const clearPendingTimer = () => {
		if ( timer !== null ) {
			clearTimer( timer );
			timer = null;
		}
	};

	const run = () => {
		clearPendingTimer();

		if ( observe() ) {
			attempts = 0;
			return true;
		}

		if ( attempts >= maxAttempts ) {
			return false;
		}

		attempts += 1;
		timer = setTimer( () => {
			timer = null;
			run();
		}, delay );

		return false;
	};

	const cancel = () => {
		clearPendingTimer();
		attempts = 0;
	};

	return { run, cancel };
}

/**
 * Persist WooCommerce's current address before asking its cart/extensions
 * endpoint for rates. Otherwise that endpoint can return an older customer
 * address and replace a phone number the shopper has just typed.
 *
 * @param {Object} request      Quote or clear request.
 * @param {Object} dependencies WooCommerce cart operations.
 * @return {Promise<*>} Cart update result, when sent.
 */
export async function sendQuoteAfterCustomerUpdate( request, dependencies ) {
	const { getSnapshot, updateCustomerData, updateQuote } = dependencies;
	if ( request.action === 'quote' ) {
		const snapshot = getSnapshot();
		if ( getRequestKey( snapshot.request ) !== getRequestKey( request ) ) {
			return;
		}

		await updateCustomerData( {
			billing_address: snapshot.useShippingAsBilling
				? {
						...snapshot.cartData.billingAddress,
						phone: snapshot.cartData.shippingAddress.phone,
				  }
				: snapshot.cartData.billingAddress,
			shipping_address: snapshot.cartData.shippingAddress,
		} );

		// A shopper can edit the address while WooCommerce saves it. Never
		// publish a quote for a request that is no longer on screen.
		if (
			getRequestKey( getSnapshot().request ) !== getRequestKey( request )
		) {
			return;
		}
	}

	return updateQuote( request );
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
			onError( error, next.request );
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

		timer = setTimer(
			() => {
				timer = null;
				void flush();
			},
			request.action === 'clear' ? 0 : delay
		);
	};

	return { queue, flush };
}
