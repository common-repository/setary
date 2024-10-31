<?php
/**
 * Custom WooCommerce endpoint to support batch requests
 * @package Setary
 */

namespace Setary;

use WP_REST_Server;

/**
 * Finds products by field and dispatch updating by id
 */
class Batch extends \WC_REST_Controller {
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
	protected $rest_base = 'batch';

	/**
	 * Register the routes for info.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'batch' ],
					'permission_callback' => [ $this, 'batch_permissions_check' ],
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
	public function batch_permissions_check( $request ) {
		if ( ! wc_rest_check_post_permissions( 'product', 'batch' ) ) {
			return new \WP_Error( 'woocommerce_rest_cannot_view', __( 'Sorry, you cannot read info.', 'setary' ), [ 'status' => rest_authorization_required_code() ] );
		}

		return true;
	}

	/**
	 * Find the products by field and route to updater.
	 *
	 * @return array
	 */
	public function batch( $request ) {
		$wp_rest_server = rest_get_server();

		$results = [];

		foreach($request->get_json_params() as $action) {
			$r = clone $request;

			$r->set_route('/wc/' . $action['endpoint']);
			$r->set_method(strtoupper($action['method']));
			$r->set_body_params($action['data']);

			$results[] = $wp_rest_server->dispatch( $r );
		}

		return $results;
	}
}
