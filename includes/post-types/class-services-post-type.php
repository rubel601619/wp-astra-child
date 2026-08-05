<?php

namespace Theme\Children\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Services custom post type.
 *
 * Registers the "Services" post type with full content support, built-in
 * taxonomies and REST API integration. Works with the redirect management
 * system out of the box: when a published service slug changes, a 301
 * redirect is created automatically from the old URL to the new URL.
 *
 * @since 1.0.0
 */
final class Services_Post_Type {

	/**
	 * Post type slug.
	 */
	const SLUG = 'services';

	/**
	 * REST API base slug.
	 */
	const REST_BASE = 'services';

	/**
	 * Admin menu position.
	 */
	const MENU_POSITION = 6;

	/**
	 * Meta key for the button URL.
	 */
	const META_URL = '_astra_child_service_button_url';

	/**
	 * Meta key for the button text.
	 */
	const META_TEXT = '_astra_child_service_button_text';

	/**
	 * Meta key for the "open in new tab" checkbox.
	 */
	const META_NEW_TAB = '_astra_child_service_button_new_tab';

	/**
	 * Singleton instance.
	 *
	 * @var Services_Post_Type|null
	 */
	private static $instance;

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'after_switch_theme', array( $this, 'flush_rewrites' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::SLUG, array( $this, 'save_button_info' ) );
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
	 * Register the Services post type.
	 *
	 * @return void
	 */
	public function register() {
		$labels = array(
			'name'                  => _x( 'Services', 'Post type general name', 'astra-child' ),
			'singular_name'         => _x( 'Service', 'Post type singular name', 'astra-child' ),
			'menu_name'             => _x( 'Services', 'Admin Menu text', 'astra-child' ),
			'name_admin_bar'        => _x( 'Service', 'Add New on Toolbar', 'astra-child' ),
			'add_new'               => __( 'Add New', 'astra-child' ),
			'add_new_item'          => __( 'Add New Service', 'astra-child' ),
			'new_item'              => __( 'New Service', 'astra-child' ),
			'edit_item'             => __( 'Edit Service', 'astra-child' ),
			'view_item'             => __( 'View Service', 'astra-child' ),
			'all_items'             => __( 'All Services', 'astra-child' ),
			'search_items'          => __( 'Search Services', 'astra-child' ),
			'parent_item_colon'     => __( 'Parent Service:', 'astra-child' ),
			'not_found'             => __( 'No services found.', 'astra-child' ),
			'not_found_in_trash'    => __( 'No services found in Trash.', 'astra-child' ),
			'featured_image'        => _x( 'Service Image', 'Overrides the "Featured Image" phrase for this post type', 'astra-child' ),
			'set_featured_image'    => _x( 'Set service image', 'Overrides the "Set featured image" phrase for this post type', 'astra-child' ),
			'remove_featured_image' => _x( 'Remove service image', 'Overrides the "Remove featured image" phrase for this post type', 'astra-child' ),
			'use_featured_image'    => _x( 'Use as service image', 'Overrides the "Use as featured image" phrase for this post type', 'astra-child' ),
			'archives'              => _x( 'Service archives', 'The post type archive label used in nav menus', 'astra-child' ),
			'insert_into_item'      => _x( 'Insert into service', 'Overrides the "Insert into post" phrase for this post type', 'astra-child' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this service', 'Overrides the "Uploaded to this post" phrase for this post type', 'astra-child' ),
			'filter_items_list'     => _x( 'Filter services list', 'Screen reader text for the filter links heading on the post type listing screen', 'astra-child' ),
			'items_list_navigation' => _x( 'Services list navigation', 'Screen reader text for the pagination heading on the post type listing screen', 'astra-child' ),
			'items_list'            => _x( 'Services list', 'Screen reader text for the items list heading on the post type listing screen', 'astra-child' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => true,
			'show_in_admin_bar'  => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'services' ),
			'capability_type'    => 'post',
			'has_archive'        => 'all-services',
			'hierarchical'       => false,
			'menu_position'      => self::MENU_POSITION,
			'menu_icon'          => 'dashicons-admin-tools',
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
	 * Flush rewrite rules after the theme is activated so the service
	 * archive and permalink structure are registered.
	 *
	 * @return void
	 */
	public function flush_rewrites() {
		flush_rewrite_rules();
	}

	/**
	 * Register the "Button information" meta box.
	 *
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'astra_child_service_button',
			__( 'Button information', 'astra-child' ),
			array( $this, 'render_button_info_meta_box' ),
			self::SLUG,
			'side',
			'default'
		);
	}

	/**
	 * Render the "Button information" meta box fields.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render_button_info_meta_box( $post ) {
		wp_nonce_field( 'astra_child_service_button', 'astra_child_service_button_nonce' );

		$url    = get_post_meta( $post->ID, self::META_URL, true );
		$text   = get_post_meta( $post->ID, self::META_TEXT, true );
		$newtab = get_post_meta( $post->ID, self::META_NEW_TAB, true );
		?>
		<p>
			<label for="astra_child_service_button_url"><strong><?php esc_html_e( 'URL', 'astra-child' ); ?></strong></label><br />
			<input type="url" id="astra_child_service_button_url" name="astra_child_service_button_url" value="<?php echo esc_attr( $url ); ?>" class="widefat" placeholder="http://wp.test/hello-world" />
			<span class="description"><?php esc_html_e( 'Link the card button points to.', 'astra-child' ); ?></span>
		</p>
		<p>
			<label for="astra_child_service_button_text"><strong><?php esc_html_e( 'Text', 'astra-child' ); ?></strong></label><br />
			<input type="text" id="astra_child_service_button_text" name="astra_child_service_button_text" value="<?php echo esc_attr( $text ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'visit hello world', 'astra-child' ); ?>" />
			<span class="description"><?php esc_html_e( 'Button label. Defaults to "Read More" when empty.', 'astra-child' ); ?></span>
		</p>
		<p>
			<label for="astra_child_service_button_new_tab">
				<input type="checkbox" id="astra_child_service_button_new_tab" name="astra_child_service_button_new_tab" value="1" <?php checked( '1', $newtab ); ?> />
				<?php esc_html_e( 'Open in new tab', 'astra-child' ); ?>
			</label>
		</p>
		<?php
	}

	/**
	 * Save the "Button information" meta box fields.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_button_info( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$nonce = isset( $_POST['astra_child_service_button_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['astra_child_service_button_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'astra_child_service_button' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$url = isset( $_POST['astra_child_service_button_url'] ) ? esc_url_raw( wp_unslash( $_POST['astra_child_service_button_url'] ) ) : '';
		$text = isset( $_POST['astra_child_service_button_text'] ) ? sanitize_text_field( wp_unslash( $_POST['astra_child_service_button_text'] ) ) : '';

		update_post_meta( $post_id, self::META_URL, $url );
		update_post_meta( $post_id, self::META_TEXT, $text );
		update_post_meta( $post_id, self::META_NEW_TAB, isset( $_POST['astra_child_service_button_new_tab'] ) ? '1' : '0' );
	}

	/**
	 * Get the singleton instance.
	 *
	 * @return Services_Post_Type
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
