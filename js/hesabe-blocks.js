( function ( wp ) {
	// Helper to find registry from several possible globals
	var findRegistry = function () {
		// WooCommerce Blocks exposes registry at several possible locations
		if ( window.wc && window.wc.wcBlocksRegistry ) {
			return window.wc.wcBlocksRegistry;
		}
		if ( window.wcBlocksRegistry ) {
			return window.wcBlocksRegistry;
		}
		if ( window.wc_blocks_registry ) {
			return window.wc_blocks_registry;
		}
		if ( window.wc && window.wc.wcBlocks && window.wc.wcBlocks.registry ) {
			return window.wc.wcBlocks.registry;
		}
		// Check in wp.data (used by Gutenberg)
		if ( wp && wp.data && typeof wp.data.select === 'function' ) {
			try {
				var store = wp.data.select( 'wc/blocks-registry' );
				if ( store && store.getPaymentMethods ) {
					return store;
				}
			} catch ( e ) {}
		}
		return null;
	};

	// Helper to find localized data from several fallbacks
	var findSettings = function () {
		if ( window.wcHesabeBlocksData && window.wcHesabeBlocksData.hesabe_data ) {
			return window.wcHesabeBlocksData.hesabe_data;
		}
		if ( window.wc_hesabe_blocks && window.wc_hesabe_blocks.hesabe_data ) {
			return window.wc_hesabe_blocks.hesabe_data;
		}
		// try older global name used by some integrations
		if ( window.wcPayments && window.wcPayments.hesabe ) {
			return window.wcPayments.hesabe;
		}
		return {};
	};

	var __ = wp && wp.i18n && wp.i18n.__ ? wp.i18n.__ : function ( s ) { return s; };
	var el = wp && wp.element && wp.element.createElement ? wp.element.createElement : function () { return null; };

	// Attempt registration with retries until registry is available
	var retries = 0;
	var maxRetries = 50;  // Increased from 20 to give more time for dependencies
	var retryDelay = 500;  // Increased from 250ms to 500ms

	var tryRegister = function () {
		var wcBlocksRegistry = findRegistry();
		var settings = findSettings();

		if ( ! wcBlocksRegistry || typeof wcBlocksRegistry.registerPaymentMethod !== 'function' ) {
			retries++;
			if ( retries % 10 === 0 || retries === 1 ) {
				try {
					console.log( 'Hesabe: registry check #' + retries + ', found:', wcBlocksRegistry ? 'yes' : 'no', 'window keys:', Object.keys( window ).filter( function ( k ) { return k.indexOf( 'wc' ) === 0 || k.indexOf( 'Wc' ) === 0 } ) );
				} catch ( e ) {}
			}
			if ( retries < maxRetries ) {
				setTimeout( tryRegister, retryDelay );
			} else {
				try {
					console.error( 'Hesabe: payment registry not found after ' + retries + ' retries. Checked locations: window.wc.wcBlocksRegistry, window.wcBlocksRegistry, window.wc_blocks_registry, window.wc.wcBlocks.registry, wp.data' );
				} catch ( e ) {}
			}
			return;
		}

		try {
			console.info( 'Hesabe: block script initialized', { settings: settings } );
		} catch ( e ) {}

		var labelText = settings.title || __( 'Hesabe Payments', 'woocommerce-hesabe' );
		var description = settings.description || '';
		var paymentMethods = settings.paymentMethods || [];

		// Build content element: include description and radio inputs for each payment method
		var contentElement = null;
		var descriptionElement = null;
		var gatewayIconElement = null;
		if ( settings.icon ) {
			gatewayIconElement = el( 'img', { src: settings.icon, alt: labelText, style: { width: '48px', height: 'auto', display: 'block', marginBottom: '8px' } } );
		}
		if ( description && description.length > 0 ) {
			descriptionElement = el( 'div', { dangerouslySetInnerHTML: { __html: description } } );
		}
		if ( paymentMethods && paymentMethods.length ) {
			var methodInputs = [];
			for ( var m = 0; m < paymentMethods.length; m++ ) {
				var pm = paymentMethods[m];
				methodInputs.push(
					el( 'label', { key: pm.id, style: { display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '8px' } },
						el( 'input', { type: 'radio', name: 'hesabe_selected_payment_type', value: pm.id, defaultChecked: m === 0 } ),
						pm.icon ? el( 'img', { src: pm.icon, alt: pm.name, style: { width: '32px', height: 'auto', display: 'inline-block' } } ) : null,
						el( 'span', null, pm.name )
					)
				);
			}
			contentElement = el( 'div', null, descriptionElement, el( 'div', null, methodInputs ) );
		} else if ( descriptionElement ) {
			contentElement = descriptionElement;
		}

		var Edit = function ( props ) {
			var defaultValue = paymentMethods && paymentMethods.length ? paymentMethods[0].id : '';
			var inputs = [];
			if ( paymentMethods && paymentMethods.length ) {
				for ( var i = 0; i < paymentMethods.length; i++ ) {
					var method = paymentMethods[i];
					inputs.push(
						el(
							'label',
							{ key: method.id, style: { display: 'block', marginBottom: '8px' } },
							el( 'input', { 
								type: 'radio', 
								name: 'hesabe_payment_option', 
								value: method.id,
								defaultChecked: i === 0,
								onChange: function(e) {
									if (e.target.checked) {
										var hiddenInput = document.querySelector('input[name="hesabe_selected_payment_type"]');
										if (hiddenInput) {
											hiddenInput.value = e.target.value;
										}
									}
								}
							} ),
							' ',
							method.name
						)
					);
				}
			}

			// Hidden input that server expects
			inputs.push(
				el( 'input', {
					type: 'hidden',
					id: 'hesabe_selected_payment_type',
					name: 'hesabe_selected_payment_type',
					value: defaultValue,
				} )
			);

			return el( 'div', null, contentElement, el( 'div', null, inputs ) );
		};

		var canMakePayment = function () {
			return !!settings.enabled;
		};

		var method = {
			name: 'hesabe',
			label: labelText,
			content: contentElement,
			// `edit` must be a React element or null — create an element from the Edit component
			edit: el( Edit ),
			canMakePayment: canMakePayment,
			ariaLabel: labelText,
			// Registry expects supports as an object with a `features` array
			supports: {
				features: ( settings.supports && settings.supports.length ) ? settings.supports : [ 'products' ],
			},
			// Expose available payment methods and icon/description so Blocks UI can render options
			paymentMethods: paymentMethods || [],
			icon: settings.icon || null,
			description: description || null,
		};

		try { console.info( 'Hesabe: supports sent to registry', ( method.supports && method.supports.features ) ? method.supports.features : [] ); } catch (e) {}

		try {
			console.info( 'Hesabe: attempting to register payment method with block registry', wcBlocksRegistry );

			// Build a serializable payload for debugging (functions removed)
			var methodPayload = {
				name: method.name,
				label: method.label,
				contentType: method.content ? typeof method.content : null,
				editType: method.edit ? typeof method.edit : null,
				canMakePaymentType: typeof method.canMakePayment,
				ariaLabel: method.ariaLabel,
				supports: method.supports,
				paymentMethods: method.paymentMethods,
				icon: method.icon,
				description: method.description,
			};
			try { console.info( 'Hesabe: method payload (serializable):', methodPayload ); } catch ( e ) {}

			wcBlocksRegistry.registerPaymentMethod( method );
			console.info( 'Hesabe: registered payment method with block registry' );

			// Diagnostic: read back a serializable snapshot from registry
			try {
				if ( typeof wcBlocksRegistry.getPaymentMethod === 'function' ) {
					var stored = wcBlocksRegistry.getPaymentMethod( 'hesabe' );
					var storedSnapshot = {
						name: stored && stored.name,
						label: stored && stored.label,
						canMakePaymentType: stored && typeof stored.canMakePayment,
						supports: stored && stored.supports,
						paymentMethods: stored && stored.paymentMethods,
						icon: stored && stored.icon,
						description: stored && stored.description,
					};
					console.info( 'Hesabe: registry.getPaymentMethod("hesabe") snapshot:', storedSnapshot );
				}
				if ( typeof wcBlocksRegistry.getPaymentMethods === 'function' ) {
					console.info( 'Hesabe: registry.getPaymentMethods() keys:', Object.keys( wcBlocksRegistry.getPaymentMethods() || {} ) );
				}
			} catch ( e ) {
				console.warn( 'Hesabe: unable to read registry state', e );
			}
		} catch ( e ) {
			console.error( 'Hesabe: unable to register payment method', e );
		}

			// Try to inject a Hesabe icon into the main payment method label (targeting the block's label id)
			(function insertMainIcon() {
				if ( ! settings.icon ) {
					return;
				}
				var attempts = 0;
				var max = 12;
				var delay = 500;
				var tryInsert = function() {
					attempts++;
					try {
						// Prefer the specific label id used by the block markup
						var selectors = [
							'#radio-control-wc-payment-method-options-hesabe__label',
							'label[for="radio-control-wc-payment-method-options-hesabe"]'
						];
						var lab = null;
						for ( var s = 0; s < selectors.length; s++ ) {
							lab = document.querySelector( selectors[s] );
							if ( lab ) break;
						}
						if ( lab && ! lab.querySelector('.hesabe-main-icon') ) {
							var img = document.createElement('img');
							img.src = settings.icon;
							img.alt = labelText || 'Hesabe';
							img.className = 'hesabe-main-icon';
							img.style.width = '24px';
							img.style.height = 'auto';
							img.style.marginRight = '8px';
							img.style.verticalAlign = 'middle';
							// Ensure the label uses inline-flex so icon + text align nicely
							try {
								var prevDisplay = lab.style.display;
								lab.style.display = 'inline-flex';
								lab.style.alignItems = 'center';
								lab.style.gap = '8px';
							} catch ( _e ) {}
							// Insert the icon at the start of the label
							lab.insertBefore(img, lab.firstChild);
							return;
						}
					} catch (e) {}
					if ( attempts < max ) {
						setTimeout( tryInsert, delay );
					}
				};
				tryInsert();
			})();

			// Sync selected sub-option with main payment method radio and a hidden checkout input
			(function setupSelectionSync() {
				var attempts = 0;
				var max = 12;
				var delay = 500;
				var trySetup = function() {
					attempts++;
					try {
						var radios = document.querySelectorAll('input[name="hesabe_selected_payment_type"]');
						if ( ! radios || radios.length === 0 ) {
							if ( attempts < max ) { setTimeout( trySetup, delay ); }
							return;
						}
						var form = document.querySelector('form.checkout, form.woocommerce-checkout') || document.body;
						var hid = form.querySelector('input[name="hesabe_selected_payment_type"]');
						if ( ! hid ) {
							hid = document.createElement('input');
							hid.type = 'hidden';
							hid.name = 'hesabe_selected_payment_type';
							form.appendChild(hid);
						}

						var updateMain = function( val ) {
							try {
								// Prefer the specific input id used by the block markup
								var main = document.querySelector('#radio-control-wc-payment-method-options-hesabe') || document.querySelector('input[id^="radio-control-wc-payment-method-options-hesabe"]') || document.querySelector('input[name="payment_method"][value*="hesabe"]');
								if ( main ) {
									main.setAttribute('value', val);
									main.value = val;
								}
							} catch ( e ) {}
							try { hid.value = val; } catch ( e ) {}
						};

						// Set up change handlers for all radio buttons
						radios.forEach(function(r){
							r.addEventListener('change', function(){ 
								if ( this.checked ) { 
									updateMain( this.value ); 
								} 
							});
						});

						// Also handle payment option radios (if they exist) and sync with hesabe_selected_payment_type
						var paymentOptionRadios = document.querySelectorAll('input[name="payment_option"]');
						paymentOptionRadios.forEach(function(r){
							r.addEventListener('change', function(){ 
								if ( this.checked ) { 
									updateMain( this.value ); 
								} 
							});
						});

						var checked = Array.from(radios).find(function(r){ return r.checked; });
						if ( checked ) {
							updateMain( checked.value );
						} else if ( radios.length ) {
							radios[0].checked = true; updateMain( radios[0].value );
						}
					} catch ( e ) {
						if ( attempts < max ) { setTimeout( trySetup, delay ); }
					}
				};
				trySetup();
			})();
	};

	// Start trying to register when DOM is ready
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', tryRegister );
	} else {
		tryRegister();
	}

// Intercept checkout submission when Hesabe is selected and perform Hesabe flow
(function integrateHesabeDuringCheckout() {
	var attempts = 0;
	var max = 10;
	var delay = 500;

	var tryAttach = function () {
		attempts++;
		try {
			var form = document.querySelector('form.checkout, form.woocommerce-checkout');
			if (!form) {
				if (attempts < max) { setTimeout(tryAttach, delay); }
				return;
			}

			form.addEventListener('submit', function (e) {
				try {
					var hesabeMain = document.querySelector('#radio-control-wc-payment-method-options-hesabe') || document.querySelector('input[id^="radio-control-wc-payment-method-options-hesabe"]') || document.querySelector('input[name="payment_method"][value*="hesabe"]');
					if (!hesabeMain) { return; }
					var isHesabe = (hesabeMain.checked || hesabeMain.getAttribute('value') && hesabeMain.getAttribute('value').toString() !== '' && hesabeMain.getAttribute('value').toString() !== '0' && hesabeMain.getAttribute('value').toString() !== 'hesabe');
					// If hesabe radio is checked OR its value is set to a numeric sub-option, treat as hesabe
					var checkedRadio = document.querySelector('input[type="radio"][name="payment_method"]:checked');
					if (checkedRadio && checkedRadio.value !== 'hesabe' && checkedRadio.value.indexOf('hesabe') === -1) {
						// other payment method selected
						return;
					}

					// Determine selected hesabe sub-option
					var selectedSub = (function () {
						var r = document.querySelectorAll('input[name="hesabe_selected_payment_type"]');
						if (r && r.length) {
							for (var i = 0; i < r.length; i++) { if (r[i].checked) return r[i].value; }
							return r[0].value;
						}
						// fallback to hidden input or main value
						var hid = document.querySelector('input[name="hesabe_selected_payment_type"]');
						if (hid && hid.value) return hid.value;
						if (hesabeMain && hesabeMain.value) return hesabeMain.value;
						return '';
					})();

					// If not hesabe selection, let normal submit continue
					if (!selectedSub) { return; }

					// Prevent default and submit via AJAX to create order
					e.preventDefault();
					// Ensure the selected values exist as hidden inputs on the form (safer than FormData-only)
					(function ensureHiddenInputs() {
						function setHidden(name, value) {
							var el = form.querySelector('input[name="' + name + '"]');
							if (!el) {
								el = document.createElement('input');
								el.type = 'hidden';
								el.name = name;
								form.appendChild(el);
							}
							el.value = value;
						}

						try { setHidden('hesabe_selected_payment_type', selectedSub); } catch (e) {}
						try { setHidden('payment_method', 'hesabe'); } catch (e) {}
						try { setHidden('payment_method_data', JSON.stringify({ hesabe_selected_payment_type: selectedSub })); } catch (e) {}
					})();

					var fd = new FormData(form);
					// Ensure action for wc-ajax
					fd.append('wc-ajax', 'checkout');

					fetch( wc_checkout_params ? wc_checkout_params.checkout_url || '?wc-ajax=checkout' : '?wc-ajax=checkout', {
						method: 'POST',
						credentials: 'same-origin',
						body: fd
					}).then(function (resp) { return resp.json(); }).then(function (json) {
						if (json && json.result === 'success' && json.redirect) {
							// Try to extract order id from redirect URL
							var orderId = null;
							try {
								var m = json.redirect.match(/order-pay\/(\d+)/);
								if (m) orderId = parseInt(m[1], 10);
							} catch (ee) {}
							// fallback: attempt to parse query param 'order' or 'order_id'
							if (!orderId) {
								try {
									var u = new URL(json.redirect, window.location.origin);
									orderId = u.searchParams.get('order') || u.searchParams.get('order_id');
									if (orderId) orderId = parseInt(orderId, 10);
								} catch (e) {}
							}

							if (!orderId) {
								// If we cannot determine order id, still attempt to call create payment with order_id 0 (server may handle)
								orderId = 0;
							}

							// Call our AJAX endpoint to create Hesabe payment
							var payload = new FormData();
							payload.append('action', 'hesabe_create_payment');
							payload.append('nonce', (window.wcHesabeBlocksData && window.wcHesabeBlocksData.hesabe_data && window.wcHesabeBlocksData.hesabe_data.nonce) || '');
							payload.append('order_id', orderId);
							payload.append('payment_type', selectedSub);

							fetch( '<?php echo admin_url( "admin-ajax.php" ); ?>', { method: 'POST', credentials: 'same-origin', body: payload } )
								.then(function (r) { return r.json(); })
								.then(function (res) {
									if (res && res.success && res.data && res.data.payment_url) {
										window.location.href = res.data.payment_url;
									} else {
										// fallback to default redirect
										if (json.redirect) window.location.href = json.redirect;
									}
								}).catch(function () { if (json.redirect) window.location.href = json.redirect; });
						} else {
							// fallback to default behavior
							if (json && json.messages) {
								try { wc_checkout_form.form_submit_handler(); } catch (e) {}
							}
						}
					}).catch(function () { /* network error - allow normal submit fallback */ form.submit(); });

				} catch (err) {}
			}, { capture: true });

		} catch (e) {
			if (attempts < max) setTimeout(tryAttach, delay);
		}
	};
	tryAttach();
})();

// Intercept Store API checkout POSTs, try to inject hesabe payment data and
// — if an order is created — call our AJAX endpoint to create Hesabe checkout and redirect.
(function interceptStoreApiCheckout() {
	if (!window.fetch) return;
	var originalFetch = window.fetch.bind(window);
	window.fetch = function (input, init) {
		try {
			var url = (typeof input === 'string') ? input : (input && input.url) || '';
			if (url && url.indexOf('/wc/store/v1/checkout') !== -1) {
				var method = (init && init.method) || 'GET';
				if (method.toUpperCase() === 'POST') {
					// attempt to read JSON body and ensure payment_data contains hesabe selection
					var ensureAndContinue = function () {
						return originalFetch(input, init).then(function (resp) {
							try {
								var clone = resp.clone();
								return clone.json().then(function (json) {
									// If store returned success and an order redirect, extract order id
									var redirectUrl = json && (json.redirect || (json.data && json.data.redirect));
									var orderId = null;
									if (redirectUrl && typeof redirectUrl === 'string') {
										var m = redirectUrl.match(/order-pay\/(\d+)/) || redirectUrl.match(/order-pay.*order=(\d+)/);
										if (m) orderId = parseInt(m[1], 10);
									}
									// Some Store API responses include order object
									if (!orderId && json && json.order && json.order.id) {
										orderId = parseInt(json.order.id, 10);
									}

									// If we have an order id, call our AJAX to create Hesabe payment
									var hid = document.querySelector('input[name="hesabe_selected_payment_type"]');
									var selected = hid ? hid.value : '';
									if (orderId && selected && selected !== '0') {
										var form = new FormData();
										form.append('action', 'hesabe_create_payment');
										form.append('nonce', (window.wcHesabeBlocksData && window.wcHesabeBlocksData.hesabe_data && window.wcHesabeBlocksData.hesabe_data.nonce) || '');
										form.append('order_id', orderId);
										form.append('payment_type', selected);
										return originalFetch('/wp-admin/admin-ajax.php', { method: 'POST', credentials: 'same-origin', body: form }).then(function (r) {
											return r.json().then(function (res) {
												if (res && res.success && res.data && res.data.payment_url) {
													window.location.href = res.data.payment_url;
													return new Promise(function () {}); // never resolve
												}
												return resp;
											}).catch(function () { return resp; });
										}).catch(function () { return resp; });
									}

									return resp;
								}).catch(function () { return resp; });
							} catch (e) {
								return resp;
							}
						}).catch(function (err) { return Promise.reject(err); });
					};

					// Attempt to modify init.body if it's JSON and we can parse it
					try {
						if (init && init.body && typeof init.body === 'string') {
							var obj = JSON.parse(init.body);
							var hid = document.querySelector('input[name="hesabe_selected_payment_type"]');
							var selected = hid ? hid.value : '';
							if (selected && selected !== '0') {
								obj.payment_method = 'hesabe';
								obj.payment_data = obj.payment_data || {};
								obj.payment_data.hesabe_selected_payment_type = selected;
								init = Object.assign({}, init, { body: JSON.stringify(obj) });
								init.headers = init.headers || {};
								if (init.headers && typeof init.headers.set === 'function') {
									try { init.headers.set('Content-Type', 'application/json'); } catch (e) {}
								} else if (typeof init.headers === 'object') {
									init.headers['Content-Type'] = 'application/json';
								}
							}
						}
					} catch (e) {
						// ignore parse errors
					}

					return ensureAndContinue();
				}
			}
		} catch (e) {}
		return originalFetch(input, init);
	};
})();

} )( window.wp );
