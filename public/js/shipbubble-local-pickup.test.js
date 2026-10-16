/** @jest-environment jsdom */
const fs = require( 'fs' );
const path = require( 'path' );
const $ = require( 'jquery' );

const script = fs.readFileSync(
	path.join( __dirname, 'shipbubble-local-pickup.js' ),
	'utf8'
);

describe( 'local pickup checkout address lookup', () => {
	let requests;
	let startupErrors;

	async function start( { fixedCountry = false, shippingFields = true } = {} ) {
		const addressFields = ( type ) => `
			<input id="${ type }_address_1" value="1 Test Street">
			<input id="${ type }_city" value="Lagos">
			<select id="${ type }_state"><option selected>Lagos</option></select>
			${ fixedCountry
				? `<input id="${ type }_country" value="NG">`
				: `<select id="${ type }_country"><option value="NG" selected>Nigeria</option></select>` }
		`;
		document.body.innerHTML = `
			<form>${ addressFields( 'billing' ) }
			${ shippingFields ? addressFields( 'shipping' ) : '' }
			<input type="checkbox" id="ship-to-different-address-checkbox">
			<input id="shipbubble-local-pickup-address" value="Default store">
			<div id="shipbubble-local-pickup-address-text">Default store</div></form>`;
		new Function( 'jQuery', script )( $ );
		await new Promise( ( resolve ) => $( document ).ready( resolve ) );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	}

	beforeEach( () => {
		requests = [];
		startupErrors = [];
		window.shipbubble_local_pickup = {
			nonce: 'test', ajaxurl: '/pickup', logo: '/logo.svg',
			countries: { NG: 'Nigeria', GH: 'Ghana' },
		};
		$.readyException = ( error ) => startupErrors.push( error );
		$.blockUI = jest.fn();
		$.unblockUI = jest.fn();
		const request = ( options ) => {
			const deferred = $.Deferred();
			const promise = deferred.promise();
			promise.abort = jest.fn( () => deferred.reject( {}, 'abort' ) );
			requests.push( { options, deferred, promise } );
			return promise;
		};
		$.ajax = jest.fn( request );
		$.post = jest.fn( ( url, data ) => request( { url, data } ) );
		jest.spyOn( console, 'error' ).mockImplementation( () => {} );
	});

	afterEach( () => {
		$( document ).off();
		jest.restoreAllMocks();
	});

	test( 'starts with a fixed country and no separate shipping fields', async () => {
		await start( { fixedCountry: true, shippingFields: false } );
		expect( startupErrors ).toEqual( [] );
		expect( requests ).toHaveLength( 1 );
		expect( requests[ 0 ].options.data.data.country ).toBe( 'Nigeria' );
	});

	test( 'accepts a decoded JSON response and releases the checkout overlay', async () => {
		await start();
		expect( startupErrors ).toEqual( [] );
		expect( () => requests[ 0 ].deferred.resolve( {
			status: 'success', data: { address: 'Resolved store' },
		} ) ).not.toThrow();
		expect( $( '#shipbubble-local-pickup-address' ).val() ).toBe( 'Resolved store' );
		expect( $( '#shipbubble-local-pickup-address-text' ).text() ).toBe( 'Resolved store' );
		expect( $.unblockUI ).toHaveBeenCalled();
	});

	test.each( [ null, {}, { data: {} }, { data: { address: null } } ] )(
		'keeps the default address and releases the overlay for an unusable response: %p',
		async ( response ) => {
			await start();
			expect( () => requests[ 0 ].deferred.resolve( response ) ).not.toThrow();
			expect( $( '#shipbubble-local-pickup-address' ).val() ).toBe( 'Default store' );
			expect( $.unblockUI ).toHaveBeenCalled();
		}
	);

	test.each( [ 'error', 'parsererror', 'timeout' ] )(
		'keeps checkout usable after a lookup %s', async ( status ) => {
			await start();
			requests[ 0 ].deferred.reject( {}, status );
			expect( console ).toHaveErroredWith( 'Failed to update local pickup address.' );
			expect( $( '#shipbubble-local-pickup-address' ).val() ).toBe( 'Default store' );
			expect( $.unblockUI ).toHaveBeenCalled();
		}
	);

	test( 'uses the freshly selected shipping address and ignores outdated responses', async () => {
		await start();
		// Some transports can still deliver a response after cancellation.
		requests[ 0 ].promise.abort.mockImplementation( () => {} );
		$( '#shipping_city' ).val( 'Ikeja' ).trigger( 'change' );
		expect( requests ).toHaveLength( 1 );
		$( '#ship-to-different-address-checkbox' ).prop( 'checked', true ).trigger( 'change' );
		expect( requests ).toHaveLength( 2 );
		expect( requests[ 1 ].options.data.data.city ).toBe( 'Ikeja' );
		requests[ 0 ].deferred.resolve( { data: { address: 'Old billing store' } } );
		expect( $( '#shipbubble-local-pickup-address' ).val() ).toBe( 'Default store' );
		expect( $.unblockUI ).not.toHaveBeenCalled();
		requests[ 1 ].deferred.resolve( { data: { address: 'Shipping store' } } );
		expect( $( '#shipbubble-local-pickup-address' ).val() ).toBe( 'Shipping store' );
	});

	test( 'waits for complete billing details without requiring unused shipping fields', async () => {
		await start( { shippingFields: false } );
		requests[ 0 ].deferred.resolve( { data: { address: 'Billing store' } } );
		$( '#billing_city' ).val( '' ).trigger( 'change' );
		expect( requests ).toHaveLength( 1 );
		$( '#billing_city' ).val( 'Ikeja' ).trigger( 'change' );
		expect( requests ).toHaveLength( 2 );
		expect( requests[ 1 ].options.data.data.city ).toBe( 'Ikeja' );
		expect( startupErrors ).toEqual( [] );
	});
} );
