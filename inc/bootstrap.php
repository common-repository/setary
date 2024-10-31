<?php
/**
 * Registers root actions and features.
 *
 * @package Setary
 */

namespace Setary;

add_action( 'before_woocommerce_init', __NAMESPACE__ . '\\declare_hpos_compatibility' );
add_action( 'init', __NAMESPACE__ . '\\init' );

/**
 * Initialize all actions.
 *
 * @return void
 */
function init() {
	add_filter( 'woocommerce_rest_api_get_rest_namespaces', __NAMESPACE__ . '\\get_rest_namespaces' );

	$product_controller = new Product_Controller();

	add_filter( 'woocommerce_rest_pre_insert_product_object', [ $product_controller, 'pre_insert_product_object' ], 10, 3 );
	add_filter( 'woocommerce_rest_pre_insert_product_variation_object', [ $product_controller, 'pre_insert_product_object' ], 10, 3 );
	add_filter( 'rest_exposed_cors_headers', [ $product_controller, 'rest_exposed_cors_headers' ] );
}

function yoast_plugin_active() {
	return class_exists( '\Yoast_WooCommerce_SEO' );
}

/**
 * Delcare HPOS compatibility.
 *
 * @return void
 */
function declare_hpos_compatibility() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SETARY__FILE__, true );
	}
}

/**
 * Add custom namespaces to WC Rest API.
 *
 * @param array $namespaces WooCommerce available endpoint classes.
 * @return array
 */
function get_rest_namespaces( $namespaces ) {
	$namespaces['wc/setary'] = [
		// https://setary.wp.loc/wp-json/wc/setary/products/.
		'products'        => __NAMESPACE__ . '\\Products_With_Variations',
		'product-variations' => __NAMESPACE__ . '\\Products_Variations',
		'upload_image'    => __NAMESPACE__ . '\\Upload_Image',
		'media_list'      => __NAMESPACE__ . '\\Media_List',
		'meta_attributes' => __NAMESPACE__ . '\\MetaAttributes',
		'product_tools'   => __NAMESPACE__ . '\\ProductTools',
		'batch'           => __NAMESPACE__ . '\\Batch',
		// https://setary.wp.loc/wp-json/wc/setary/info/.
		'info'            => __NAMESPACE__ . '\\Info',
	];

	return $namespaces;
}
