<?php
/**
 * Custom WooCommerce endpoint for products and variations in one list.
 *
 * @package Setary
 */

// phpcs:disable Squiz.PHP.CommentedOutCode.Found,Squiz.Commenting.FunctionComment.MissingParamComment

namespace Setary;

/**
 * Custom REST Api method for products and variations in one request.
 *
 * @package Setary
 */
class Products_Variations extends \WC_REST_Product_Variations_Controller {
	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc/setary';

	protected $rest_base = 'product-variations/(?P<product_id>[\d]+)/variations';

	/**
	 * Prepare a single product for create or update.
	 *
	 * @param  WP_REST_Request $request Request object.
	 * @param  bool            $creating If is creating a new object.
	 * @return WP_Error|WC_Data
	 */
	protected function prepare_object_for_database( $request, $creating = false ) {
		$meta_data = $request->get_param('meta_data');
		$meta_data_updated = false;

		$meta_to_save = [];

		foreach( $meta_data as $index => $meta ) {
			$key = $meta['key'];
			$value = $meta['value'];
			
			if( strpos( $key, '___' ) === 0 ) {
				$meta_key = substr( $key, 3 );
				$meta_to_save[ $meta_key ] = $value;
				unset( $meta_data[ $index ] );
				$meta_data_updated = true;
			}

			if( yoast_plugin_active() )  {
				if( strpos($key, '_yoast_seo_global_identifier_') !== 0 ) {
					continue;
				}

				$meta_to_save[ $key ] = $value;
				unset( $meta_data[ $index ] );
				$meta_data_updated = true;
			}
		}

		if( $meta_data_updated ) {
			$request->set_param( 'meta_data', $meta_data );
		}

		$product = parent::prepare_object_for_database( $request, $creating );

		foreach( $meta_to_save as $meta_key => $meta_value ) {
			if( yoast_plugin_active() )  {
				$is_variation = $product->is_type( 'variation' );

				$meta_key_yoast = ! $is_variation ? 'wpseo_global_identifier_values' : 'wpseo_variation_global_identifiers_values';

				$global_identifier_values = get_post_meta( $product->get_id(), $meta_key_yoast, true );

				if( strpos($meta_key, '_yoast_seo_global_identifier_') === 0 ) {
					$indentifier_key = str_replace( '_yoast_seo_global_identifier_', '', $key );

					if( ! is_array( $global_identifier_values ) ) {
						$global_identifier_values = [];
					}
	
					$global_identifier_values[$indentifier_key] = $value;
					
					update_post_meta( $product->get_id(), $meta_key_yoast, $global_identifier_values );
	
					continue;	
				}
			}

			update_post_meta( $product->get_id(), $meta_key, $meta_value );
		}

		return $product;
	}
}
