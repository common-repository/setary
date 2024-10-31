<?php
/**
 * Custom WooCommerce endpoint with common data,
 * it will be used to check the version of the plugin
 * and check credentials and check that plugin activated.
 *
 * @package Setary
 */

namespace Setary;

use Setary\Utils;

/**
 * Extend product controller to add special methods for products.
 */
class Product_Controller {
	static function ends_with( $haystack, $needle ) {
		$length = strlen( $needle );
		if( !$length ) {
			return true;
		}
		return substr( $haystack, -$length ) === $needle;
	}

	public function attr_string_to_array($str) {
		if( is_array($str) ) {
			return $str;
		}

		$new_options = explode('|', $str);
		$new_options = array_map('trim', $new_options);
		$new_options = array_filter($new_options, fn($value) => !is_null($value) && $value !== '');
		$new_options = array_unique($new_options);

		return $new_options;
	}

	public function preprocess_attr_term_change($name, $pending_chandes) {
		$term_name = $name . '';
		$term_title = substr($name, 3);

		$taxonomies = array_map(function($taxonomy) { return wc_attribute_taxonomy_name( $taxonomy->attribute_name ); }, wc_get_attribute_taxonomies() ); // wp_list_pluck

		if( ! in_array($name, $taxonomies) ) {
			wc_create_attribute([
				'slug' => $term_name,
				'name' => $term_title,
				'type' => 'text',
			]);
		}

		if( isset($pending_chandes['options']) ) {
			$options = $pending_chandes['options'];

			foreach($options as $index => $option) {
				if (! $term = get_term_by( 'name', $option, $term_name )) {
					wp_insert_term( $option,  $term_name );
					$term = get_term_by( 'name', $option, $term_name );
				}

				$options[$index] = $term->name;

				$term = false;
			}

			$pending_chandes['options'] = $options;
		}

		return $pending_chandes;
	}

	public function attr_change($product, $name, $pending_chandes) {
		$is_variation = $product->is_type( 'variation' );
		
		$is_local = 0 === strpos( $name, 'la_' );
		$attribute_key = $is_local ? substr($name, 3) : $name;

		$attributes = $product->get_attributes();

		if(!$is_variation) {
			
			if(!isset($attributes[$attribute_key])) {
				$attr = new \WC_Product_Attribute();

				$is_tax = taxonomy_exists($attribute_key);

				$attr->set_name( $is_tax ? $attribute_key : ucfirst( $attribute_key ) );

				$attr->set_visible( 1 );
				$attr->set_variation( 1 );
				$attr->set_position( 0 );
				$attr->set_id( $is_tax ? wc_attribute_taxonomy_id_by_name( $attribute_key ) : 0 );
				
				$attributes[$attribute_key] = $attr;
			} else {
				$attributes[$attribute_key] = clone $attributes[$attribute_key];
			}
		}

		$attr = $attributes[$attribute_key];

		$changed = false;

		if( isset($pending_chandes['visible']) ) {
			$attr->set_visible( wc_string_to_bool($pending_chandes['visible']) );

			$changed = true;
		}

		if( isset($pending_chandes['variation']) ) {
			$attr->set_variation( wc_string_to_bool($pending_chandes['variation']) );

			$changed = true;
		}

		if( isset($pending_chandes['options']) ) {
			$options = $pending_chandes['options'];

			if( $is_variation ) {
				$attributes[ $attribute_key ] = sanitize_title_with_dashes( wc_implode_text_attributes($options) );
			} else {		
				if( count($options) ) {
					$attributes[$attribute_key]->set_options( $options );
				} else {
					unset( $attributes[$attribute_key] );
				}
			}

			$changed = true;
		}


		if( $changed ) {
			$product->set_attributes($attributes);
			$product->save();
		}
	}

	/**
	 * Filter product before insert to database.
	 *
	 * @param \WC_Product      $product  Object object.
	 * @param \WP_REST_Request $request  Request object.
	 * @param bool            $creating If is creating a new object.
	 *
	 * @return \WC_Product|\WP_Error
	 */
	public function pre_insert_product_object( $product, $request, $creating ) {
		if ( ! empty( $request['type'] ) && empty( $request['product_type'] ) ) {
			$request['product_type'] = $request['type'];
		}
	
		// Change variation to product, or product to variation.
		if ( ! empty( $request['product_type'] ) ) {
			$product = $this->change_type( $product, $request['product_type'] );
		}

		$format_ids = array(
			'formatted_upsell_ids',
			'formatted_cross_sell_ids'
		);

		// Loop cells which need formatted IDs and check if the method exists.
		foreach( $format_ids as $format_id ) {
			$method = sprintf( 'set_%s', str_replace( 'formatted_', '', $format_id ) );

			if ( isset( $request[ $format_id ] ) && method_exists( $product, $method ) ) {
				$ids_array = preg_split( '/\s*,\s*/', $request[ $format_id ] );

				// Call the `set` method for the product.
				call_user_func( array( $product, $method ), $ids_array );
			}
		}

		if ( isset( $request['attributes_data'] ) && is_array($request['attributes_data']) && count( $request['attributes_data'] ) ) {
			$attributes_data = [];

			foreach($request['attributes_data'] as $attribute) {
				$key = $attribute['key'];
				
				foreach([ 'visible' => '_visible_on_product_page', 'variation' => '_used_for_variations'] as $prop_key =>  $suffix) {
					if ( self::ends_with($key, $suffix ) ) {
						$attr_key = substr($attribute['key'], 0, -1 * strlen($suffix));
				
						if( !isset($attributes_data[$attr_key]) ) {
							$attributes_data[$attr_key] = [];
						}
	
						$attributes_data[$attr_key][$prop_key] = $attribute['value'];

						continue 2;
					}
				}

				$options = $this->attr_string_to_array( $attribute['value'] );
				$attributes_data[$key] = [
					'options' => $options
				];
			}

			foreach($attributes_data as $key => $attribute) {
				$is_local = 0 === strpos( $key, 'la_' );
				
				if( ! $is_local ) {
					$attribute = $this->preprocess_attr_term_change($key, $attribute);
				}

				$this->attr_change($product, $key, $attribute);
			}
		}

		if ( isset( $request['tax_class'] ) && 'standard' === $request['tex_class'] ) {
			$product->set_tax_class( '' );
		}

		if ( isset( $request['formatted_categories'] ) ) {
			$category_ids = $this->get_term_ids( $request['formatted_categories'], 'product_cat' );
			$product->set_category_ids( $category_ids );
		}

		if ( isset( $request['formatted_tags'] ) ) {
			$category_ids = $this->get_term_ids( $request['formatted_tags'], 'product_tag' );
			$product->set_tag_ids( $category_ids );
		}

		if ( $product->is_type( 'variation' ) && ! empty( $request['images'] ) ) {
			$images         = $request['images'];
			$featured_image = array_shift( $images );

			$product->set_image_id( $featured_image['id'] );

			if ( ! empty( $images ) ) {
				$gallery_image_ids = wp_list_pluck( $images, 'id' );
				$product->set_gallery_image_ids( $gallery_image_ids );
			}
		}

		// Check if `type` is being saved
		if ( isset( $request['product_type'] ) ) {
			// Ensure the new type exists
			if ( 'variation' !== $request['product_type'] && ! in_array( $request['product_type'], array_keys( wc_get_product_types() ) ) ) {
				return new \WP_Error( 'invalid_product_type', __( 'The product type does not exist.', 'setary' ), array( 'status' => 400 ) );
			}
		} else {
			$product_type = Utils::get_product_type( $product->get_id() );

			if ( empty( $product_type ) ) {
				return new \WP_Error( 'unable_to_determine_product_type', __( 'Unable to determine product type.', 'setary' ), array( 'status' => 400 ) );
			}

			// Compare with the product type from get_type()
			if ( $product_type !== $product->get_type() ) {
				return new \WP_Error( 'product_type_mismatch', sprintf( __( 'Product type not found. Enable necessary plugins for custom product types. <a href="%s" target="_blank">Learn more</a>.', 'setary' ), esc_url( 'https://setary.com/docs/how-to-enable-custom-product-types-in-setary/' ) ), array( 'status' => 400 ) );
			}
		}

		// These keys are not saved by default, so let's process them here.
		$keys = [
			'width',
			'height',
			'length',
			'parent_id',
		];

		foreach( $keys as $key ) {
			if( isset( $request[ $key ] ) ) {
				$method_name = sprintf( 'set_%s', $key );

				if ( ! method_exists( $product, $method_name ) ) {
					continue;
				}

				call_user_func( array( $product, $method_name ), $request[ $key ] );
			}
		}

		do_action( 'setary_pre_insert_product_object', $product, $request, $creating );

		return $product;
	}

	/**
	 * Expose Setary headers.
	 *
	 * @param $expose_headers
	 *
	 * @return mixed
	 */
	public function rest_exposed_cors_headers( $expose_headers ) {
		$expose_headers[] = 'X-Setary-PluginVersion';

		return $expose_headers;
	}

	/**
	 * Get term IDs based on formatted taxonomy/term string - ( Cat 1 | Cat 2 > Nested Cat ).
	 *
	 * @param string $formatted_terms Formatted terms.
	 * @param string $taxonomy Taxonomy.
	 *
	 * @return array
	 */
	public function get_term_ids( $formatted_terms, $taxonomy ) {
		$term_ids = [];

		if ( empty( $formatted_terms ) ) {
			return $term_ids;
		}

		// Split string with regex to account for any number of spaces before/after "|".
		$terms = preg_split( '/\s*\|\s*/', $formatted_terms );

		foreach ( $terms as $i => $term ) {
			// Split string with regex to account for any number of spaces before/after ">".
			$term_parts = preg_split( '/\s*>\s*/', $term );
			$parent_id      = 0;

			foreach ( $term_parts as $term_name ) {
				$term_id = $this->get_term_id( $term_name, $parent_id, $taxonomy );
				$parent_id   = $term_id;

				// Replace term ID with the new one. This will get the deepest ID.
				$term_ids[ $i ] = $term_id;
			}
		}

		return $term_ids;
	}

	/**
	 * Get term ID or create a new one.
	 *
	 * @param string $term_name
	 * @param int    $parent_id
	 * @param string $taxonomy
	 *
	 * @return int|mixed
	 */
	public function get_term_id( $term_name, $parent_id, $taxonomy ) {
		$args = [
			'name'       => $term_name,
			'parent'     => $parent_id,
			'hide_empty' => false,
		];

		$terms = get_terms( $taxonomy, $args );

		// If no term found, create one.
		if ( empty( $terms ) ) {
			$term_args = [];

			if ( $parent_id ) {
				$term_args['parent'] = $parent_id;
			}

			$term    = wp_insert_term( $term_name, $taxonomy, $term_args );
			$term_id = $term['term_id'];
		} else {
			$term_id = $terms[0]->term_id;
		}

		return $term_id;
	}

	/**
	 * Change variation to product, or product to variation.
	 *
	 * @param \WC_Product_Variation|\WC_Product $product Product.
	 *
	 * @return int|false
	 */
	public function transition_product_variation( $product ) {
		global $wpdb;

		$product_id = $product->get_id();
		$new_type   = 'variation' === $product->get_type() ? 'product' : 'product_variation';

		$args = array(
			'post_type' => $new_type,
		);

		$format = array( '%s' );

		if ( 'product' === $new_type ) {
			$args['post_parent'] = 0;
			$format[] = '%d';
		}

		// Update the post type
		$update = $wpdb->update(
			$wpdb->posts,
			$args,
			array( 'ID' => $product_id ),
			$format,
			array( '%d' )
		);

		wp_cache_delete( $product_id, 'posts' );

		if ( is_wp_error( $update ) ) {
			return false;
		}

		return $update;
	}

	/**
	 * Change product type.
	 *
	 * @param WC_Product $product  Product.
	 * @param string     $new_type product type.
	 *
	 * @return mixed
	 */
	public function change_type( $product, $new_type = 'simple' ) {
		$current_type = $product->get_type();
		$current_type_is_variation = 'variation' === $current_type;
		$new_type_is_variation = 'variation' === $new_type;

		$should_transition = ( $current_type_is_variation && ! $new_type_is_variation ) || ( ! $current_type_is_variation && $new_type_is_variation );

		if ( $should_transition ) {
			$this->transition_product_variation( $product );
		}

		// If current type was not a variation, remove the type. Variations have no product type.
		if ( ! $current_type_is_variation ) {
			wp_remove_object_terms( $product->get_id(), $current_type, 'product_type' );
		}

		// If new type is not a variation, add it as a term. Variations have no product type.
		if ( ! $new_type_is_variation ) {
			wp_set_object_terms( $product->get_id(), $new_type, 'product_type' );
		}

		$classname = \WC_Product_Factory::get_classname_from_product_type( $new_type );

		if ( ! $classname || ! class_exists( $classname ) ) {
			return $product;
		}

		$changes = $product->get_changes();
		$productClass = new $classname( $product->get_id() );
		$productClass->set_props( $changes );

		return $productClass;
	}
}
