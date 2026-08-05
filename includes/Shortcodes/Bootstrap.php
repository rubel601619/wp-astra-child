<?php

namespace Theme\Children\Shortcodes;

defined( 'ABSPATH' ) || exit;

/**
 * Boots the Services shortcode.
 *
 * Registers the `[services]` shortcode tag and its stylesheet handle.
 *
 * @since 1.0.0
 */
final class Bootstrap {

	/**
	 * Singleton instance.
	 *
	 * @var Bootstrap|null
	 */
	private static $instance;

	/**
	 * Transient that caches pages using the services shortcode.
	 */
	const PAGES_TRANSIENT = 'astra_child_services_shortcode_pages';

	/**
	 * Option that stores a hash of the cached pages for rewrite flushing.
	 */
	const PAGES_HASH_OPTION = 'astra_child_services_shortcode_pages_hash';

	/**
	 * Constructor.
	 */
	private function __construct() {
		require_once __DIR__ . '/Services_Shortcode.php';

		add_shortcode( Services_Shortcode::SHORTCODE, array( Services_Shortcode::class, 'render' ) );
		add_action( 'init', array( $this, 'register_styles' ) );
		add_action( 'init', array( $this, 'register_pagination_rules' ) );
		add_action( 'save_post_page', array( $this, 'invalidate_shortcode_pages' ) );
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {
	}

	/**
	 * Prevent unserializing.
	 */
	public function __wakeup() {
	}

	/**
	 * Register the shortcode stylesheet handle.
	 *
	 * The style is only enqueued when the shortcode is actually rendered.
	 *
	 * @return void
	 */
	public function register_styles() {
		wp_register_style(
			Services_Shortcode::CSS_HANDLE,
			get_stylesheet_directory_uri() . '/assets/css/services-shortcode.css',
			array(),
			Services_Shortcode::CSS_VERSION
		);
	}

	/**
	 * Register top-priority rewrite rules for the paginated shortcode pages.
	 *
	 * When a page using `[services]` shares its slug with the Services post
	 * type archive or single rewrite base (e.g. /services/), WordPress routes
	 * `/{slug}/page/N/` to a single service named "page" instead of the page's
	 * pagination. This adds a matching rule at top priority so the static page
	 * wins and its `paged` query var is set correctly.
	 *
	 * @return void
	 */
	public function register_pagination_rules() {
		$pages = self::get_shortcode_pages();

		foreach ( $pages as $uri ) {
			$regex = preg_quote( $uri, '/' ) . '/page/?([0-9]{1,})/?$';
			$query = 'index.php?pagename=' . $uri . '&paged=$matches[1]';

			add_rewrite_rule( $regex, $query, 'top' );
		}

		self::maybe_flush_rules( $pages );
	}

	/**
	 * Get the URI of every published page that uses the services shortcode.
	 *
	 * Cached in a transient so the lookup only runs once per hour.
	 *
	 * @return string[] Page URIs (e.g. "services" or "parent/services").
	 */
	private static function get_shortcode_pages() {
		$pages = get_transient( self::PAGES_TRANSIENT );

		if ( false === $pages ) {
			$pages   = array();
			$matches = get_posts( array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			) );

			foreach ( $matches as $page_id ) {
				if ( has_shortcode( get_post_field( 'post_content', $page_id ), Services_Shortcode::SHORTCODE ) ) {
					$pages[] = get_page_uri( $page_id );
				}
			}

			set_transient( self::PAGES_TRANSIENT, $pages, HOUR_IN_SECONDS );
		}

		return $pages;
	}

	/**
	 * Clear the cached page list so rewrite rules stay in sync.
	 *
	 * @return void
	 */
	public function invalidate_shortcode_pages() {
		delete_transient( self::PAGES_TRANSIENT );
	}

	/**
	 * Flush rewrite rules when the set of shortcode pages changes.
	 *
	 * @param string[] $pages Current page URIs.
	 * @return void
	 */
	private static function maybe_flush_rules( $pages ) {
		$hash = md5( serialize( $pages ) );

		if ( $hash !== get_option( self::PAGES_HASH_OPTION, '' ) ) {
			update_option( self::PAGES_HASH_OPTION, $hash );
			flush_rewrite_rules();
		}
	}

	/**
	 * Get the singleton instance.
	 *
	 * @return Bootstrap
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
