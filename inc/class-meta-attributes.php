<?php
/**
 * Custom WooCommerce endpoint with common data,
 * it will be used to check the version of the plugin
 * and check credentials and check that plugin activated.
 *
 * @package Setary
 */

namespace Setary;

use WP_REST_Server;

/**
 * Filter out core WooCommerce meta keys
 *
 * @package Setary
 */
class Setary_Product_Data_Store_CPT extends \WC_Product_Data_Store_CPT {
	public function filter_raw_meta_keys( $raw_meta_data ) {
		$meta_data = array_filter( $raw_meta_data, array( $this, 'exclude_internal_meta_keys' ) );

		if( yoast_plugin_active() ) {
			$yoast_global_identifier_types = [
				'gtin8',
				'gtin12',
				'gtin13',
				'gtin14',
				'mpn',
			];

			$yoast_global_identifier_types = array_map( fn($key) => '_yoast_seo_global_identifier_' . $key, $yoast_global_identifier_types );

			$meta_data = array_merge( $meta_data, $yoast_global_identifier_types );
		}

		$meta_data = array_values( $meta_data );

		if( in_array( 'product_type', $meta_data, true ) ) {
			$meta_data = array_filter( $meta_data, fn($key) => $key !== 'product_type' );
			$meta_data[] = '___product_type';
		}

		return $meta_data;
	}

	protected function exclude_internal_meta_keys( $meta ) {
		return ! in_array( $meta, $this->internal_meta_keys, true );
	}
}

/**
 * Used to retrive additional information from site.
 */
class MetaAttributes extends \WC_REST_Controller {
	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc/setary';
	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'meta_attributes_list';

	/**
	 * Register the routes for info.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_info' ],
					'permission_callback' => [ $this, 'get_info_permissions_check' ],
				],
			]
		);
	}

	/**
	 * Check if a given request has access to read info.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|boolean
	 */
	public function get_info_permissions_check( $request ) {
		if ( ! wc_rest_check_post_permissions( 'product', 'batch' ) ) {
			return new \WP_Error( 'woocommerce_rest_cannot_view', __( 'Sorry, you cannot read info.', 'setary' ), [ 'status' => rest_authorization_required_code() ] );
		}

		return true;
	}

	/**
	 * Returns list of available custom meta keys.
	 *
	 * @return array
	 */
	public function get_info() {
		global $wpdb;

		$results = $wpdb->get_col(
			"SELECT DISTINCT(pm.meta_key) as meta_key FROM {$wpdb->prefix}postmeta pm JOIN {$wpdb->prefix}posts p ON pm.post_id = p.ID AND p.post_type IN ('product','product_variation') AND pm.meta_value NOT LIKE 'field_%' AND pm.meta_key NOT LIKE 'attribute_%' ORDER BY pm.meta_key ASC"
		);

		$data_store = new Setary_Product_Data_Store_CPT();

		$store_info = apply_filters( 'setary_get_store_info', [
			'success'    => true,
			'meta'       => $data_store->filter_raw_meta_keys( $results ),
			'attributes' => $this->get_all_woocommerce_attribute_names_and_labels(),
			'types'      => $this->get_product_types(),
		] );

		// Make array keys sequential for easier parsing on the client.
		$store_info['meta'] = array_values( $store_info['meta'] );

		return $store_info;
	}

	/**
	 * Retrieves a unique array of all available attributes for WooCommerce products.
	 *
	 * This function fetches both product-specific attributes from the post_meta table
	 * and global attributes from the wp_woocommerce_attribute_taxonomies table.
	 *
	 * @return array An array of unique attribute names.
	 */
	function get_all_woocommerce_attribute_names_and_labels() {
		global $wpdb;

		// Check if the attributes are already cached.
		// $all_attributes = wp_cache_get( 'all_woocommerce_attributes', 'custom' );
		// if ( $all_attributes !== false ) {
		// 	return $all_attributes;
		// }

		// Fetch product-specific attributes from the post_meta table.
		$product_attributes_query   = "
		SELECT DISTINCT meta_value
		FROM {$wpdb->postmeta}
		WHERE meta_key = '_product_attributes'
		";
		$product_attributes_results = $wpdb->get_col( $product_attributes_query );

		// wp_send_json($product_attributes_results);

		// Extract unique attribute names and labels from the product-specific attributes.
		$product_attributes = array();

		foreach ( $product_attributes_results as $serialized_attributes ) {
			$attributes = unserialize( $serialized_attributes );
			
			if ( is_array( $attributes ) ) {
				foreach ( $attributes as $attribute ) {
					if ( isset( $attribute['name'] ) && isset( $attribute['is_taxonomy'] ) && ! $attribute['is_taxonomy'] ) {
						$sanitized_name = 'la_' . sanitize_title( $attribute['name'] );
						
						if(! isset($product_attributes[ $sanitized_name ]) ) {
							$product_attributes[ $sanitized_name ] = array(
								'name'  => $sanitized_name,
								'label' => $attribute['name'],
								'values' => array()
							);
						}

						$values = $product_attributes[ $sanitized_name ]['values'];

						$values = array_merge(
							$values,
							array_map('trim', explode('|', $attribute['value']))
						);

						$values = array_filter($values, fn($value) => !is_null($value) && $value !== '');
						$values = array_unique($values);
						$product_attributes[ $sanitized_name ]['values'] = array_values($values);
					}
				}
			}
		}

		// Fetch global attributes from the wp_woocommerce_attribute_taxonomies table.
		$global_attributes_query = "
		SELECT CONCAT('pa_', attribute_name) AS name, attribute_label AS label
		FROM {$wpdb->prefix}woocommerce_attribute_taxonomies
		";
		$global_attributes       = $wpdb->get_results( $global_attributes_query, ARRAY_A );

		// Convert global attributes to an associative array with attribute names as keys.
		$global_attributes_assoc = array();

		foreach ( $global_attributes as $attribute ) {
			$global_attributes_assoc[ $attribute['name'] ] = $attribute;

			$terms = get_terms( array(
				'taxonomy'   => $attribute['name'],
				'hide_empty' => false,
			) );

			$values = array_map(function($term) {return $term->name;}, $terms);

			$global_attributes_assoc[ $attribute['name'] ]['values'] = array_values($values);
		}

		// Merge product-specific and global attributes into a single array.
		$all_attributes = array_merge( $product_attributes, $global_attributes_assoc );
		$all_attributes = array_values($all_attributes);

		// Cache the result.
		// wp_cache_set( 'all_woocommerce_attributes', $all_attributes, 'custom' );

		// Return the result.
		return $all_attributes;
	}

	/**
	 * Retrieves a unique array of all available product types for WooCommerce products.
	 *
	 * @return array An array of unique product types.
	 */
	function get_product_types() {
		$types = wc_get_product_types();

		$types['variation'] = __( 'Variation', 'setary' );

		// Loop all types and remove " product" or " Product" from the end.
		foreach ( $types as $key => $type ) {
			$types[ $key ] = preg_replace( '/\s+product$/i', '', $type );
		}

		return $types;
	}
}
