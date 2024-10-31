<?php
/**
 * Settings class.
 *
 * @package Setary
 */

namespace Setary;

/**
 * Settings class.
 */
class Settings {
	/**
	 * Construct.
	 */
	public function __construct() {
		add_filter( 'woocommerce_get_sections_advanced', array( $this, 'add_section' ) );
		add_filter( 'woocommerce_get_settings_advanced', array( $this, 'add_settings' ), 10, 2 );
		add_filter( 'plugin_action_links_' . SETARY_BASENAME, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Add a new section under WooCommerce -> Settings -> Advanced.
	 *
	 * @param array $sections Existing sections.
	 *
	 * @return array Modified sections.
	 */
	public function add_section( $sections ) {
		$sections['setary'] = __( 'Setary', 'setary' );

		return $sections;
	}

	/**
	 * Add settings to the new Setary section.
	 *
	 * @param array  $settings        Existing settings.
	 * @param string $current_section The current section being displayed.
	 *
	 * @return array Modified settings.
	 */
	public function add_settings( $settings, $current_section ) {
		if ( 'setary' !== $current_section ) {
			return $settings;
		}

		$settings   = array();
		$settings[] = array(
			'title' => __( 'Setary Settings', 'setary' ),
			'desc'  => __( 'Customize Setary\'s functionality by controlling the plugins active during its requests, optimizing both performance and compatibility.', 'setary' ),
			'type'  => 'title',
			'id'    => 'setary_settings_title',
		);

		// Define plugins to exclude.
		$exclude_plugins = array( '/setary.php', '/woocommerce.php' );

		// Get all active plugins.
		$active_plugins = get_option( 'active_plugins' );
		$plugins        = array();
		foreach ( $active_plugins as $plugin ) {
			$exclude = false;
			foreach ( $exclude_plugins as $exclude_plugin ) {
				if ( strpos( $plugin, $exclude_plugin ) !== false ) {
					$exclude = true;
					break;
				}
			}
			if ( ! $exclude ) {
				$plugin_data        = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
				$plugins[ $plugin ] = $plugin_data['Name'];
			}
		}

		// Add multi-checkbox setting.
		$settings[] = array(
			'title'             => __( 'Enable Plugins', 'setary' ),
			'desc'              => __( 'Enable specific plugins during Setary\'s requests.', 'setary' ) . '<br>' . __( 'Note: Enabling plugins may increase Setary\'s load times. Some plugins (like those adding additional product types to WooCommerce) need to be enabled to preserve custom product types on save.', 'setary' ),
			'id'                => 'setary_enabled_plugins',
			'default'           => '',
			'type'              => 'multiselect',
			'class'             => 'wc-enhanced-select',
			'desc_tip'          => true,
			'options'           => $plugins,
			'css'               => 'min-width:300px;',
			'custom_attributes' => array(
				'data-placeholder' => __( 'Select plugins', 'setary' ),
			),
		);

		$settings[] = array(
			'type' => 'sectionend',
			'id'   => 'setary_settings_section_end',
		);

		return $settings;
	}

	/**
	 * Add a link to the settings page under Setary on the plugins list page.
	 *
	 * @param array $links Existing plugin action links.
	 *
	 * @return array Modified plugin action links.
	 */
	public function add_settings_link( $links ) {
		$settings_link = '<a href="admin.php?page=wc-settings&tab=advanced&section=setary">' . __( 'Settings', 'setary' ) . '</a>';
		array_unshift( $links, $settings_link );

		return $links;
	}
}

new Settings();
