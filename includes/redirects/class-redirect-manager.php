<?php

namespace AstraChild\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Loads and wires up the redirect management system.
 *
 * @since 1.0.0
 */
final class Redirect_Manager {

	/**
	 * Singleton instance.
	 *
	 * @var Redirect_Manager|null
	 */
	private static $instance;

	/**
	 * Get the singleton instance and boot the system.
	 *
	 * @return Redirect_Manager
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {
		$this->load_classes();

		add_action( 'after_switch_theme', array( $this, 'maybe_install' ) );
		add_action( 'init', array( $this, 'maybe_install' ), 5 );

		$this->boot();
	}

	/**
	 * Require all redirect class files.
	 *
	 * @return void
	 */
	private function load_classes() {
		$base = __DIR__;

		require_once $base . '/class-redirect-db.php';
		require_once $base . '/class-redirect-handler.php';
		require_once $base . '/class-redirect-hooks.php';
		require_once $base . '/class-redirect-list-table.php';
		require_once $base . '/class-redirect-admin.php';
	}

	/**
	 * Instantiate the redirect components.
	 *
	 * @return void
	 */
	private function boot() {
		$db = Redirect_DB::get_instance();

		new Redirect_Handler( $db );
		new Redirect_Hooks( $db );

		if ( is_admin() ) {
			new Redirect_Admin();
		}
	}

	/**
	 * Ensure the redirect table exists.
	 *
	 * @return void
	 */
	public function maybe_install() {
		Redirect_DB::get_instance()->maybe_create_table();
	}
}
