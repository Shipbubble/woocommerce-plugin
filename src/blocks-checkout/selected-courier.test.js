import {
	decodeDisplayText,
	findSelectedShipbubbleRate,
	getRateMeta,
	getSelectedCourierDisplay,
} from './selected-courier';

const shipbubbleRate = {
	rate_id: 'shipbubble_shipping_services:abc',
	method_id: 'shipbubble_shipping_services',
	name: 'Bubble Express',
	description: 'Pickup at Marina station',
	delivery_time: '1-2 business days',
	selected: true,
	meta_data: [
		{
			key: '_shipbubble_courier_image',
			value: 'https://cdn.example.test/bubble.png',
		},
	],
};

describe( 'Shipbubble selected-courier block', () => {
	test( 'finds the selected Shipbubble rate across packages', () => {
		const packages = [
			{
				shipping_rates: [
					{
						method_id: 'flat_rate',
						selected: false,
					},
					shipbubbleRate,
				],
			},
		];

		expect( findSelectedShipbubbleRate( packages ) ).toBe( shipbubbleRate );
	} );

	test( 'does not render for another selected shipping method', () => {
		const packages = [
			{
				shipping_rates: [
					{
						method_id: 'flat_rate',
						selected: true,
					},
					{ ...shipbubbleRate, selected: false },
				],
			},
		];

		expect( findSelectedShipbubbleRate( packages ) ).toBeNull();
	} );

	test( 'reads both Store API and object metadata formats', () => {
		expect(
			getRateMeta( shipbubbleRate, '_shipbubble_courier_image' )
		).toBe( 'https://cdn.example.test/bubble.png' );
		expect(
			getRateMeta(
				{
					metaData: {
						_shipbubble_courier_image: '/fallback.png',
					},
				},
				'_shipbubble_courier_image'
			)
		).toBe( '/fallback.png' );
	} );

	test( 'builds display data and falls back for Local Pickup', () => {
		expect( getSelectedCourierDisplay( shipbubbleRate ) ).toEqual( {
			name: 'Bubble Express',
			logo: 'https://cdn.example.test/bubble.png',
			eta: '1-2 business days',
			description: 'Pickup at Marina station',
		} );

		expect(
			getSelectedCourierDisplay(
				{
					name: 'Shipbubble Local Pickup',
					selected: true,
				},
				'/shipbubble.svg'
			).logo
		).toBe( '/shipbubble.svg' );
	} );

	test( 'decodes and trims WordPress HTML entities', () => {
		expect(
			decodeDisplayText( 'Within 2 &#8211; 3 working days &#x20;' )
		).toBe( 'Within 2 – 3 working days' );
	} );
} );
