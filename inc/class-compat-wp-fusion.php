<?php
/**
 * Compatibility class for WPFusion.
 *
 * @package Setary
 */

namespace Setary;

/**
 * Compatibility class for WPFusion.
 */
class Compat_WP_Fusion {
	/**
	 * Meta keys.
	 */
	protected $meta_keys = [];

	/**
	 * Construct.
	 */
	function __construct() {
		if ( ! class_exists( 'WP_Fusion' ) ) {
			return;
		}

		$this->set_keys();

		add_filter( 'setary_get_store_info', array( $this, 'get_store_info' ) );
		add_filter( 'setary_filter_response_by_context', array( $this, 'filter_response_by_context' ), 10, 3 );
		add_action( 'setary_pre_insert_product_object', array( $this, 'pre_insert_product_object' ), 10, 3 );
	}

	/**
	 * Set WP Fusion keys.
	 *
	 * @return void
	 */
	protected function set_keys() {
		$this->meta_keys = [
			'wp_fusion_apply_tags'          => [
				'title'      => __( 'WP Fusion Apply Tags' ),
				'source'     => [],
				'type'       => 'multi',
				'data'       => 'wp_fusion_apply_tags',
				'coreColumn' => true, // Means it won't be treated as meta data.
				'noFilter'   => true,
			],
			'wp_fusion_apply_tags_refunded' => [
				'title'           => __( 'WP Fusion Apply Tags Refunded' ),
				'source'          => [],
				'type'            => 'multi',
				'data'            => 'wp_fusion_apply_tags_refunded',
				'coreColumn'      => true, // Means it won't be treated as meta data.
				'disableForTypes' => [ 'variation' ],
				'noFilter'        => true,
			],
			'wp_fusion_apply_tags_failed'   => [
				'title'           => __( 'WP Fusion Apply Tags Failed' ),
				'source'          => [],
				'type'            => 'multi',
				'data'            => 'wp_fusion_apply_tags_failed',
				'coreColumn'      => true, // Means it won't be treated as meta data.
				'disableForTypes' => [ 'variation' ],
				'noFilter'        => true,
			],
		];
	}

	/**
	 * Get meta key name.
	 *
	 * @param string $key Potentially prefixed key.
	 * @param bool   $is_variation
	 *
	 * @return string
	 */
	protected function get_meta_key( $key, $is_variation = false ) {
		// If variation, add _variation suffix, if it doesn't already exist.
		if ( $is_variation && strpos( $key, '_variation' ) === false ) {
			$key .= '_variation';
		}

		return str_replace( 'wp_fusion_', '', $key );
	}

	/**
	 * Add WP Fusion info to store info.
	 *
	 * @param array $info
	 *
	 * @return array
	 */
	public function get_store_info( $info = array() ) {
		// Use array_values to reset keys. Then the app sees it as an array, not an object.
		$available_tags = array_values( wp_fusion()->settings->get_available_tags_flat() );

		// Loop keys and add to info.
		foreach ( $this->meta_keys as $value ) {
			$value['source'] = $available_tags;
			$info['meta'][] = $value;
		}

		// Loop through the meta array and unset the items where value is 'wpf-settings-woo'.
		foreach ( $info['meta'] as $key => $item ) {
			if ( 'wpf-settings-woo' === $item ) {
				unset( $info['meta'][ $key ] );
				break;
			}
		}

		return $info;
	}

	/**
	 * @param $item
	 * @param $data
	 * @param $context
	 *
	 * @return mixed
	 */
	public function filter_response_by_context( $item, $data, $context ) {
		// Loop keys and add to item.
		foreach ( $this->meta_keys as $key => $value ) {
			$item[$value['data']] = [];
		}

		// If meta data isn't present, return.
		if ( empty( $item['meta_data'] ) ) {
			return $item;
		}

		// Loop through meta data to find wpf-settings-woo.
		foreach ( $item['meta_data'] as $index => $meta_data ) {
			if ( 'wpf-settings-woo' !== $meta_data->key ) {
				continue;
			}

			$value = (array) $meta_data->value;
			$is_variation = 'variation' === $item['type'];

			// Add WP Fusion data as separate meta fields.
			foreach ( $value as $param_key => $param_value ) {
				if ( $is_variation ) {
					// If variation, and key doesn't contain _variation, skip.
					// WPF saves variation data with _variation suffix.
					if ( strpos( $param_key, '_variation' ) === false ) {
						continue;
					}

					// If variation, remove _variation suffix from key.
					// We're assigning variation data to the same key for
					// both product and variation, for consistency.
					$param_key = str_replace( '_variation', '', $param_key );

					// Variations data is saved as a sub-array to the variation
					// ID, due to legacy code.
					$param_value = $param_value[ $item['id'] ];
				}

				// Convert the value to an array and get the array values
				$array = array_values( (array) $param_value );

				// Sort the array
				sort( $array );

				// Reset keys so app sees it as an array, not an object.
				$item[ 'wp_fusion_' . $param_key ] = $array;
			}
		}

		return $item;
	}

	/**
	 * Pre insert product object.
	 *
	 * @param \WC_Product $product  Product.
	 * @param array       $request  Request.
	 * @param bool        $creating Creating.
	 *
	 * @return void
	 */
	public function pre_insert_product_object( $product, $request, $creating ) {
		$wpf_data_changed = false;
		$wpf_data         = (array) $product->get_meta( 'wpf-settings-woo', true );
		$is_variation     = $product->is_type( 'variation' );
		$product_id       = $product->get_id();

		// loop keys and check if present in request.
		foreach ( $this->meta_keys as $key => $value ) {
			if ( ! isset( $request[ $value['data'] ] ) ) {
				continue;
			}

			$wpf_key = $this->get_meta_key( $key, $is_variation );

			// Variation data is saved differently.
			$wpf_data[ $wpf_key ] = $is_variation ? array( $product_id => $request[ $value['data'] ] ) : $request[ $value['data'] ];
			$wpf_data_changed     = true;
		}

		if ( $wpf_data_changed ) {
			$product->update_meta_data( 'wpf-settings-woo', $wpf_data );
		}
	}
}

new Compat_WP_Fusion();