<?php
/**
 * Custom WooCommerce endpoint for media uploading.
 *
 * @package Setary
 */

// phpcs:disable Squiz.PHP.CommentedOutCode.Found,Squiz.Commenting.FunctionComment.MissingParamComment,Generic.Metrics.CyclomaticComplexity.TooHigh

namespace Setary;

use WC_Data;
use WC_Product;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Custom REST Api method for uploading.
 *
 * @package Setary
 */
class Upload_Image extends \WC_REST_Products_Controller {
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
	protected $rest_base = 'upload_image';

	/**
	 * Register the routes for products with va.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'upload_image' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
				],
			]
		);
	}

	/**
	 * Upload image.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function upload_image( $request ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded -- WordPress code
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		$attachment_id = \media_handle_upload(
			'file',
			$request->get_param( 'productId' ),
			[
				'post_title' => $request->get_param( 'name' ),
			]
		);

		$attachment_post = get_post( $attachment_id );
		if ( is_null( $attachment_post ) ) {
			return false;
		}

		$attachment = wp_get_attachment_image_src( $attachment_id, 'full' );
		if ( ! is_array( $attachment ) ) {
			return false;
		}

		$image = [
			'id'                => (int) $attachment_id,
			'date_created'      => wc_rest_prepare_date_response( $attachment_post->post_date, false ),
			'date_created_gmt'  => wc_rest_prepare_date_response( strtotime( $attachment_post->post_date_gmt ) ),
			'date_modified'     => wc_rest_prepare_date_response( $attachment_post->post_modified, false ),
			'date_modified_gmt' => wc_rest_prepare_date_response( strtotime( $attachment_post->post_modified_gmt ) ),
			'src'               => current( $attachment ),
			'name'              => get_the_title( $attachment_id ),
			'alt'               => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
		];

		return $image;
	}
}
