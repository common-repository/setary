<?php
/**
 * A "welcome" page to assit with onboarding users
 * who are not familiar with the app.
 *
 * @package Setary
 */

namespace Setary;

/**
 * Welcome page class.
 */
class Welcome {
	/**
	 * @var string Page slug.
	 */
	private $slug = 'setary-welcome';

	/**
	 * Construct.
	 */
	function __construct() {
		if ( ! is_admin() ) {
			return;
		}

		// Run this on plugin activation
		register_activation_hook( SETARY__FILE__, [ $this, 'activate' ] );

		add_action( 'admin_init', [ $this, 'do_activation_redirect' ] );
		add_action( 'admin_menu', [ $this, 'add_screen' ] );
		add_action( 'admin_head', [ $this, 'remove_menus' ] );
		add_action( 'admin_print_scripts', [ $this, 'disable_admin_notices' ] );
		add_filter( 'plugin_action_links_' . SETARY_BASENAME, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Get welcome page URL.
	 *
	 * @return string Welcome page URL.
	 */
	public function get_welcome_url() {
		return add_query_arg( array( 'page' => $this->slug ), admin_url( 'index.php' ) );
	}

	/**
	 * Activate plugin.
	 */
	public function activate() {
		set_transient( '_setary_welcome_screen_activation_redirect', true, 30 );
	}

	/**
	 * Do activation redirect.
	 */
	public function do_activation_redirect() {
		// Bail if no activation redirect
		if ( ! get_transient( '_setary_welcome_screen_activation_redirect' ) ) {
			return;
		}

		// Delete the redirect transient
		delete_transient( '_setary_welcome_screen_activation_redirect' );

		// Bail if activating from network, or bulk
		if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) {
			return;
		}

		// Redirect to welcome page.
		wp_safe_redirect( $this->get_welcome_url() );
	}

	/**
	 * Add welcome screen page.
	 */
	public function add_screen() {
		add_dashboard_page(
			__( 'Connect to Setary', 'setary' ),
			__( 'Connect to Setary', 'setary' ),
			'read',
			$this->slug,
			[ $this, 'content' ]
		);
	}

	/**
	 * Welcome screen content.
	 */
	public function content() {
		require_once SETARY_PATH . 'templates/admin/welcome.php';
	}

	/**
	 * Remove page from menus.
	 */
	function remove_menus() {
		remove_submenu_page( 'index.php', $this->slug );
	}

	/**
	 * Disable admin notices.
	 */
	public function disable_admin_notices() {
		global $wp_filter;

		if ( isset( $_GET['page'] ) && $this->slug === $_GET['page'] ) {
			if ( isset( $wp_filter['user_admin_notices'] ) ) {
				unset( $wp_filter['user_admin_notices'] );
			}
			if ( isset( $wp_filter['admin_notices'] ) ) {
				unset( $wp_filter['admin_notices'] );
			}
			if ( isset( $wp_filter['all_admin_notices'] ) ) {
				unset( $wp_filter['all_admin_notices'] );
			}
		}
	}

	/**
	 * Add a link to the welcome page under Setary on the plugins list page.
	 *
	 * @param array $links Existing plugin action links.
	 *
	 * @return array Modified plugin action links.
	 */
	public function add_settings_link( $links ) {
		$settings_link = '<a href="' . esc_url( $this->get_welcome_url() ) . '">' . __( 'Welcome', 'setary' ) . '</a>';
		array_unshift( $links, $settings_link );

		return $links;
	}
}

new Welcome();