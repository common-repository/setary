<?php
/**
 * Custom WooCommerce endpoint for media list.
 *
 * @package Setary
 */

// phpcs:disable Squiz.PHP.CommentedOutCode.Found,Squiz.Commenting.FunctionComment.MissingParamComment,Generic.Metrics.CyclomaticComplexity.TooHigh

namespace Setary;

use WP_REST_Post_Meta_Fields;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Custom REST Api method for media lsit.
 *
 * @package Setary
 */
class Media_List extends \WP_REST_Attachments_Controller {
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
	protected $rest_base = 'media_list';

	/**
	 * Post type to proxy.
	 *
	 * @var string
	 */
	protected $post_type = 'attachment';

	/**
	 * Initialize meta.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->meta = new WP_REST_Post_Meta_Fields( $this->post_type );
	}

	/**
	 * Register the routes for products with va.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
					'args'                => $this->get_collection_params(),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);
	}
}
