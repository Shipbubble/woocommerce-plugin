import {
	buildQuoteRequest,
	createQuoteScheduler,
	getRequestKey,
} from './request';
import metadata from './block.json';
import {
	findSelectedShipbubbleRate,
	getSelectedCourierDisplay,
} from './selected-courier';
import './style.scss';

const { createElement } = window.wp.element;
const { select, subscribe, useSelect } = window.wp.data;
const { getBlockType, registerBlockType } = window.wp.blocks;
const { CART_STORE_KEY, CHECKOUT_STORE_KEY, processErrorResponse } =
	window.wc.wcBlocksData;
const { extensionCartUpdate, registerCheckoutBlock } = window.wc.blocksCheckout;
const settings =
	window.wc.wcSettings?.getSetting?.( 'shipbubble_data', {} ) || {};

/**
 * Display-only inner block for the selected native Shipbubble rate.
 * WooCommerce remains responsible for rate selection and checkout totals.
 *
 * @return {Element|null} Selected courier panel.
 */
function SelectedCourierBlock() {
	const selectedRate = useSelect( ( storeSelect ) => {
		const cartStore = storeSelect( CART_STORE_KEY );
		const cartData = cartStore?.getCartData?.();

		return findSelectedShipbubbleRate(
			cartData?.shippingRates,
			settings.methodId
		);
	}, [] );
	const courier = getSelectedCourierDisplay( selectedRate, settings.logoUrl );

	if ( ! courier ) {
		return null;
	}

	return createElement(
		'div',
		{
			className: 'shipbubble-selected-courier',
			role: 'status',
			'aria-live': 'polite',
		},
		courier.logo
			? createElement( 'img', {
					className: 'shipbubble-selected-courier__logo',
					src: courier.logo,
					alt: courier.name ? `${ courier.name } logo` : '',
					onError: ( event ) => {
						event.currentTarget.hidden = true;
					},
			  } )
			: null,
		createElement(
			'div',
			{ className: 'shipbubble-selected-courier__body' },
			createElement(
				'p',
				{ className: 'shipbubble-selected-courier__eyebrow' },
				settings.selectedLabel || 'Selected delivery'
			),
			courier.name
				? createElement(
						'p',
						{ className: 'shipbubble-selected-courier__name' },
						courier.name
				  )
				: null,
			courier.eta
				? createElement(
						'p',
						{ className: 'shipbubble-selected-courier__detail' },
						courier.eta
				  )
				: null,
			courier.description
				? createElement(
						'p',
						{ className: 'shipbubble-selected-courier__detail' },
						courier.description
				  )
				: null
		)
	);
}

if ( ! getBlockType( metadata.name ) ) {
	registerBlockType( metadata, {
		edit: () =>
			createElement(
				'div',
				{ className: 'shipbubble-selected-courier-editor' },
				createElement( 'strong', null, 'Selected Shipbubble Courier' ),
				createElement(
					'p',
					null,
					'The selected courier logo and delivery details appear here during checkout.'
				)
			),
		save: () =>
			createElement( 'div', {
				className: 'wp-block-shipbubble-selected-courier',
			} ),
	} );
}

if ( typeof registerCheckoutBlock === 'function' ) {
	registerCheckoutBlock( {
		metadata,
		component: SelectedCourierBlock,
	} );
}

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
