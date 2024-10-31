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
 * Finds products by field and dispatch updating by id and faster update by sku and id.
 */
class ProductTools extends \WC_REST_Controller {
	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc/setary';

	/**
	 * Register the routes for info.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/save_by_field',
			[
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'save_by_field' ],
					'permission_callback' => [ $this, 'save_by_field_permissions_check' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/product',
			[
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'product' ],
					'permission_callback' => [ $this, 'save_by_field_permissions_check' ],
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
	public function save_by_field_permissions_check( $request ) {
		if ( ! wc_rest_check_post_permissions( 'product', 'batch' ) ) {
			return new \WP_Error( 'woocommerce_rest_cannot_view', __( 'Sorry, you cannot read info.', 'setary' ), [ 'status' => rest_authorization_required_code() ] );
		}

		return true;
	}

	private $relations_between_inserted_and_existing = [];

	public function product( $request ) {
		$wp_rest_server = rest_get_server();

		$request->set_route('/wc/setary/products');

		$new_id = ! empty($request['id']) && strpos($request['id'], 'new') === 0 ? $request['id'] : false;
		$parent_to_new_id = ! empty($request['parent_id']) && strpos($request['parent_id'], 'new') === 0 ? $request['parent_id'] : false;

		if( $new_id ) {
			$request->offsetSet('isNew', true);
			$request->offsetUnset('id');
		}

		if( $parent_to_new_id ) {
			$request->offsetUnset('parent_id');

			if( ! empty($this->relations_between_inserted_and_existing[$parent_to_new_id]) ) {
				$request->set_param('parent_id', $this->relations_between_inserted_and_existing[$parent_to_new_id]);
			}
		}

		if( ! empty($request['parent_id']) ) {
			$request->set_route('/wc/setary/product-variations/' . $request['parent_id'] . '/variations');
			$request->offsetUnset('parent_id');
		}

		$request->set_method('POST');

		if ( ! empty( $request['id'] ) ) {
			$product = wc_get_product( $request['id'] );
			
			if( $product ) {
				if( $product->is_type('variation') ) {
					$request->set_param('id', $product->get_id());
					$prefix = '/wc/setary/product-variations/' . $product->get_parent_id() . '/variations/' . $product->get_id();
					$request->set_route($prefix);
				} else {
					$prefix = '/wc/setary/products/' . $product->get_id();
					$request->set_route($prefix);
					$request->set_method('PUT');
				}
			}
		} else if ( ! empty( $request['sku'] ) && ! empty( $request['isNew'] ) ) {
			$id = wc_get_product_id_by_sku( $request->get_param('sku') );

			if( $id ) {
				$product = wc_get_product( $id );

				if( $product->is_type('variation') ) {
					$request->set_route('/wc/setary/product-variations/' . $product->get_parent_id() . '/variations/' . $product->get_id());
					$request->offsetUnset('id');
					$request->offsetUnset('parent_id');
				} else {
					$request->set_route('/wc/setary/products/' . $id);
					$request->offsetUnset('id');
					$request->offsetUnset('parent_id');
				}

				$request->offsetUnset('sku');
				$request->set_method('PUT');
			}
		}

		$result = $wp_rest_server->dispatch( $request );

		if( $new_id ) {
			$this->relations_between_inserted_and_existing[$new_id] = $result->data['id'];
		}

		return $result;
	}

	/**
	 * Find the products by field and route to updater.
	 *
	 * @return array
	 */
	public function save_by_field( $request ) {
		$wp_rest_server = rest_get_server();

		$results = [];

		$route = $request->get_param('route');
		$request->set_route('/wc/' . $route);
		$request->offsetUnset('route');

		$matching_field = $request->get_param('matchingField');
		
		if( $matching_field === 'sku' ) {
			$id = wc_get_product_id_by_sku( $request->get_param('matchingValue') );
			$request->offsetUnset('sku');

			if( $id > 0 ) {
				$r = clone $request;
				$r->set_param('id', $id);
				$r->set_route('/wc/' . $route . '/' . $id);
				$r->set_method('PUT');

				$results[] = $wp_rest_server->dispatch( $r );
			}	
		} else if( $matching_field === 'name' ) {
			$name = $request->get_param('matchingValue');

			if( !empty( $name ) ) {
				$query = new \WP_Query([
					'posts_per_page' => -1,
					'post_type' => ['product', 'product_variation'],
					'title' => $name,
					'fields' => 'ids',
				]);
				foreach( $query->get_posts() as $id ) {
					$r = clone $request;
					$r->set_param('id', $id);
					$results[] =  $wp_rest_server->dispatch( $r );
				}
			}
		} else if( $matching_field === 'slug' ) {
			$slug = $request->get_param('matchingValue');

			if( !empty( $slug ) ) {			
				$query = new \WP_Query([
					'posts_per_page' => -1,
					'post_type' => ['product', 'product_variation'],
					'name' => $slug,
					'fields' => 'ids'
				]);

				foreach( $query->get_posts() as $id ) {
					$r = clone $request;

					$r->set_param('id', $id);

					$results[] =  $wp_rest_server->dispatch( $r );
				}
			}
		}

		return count($results) === 1 ? $results[0] : $results;
	}
}
