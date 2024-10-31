<?php
/**
 * Compatibility class for LifterLMS.
 *
 * @package Setary
 */

namespace Setary;

/**
 * Compatibility class for LifterLMS.
 */
class Compat_LifterLMS {
	/**
	 * Meta keys.
	 */
	protected $meta_keys = [];

	/**
	 * Construct.
	 */
	function __construct() {
		if ( ! class_exists( 'LifterLMS' ) ) {
			return;
		}

		$this->set_keys();

		add_filter( 'setary_get_store_info', array( $this, 'get_store_info' ) );
	}

	/**
	 * Set WP Fusion keys.
	 *
	 * @return void
	 */
	protected function set_keys() {
		$memberships = $this->get_memberships();

		$this->meta_keys = [
			'_llms_membership_id'      => [
				'title'     => __( 'LifterLMS Members Only' ),
				'source'    => array_keys( $memberships ),
				'sourceMap' => $memberships,
				'type'      => 'dropdown',
				'data'      => '_llms_membership_id',
				'noFilter'  => true,
				'filter'    => false,
			],
			'_llms_membership_btn_txt' => [
				'title'    => __( 'LifterLMS Members Button Text' ),
				'type'     => 'text',
				'data'     => '_llms_membership_btn_txt',
				'noFilter' => true,
			],
		];
	}

	/**
	 * Add WP Fusion info to store info.
	 *
	 * @param array $info
	 *
	 * @return array
	 */
	public function get_store_info( $info = array() ) {
		// Loop through the meta array and remove all llms meta keys.
		foreach ( $info['meta'] as $key => $item ) {
			if ( 0 === strpos( $item, '_llms' ) ) {
				unset( $info['meta'][ $key ] );
			}
		}

		// Loop keys and add the keys to the meta array.
		foreach ( $this->meta_keys as $value ) {
			$info['meta'][] = $value;
		}

		return $info;
	}

	/**
	 * Retrieve a list of memberships and build an array used in product meta select boxes
	 *
	 * @return   array
	 * @since    2.0.0
	 * @version  2.0.0
	 */
	public function get_memberships() {
		$query = new \WP_Query(
			array(
				'order'          => 'ASC',
				'orderby'        => 'title',
				'post_status'    => 'publish',
				'post_type'      => 'llms_membership',
				'posts_per_page' => - 1,
			)
		);

		$options = array(
			'' => __( 'Available to all customers', 'lifterlms-woocommerce' ),
		);

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$options[ $post->ID ] = $post->post_title . ' (ID# ' . $post->ID . ')';
			}
		}

		return $options;
	}
}

new Compat_LifterLMS();