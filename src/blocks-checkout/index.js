import {
	buildQuoteRequest,
	createQuoteScheduler,
	getRequestKey,
} from './request';

const { select, subscribe } = window.wp.data;
const { CART_STORE_KEY, CHECKOUT_STORE_KEY, processErrorResponse } =
	window.wc.wcBlocksData;
const { extensionCartUpdate } = window.wc.blocksCheckout;

const scheduler = createQuoteScheduler( {
	send: ( request ) =>
		extensionCartUpdate( {
			namespace: 'shipbubble',
			data: request,
		} ),
	onError: ( error ) => processErrorResponse( error ),
} );

let lastObservedKey = null;

/**
 * Observe only customer/cart fields that influence a Shipbubble quote. Updates
 * to totals or shipping rates therefore cannot create an update loop.
 */
function observeCheckout() {
	const cartStore = select( CART_STORE_KEY );

	if (
		! cartStore ||
		cartStore.isCartDataStale?.() ||
		cartStore.isCustomerDataUpdating?.() ||
		cartStore.isAddressFieldsForShippingRatesUpdating?.()
	) {
		return;
	}

	const cartData = cartStore.getCartData?.();
	if ( ! cartData ) {
		return;
	}

	const checkoutStore = select( CHECKOUT_STORE_KEY );
	const request = buildQuoteRequest(
		cartData,
		checkoutStore?.getOrderNotes?.()
	);
	const requestKey = getRequestKey( request );

	if ( requestKey === lastObservedKey ) {
		return;
	}

	lastObservedKey = requestKey;
	scheduler.queue( request );
}

subscribe( observeCheckout );
observeCheckout();
