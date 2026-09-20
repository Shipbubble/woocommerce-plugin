import {
	buildQuoteRequest,
	createQuoteScheduler,
	getRequestKey,
	normalizeField,
	sendQuoteAfterCustomerUpdate,
} from './request';

const completeCart = {
	needsShipping: true,
	shippingAddress: {
		first_name: 'Ada',
		last_name: 'Lovelace',
		address_1: '1 Marina Road',
		city: 'Lagos',
		state: 'LA',
		country: 'NG',
		postcode: '100001',
		phone: '',
	},
	billingAddress: {
		first_name: 'Ada',
		last_name: 'Lovelace',
		email: 'ada@example.test',
		phone: '+2348000000000',
	},
};

describe( 'Shipbubble checkout-block requests', () => {
	test( 'normalizes strings without coercing other values', () => {
		expect( normalizeField( ' Lagos ' ) ).toBe( 'Lagos' );
		expect( normalizeField( null ) ).toBe( '' );
	} );

	test( 'builds a quote from shipping and billing data', () => {
		expect( buildQuoteRequest( completeCart ) ).toEqual( {
			action: 'quote',
			recipient: {
				first_name: 'Ada',
				last_name: 'Lovelace',
				email: 'ada@example.test',
				phone: '+2348000000000',
			},
			destination: {
				address_1: '1 Marina Road',
				city: 'Lagos',
				state: 'LA',
				country: 'NG',
				postcode: '100001',
			},
			delivery_instructions: '',
		} );
	} );

	test( 'clears rates when same billing is selected without a shipping phone', () => {
		expect(
			buildQuoteRequest( completeCart, '', {
				useShippingAsBilling: true,
			} )
		).toEqual( { action: 'clear' } );
	} );

	test( 'uses a newly entered shipping phone instead of the previous one', () => {
		const updatedCart = {
			...completeCart,
			shippingAddress: {
				...completeCart.shippingAddress,
				phone: '+2348111111111',
			},
			billingAddress: {
				...completeCart.billingAddress,
				phone: '',
			},
		};

		expect(
			buildQuoteRequest( updatedCart, '', {
				useShippingAsBilling: true,
			} ).recipient.phone
		).toBe( '+2348111111111' );
	} );

	test.each( [
		[ null ],
		[ { needsShipping: false } ],
		[
			{
				...completeCart,
				shippingAddress: {
					...completeCart.shippingAddress,
					city: '',
				},
			},
		],
	] )( 'clears an unavailable or incomplete quote', ( cart ) => {
		expect( buildQuoteRequest( cart ) ).toEqual( { action: 'clear' } );
	} );

	test( 'uses a stable key for normalized requests', () => {
		const request = buildQuoteRequest( completeCart );
		expect( getRequestKey( request ) ).toBe( getRequestKey( request ) );
	} );

	test( 'saves customer data before requesting courier rates', async () => {
		const request = buildQuoteRequest( completeCart );
		const calls = [];
		const updateCustomerData = jest.fn( async () => {
			calls.push( 'customer' );
		} );
		const updateQuote = jest.fn( async () => {
			calls.push( 'quote' );
		} );

		await sendQuoteAfterCustomerUpdate( request, {
			getSnapshot: () => ( { cartData: completeCart, request } ),
			updateCustomerData,
			updateQuote,
		} );

		expect( calls ).toEqual( [ 'customer', 'quote' ] );
		expect( updateCustomerData ).toHaveBeenCalledWith( {
			billing_address: completeCart.billingAddress,
			shipping_address: completeCart.shippingAddress,
		} );
	} );

	test( 'saves the shipping phone as billing phone for shared addresses', async () => {
		const cartData = {
			...completeCart,
			shippingAddress: {
				...completeCart.shippingAddress,
				phone: '+2348111111111',
			},
			billingAddress: {
				...completeCart.billingAddress,
				phone: '',
			},
		};
		const request = buildQuoteRequest( cartData, '', {
			useShippingAsBilling: true,
		} );
		const updateCustomerData = jest.fn();

		await sendQuoteAfterCustomerUpdate( request, {
			getSnapshot: () => ( {
				cartData,
				request,
				useShippingAsBilling: true,
			} ),
			updateCustomerData,
			updateQuote: jest.fn(),
		} );

		expect(
			updateCustomerData.mock.calls[ 0 ][ 0 ].billing_address.phone
		).toBe( '+2348111111111' );
	} );

	test( 'skips a quote if the address changed while saving', async () => {
		const request = buildQuoteRequest( completeCart );
		const changedRequest = buildQuoteRequest( {
			...completeCart,
			shippingAddress: {
				...completeCart.shippingAddress,
				city: 'Abuja',
			},
		} );
		let currentRequest = request;
		const updateQuote = jest.fn();

		await sendQuoteAfterCustomerUpdate( request, {
			getSnapshot: () => ( {
				cartData: completeCart,
				request: currentRequest,
			} ),
			updateCustomerData: async () => {
				currentRequest = changedRequest;
			},
			updateQuote,
		} );

		expect( updateQuote ).not.toHaveBeenCalled();
	} );

	test( 'clears an old quote without rewriting customer data', async () => {
		const updateCustomerData = jest.fn();
		const updateQuote = jest.fn();

		await sendQuoteAfterCustomerUpdate(
			{ action: 'clear' },
			{
				getSnapshot: jest.fn(),
				updateCustomerData,
				updateQuote,
			}
		);

		expect( updateCustomerData ).not.toHaveBeenCalled();
		expect( updateQuote ).toHaveBeenCalledWith( { action: 'clear' } );
	} );

	test( 'deduplicates identical requests', async () => {
		const send = jest.fn().mockResolvedValue( undefined );
		const scheduler = createQuoteScheduler( {
			send,
			delay: 0,
			setTimer: ( callback ) => {
				callback();
				return 1;
			},
			clearTimer: () => {},
		} );
		const request = buildQuoteRequest( completeCart );

		scheduler.queue( request );
		scheduler.queue( request );
		await scheduler.flush();

		expect( send ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'debounces rapid changes and sends only the newest request', async () => {
		const send = jest.fn().mockResolvedValue( undefined );
		const timers = new Map();
		let timerId = 0;
		const scheduler = createQuoteScheduler( {
			send,
			setTimer: ( callback ) => {
				timerId += 1;
				timers.set( timerId, callback );
				return timerId;
			},
			clearTimer: ( id ) => timers.delete( id ),
		} );

		scheduler.queue( {
			action: 'quote',
			destination: { city: 'Lagos' },
		} );
		scheduler.queue( {
			action: 'quote',
			destination: { city: 'Abuja' },
		} );

		expect( send ).not.toHaveBeenCalled();
		expect( timers.size ).toBe( 1 );
		const callback = Array.from( timers.values() )[ 0 ];
		callback();
		await scheduler.flush();

		expect( send ).toHaveBeenCalledTimes( 1 );
		expect( send.mock.calls[ 0 ][ 0 ].destination.city ).toBe( 'Abuja' );
	} );

	test( 'does not debounce clearing an invalid quote', () => {
		const setTimer = jest.fn().mockReturnValue( 1 );
		const scheduler = createQuoteScheduler( {
			send: jest.fn(),
			setTimer,
			clearTimer: jest.fn(),
		} );

		scheduler.queue( { action: 'clear' } );
		expect( setTimer ).toHaveBeenCalledWith( expect.any( Function ), 0 );
	} );

	test( 'runs the newest queued request after an in-flight request', async () => {
		let releaseFirst;
		const first = new Promise( ( resolve ) => {
			releaseFirst = resolve;
		} );
		const send = jest
			.fn()
			.mockReturnValueOnce( first )
			.mockResolvedValueOnce( undefined );
		const scheduler = createQuoteScheduler( {
			send,
			delay: 0,
			setTimer: ( callback ) => {
				callback();
				return 1;
			},
			clearTimer: () => {},
		} );

		scheduler.queue( { action: 'quote', destination: { city: 'Lagos' } } );
		scheduler.queue( { action: 'quote', destination: { city: 'Abuja' } } );
		releaseFirst();
		await first;
		await Promise.resolve();
		await Promise.resolve();

		expect( send ).toHaveBeenCalledTimes( 2 );
		expect( send.mock.calls[ 1 ][ 0 ].destination.city ).toBe( 'Abuja' );
	} );
} );
