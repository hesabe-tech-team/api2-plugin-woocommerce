<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * WC_Hesabe_Blocks class for WooCommerce Blocks support
 */
final class WC_Hesabe_Blocks extends AbstractPaymentMethodType {

	/**
	 * The gateway instance.
	 *
	 * @var WC_Hesabe
	 */
	private $gateway;

	/**
	 * Payment method name/id.
	 *
	 * @var string
	 */
	protected $name = 'hesabe';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_hesabe_settings', array() );
		$gateways       = WC_Payment_Gateways::instance();
		$this->gateway  = $gateways->payment_gateways()['hesabe'] ?? null;

		// Debug log: initialization
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$error_info = array(
				'gateway_exists' => (bool) $this->gateway,
				'settings_enabled' => isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'undefined',
			);
			error_log( '[HESABE BLOCKS] initialize: ' . print_r( $error_info, true ) );
		}
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts won't be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		// Prefer gateway's availability when present, otherwise fall back to saved settings
		if ( ! is_null( $this->gateway ) ) {
			try {
				return (bool) $this->gateway->is_available();
			} catch ( Exception $e ) {
				return false;
			}
		}
		$opts = get_option( 'woocommerce_hesabe_settings', array() );
		return isset( $opts['enabled'] ) && $opts['enabled'] === 'yes';
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$script_asset_path = WC_HESABE_ABSPATH . 'js/hesabe-blocks.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? include( $script_asset_path )
			: array(
				'dependencies' => array(),
				'version'      => WC_HESABE_VERSION,
			);
		$script_url        = WC_HESABE_PLUGIN_URL . '/js/hesabe-blocks.js';

		wp_register_script(
			'wc-hesabe-blocks',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations(
				'wc-hesabe-blocks',
				'woocommerce-hesabe',
				WC_HESABE_ABSPATH . 'languages'
			);
		}

		return array( 'wc-hesabe-blocks' );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment method script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		// Build supports array - ensure 'products' and 'refunds' are always present for checkout block compatibility
		$supports = array( 'products', 'refunds' );  // Default for checkout blocks
		
		if ( $this->gateway && ! empty( $this->gateway->supports ) ) {
			$gateway_supports = array_values( (array) $this->gateway->supports );
			// Merge with gateway supports, ensuring required features are always present
			$supports = array_unique( array_merge( $supports, $gateway_supports ) );
		}

		$data = array(
			'title'          => $this->gateway ? $this->gateway->title : __( 'Hesabe Payments', 'woocommerce-hesabe' ),
			'description'    => $this->gateway ? $this->gateway->description : '',
			'supports'       => $supports,
			'icon'           => $this->gateway ? $this->gateway->icon : '',
			'enabled'        => $this->is_active(),
			'paymentMethods' => $this->get_enabled_payment_methods(),
			'nonce'          => wp_create_nonce( 'wc_hesabe_blocks_nonce' ),
		);

		// Ensure there's at least one payment method for Blocks UI to consider the gateway usable.
		if ( empty( $data['paymentMethods'] ) ) {
			$data['paymentMethods'] = array(
				array(
					'id'   => 'hosted',
					'name' => __( 'Hesabe (Hosted)', 'woocommerce-hesabe' ),
					'icon' => $this->gateway ? $this->gateway->icon : '',
				),
			);
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[HESABE BLOCKS] get_payment_method_data: ' . wp_json_encode( $data ) );
		}

		// Ensure the block script can read its data from a global variable
		if ( wp_script_is( 'wc-hesabe-blocks', 'registered' ) ) {
			$inline = 'window.wcHesabeBlocksData = window.wcHesabeBlocksData || {}; window.wcHesabeBlocksData.hesabe_data = ' . wp_json_encode( $data ) . ';';
			wp_add_inline_script( 'wc-hesabe-blocks', $inline, 'before' );
		}

		return $data;
	}

	/**
	 * Get enabled payment methods from settings
	 *
	 * @return array
	 */
	private function get_enabled_payment_methods() {
		// Prefer gateway settings when available, otherwise fall back to stored options
		$methods = array();
		if ( $this->gateway && ! empty( $this->gateway->settings ) ) {
			$settings = $this->gateway->settings;
		} else {
			$settings = get_option( 'woocommerce_hesabe_settings', array() );
		}

		if ( isset( $settings['direct'] ) && $settings['direct'] === 'yes' ) {
			if ( isset( $settings['direct1'] ) && $settings['direct1'] === 'yes' ) {
				$methods[] = array(
					'id'   => '1',
					'name' => 'KNET',
					'icon' => WC_HESABE_PLUGIN_URL . '/include/images/knet.png',
				);
			}

			if ( isset( $settings['direct2'] ) && $settings['direct2'] === 'yes' ) {
				$methods[] = array(
					'id'   => '11',
					'name' => 'Apple Pay (Knet)',
					'icon' => WC_HESABE_PLUGIN_URL . '/include/images/apple.png',
				);
			}

			if ( isset( $settings['direct3'] ) && $settings['direct3'] === 'yes' ) {
				$methods[] = array(
					'id'   => '2',
					'name' => 'Visa/Mastercard',
					'icon' => WC_HESABE_PLUGIN_URL . '/include/images/mastervisa.png',
				);
			}

			if ( isset( $settings['direct4'] ) && $settings['direct4'] === 'yes' ) {
				$methods[] = array(
					'id'   => '7',
					'name' => 'Amex',
					'icon' => WC_HESABE_PLUGIN_URL . '/include/images/amex_new.png',
				);
			}

			if ( isset( $settings['direct5'] ) && $settings['direct5'] === 'yes' ) {
				$methods[] = array(
					'id'   => '9',
					'name' => 'Apple Pay',
					'icon' => WC_HESABE_PLUGIN_URL . '/include/images/apple.png',
				);
			}
		}

		return $methods;
	}
}
