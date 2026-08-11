<?php

namespace Theme\Children\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Destinations custom post type.
 *
 * Registers the "Destinations" post type with full content support, built-in
 * taxonomies and REST API integration. Works with the redirect management
 * system out of the box: when a published destination slug changes, a 301
 * redirect is created automatically from the old URL to the new URL.
 *
 * @since 1.0.0
 */
final class Destinations_Post_Type {

	/**
	 * Post type slug.
	 */
	const SLUG = 'destinations';

	/**
	 * REST API base slug.
	 */
	const REST_BASE = 'destinations';

	/**
	 * Admin menu position.
	 */
	const MENU_POSITION = 7;

	/**
	 * Singleton instance.
	 *
	 * @var Destinations_Post_Type|null
	 */
	private static $instance;

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'after_switch_theme', array( $this, 'flush_rewrites' ) );
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
	 * Register the Destinations post type.
	 *
	 * @return void
	 */
	public function register() {
		$labels = array(
			'name'                  => _x( 'Destinations', 'Post type general name', 'astra-child' ),
			'singular_name'         => _x( 'Destination', 'Post type singular name', 'astra-child' ),
			'menu_name'             => _x( 'Destinations', 'Admin Menu text', 'astra-child' ),
			'name_admin_bar'        => _x( 'Destination', 'Add New on Toolbar', 'astra-child' ),
			'add_new'               => __( 'Add New', 'astra-child' ),
			'add_new_item'          => __( 'Add New Destination', 'astra-child' ),
			'new_item'              => __( 'New Destination', 'astra-child' ),
			'edit_item'             => __( 'Edit Destination', 'astra-child' ),
			'view_item'             => __( 'View Destination', 'astra-child' ),
			'all_items'             => __( 'All Destinations', 'astra-child' ),
			'search_items'          => __( 'Search Destinations', 'astra-child' ),
			'parent_item_colon'     => __( 'Parent Destination:', 'astra-child' ),
			'not_found'             => __( 'No destinations found.', 'astra-child' ),
			'not_found_in_trash'    => __( 'No destinations found in Trash.', 'astra-child' ),
			'featured_image'        => _x( 'Destination Image', 'Overrides the "Featured Image" phrase for this post type', 'astra-child' ),
			'set_featured_image'    => _x( 'Set destination image', 'Overrides the "Set featured image" phrase for this post type', 'astra-child' ),
			'remove_featured_image' => _x( 'Remove destination image', 'Overrides the "Remove featured image" phrase for this post type', 'astra-child' ),
			'use_featured_image'    => _x( 'Use as destination image', 'Overrides the "Use as featured image" phrase for this post type', 'astra-child' ),
			'archives'              => _x( 'Destination archives', 'The post type archive label used in nav menus', 'astra-child' ),
			'insert_into_item'      => _x( 'Insert into destination', 'Overrides the "Insert into post" phrase for this post type', 'astra-child' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this destination', 'Overrides the "Uploaded to this post" phrase for this post type', 'astra-child' ),
			'filter_items_list'     => _x( 'Filter destinations list', 'Screen reader text for the filter links heading on the post type listing screen', 'astra-child' ),
			'items_list_navigation' => _x( 'Destinations list navigation', 'Screen reader text for the pagination heading on the post type listing screen', 'astra-child' ),
			'items_list'            => _x( 'Destinations list', 'Screen reader text for the items list heading on the post type listing screen', 'astra-child' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'exclude_from_search' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => true,
			'show_in_admin_bar'  => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'destinations' ),
			'capability_type'    => 'post',
			'has_archive'        => 'all-destinations',
			'hierarchical'       => false,
			'menu_position'      => self::MENU_POSITION,
			'menu_icon'          => 'dashicons-location-alt',
			'supports'           => array(
				'title',
				'editor',
				'thumbnail',
				'excerpt',
				'author',
				'revisions',
				'custom-fields',
				'comments',
				'trackbacks',
				'page-attributes',
				'post-formats',
			),
			'taxonomies'             => array( 'category', 'post_tag' ),
			'show_in_rest'           => true,
			'rest_base'              => self::REST_BASE,
			'rest_controller_class'  => 'WP_REST_Posts_Controller',
		);

		register_post_type( self::SLUG, $args );
	}

	/**
	 * Flush rewrite rules after the theme is activated so the destination
	 * archive and permalink structure are registered.
	 *
	 * @return void
	 */
	public function flush_rewrites() {
		flush_rewrite_rules();
	}

	/**
	 * Get the singleton instance.
	 *
	 * @return Destinations_Post_Type
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
