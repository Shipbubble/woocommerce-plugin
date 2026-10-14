import {
	buildQuoteRequest,
	createHydrationRetry,
	createQuoteScheduler,
	getMissingQuoteFields,
	getRequestKey,
	sendQuoteAfterCustomerUpdate,
} from './request';
import metadata from './block.json';
import {
	findSelectedShipbubbleRate,
	getSelectedCourierDisplay,
} from './selected-courier';
import './style.scss';

const { createElement, createRoot, render, useEffect, useRef, useState } =
	window.wp.element;
const { dispatch, select, subscribe, useSelect } = window.wp.data;
const { getBlockType, registerBlockType } = window.wp.blocks;
const { CART_STORE_KEY, CHECKOUT_STORE_KEY, processErrorResponse } =
	window.wc.wcBlocksData;
const { extensionCartUpdate, registerCheckoutBlock } = window.wc.blocksCheckout;
const settings =
	window.wc.wcSettings?.getSetting?.( 'shipbubble_data', {} ) || {};

let quoteUiState = { status: 'idle', missingFields: [] };
const quoteUiListeners = new Set();

function setQuoteUiState( status, missingFields = [] ) {
	const nextKey = `${ status }:${ missingFields.join( ',' ) }`;
	const currentKey = `${
		quoteUiState.status
	}:${ quoteUiState.missingFields.join( ',' ) }`;

	if ( nextKey === currentKey ) {
		return;
	}

	quoteUiState = { status, missingFields };
	quoteUiListeners.forEach( ( listener ) => listener( quoteUiState ) );
}

function useQuoteUiState() {
	const [ state, setState ] = useState( quoteUiState );

	useEffect( () => {
		quoteUiListeners.add( setState );
		return () => quoteUiListeners.delete( setState );
	}, [] );

	return state;
}

/**
 * Display-only inner block for the selected native Shipbubble rate.
 * WooCommerce remains responsible for rate selection and checkout totals.
 *
 * @return {Element|null} Selected courier panel.
 */
function SelectedCourierBlock() {
	const containerRef = useRef( null );
	const quoteState = useQuoteUiState();
	const selectedRate = useSelect( ( storeSelect ) => {
		const cartStore = storeSelect( CART_STORE_KEY );
		const cartData = cartStore?.getCartData?.();

		return findSelectedShipbubbleRate(
			cartData?.shippingRates,
			settings.methodId
		);
	}, [] );
	const courier = getSelectedCourierDisplay( selectedRate, settings.logoUrl );
	const replacesNativeMessage = [ 'incomplete', 'loading', 'error' ].includes(
		quoteState.status
	);

	useEffect( () => {
		const shippingStep = containerRef.current?.closest(
			'.wp-block-woocommerce-checkout-shipping-methods-block'
		);

		shippingStep?.classList.toggle(
			'shipbubble-has-helpful-status',
			replacesNativeMessage
		);

		return () =>
			shippingStep?.classList.remove( 'shipbubble-has-helpful-status' );
	}, [ replacesNativeMessage ] );

	if ( ! courier && quoteState.status === 'idle' ) {
		return null;
	}

	let statusMessage = '';
	if ( quoteState.status === 'incomplete' ) {
		statusMessage = quoteState.missingFields.length
			? `Complete the following required fields to load delivery options: ${ quoteState.missingFields.join(
					', '
			  ) }.`
			: 'Complete your contact and shipping details to load delivery options.';
	} else if ( quoteState.status === 'loading' ) {
		statusMessage = 'Loading delivery options…';
	} else if ( quoteState.status === 'error' ) {
		statusMessage =
			'We could not load delivery options. Check your details and try again.';
	}

	return createElement(
		'div',
		{
			className: 'shipbubble-checkout-feedback',
			ref: containerRef,
			role: 'status',
			'aria-live': 'polite',
		},
		statusMessage
			? createElement(
					'p',
					{
						className: `shipbubble-quote-status shipbubble-quote-status--${ quoteState.status }`,
					},
					quoteState.status === 'loading'
						? createElement( 'span', {
								className: 'shipbubble-quote-status__spinner',
								'aria-hidden': 'true',
						  } )
						: null,
					statusMessage
			  )
			: null,
		courier
			? createElement(
					'div',
					{ className: 'shipbubble-selected-courier' },
					courier.logo
						? createElement( 'img', {
								className: 'shipbubble-selected-courier__logo',
								src: courier.logo,
								alt: courier.name
									? `${ courier.name } logo`
									: '',
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
							{
								className:
									'shipbubble-selected-courier__eyebrow',
							},
							settings.selectedLabel || 'Selected delivery'
						),
						courier.name
							? createElement(
									'p',
									{
										className:
											'shipbubble-selected-courier__name',
									},
									courier.name
							  )
							: null,
						courier.eta
							? createElement(
									'p',
									{
										className:
											'shipbubble-selected-courier__detail',
									},
									courier.eta
							  )
							: null,
						courier.description
							? createElement(
									'p',
									{
										className:
											'shipbubble-selected-courier__detail',
									},
									courier.description
							  )
							: null
					)
			  )
			: null
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

/**
 * Older Checkout pages do not contain extension inner-block markup until they
 * are saved again. Mount the same React component into the Shipping options
 * step so the status and selected-courier UI work immediately.
 */
function mountCheckoutBlockFallback() {
	if ( document.querySelector( '.wp-block-shipbubble-selected-courier' ) ) {
		return true;
	}

	const shippingStep = document.querySelector(
		'.wp-block-woocommerce-checkout-shipping-methods-block .wc-block-components-checkout-step__content'
	);
	if ( ! shippingStep ) {
		return false;
	}

	const mount = document.createElement( 'div' );
	mount.className = 'wp-block-shipbubble-selected-courier';
	mount.dataset.shipbubbleFallback = 'true';
	shippingStep.appendChild( mount );

	if ( typeof createRoot === 'function' ) {
		createRoot( mount ).render( createElement( SelectedCourierBlock ) );
	} else if ( typeof render === 'function' ) {
		render( createElement( SelectedCourierBlock ), mount );
	}

	return true;
}

if ( typeof document !== 'undefined' && ! mountCheckoutBlockFallback() ) {
	const mountObserver = new window.MutationObserver( () => {
		if ( mountCheckoutBlockFallback() ) {
			mountObserver.disconnect();
		}
	} );
	mountObserver.observe( document.documentElement, {
		childList: true,
		subtree: true,
	} );
}

function getCheckoutSnapshot() {
	const cartData = select( CART_STORE_KEY )?.getCartData?.();
	const checkoutStore = select( CHECKOUT_STORE_KEY );
	const useShippingAsBilling =
		checkoutStore?.getUseShippingAsBilling?.() === true;

	return {
		cartData,
		useShippingAsBilling,
		missingFields: getMissingQuoteFields( cartData, {
			useShippingAsBilling,
		} ),
		request: buildQuoteRequest(
			cartData,
			checkoutStore?.getOrderNotes?.(),
			{
				useShippingAsBilling,
			}
		),
	};
}

const scheduler = createQuoteScheduler( {
	send: async ( request ) => {
		try {
			const result = await sendQuoteAfterCustomerUpdate( request, {
				getSnapshot: getCheckoutSnapshot,
				updateCustomerData: ( customerData ) =>
					dispatch( CART_STORE_KEY ).updateCustomerData(
						customerData,
						true
					),
				updateQuote: ( currentRequest ) =>
					extensionCartUpdate( {
						namespace: 'shipbubble',
						data: currentRequest,
					} ),
			} );

			if (
				request.action === 'quote' &&
				getRequestKey( getCheckoutSnapshot().request ) ===
					getRequestKey( request )
			) {
				setQuoteUiState( 'ready' );
			}

			return result;
		} catch ( error ) {
			if ( request.action === 'quote' ) {
				setQuoteUiState( 'error' );
			}
			throw error;
		}
	},
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
		return false;
	}

	const { cartData, missingFields, request } = getCheckoutSnapshot();
	if ( ! cartData ) {
		return false;
	}
	const requestKey = getRequestKey( request );
	const hydrationComplete =
		request.action === 'quote' || cartData.needsShipping === false;

	if ( cartData.needsShipping === false ) {
		setQuoteUiState( 'idle' );
	} else if ( request.action === 'clear' ) {
		setQuoteUiState( 'incomplete', missingFields );
	}

	if ( requestKey === lastObservedKey ) {
		return hydrationComplete;
	}

	lastObservedKey = requestKey;
	if ( request.action === 'quote' ) {
		setQuoteUiState( 'loading' );
	}
	scheduler.queue( request );

	return hydrationComplete;
}

const hydrationRetry = createHydrationRetry( { observe: observeCheckout } );

subscribe( hydrationRetry.run );
hydrationRetry.run();
