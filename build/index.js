/* global wc, wp */
( function () {
	'use strict';

	if ( ! window.wc || ! window.wc.wcBlocksRegistry || ! window.wc.wcSettings || ! window.wp ) {
		return;
	}

	const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
	const settings = window.wc.wcSettings.getPaymentMethodData( 'hesabe' ) || {};
	const { __ } = window.wp.i18n || { __: ( s ) => s };

	const decodeEntities =
		( window.wp.htmlEntities && window.wp.htmlEntities.decodeEntities )
			? window.wp.htmlEntities.decodeEntities
			: ( s ) => s;

	const { createElement } = window.wp.element;
	const { useEffect, useMemo, useState } = window.wp.element;

	const title = decodeEntities( settings.title || 'Hesabe Payments' );
	const legacyDesc = 'The Hesabe payment gateway provider in Kuwait for e-payment through credit card & debit card';
	const descriptionRaw = decodeEntities( settings.description || '' );
	const description = descriptionRaw && descriptionRaw.includes( legacyDesc ) ? '' : descriptionRaw;

	const Label = () =>
		createElement(
			window.wp.element.Fragment,
			null,
			settings.icon ? createElement( 'img', { src: settings.icon, alt: title } ) : null,
			' ',
			title
		);

	const isSafari = () => {
		const ua = window.navigator && window.navigator.userAgent ? window.navigator.userAgent : '';
		return ua.includes( 'Safari' ) && !ua.includes( 'Chrome' ) && !ua.includes( 'Chromium' );
	};

	const canMakeApplePay = () => {
		try {
			return !!(
				window.ApplePaySession &&
				typeof window.ApplePaySession.canMakePayments === 'function' &&
				window.ApplePaySession.canMakePayments()
			);
		} catch ( e ) {
			return false;
		}
	};

	const Content = ( props ) => {
		const payOnHesabe = !! settings.pay_on_hesabe;
		const direct = ! payOnHesabe;
		const methods = Array.isArray( settings.methods ) ? settings.methods : [];

		const [ selected, setSelected ] = useState( '0' );
		const [ error, setError ] = useState( '' );

		const applePayAvailable = useMemo(
			() => isSafari() && canMakeApplePay(),
			// eslint-disable-next-line react-hooks/exhaustive-deps
			[]
		);

		const availableMethods = useMemo( () => {
			if ( applePayAvailable ) {
				return methods;
			}
			return methods.filter( ( m ) => String( m.id ) !== '9' && String( m.id ) !== '11' );
		}, [ methods, applePayAvailable ] );

		useEffect( () => {
			if ( ! applePayAvailable && ( selected === '9' || selected === '11' ) ) {
				setSelected( '0' );
				setError( __( 'Apple Pay is not available in this browser/device.', 'hesabe-woocommerce' ) );
			}
		}, [ applePayAvailable, selected ] );

		// Wire paymentMethodData into checkout submission (like MyFatoorah)
		useEffect( () => {
			if ( ! props || ! props.eventRegistration || ! props.emitResponse ) {
				return;
			}
			const { eventRegistration, emitResponse } = props;
			const { onPaymentSetup } = eventRegistration;

			const unsubscribe = onPaymentSetup( async () => {
				if ( direct ) {
					if ( ! selected || selected === '0' ) {
						return {
							type: emitResponse.responseTypes.ERROR,
							message: __( 'Please select a payment method to continue.', 'hesabe-woocommerce' ),
						};
					}
					return {
						type: emitResponse.responseTypes.SUCCESS,
						meta: {
							paymentMethodData: {
								hesabe_selected_payment_type: String( selected ),
							},
						},
					};
				}

				// Indirect mode
				return {
					type: emitResponse.responseTypes.SUCCESS,
					meta: {
						paymentMethodData: {
							hesabe_selected_payment_type: '0',
						},
					},
				};
			} );

			return () => {
				if ( typeof unsubscribe === 'function' ) {
					unsubscribe();
				}
			};
		}, [ props, direct, selected ] );

		// Basic UI that matches the old V5 radio list
		if ( payOnHesabe ) {
			return createElement(
				'div',
				null,
				description ? createElement( 'p', null, description ) : null,
				createElement(
					'p',
					{ style: { fontStyle: 'italic', color: '#666' } },
					__( 'You will be able to select your preferred payment method on the Hesabe payment page.', 'hesabe-woocommerce' )
				)
			);
		}

		return createElement(
			'div',
			null,
			description ? createElement( 'p', null, description ) : null,
			createElement( 'p', null, createElement( 'strong', null, __( 'Select Payment Methods:', 'hesabe-woocommerce' ) ) ),
			availableMethods.length
				? createElement(
					'div',
					{ className: 'hesabe-block-methods' },
					availableMethods.map( ( m ) =>
						createElement(
							'p',
							{ key: m.id },
							createElement(
								'label',
								null,
								createElement( 'input', {
									type: 'radio',
									name: 'hesabe_payment_option',
									value: m.id,
									checked: selected === String( m.id ),
									onChange: ( e ) => {
										setSelected( e.target.value );
										setError( '' );
									},
								} ),
								' ',
								m.icon ? createElement( 'img', { src: m.icon, alt: m.label, style: { height: '24px', verticalAlign: 'middle', marginRight: '8px' } } ) : null,
								m.label
							)
						)
					)
				)
				: createElement( 'p', { style: { color: '#d63638' } }, __( 'No payment methods are enabled. Please enable at least one in settings.', 'hesabe-woocommerce' ) ),
			error ? createElement( 'p', { style: { color: '#d63638' } }, error ) : null
		);
	};

	registerPaymentMethod( {
		name: 'hesabe',
		label: createElement( Label, null ),
		ariaLabel: title,
		content: createElement( Content, null ),
		edit: createElement( Content, null ),
		canMakePayment: () => true,
		supports: {
			features: settings.supports || [],
		},
	} );
} )();


