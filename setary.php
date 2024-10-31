<?php
/**
 * Plugin Name:          Setary — Bulk Editor for WooCommerce Products
 * Plugin URI:           https://setary.com/
 * Description:          A helper plugin to bridge the gap between WordPress and Setary.
 *
 * Text Domain:          setary
 * Domain Path:          /languages
 *
 * Author:               Setary
 * Author URI:           https://setary.com/
 *
 * Version:              1.13.0
 * WC requires at least: 6.0.0
 * WC tested up to:      8.9.1
 *
 * @package              Setary
 */

namespace Setary;

define( 'SETARY__FILE__', __FILE__ );
define( 'SETARY_PATH', \plugin_dir_path( __FILE__ ) );
define( 'SETARY_URL', \plugins_url( '/', __FILE__ ) );
define( 'SETARY_VERSION', '1.13.0' );
define( 'SETARY_SITE_URL', 'https://setary.com/' );
define( 'SETARY_APP_URL', 'https://setary.com/app/' );
define( 'SETARY_BASENAME', plugin_basename( __FILE__ ) );

// Load before plugins loaded.
include_once __DIR__ . '/inc/class-welcome.php';

add_action( 'plugins_loaded', __NAMESPACE__ . '\\plugins_loaded' );

/**
 * Load plugin files.
 *
 * @return void
 */
function plugins_loaded() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	include_once __DIR__ . '/inc/class-product-controller.php';
	include_once __DIR__ . '/inc/class-products-with-variations.php';
	include_once __DIR__ . '/inc/class-products-variations.php';
	include_once __DIR__ . '/inc/class-upload-image.php';
	include_once __DIR__ . '/inc/class-media-list.php';
	include_once __DIR__ . '/inc/class-meta-attributes.php';
	include_once __DIR__ . '/inc/class-product-tools.php';
	include_once __DIR__ . '/inc/class-info.php';
	include_once __DIR__ . '/inc/class-batch.php';
	include_once __DIR__ . '/inc/class-mu.php';
	include_once __DIR__ . '/inc/class-settings.php';
	include_once __DIR__ . '/inc/class-utils.php';
	include_once __DIR__ . '/inc/bootstrap.php';

	// Compatibility.
	include_once __DIR__ . '/inc/class-compat-wp-fusion.php';
	include_once __DIR__ . '/inc/class-compat-lifterlms.php';
}
