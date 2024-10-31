<?php
/**
 * Utility functions and helpers.
 *
 * @package Setary
 */

namespace Setary;

/**
 * Utility functions and helpers.
 */
class Utils {

	/**
	 * Get type directly from the term.
	 *
	 * @param int $product_id
	 *
	 * @return string
	 */
	public static function get_product_type( $product_id ) {
		// Default new products with zero id.
		if ( ! $product_id ) {
			return 'simple';
		}

		$post_type = get_post_type( $product_id );

		if ( 'product' !== $post_type ) {
			$product = wc_get_product( $product_id );

			return $product->get_type();
		}

		$terms = wp_get_object_terms( $product_id, 'product_type' );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return 'simple';
		}

		return $terms[0]->slug;
	}

	/**
	 * Log helper.
	 *
	 * Use with tail -f ./wp-content/debug.log
	 *
	 * @param array | string | int $log
	 */
	public static function log( $log ) {

		if ( is_array( $log ) || is_object( $log ) ) {
			error_log( print_r( $log, true ) );
		} else {
			error_log( $log );
		}

	}

}
