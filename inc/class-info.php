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
 * Used to retrive additional information from site.
 */
class Info extends \WC_REST_Controller {
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
	protected $rest_base = 'info';

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
	 * @param  \WP_REST_Request $request Full details about the request.
	 * @return \WP_Error|boolean
	 */
	public function get_info_permissions_check( $request ) {
		if ( ! wc_rest_check_post_permissions( 'product', 'batch' ) ) {
			return new \WP_Error( 'woocommerce_rest_cannot_view', __( 'Sorry, you cannot read info.', 'setary' ), [ 'status' => rest_authorization_required_code() ] );
		}

		return true;
	}

	/**
	 * Returns info about site and installed plugin.
	 *
	 * @return array
	 */
	public function get_info() {
		return [
			'success'  => true,
			'siteName' => get_bloginfo( 'name' ),
			'version'  => SETARY_VERSION,
		];
	}
}
