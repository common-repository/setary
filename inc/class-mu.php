<?php
/**
 * Set up the MU helper file.
 *
 * @package Setary
 */

namespace Setary;

/**
 * MU class.
 */
class MU {
	/**
	 * @var string MU file version
	 */
	private static $version = '1.2.0';

	/**
	 * Construct.
	 */
	function __construct() {
		add_action( 'admin_init', array( $this, 'copy_mu_file' ) );
	}

	/**
	 * Copy or replace the mu-setary-helper.php file to the mu-plugins folder.
	 */
	public function copy_mu_file() {
		if ( ! class_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$mu_file_name = 'mu-setary-helper.php';
		$source_file  = SETARY_PATH . $mu_file_name;
		$target_file  = WPMU_PLUGIN_DIR . '/' . $mu_file_name;

		// Get the current file version from the database
		$current_version = get_option( 'setary_mu_file_version', '0' );

		// Initialize the WP_Filesystem
		WP_Filesystem();
		global $wp_filesystem;

		// Check if the target file needs to be updated
		if ( $wp_filesystem->exists( $target_file ) && version_compare( $current_version, self::$version, '<' ) ) {
			$wp_filesystem->delete( $target_file ); // Remove the old version of the plugin file
		}

		// Check if the target file needs to be moved or updated
		if ( ! $wp_filesystem->exists( $target_file ) || version_compare( $current_version, self::$version, '<' ) ) {
			// Create the mu-plugins folder if it doesn't exist
			if ( ! $wp_filesystem->is_dir( WPMU_PLUGIN_DIR ) ) {
				$wp_filesystem->mkdir( WPMU_PLUGIN_DIR );
			}

			// Copy the new version of the plugin file to the mu-plugins folder
			$wp_filesystem->copy( $source_file, $target_file, true, FS_CHMOD_FILE );

			// Update the file version in the database
			update_option( 'setary_mu_file_version', self::$version );
		}
	}

}

new MU();