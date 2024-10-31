<?php
/**
 * Setary Custom Endpoint Optimizations
 *
 * Disable all plugins except WooCommerce and Setary for custom endpoint requests.
 * This file is moved to the mu-plugins directory automatically by class-mu.php.
 */

namespace Setary\MU\Helper;

/**
 * Check if the current request is for the custom endpoint.
 *
 * @return bool True if the request is for the custom endpoint, false otherwise.
 */
function is_custom_endpoint_request() {
	$request_uri      = $_SERVER['REQUEST_URI'];
	$rest_prefix      = rest_get_url_prefix();
	$endpoint_pattern = "/^\/$rest_prefix\/wc\/setary\//";

	return preg_match( $endpoint_pattern, $request_uri );
}

/**
 * Filter the list of active plugins for custom endpoint requests.
 *
 * @param array $active_plugins The list of active plugins.
 *
 * @return array The filtered list of active plugins.
 */
function filter_active_plugins( $active_plugins ) {
	if ( ! is_custom_endpoint_request() || ! is_array( $active_plugins ) ) {
		return $active_plugins;
	}

	$filtered_plugins     = array();
	$allowed_plugin_files = array( '/setary.php', '/woocommerce.php' );

	// Get plugins enabled from the Setary settings.
	$enabled_plugins = get_option( 'setary_enabled_plugins' );

	if ( is_array( $enabled_plugins ) ) {
		$allowed_plugin_files = array_merge( $allowed_plugin_files, $enabled_plugins );
	}

	foreach ( $active_plugins as $plugin ) {
		foreach ( $allowed_plugin_files as $allowed_plugin_file ) {
			if ( strpos( $plugin, $allowed_plugin_file ) !== false ) {
				$filtered_plugins[] = $plugin;
				break;
			}
		}
	}

	return $filtered_plugins;
}

add_filter( 'option_active_plugins', __NAMESPACE__ . '\filter_active_plugins' );