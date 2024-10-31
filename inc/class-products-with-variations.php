<?php
/**
 * Custom WooCommerce endpoint for products and variations in one list.
 *
 * @package Setary
 */

// phpcs:disable Squiz.PHP.CommentedOutCode.Found,Squiz.Commenting.FunctionComment.MissingParamComment

namespace Setary;

use Setary\Utils;
use WC_Data;
use WC_Product;
use WP_REST_Request;
use WP_REST_Server;


/**
 * Helper internal class to work with WHERE SQL to use as closures
 * 
 * @package Setary
 */
class FilterByBetween {
	public function __construct($query, $data) {
		$this->query = $query;
		$this->data = $data;
	}


	public function filter($where = '') {
		global $wpdb;

		$where .= call_user_func_array([ $wpdb, 'prepare' ], array_merge( [$this->query], $this->data ));
		
		return $where;
	}
}

/**
 * Custom REST Api method for products and variations in one request.
 *
 * @package Setary
 */
class Products_With_Variations extends \WC_REST_Products_Controller {
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
	protected $rest_base = 'products';

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

		parent::register_routes();
	}

	/**
	 * Add post_meta table to query if we're sorting by meta.
	 *
	 * @param string   $join
	 * @param WP_Query $query
	 *
	 * @return string
	 */
	public function edit_posts_join( $join, $query ) {
		$sort_option = $this->get_sort_option();

		// No need to add post_meta if the sort is a post table method.
		if ( ! $sort_option || $sort_option['post_table_orderby'] ) {
			return $join;
		}

		// Check if this is the query you want to modify
		global $wpdb;

		// Join the postmeta table with the main query
		$join .= " LEFT JOIN {$wpdb->postmeta} ON {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id AND {$wpdb->postmeta}.meta_key = '_sku'";

		return $join;
	}

	/**
	 * Add special order query to order variations together with products.
	 *
	 * @param string   $orderby_statement
	 * @param WP_Query $query
	 *
	 * @return string
	 */
	public function edit_posts_orderby( $orderby_statement, $query ) {
		// First, sort by the post_parent if it exists, otherwise,
		// sort by the id. This will group the parent product with its variations.
		$orderby_statement = 'IF(post_parent = 0, id, post_parent) ASC,';

		// Next, sort by a custom value (0 for parents, 1 for variations).
		// This will ensure the parent product always appears first in the group.
		$orderby_statement .= 'IF(post_parent = 0, 0, 1) ASC,';

		// Finally, sort by the id in ascending order for the remaining sorting.
		$orderby_statement .= 'id ASC';

		$sort_option = $this->get_sort_option();

		if ( ! $sort_option ) {
			return $orderby_statement;
		}

		global $wpdb;

		if ( $sort_option['post_table_orderby'] ) {
			$orderby_statement = "{$sort_option['post_table_orderby']} {$sort_option['order']}";
		} else {
			$orderby_statement = $wpdb->prepare(
				"CASE WHEN {$wpdb->postmeta}.meta_key = %s THEN {$wpdb->postmeta}.meta_value ELSE {$wpdb->posts}.ID END {$sort_option['order']}",
				$sort_option['orderby']
			);
		}

		return $orderby_statement;
	}

	/**
	 * Get sort option from filters.
	 *
	 * @return array|false
	 */
	public function get_sort_option() {
		static $sort_option = null;

		if ( ! is_null( $sort_option ) ) {
			return $sort_option;
		}

		$sort_option = false;

		if ( empty( $this->filters ) ) {
			return $sort_option;
		}

		$post_table_orderby_map = array(
			'id'        => 'id',
			'parent_id' => 'post_parent',
			'name'      => 'post_title',
		);

		foreach ( $this->filters as $filter ) {
			if ( empty( $filter['sortOption'] ) ) {
				continue;
			}

			$orderby            = isset( $filter['filterData'] ) ? $filter['filterData'] : $filter['data'];
			$post_table_orderby = isset( $post_table_orderby_map[ $orderby ] ) ? $post_table_orderby_map[ $orderby ] : false;

			$sort_option = array(
				'order'              => $filter['sortOption'],
				'orderby'            => $orderby,
				'post_table_orderby' => $post_table_orderby,
			);

			break;
		}

		return $sort_option;
	}

	
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

	/**
	 * Remove extra fields, it reduces execution time (-80%).
	 *
	 * @param \WC_Product $product
	 * @param string      $context
	 * @return array
	 */
	protected function get_product_data( $product, $context = 'view', $requestOriginal  = false ) {
		// We want the 'edit' versions of each field.
		$context = 'edit';

		$request = new \WP_REST_Request( '', '', [ 'context' => $context ] );

		// Enable fields in response to increase speed of request and reduce response size.
		$params = $this->get_core_fields_from_request();

		$request->set_param( '_fields', $params );

		$this->filter_variation_data();

		/**
		 * Prevent shuffling of related products.
		 */
		add_filter( 'woocommerce_product_related_posts_shuffle', '__return_false' );

		$item = parent::get_product_data( $product, $context, $request );
	
		if( $requestOriginal && is_object( $requestOriginal ) ) {
			$fields = $requestOriginal->get_param( 'fields' );

			foreach ( $fields as $field ) {
				if( strpos( $field, '___' ) === 0 ) {
					$meta_key = substr( $field, 3 );
					$value = get_post_meta( $item['id'], $meta_key, true );
					$item[$field] = $value;
				}
			}
		}

		return $item;
	}

	/**
	 * Get core fields from the API request.
	 *
	 * @return string[]
	 */
	protected function get_core_fields_from_request() {
		$params = [
			0  => 'id',
			1  => 'name',
			2  => 'slug',
			3  => 'permalink',
			4 => 'date_created',
			5 => 'date_created_gmt',
			6 => 'date_modified',
			7 => 'date_modified_gmt',
			8  => 'type',
			9  => 'status',
			10 => 'featured',
			11 => 'catalog_visibility',
			12 => 'description',
			13 => 'short_description',
			14 => 'sku',
			15 => 'price',
			16 => 'regular_price',
			17 => 'sale_price',
			18 => 'date_on_sale_from',
			19 => 'date_on_sale_from_gmt',
			20 => 'date_on_sale_to',
			21 => 'date_on_sale_to_gmt',
			22 => 'price_html',
			23 => 'on_sale',
			24 => 'purchasable',
			25 => 'total_sales',
			26 => 'virtual',
			27 => 'downloadable',
			28 => 'downloads',
			29 => 'download_limit',
			30 => 'download_expiry',
			31 => 'external_url',
			32 => 'button_text',
			33 => 'tax_status',
			34 => 'tax_class',
			35 => 'manage_stock',
			36 => 'stock_quantity',
			37 => 'stock_status',
			38 => 'backorders',
			39 => 'backorders_allowed',
			40 => 'backordered',
			41 => 'low_stock_amount',
			42 => 'sold_individually',
			43 => 'weight',
			44 => 'dimensions',
			45 => 'shipping_required',
			46 => 'shipping_taxable',
			47 => 'shipping_class',
			48 => 'shipping_class_id',
			49 => 'reviews_allowed',
			50 => 'average_rating',
			51 => 'rating_count',
			52 => 'related_ids',
			53 => 'upsell_ids',
			54 => 'cross_sell_ids',
			55 => 'parent_id',
			56 => 'purchase_note',
			57 => 'categories',
			58 => 'tags',
			59 => 'images',
			60 => 'attributes',
			61 => 'default_attributes',
			62 => 'variations',
			63 => 'grouped_products',
			64 => 'menu_order',
			65 => 'meta_data',
		];

		// Get the requested fields.
		$requested_fields = isset( $_GET['fields'] ) ? wp_unslash( $_GET['fields'] ) : array();

		if ( empty( $requested_fields ) ) {
			return $params;
		}

		$required_fields = [ 'id', 'parent_id', 'name', 'product_type' ];

		// Merge the required fields with the requested fields.
		$requested_fields = array_merge( $required_fields, $requested_fields );

		// Make it unique.
		$requested_fields = array_unique( $requested_fields );

		$cache_key             = 'setary_product_fields_' . md5( wp_json_encode( $requested_fields ) );
		$cached_matched_fields = wp_cache_get( $cache_key );

		if ( $cached_matched_fields ) {
			return $cached_matched_fields;
		}

		// Map requested fields to the correct WooCommerce field.
		$field_map = [
			'length' => 'dimensions',
			'width'  => 'dimensions',
			'height' => 'dimensions',
			'formatted_upsell_ids' => 'upsell_ids',
			'formatted_cross_sell_ids' => 'cross_sell_ids',
			'formatted_categories' => 'categories',
			'formatted_tags' => 'tags',
			'attribute_*' => 'attributes',
			'product_type' => 'type',
		];

		// Always get attributes so they show in the columns list.
		// @todo: See if we can get the attributes without this.
		$matched_fields = [
			60 => 'attributes',
		];

		// Create an array of fields that match the requested fields.
		foreach ( $requested_fields as $value ) {
			$value = isset( $field_map[ $value ] ) ? $field_map[ $value ] : $value;

			if ( in_array( $value, $params, true ) ) {
				$array_key                    = array_search( $value, $params, true );
				$matched_fields[ $array_key ] = $value;
			} else {
				// If it's not a core field, it must be meta data.
				$matched_fields[ 65 ] = 'meta_data';
			}
		}

		ksort( $matched_fields );

		wp_cache_set( $cache_key, $matched_fields );

		return ! empty( $matched_fields ) ? $matched_fields : $params;
	}

	/**
	 * Filter variation data to ensure we're not
	 * getting data belonging to the parent product.
	 */
	public function filter_variation_data() {
		$variation_data_keys = array(
			'image_id',
			'width',
			'height',
			'length',
			'weight',
			'shipping_class_id',
		);

		foreach ( $variation_data_keys as $key ) {
			add_filter( 'woocommerce_product_variation_get_' . $key, function( $value, $product ) use ( $key ) {
				$method_name = 'get_' . $key;

				if ( ! method_exists( $product, $method_name ) ) {
					return $value;
				}

				return call_user_func( array( $product, $method_name ), 'edit' );
			}, 10, 2 );
		}
	}

	/**
	 * Remove variations param, we do not need it, we have all items in list.
	 *
	 * @param array  $data
	 * @param string $context
	 * @return array
	 */

	public function filter_response_by_context( $data, $context ) {
		$item = parent::filter_response_by_context( $data, $context );

		unset( $item['variations'] );

		if ( isset( $item['categories'] ) ) {
			$item['formatted_categories'] = $this->format_categories( $item );
		}

		if ( isset( $item['tags'] ) ) {
			$item['formatted_tags'] = $this->format_tags( $item );
		}

		if ( isset( $item['images'] ) ) {
			$item['formatted_images'] = $this->format_images( $item );
		}

		if ( isset( $item['date_on_sale_from'] ) ) {
			$item['date_on_sale_from'] = $this->format_date( $item['date_on_sale_from'] );
		}

		if ( isset( $item['date_on_sale_to'] ) ) {
			$item['date_on_sale_to'] = $this->format_date( $item['date_on_sale_to'] );
		}

		if ( isset( $item['upsell_ids'] ) ) {
			$item['formatted_upsell_ids'] = $this->format_ids( $item['upsell_ids'] );
		}

		if ( isset( $item['cross_sell_ids'] ) ) {
			$item['formatted_cross_sell_ids'] = $this->format_ids( $item['cross_sell_ids'] );
		}

		if ( isset( $item['dimensions'] ) ) {
			$item['width']  = $this->get_dimension( $item, 'width' );
			$item['height'] = $this->get_dimension( $item, 'height' );
			$item['length'] = $this->get_dimension( $item, 'length' );
		}

		$item['tax_class'] = empty( $item['tax_class'] ) ? 'standard' : $item['tax_class'];
		$item['product_type'] = Utils::get_product_type( $item['id'] );
		$item['type'] = $item['product_type'];

		if ( isset( $item['manage_stock'] ) ) {
			// If manage stock is "parent", then really it means "No".
			$item['manage_stock'] = $item['manage_stock'] && 'parent' !== $item['manage_stock'];
		}

		// Reset keys so that they are always sequential.
		if ( ! empty( $item['meta_data'] ) ) {
			$item['meta_data'] = array_values( $item['meta_data'] ); // Make a sequential array for the response.
		}

		if( yoast_plugin_active() ) {
			$is_variation = $item['product_type'] === 'variation';

			$meta_key = ! $is_variation ? 'wpseo_global_identifier_values' : 'wpseo_variation_global_identifiers_values';

			$variation_global_ids  = get_post_meta( $item['id'], $meta_key, true );

			if( is_array($variation_global_ids) ) {
				foreach( $variation_global_ids as $key => $value ) {
					$item[ '_yoast_seo_global_identifier_' . $key ] = $value;
				}
			}
		}

		return apply_filters( 'setary_filter_response_by_context', $item, $data, $context );
	}

	/**
	 * Remove links, it increases performance(-40%).
	 *
	 * @param WC_Data         $object
	 * @param WP_REST_Request $request
	 * @return array
	 */
	protected function prepare_links( $object, $request ) {
		// Remove links from response.
		return [];
	}

	/**
	 * Get a collection of posts.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded -- WordPress code
		global $wpdb;

		$this->filters = json_decode( $request->get_param('filters'), true );

		$query_args              = $this->prepare_objects_query( $request );
		$query_args['post_type'] = [ 'product', 'product_variation' ];

		foreach($this->filters as $filter) {
			if( ! $filter['active'] ) {
				continue;
			}

			if( empty( $filter['from'] ) ) {
				$filter['from'] = 0;
			}

			if( empty( $filter['to'] ) && ( isset( $filter['to'] ) && '0' !== $filter['to'] ) ) {
				$filter['to'] = $filter['from'];
			}

			$key = isset($filter['filterData']) && !empty($filter['filterData']) ? $filter['filterData'] : $filter['data'];
			$originalKey = $key;

			if( strpos( $key, '___' ) === 0 ) {
				$key = substr( $key, 3 );
			}

			if( ! empty( $filter['taxonomy'] ) && 'query' === $filter['mode'] ) {
				$terms = get_terms( $filter['taxonomy'], [ 'search' => $filter['query'], 'fields' => 'ids'  ] );
				
				$query_args['tax_query'] = $this->add_tax_query( // WPCS: slow query ok.
					$query_args,
					array(
						'taxonomy' => $filter['taxonomy'],
						'field' => 'term_id',
						'operator' => 'IN',
						'terms'    => $terms,
					)
				);
				continue;
			}

			$numeric_post_fields = [
				'id' => 'ID',
				'parent_id' => 'post_parent',
				'menu_order' => 'menu_order',
			];

			// When searching by empty value, we need to use different query.
			// Continue after adding filter.
			if ( ! empty( $filter['mode'] ) && 'empty' === $filter['mode'] ) {
				if( 'name' === $key ) {
					add_filter( 'posts_where', [ new FilterByBetween(" AND ({$wpdb->prefix}posts.post_title IS NULL OR {$wpdb->prefix}posts.post_title = '')", array()), 'filter' ]);
				} else if( 'description' === $key ) {
					add_filter( 'posts_where', [ new FilterByBetween(" AND ({$wpdb->prefix}posts.post_content IS NULL OR {$wpdb->prefix}posts.post_content = '')", array()), 'filter' ]);
				} else if( 'short_description' === $key ) {
					add_filter( 'posts_where', [ new FilterByBetween(" AND ({$wpdb->prefix}posts.post_excerpt IS NULL OR {$wpdb->prefix}posts.post_excerpt = '')", array()), 'filter' ]);
				} else if ( array_key_exists( $key, $numeric_post_fields ) ) {
					$field = $numeric_post_fields[ $key ];
					add_filter( 'posts_where', [ new FilterByBetween(" AND (({$wpdb->prefix}posts.{$field} IS NULL OR {$wpdb->prefix}posts.{$field} = '') AND {$wpdb->prefix}posts.{$field} != 0)", array()), 'filter' ]);
				} else if( 'slug' === $key ) {
					add_filter( 'posts_where', [ new FilterByBetween(" AND ({$wpdb->prefix}posts.post_name IS NULL OR {$wpdb->prefix}posts.post_name = '')", array()), 'filter' ]);
				} else if( ! empty( $filter['taxonomy'] ) ) {
					$query_args['tax_query'] = $this->add_tax_query( // WPCS: slow query ok.
						$query_args,
						[
							'relation' => 'OR',
							[
								'taxonomy' => $filter['taxonomy'],
								'operator' => 'NOT EXISTS',
							],
							[
								'taxonomy' => $filter['taxonomy'],
								'field'    => 'slug',
								'operator' => 'IN',
								'terms'    => ['uncategorized'],
							]
						]
					);
				} else if( 0 === strpos($key, 'la_') ) {
				
				} else if( 0 === strpos($key, 'pa_') ) {
					$query_args['tax_query'] = $this->add_tax_query( // WPCS: slow query ok.
						$query_args,
						[
							'relation' => 'OR',
							[
								'taxonomy' => $key,
								'operator' => 'NOT EXISTS',
							],
							[
								'taxonomy' => $key,
								'field'    => 'slug',
								'operator' => 'IN',
								'terms'    => ['uncategorized'],
							]
						]
					);
				} else {
					$query_args['meta_query'] = $this->add_meta_query( // WPCS: slow query ok.
						$query_args,
						$this->get_empty_meta_query( $key )
					);
				}
				continue;
			}

			if( 0 === strpos($key, 'la_') ) {
				$operator = false;

				if('query' === $filter['mode']) {
					$operator = 'REGEXP';
				}

				if(!$operator) {
					continue;
				}

				$attribute_name = \sanitize_text_field(substr($key, 3));
				
				$sql = "SELECT pm.post_id FROM {$wpdb->prefix}postmeta pm WHERE (pm.meta_key IN('_product_attributes') AND pm.meta_value " . $operator . " %s) OR (pm.meta_key = %s AND pm.meta_value = %s);";
				$quoted_key   = preg_quote( $attribute_name, '/' );
        		$quoted_value = preg_quote( $filter['query'], '/' );
				$regexp = 's:[0-9]+:"' . $quoted_key . '";[a-z]:[0-9]+:\{[a-z]:[0-9]+:"name";[a-z]:[0-9]+:"([a-zA-Z0-9?!+*-_.:,;=&%$/()@<> ]+)";[a-z]:[0-9]+:"value";[a-z]:[0-9]+:"(' . $quoted_value . '[ ";]|([a-zA-Z0-9?!+*-_.:,;=&%$/()@<> ]+ \| )+(' . $quoted_value . '[ ";]))';
				$sql = $wpdb->prepare($sql, $regexp, 'attribute_'.$attribute_name, $filter['query']);
				
				$ids = $wpdb->get_col($sql);

				$query_args['post__in'] = array_values($ids);
				continue;
			} else if( 0 === strpos($key, 'pa_') ) {
				$query_args['tax_query'] = $this->add_tax_query( // WPCS: slow query ok.
					$query_args,
					array(
						'taxonomy'        => $key,
						'field'           => 'name',
						'terms'           =>  $filter['query'],
						'operator'        =>  'IN',
					)
				);
				
				continue;
			}

			// Otherwise, we can use standard query.
			if ( array_key_exists( $key, $numeric_post_fields ) ) {
				$field = $numeric_post_fields[ $key ];
				add_filter( 'posts_where', [ new FilterByBetween(" AND {$wpdb->prefix}posts.{$field} BETWEEN %d AND %d", array( floatval($filter['from']), floatval($filter['to']) )), 'filter' ]);
			} else if( 'product_type' === $key && $originalKey === $key ) {
				if( in_array( 'variation', $filter['query'], true ) ) {
					$query_args['post_type'] = [ 'product_variation' ];
				} else {
					$query_args['tax_query'] = $this->add_tax_query( // WPCS: slow query ok.
						$query_args,
						array(
							'taxonomy' => 'product_type',
							'field'    => 'slug',
							'terms'    => $filter['query'],
						)
					);
				}
			} else if( 'name' === $key ) {
				add_filter( 'posts_where', [ new FilterByBetween(" AND {$wpdb->prefix}posts.post_title LIKE %s", array( '%' . $filter['query'] . '%' )), 'filter' ]);
			} else if( 'description' === $key ) {
				add_filter( 'posts_where', [ new FilterByBetween(" AND {$wpdb->prefix}posts.post_content LIKE %s", array( '%' . $filter['query'] . '%' )), 'filter' ]);
			} else if( 'short_description' === $key ) {
				add_filter( 'posts_where', [ new FilterByBetween(" AND {$wpdb->prefix}posts.post_excerpt LIKE %s", array( '%' . $filter['query'] . '%' )), 'filter' ]);
			} else if( 'slug' === $key ) {
				add_filter( 'posts_where', [ new FilterByBetween(" AND {$wpdb->prefix}posts.post_name LIKE %s", array( '%' . $filter['query'] . '%' )), 'filter' ]);
			} else if( in_array( $key, [ 'backorders', 'manage_stock' ], true)  ) {
				// if( 'manage_stock' === $key && in_array('parent', $filter['query']) ) {
				// 	continue;
				// } else {
					$query_args['meta_query'] = $this->add_meta_query( // WPCS: slow query ok.
						$query_args,
						array(
							'key'     => '_' . $key,
							'value'   => $filter['query'],
							'compare' => 'IN',
						)
					);
				// }
			} else if( 'featured' === $key  ) {
				$query_args['tax_query'] = $this->add_tax_query( // WPCS: slow query ok.
					$query_args,
					array(
						'taxonomy' => 'product_visibility',
						'field' => 'slug',
						'operator' => in_array( 'no', $filter['query'], true ) ? 'NOT IN' : 'IN',
						'terms'    => ['featured'],
					)
				);
			} else if( in_array( $key, [ 'catalog_visibility' ], true)  ) {
				$terms = [];

				// public/wp/wp-content/plugins/woocommerce/includes/data-stores/class-wc-product-data-store-cpt.php:411

				if( ! in_array( 'visible', $filter['query'], true ) ) {
					if( in_array( 'search', $filter['query'], true ) ) {
						$terms['exclude-from-catalog'] = 'exclude-from-catalog';
					}
					if( in_array( 'catalog', $filter['query'], true ) ) {
						$terms['exclude-from-search'] = 'exclude-from-search';
					}
					if( in_array( 'hidden', $filter['query'], true ) ) {
						$terms['exclude-from-catalog'] = 'exclude-from-search';
						$terms['exclude-from-search'] = 'exclude-from-search';
					}

					$query_args['tax_query'] = $this->add_tax_query( // WPCS: slow query ok.
						$query_args,
						array(
							'taxonomy' => 'product_visibility',
							'field' => 'slug',
							'operator' => 'IN',
							'terms'    => $terms,
						)
					);
				} else if ( in_array( 'visible', $filter['query'], true ) ) {

					$terms['exclude-from-catalog'] = 'exclude-from-search';
					$terms['exclude-from-search'] = 'exclude-from-search';

					$query_args['tax_query'] = $this->add_tax_query( // WPCS: slow query ok.
						$query_args,
						array(
							'taxonomy' => 'product_visibility',
							'field' => 'slug',
							'operator' => 'NOT IN',
							'terms'    => $terms,
						)
					);
				}
			} else if( 'status' === $key ) {
				$query_args['post_status'] = $filter['query'];
			} else if( 'images' === $key ) {
				global $wpdb;

				$sql = trim("
					SELECT post_id from (
						SELECT pm.post_id, SUM((CHAR_LENGTH(meta_value) - CHAR_LENGTH(REPLACE(meta_value, ',', '')) + 1)) cou FROM {$wpdb->prefix}postmeta pm WHERE pm.meta_key IN('_product_image_gallery', '_thumbnail_id') GROUP BY pm.post_id  
					) p WHERE 1 %s;
				");

				if('one' === $filter['mode']) {
					$sql = sprintf($sql, ' AND cou = 1 ');
					$ids = $wpdb->get_col($sql);

					$query_args['post__in'] = $ids;
				} else if('many' === $filter['mode']) {
					$sql = sprintf($sql, ' AND cou > 1 ');
					$ids = $wpdb->get_col($sql);

					$query_args['post__in'] = $ids;
				} else if('no' === $filter['mode']) {
					$ids = $wpdb->get_col(sprintf($sql, ''));
					$query_args['post__not_in'] = $ids;
				} else if('query' === $filter['mode']) {
					$attachment_ids = get_posts([
						'post_type' => 'attachment',
						'posts_per_page' => -1,
						's' => $filter['query'],
						'fields' => 'ids'
					]);

					$sql = trim("
						SELECT post_id from (
							SELECT pm.post_id, GROUP_CONCAT(pm.meta_value) imgs FROM {$wpdb->prefix}postmeta pm WHERE pm.meta_key IN('_product_image_gallery', '_thumbnail_id') GROUP BY pm.post_id
						) p WHERE p.imgs REGEXP %s;
					");
					$match = [];
					foreach ($attachment_ids as $attachment_id) {
						$match[] = '((?:,|^)' . $attachment_id . '(?:,|$))';
					}

					$ids = $wpdb->get_col($wpdb->prepare($sql, implode('|', $match)));
					$query_args['post__in'] = $ids;
				}
			} else if( ! empty( $filter['query'] ) ) {
				$query_args['meta_query'] = $this->add_meta_query( // WPCS: slow query ok.
					$query_args,
					array(
						'key'     => $key,
						'value'   => $filter['query'],
						'compare' => is_array($filter['query']) ? 'IN' : 'LIKE',
					)
				);
			} else if ( $filter['type'] === 'numeric' ) {
				$query_args['meta_query'] = $this->add_meta_query( // WPCS: slow query ok.
					$query_args,
					array(
						'key'     => $key,
						'value'   => array( floatval( $filter['from'] ), floatval( $filter['to'] ) ),
						'compare' => 'BETWEEN',
					)
				);
			}

			// Special case for tax_class. `'standard` is actually an empty string or not set.
			if ( '_tax_class' === $key && in_array( 'standard', $filter['query'] ) ) {
				$query_args['meta_query'] = $this->add_meta_query( // WPCS: slow query ok.
					$query_args,
					$this->get_empty_meta_query( $key ),
					'OR'
				);
			}
		}
		
		if( empty($query_args['post__in']) ) {
			unset($query_args['post__in']);
		}

		if( empty($query_args['post_parent__in']) ) {
			unset($query_args['post_parent__in']);
		}

		// wp_send_json($query_args);

		if ( is_wp_error( current( $query_args ) ) ) {
			return current( $query_args );
		}

		if ( ! empty( $query_args['s'] ) ) {
		    $sku_query = "
		        OR {$wpdb->posts}.ID IN (
		            SELECT DISTINCT post_id
		            FROM {$wpdb->postmeta}
		            WHERE meta_key = '_sku' AND meta_value LIKE %s
		        )
		        AND {$wpdb->posts}.post_type IN ('product', 'product_variation')
		        AND {$wpdb->posts}.post_status NOT IN ('trash', 'auto-draft')
		    ";

		    $search_term = '%' . $wpdb->esc_like( $query_args['s'] ) . '%';
		    add_filter('posts_where', [ new FilterByBetween( $sku_query, [ $search_term ] ), 'filter'] );
		}
		add_filter( 'posts_join', [ $this, 'edit_posts_join' ], 10, 2 );
		add_filter( 'posts_orderby', [ $this, 'edit_posts_orderby' ], 10, 2 );

		$this->_fields = [ 'id' ];

		$query_results = $this->get_objects( $query_args );

		$objects = [];
		foreach ( $query_results['objects'] as $object ) {
			if ( ! wc_rest_check_post_permissions( $this->post_type, 'read', $object->get_id() ) ) {
				continue;
			}

			$data      = $this->prepare_object_for_response( $object, $request );
			$objects[] = $this->prepare_response_for_collection( $data );
		}

		$page      = (int) $query_args['paged'];
		$max_pages = $query_results['pages'];

		$response = rest_ensure_response( $objects );
		$response->header( 'X-WP-Total', $query_results['total'] );
		$response->header( 'X-WP-TotalPages', (int) $max_pages );
		$response->header( 'X-Setary-PluginVersion', SETARY_VERSION );

		$base          = $this->rest_base;
		$attrib_prefix = '(?P<';
		if ( strpos( $base, $attrib_prefix ) !== false ) {
			$attrib_names = [];
			preg_match( '/\(\?P<[^>]+>.*\)/', $base, $attrib_names, PREG_OFFSET_CAPTURE );
			foreach ( $attrib_names as $attrib_name_match ) {
				$beginning_offset = strlen( $attrib_prefix );
				$attrib_name_end  = strpos( $attrib_name_match[0], '>', $attrib_name_match[1] );
				$attrib_name      = substr( $attrib_name_match[0], $beginning_offset, $attrib_name_end - $beginning_offset );
				if ( isset( $request[ $attrib_name ] ) ) {
					$base = str_replace( "(?P<$attrib_name>[\d]+)", $request[ $attrib_name ], $base );
				}
			}
		}
		$base = add_query_arg( $request->get_query_params(), rest_url( sprintf( '/%s/%s', $this->namespace, $base ) ) );

		if ( $page > 1 ) {
			$prev_page = $page - 1;
			if ( $prev_page > $max_pages ) {
				$prev_page = $max_pages;
			}
			$prev_link = add_query_arg( 'page', $prev_page, $base );
			$response->link_header( 'prev', $prev_link );
		}
		if ( $max_pages > $page ) {
			$next_page = $page + 1;
			$next_link = add_query_arg( 'page', $next_page, $base );
			$response->link_header( 'next', $next_link );
		}

		return $response;
	}

	/**
	 * Get an empty meta query.
	 *
	 * @return array
	 */
	protected function get_empty_meta_query( $key ) {
		return [
			'relation' => 'OR',
			[
				'key'     => $key,
				'value'   => '',
				'compare' => '=',
			],
			[
				'key'     => $key,
				'value'   => '0',
				'compare' => '=',
			],
			[
				'key'     => $key,
				'compare' => 'NOT EXISTS',
				'value'   => 'null',
			],
		];
}

	/**
	 * Push tax query to object with AND condition
	 * 
	 * @param array $args       Query args.
	 * @param array $meta_query Meta query.
	 * @return mixed array
	 */
	protected function add_tax_query( $args, $tax_query ) {
		if ( empty( $args['tax_query'] ) ) {
			$args['tax_query'] = array();
		}

		$args['tax_query'][] = $tax_query;

		return $args['tax_query'];
	}

	protected function get_attributes( $product ) {
		$attributes = array();

		if ( $product->is_type( 'variation' ) ) {
			$_product = wc_get_product( $product->get_parent_id() );
			
			foreach ( $product->get_variation_attributes() as $attribute_name => $attribute ) {
				$name = str_replace( 'attribute_', '', $attribute_name );

				if ( empty( $attribute ) && '0' !== $attribute ) {
					continue;
				}

				// Taxonomy-based attributes are prefixed with `pa_`, otherwise simply `attribute_`.
				if ( 0 === strpos( $attribute_name, 'attribute_pa_' ) ) {
					$option_term  = get_term_by( 'slug', $attribute, $name );
					$attributes[] = array(
						'id'     => wc_attribute_taxonomy_id_by_name( $name ),
						'name'   => 'pa_' . sanitize_title( $this->get_attribute_taxonomy_name( $name, $_product ) ),
						'label'  => $this->get_attribute_taxonomy_name( $name, $_product ),
						'option' => $option_term && ! is_wp_error( $option_term ) ? $option_term->name : $attribute,
					);
				} else {
					$attributes[] = array(
						'id'     => 0,
						'slug'  => $name,
						'name'   => 'la_' . sanitize_title( $this->get_attribute_taxonomy_name( $name, $_product ) ),
						'label'  => $this->get_attribute_taxonomy_name( $name, $_product ),
						'option' => $attribute,
					);
				}
			}
		} else {
			foreach ( $product->get_attributes() as $attribute ) {
				$attributes[] = array(
					'id'        => $attribute['is_taxonomy'] ? wc_attribute_taxonomy_id_by_name( $attribute['name'] ) : 0,
					'taxonomy'  => $attribute['is_taxonomy'] ? $attribute['name'] : null,
					'label'     => $this->get_attribute_taxonomy_name( $attribute['name'], $product ),
					'name'      => $attribute['is_taxonomy'] ? $attribute['name'] : 'la_' . sanitize_title( $this->get_attribute_taxonomy_name(  $attribute['name'], $product ) ),
					'position'  => (int) $attribute['position'],
					'visible'   => (bool) $attribute['is_visible'],
					'variation' => (bool) $attribute['is_variation'],
					'options'   => $this->get_attribute_options( $product->get_id(), $attribute ),
				);
			}
		}

		return $attributes;
	}

	/**
	 * Format categories for response.
	 *
	 * @return string
	 */
	public function format_images( $item = [] ) {
		$images = [];

		if ( empty( $item['images'] ) ) {
			return '';
		}

		foreach ( $item['images'] as $image ) {
			$images[] = $image['id'];
		}

		return implode( ',', $images );
	}

	/**
	 * Format categories for response.
	 *
	 * @return string
	 */
	public function format_categories( $item = [] ) {
		// Get a formatted list of categories for the product. Display hierarchical categories like "Parent Category > Child Category".
		$categories = [];

		foreach ( $item['categories'] as $category ) {
			$category_structure = [
				$category['name'],
			];

			$parents = get_ancestors( $category['id'], 'product_cat' );

			if ( ! empty( $parents ) ) {
				foreach ( $parents as $parent ) {
					$category_structure[] = get_term( $parent, 'product_cat' )->name;
				}
			}

			$category_structure = array_reverse( $category_structure );

			$categories[] = implode( ' > ', $category_structure );
		}

		return implode( ' | ', $categories );
	}

	/**
	 * Format tags for response.
	 *
	 * @return string
	 */
	public function format_tags( $item = [] ) {
		if ( empty( $item['tags'] ) || ! is_array( $item['tags'] ) ) {
			return '';
		}

		$tag_names = wp_list_pluck( $item['tags'], 'name' );

		return implode( ' | ', $tag_names );
	}

	/**
	 * Format date string.
	 *
	 * @param string $date Date.
	 *
	 * @return string
	 */
	public function format_date( $date ) {
		if ( empty( $date ) ) {
			return $date;
		}

		$date_parts = explode( 'T', $date );

		return $date_parts[0];
	}

	/**
	 * Format IDs as comma separated string.
	 *
	 * @param $ids
	 *
	 * @return string
	 */
	public function format_ids( $ids = array() ) {
		return implode( ', ', $ids );
	}

	/**
	 * Get a specific product dimension.
	 *
	 * @param array  $item Item array.
	 * @param string $key  Key.
	 *
	 * @return string|float
	 */
	public function get_dimension( $item, $key ) {
		if ( empty( $item['dimensions'] ) || empty( $item['dimensions'][ $key ] ) ) {
			return '';
		}

		return floatval( $item['dimensions'][ $key ] );
	}

	/**
	 * Add meta query.
	 *
	 * @param array  $args       Query args.
	 * @param array  $meta_query Meta query.
	 * @param string $relation
	 *
	 * @return array
	 */
	protected function add_meta_query( $args, $meta_query, $relation = 'AND' ) {
		if ( empty( $args['meta_query'] ) ) {
			$args['meta_query'] = array();
		}

		$args['meta_query']['relation'] = $relation;
		$args['meta_query'][]           = $meta_query;

		return $args['meta_query'];
	}
}
