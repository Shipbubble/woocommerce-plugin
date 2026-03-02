/**
 * Shipbubble – WooCommerce Checkout Blocks integration.
 *
 * Uses the ExperimentalOrderShippingPackages slot fill to inject the courier
 * selection UI below the shipping method radios in the blocks checkout.
 *
 * Data flow:
 *  1. Customer fills address → clicks "Get Delivery Prices" → AJAX fetches rates.
 *  2. Customer selects a courier →
 *       a. extensionCartUpdate()        → server stores selection in WC session,
 *                                          clears shipping cache → cart recalculates
 *                                          → order total updates in real time.
 *       b. __internalSetExtensionData() → data is sent with the checkout POST
 *                                          so the server can save order meta.
 */
( function () {
	'use strict';

	// Attempt to register the blocks integration.
	// Returns true when WC Blocks is ready (even if the plugin is inactive),
	// or false when WC Blocks is not yet available (triggers a retry).
	function tryRegister() {
		if (
			! window.wc ||
			! window.wc.blocksCheckout ||
			! window.wp ||
			! window.wp.plugins ||
			! window.wp.element ||
			! window.wp.data
		) {
			return false;
		}

		// Integration data is merged into window.wcSettings by WooCommerce
		// Blocks' AssetDataRegistry under the key {get_name()}_data = 'shipbubble_data'.
		// Read it here — after WC Blocks has loaded — so the data is available.
		var getSetting = window.wc.wcSettings && window.wc.wcSettings.getSetting;
		var scriptData = getSetting
			? getSetting( 'shipbubble_data', {} )
			: ( window.wcSettings && window.wcSettings.shipbubble_data ) || {};

		// Only activate when the plugin is switched on in Shipbubble settings.
		// Return true (not false) so we don't keep retrying when inactive.
		if ( ! scriptData.isActive ) {
			return true;
		}

		var registerPlugin  = window.wp.plugins.registerPlugin;
		var createElement   = window.wp.element.createElement;
		var useState        = window.wp.element.useState;
		var useEffect       = window.wp.element.useEffect;
		var useRef          = window.wp.element.useRef;
		var useSelect       = window.wp.data.useSelect;
		var useDispatch     = window.wp.data.useDispatch;

		var _bc = window.wc.blocksCheckout || {};
		var ExperimentalOrderShippingPackages = _bc.ExperimentalOrderShippingPackages || null;
		var extensionCartUpdate = _bc.extensionCartUpdate || function () {}; // no-op fallback
		var createPortal = window.wp.element.createPortal || null;

		// Need at least one rendering path (slot fill or DOM portal).
		if ( ! ExperimentalOrderShippingPackages && ! createPortal ) {
			return false;
		}

		// Stable string keys for WC data stores.
		var CART_STORE_KEY     = 'wc/store/cart';
		var CHECKOUT_STORE_KEY = 'wc/store/checkout';

		// -----------------------------------------------------------------
		// Main component
		// -----------------------------------------------------------------
		function ShipbubbleBlocksUI() {
			var isLocalPickup  = scriptData.isLocalPickup;
			var localPickupText = scriptData.localPickupText || 'Pickup in store';
			var pickupAddress   = scriptData.pickupAddress   || '';

			// ---- state --------------------------------------------------
			var _s1 = useState( [] );
			var couriers       = _s1[0]; var setCouriers     = _s1[1];

			var _s2 = useState( false );
			var loading        = _s2[0]; var setLoading      = _s2[1];

			var _s3 = useState( '' );
			var errorMsg       = _s3[0]; var setErrorMsg     = _s3[1];

			var _s4 = useState( null );
			var selectedCourier = _s4[0]; var setSelectedCourier = _s4[1];

			var _s5 = useState( '' );
			var requestToken   = _s5[0]; var setRequestToken = _s5[1];

			var _s6 = useState( '' );
			var rateDateTime   = _s6[0]; var setRateDateTime = _s6[1];

			var _s7 = useState( '₦' );
			var currencySymbol = _s7[0]; var setCurrencySymbol = _s7[1];

			// deliveryMethod: 'shipping' | 'pickup' | null
			var _s8 = useState( isLocalPickup ? null : 'shipping' );
			var deliveryMethod    = _s8[0]; var setDeliveryMethod = _s8[1];

			// ---- store data ---------------------------------------------
			var customerData = useSelect( function ( select ) {
				try {
					var cartData = select( CART_STORE_KEY ).getCartData();
					return {
						shippingAddress : cartData && cartData.shippingAddress ? cartData.shippingAddress : {},
						billingAddress  : cartData && cartData.billingAddress  ? cartData.billingAddress  : {},
					};
				} catch ( e ) {
					return { shippingAddress: {}, billingAddress: {} };
				}
			} );

			// __internalSetExtensionData – used to include data in the checkout POST.
			var checkoutDispatch    = useDispatch( CHECKOUT_STORE_KEY );
			var setExtensionData    = checkoutDispatch && checkoutDispatch.__internalSetExtensionData;

			// ---- reset couriers when the customer changes address -------
			var prevAddressKey = useRef( '' );
			useEffect( function () {
				var key = JSON.stringify( customerData.shippingAddress ) + JSON.stringify( customerData.billingAddress );
				if ( prevAddressKey.current && prevAddressKey.current !== key ) {
					setCouriers( [] );
					setSelectedCourier( null );
					setRequestToken( '' );
					setRateDateTime( '' );
					extensionCartUpdate( { namespace: 'shipbubble', data: { clear: true } } );
				}
				prevAddressKey.current = key;
			}, [ customerData.shippingAddress, customerData.billingAddress ] );

			// ---- sync extension data when selection changes -------------
			useEffect( function () {
				if ( ! setExtensionData ) return;

				if ( deliveryMethod === 'pickup' ) {
					setExtensionData( 'shipbubble', {
						request_token       : '',
						service_code        : '',
						courier_id          : 'local_pickup',
						selected_courier    : 'Local Pickup',
						cost                : '0',
						rate_datetime       : '',
						is_local_pickup     : 'true',
						local_pickup_address: pickupAddress,
					} );
				} else if ( selectedCourier ) {
					setExtensionData( 'shipbubble', {
						request_token       : requestToken,
						service_code        : selectedCourier.service_code,
						courier_id          : selectedCourier.courier_id,
						selected_courier    : selectedCourier.courier_name,
						cost                : String( selectedCourier.total ),
						rate_datetime       : rateDateTime,
						is_local_pickup     : 'false',
						local_pickup_address: '',
					} );
				} else {
					setExtensionData( 'shipbubble', {
						request_token       : '',
						service_code        : '',
						courier_id          : '',
						selected_courier    : '',
						cost                : '',
						rate_datetime       : '',
						is_local_pickup     : 'false',
						local_pickup_address: '',
					} );
				}
			}, [ selectedCourier, deliveryMethod, requestToken ] );

			// ---- helpers ------------------------------------------------
			function getEffectiveAddress() {
				var s = customerData.shippingAddress || {};
				var b = customerData.billingAddress  || {};
				return ( s.address_1 ) ? s : b;
			}

			// ---- fetch rates -------------------------------------------
			function handleFetchRates() {
				var addr    = getEffectiveAddress();
				var billing = customerData.billingAddress || {};

				var firstName = addr.first_name || billing.first_name || '';
				var lastName  = addr.last_name  || billing.last_name  || '';
				var email     = billing.email   || addr.email         || '';
				var phone     = billing.phone   || addr.phone         || '';
				var address1  = addr.address_1  || billing.address_1  || '';
				var city      = addr.city       || billing.city       || '';
				var state     = addr.state      || billing.state      || '';
				var country   = addr.country    || billing.country    || '';
				var postcode  = addr.postcode   || billing.postcode   || '';

				if ( ! firstName || ! lastName || ! email || ! phone || ! address1 || ! city || ! country ) {
					setErrorMsg( 'Please fill in your contact and address details before fetching rates.' );
					return;
				}

				setErrorMsg( '' );
				setLoading( true );
				setCouriers( [] );
				setSelectedCourier( null );

				var addressStr = address1 + ', ' + city;
				if ( state ) addressStr += ', ' + state;
				addressStr += ', ' + country;

				var body = new URLSearchParams();
				body.append( 'nonce',          scriptData.nonce );
				body.append( 'action',         'request_shipping_rates' );
				body.append( 'data[name]',     firstName + ' ' + lastName );
				body.append( 'data[email]',    email );
				body.append( 'data[phone]',    phone );
				body.append( 'data[address]',  addressStr );
				if ( postcode ) body.append( 'data[postcode]', postcode );

				fetch( scriptData.ajaxurl, {
					method : 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body   : body.toString(),
				} )
					.then( function ( res ) { return res.text(); } )
					.then( function ( text ) {
						setLoading( false );
						var response;
						try { response = JSON.parse( text ); } catch ( e ) {
							setErrorMsg( 'Unexpected server response. Please try again.' );
							return;
						}

						if ( response.status === 'success' ) {
							var ratesData  = response.data;
							var token      = ratesData.request_token || '';
							var extra      = parseFloat( ratesData.extra_charges ) || 0;
							var now        = new Date().toLocaleString();

							setRequestToken( token );
							setRateDateTime( now );
							setCurrencySymbol( ratesData.currency_symbol || '₦' );

							var mapped = ( ratesData.couriers || [] ).map( function ( c ) {
								return Object.assign( {}, c, {
									total: parseFloat( c.rate_card_amount ) + extra,
								} );
							} );
							setCouriers( mapped );
						} else {
							var msg = response.data || response.message || 'Unable to fetch rates.';
							if ( typeof msg === 'object' ) msg = 'Unable to fetch rates, please try again.';
							setErrorMsg( msg );
						}
					} )
					.catch( function () {
						setLoading( false );
						setErrorMsg( 'Unable to fetch rates. Please try again later.' );
					} );
			}

			// ---- courier selected --------------------------------------
			function handleCourierSelect( courier ) {
				setSelectedCourier( courier );

				// Trigger a server-side cart recalculation so the order total
				// reflects the selected courier's cost immediately.
				extensionCartUpdate( {
					namespace: 'shipbubble',
					data: {
						courier_id   : courier.courier_id,
						service_code : courier.service_code,
						courier_name : courier.courier_name,
						request_token: requestToken,
						cost         : String( courier.total ),
					},
				} );
			}

			// ---- delivery method toggle (pickup vs shipping) -----------
			function handleDeliveryMethodChange( method ) {
				setDeliveryMethod( method );
				setCouriers( [] );
				setSelectedCourier( null );
				setRequestToken( '' );
				setErrorMsg( '' );

				if ( method === 'pickup' ) {
					extensionCartUpdate( {
						namespace: 'shipbubble',
						data: {
							courier_id   : 'local_pickup',
							courier_name : 'Local Pickup',
							cost         : '0',
							is_local_pickup: true,
						},
					} );
				} else {
					extensionCartUpdate( { namespace: 'shipbubble', data: { clear: true } } );
				}
			}

			// ---- render -------------------------------------------------
			var children = [];

			// Local-pickup / shipping radio tabs
			if ( isLocalPickup ) {
				children.push(
					createElement( 'div', { key: 'tabs', className: 'shipbubble-delivery-options-card' },
						// Pickup tab
						createElement( 'div', {
							className: 'shipbubble-delivery-option' + ( deliveryMethod === 'pickup' ? ' selected' : '' ),
							onClick: function () { handleDeliveryMethodChange( 'pickup' ); },
						},
							createElement( 'div', { className: 'shipbubble-option-container' },
								createElement( 'div', { className: 'shipbubble-radio-label' },
									createElement( 'input', {
										type    : 'radio',
										id      : 'sb-block-pickup-option',
										name    : 'sb_delivery_method',
										checked : deliveryMethod === 'pickup',
										onChange: function () { handleDeliveryMethodChange( 'pickup' ); },
									} ),
									createElement( 'div', { className: 'shipbubble-pickup-text-container' },
										createElement( 'label', { htmlFor: 'sb-block-pickup-option' }, localPickupText ),
										pickupAddress ? createElement( 'div', { className: 'shipbubble-pickup-address' }, pickupAddress ) : null
									)
								)
							)
						),
						// Shipping tab
						createElement( 'div', {
							className: 'shipbubble-delivery-option' + ( deliveryMethod === 'shipping' ? ' selected' : '' ),
							onClick: function () { handleDeliveryMethodChange( 'shipping' ); },
						},
							createElement( 'div', { className: 'shipbubble-option-container' },
								createElement( 'div', { className: 'shipbubble-radio-label' },
									createElement( 'input', {
										type    : 'radio',
										id      : 'sb-block-shipping-option',
										name    : 'sb_delivery_method',
										checked : deliveryMethod === 'shipping',
										onChange: function () { handleDeliveryMethodChange( 'shipping' ); },
									} ),
									createElement( 'div', { className: 'shipbubble-pickup-text-container' },
										createElement( 'label', { htmlFor: 'sb-block-shipping-option' }, 'Get Delivery Prices' ),
										createElement( 'div', { className: 'shipbubble-pickup-address' }, '(Click here to get shipping rates)' )
									)
								)
							)
						)
					)
				);
			}

			// Courier section — visible when deliveryMethod is 'shipping'
			if ( deliveryMethod !== 'pickup' ) {
				var courierSectionChildren = [];

				// "Get Delivery Prices" button (no local-pickup tabs, or shipping selected)
				if ( ! isLocalPickup ) {
					courierSectionChildren.push(
						createElement( 'button', {
							key     : 'fetch-btn',
							id      : 'sb-block-request-rates',
							onClick : function ( e ) { e.preventDefault(); handleFetchRates(); },
							disabled: loading,
						},
							createElement( 'p', null, loading ? 'Fetching rates…' : 'Get Delivery Prices' )
						)
					);
				} else if ( deliveryMethod === 'shipping' ) {
					// Auto-fetch when the shipping tab is selected via local-pickup mode
					// Button rendered as a secondary trigger
					courierSectionChildren.push(
						createElement( 'button', {
							key    : 'fetch-btn-lp',
							style  : { display: couriers.length ? 'none' : '' },
							onClick: function ( e ) { e.preventDefault(); handleFetchRates(); },
							disabled: loading,
						},
							createElement( 'p', null, loading ? 'Fetching rates…' : 'Get Delivery Prices' )
						)
					);
				}

				// Error message
				if ( errorMsg ) {
					courierSectionChildren.push(
						createElement( 'p', { key: 'error', style: { color: '#cc0000', marginTop: '8px' } }, errorMsg )
					);
				}

				// Loading spinners
				if ( loading ) {
					courierSectionChildren.push(
						createElement( 'div', { key: 'loader', className: 'shipbubble-loading' },
							createElement( 'span', null ),
							createElement( 'span', null ),
							createElement( 'span', null ),
							createElement( 'span', null )
						)
					);
				}

				// Courier list
				if ( couriers.length > 0 ) {
					var courierItems = couriers.map( function ( courier, i ) {
						var isSelected =
							selectedCourier &&
							selectedCourier.courier_id   === courier.courier_id &&
							selectedCourier.service_code === courier.service_code;

						var topChildren = [
							createElement( 'img', {
								key: 'img',
								src: courier.courier_image,
								alt: courier.courier_name,
							} ),
							createElement( 'div', { key: 'msg', className: 'message' },
								createElement( 'div', { className: 'radio-info' },
									createElement( 'p', { className: 'title' }, courier.courier_name ),
									createElement( 'p', { className: 'price' }, currencySymbol + ' ' + courier.total.toLocaleString() )
								),
								createElement( 'span', { className: 'delivery-time' }, courier.delivery_eta ),
								( courier.pickup_station && courier.pickup_station.address )
									? createElement( 'p', { className: 'pickup-info' }, 'Pickup at ' + courier.pickup_station.address )
									: null
							),
						];

						return createElement( 'div', {
							key      : courier.courier_id + '_' + i,
							className: 'container-delivery-card-list-item' + ( isSelected ? ' active' : '' ),
							onClick  : function () { handleCourierSelect( courier ); },
						},
							createElement( 'div', { className: 'container-delivery-card-list-item-top' }, topChildren ),
							createElement( 'div', { className: 'radio-item' },
								createElement( 'input', {
									type    : 'radio',
									name    : 'sb_delivery_option',
									checked : !! isSelected,
									onChange: function () { handleCourierSelect( courier ); },
								} ),
								createElement( 'label', null )
							)
						);
					} );

					courierSectionChildren.push(
						createElement( 'div', { key: 'courier-list', id: 'sb-block-courier-list', className: 'container-delivery-card' },
							createElement( 'div', { className: 'container-delivery-card-header' },
								createElement( 'p', { id: 'sb-block-status-text' }, 'Select a delivery option' )
							),
							createElement( 'div', { className: 'container-delivery-card-list' }, courierItems )
						)
					);
				}

				children.push(
					createElement( 'div', { key: 'courier-section', id: 'sb-block-courier-section' },
						createElement( 'div', { className: 'container-card' }, courierSectionChildren )
					)
				);
			}

			return createElement( 'div', { className: 'shipbubble-delivery-method-container' }, children );
		}

		// -----------------------------------------------------------------
		// ShipbubbleMount: renders via slot fill when available, otherwise
		// falls back to a React portal injected directly into the DOM after
		// the shipping rates control (handles older / newer WC block versions).
		// -----------------------------------------------------------------
		function ShipbubbleMount() {
			var _pm = useState( null );
			var portalEl = _pm[0]; var setPortalEl = _pm[1];

			useEffect( function () {
				if ( ExperimentalOrderShippingPackages ) {
					return; // slot fill handles placement, nothing to do
				}

				var PORTAL_ID = 'sb-blocks-portal';

				function tryMount() {
					var existing = document.getElementById( PORTAL_ID );
					if ( existing ) { setPortalEl( existing ); return true; }

					// Try common WooCommerce Blocks shipping-section selectors.
					var section =
						document.querySelector( '.wc-block-components-shipping-rates-control' ) ||
						document.querySelector( '.wc-block-checkout__shipping-option' ) ||
						document.querySelector( '[data-block-name="woocommerce/checkout-shipping-methods-block"]' );

					if ( ! section ) { return false; }

					var div = document.createElement( 'div' );
					div.id = PORTAL_ID;
					// Insert right after the shipping rates list.
					section.parentNode.insertBefore( div, section.nextSibling );
					setPortalEl( div );
					return true;
				}

				if ( ! tryMount() ) {
					var obs = new MutationObserver( function () {
						if ( tryMount() ) { obs.disconnect(); }
					} );
					obs.observe( document.body, { childList: true, subtree: true } );
					return function () { obs.disconnect(); };
				}
			}, [] );

			// --- Slot fill path (preferred) ---
			if ( ExperimentalOrderShippingPackages ) {
				return createElement( ExperimentalOrderShippingPackages, null,
					createElement( ShipbubbleBlocksUI, null )
				);
			}

			// --- DOM portal fallback ---
			if ( portalEl && createPortal ) {
				return createPortal( createElement( ShipbubbleBlocksUI, null ), portalEl );
			}

			return null;
		}

		// -----------------------------------------------------------------
		// Register the plugin with the WooCommerce checkout block.
		// -----------------------------------------------------------------
		registerPlugin( 'shipbubble-blocks', {
			render: function () {
				return createElement( ShipbubbleMount, null );
			},
			scope: 'woocommerce-checkout',
		} );

		return true;
	}

	// Try immediately; retry on DOMContentLoaded in case Blocks loads late.
	if ( ! tryRegister() ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			tryRegister();
		} );
	}
} )();
