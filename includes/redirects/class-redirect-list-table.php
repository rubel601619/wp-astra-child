<?php

namespace AstraChild\Redirects;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List table for the redirect admin screen.
 *
 * @since 1.0.0
 */
final class Redirect_List_Table extends \WP_List_Table {

	const STATUS_LABELS = array(
		301 => '301 Moved Permanently',
		302 => '302 Found',
		307 => '307 Temporary Redirect',
		308 => '308 Permanent Redirect',
	);

	/**
	 * Database instance.
	 *
	 * @var Redirect_DB
	 */
	private $db;

	/**
	 * Constructor.
	 *
	 * @param Redirect_DB $db Database instance.
	 */
	public function __construct( Redirect_DB $db ) {
		$this->db = $db;

		parent::__construct(
			array(
				'singular' => 'redirect',
				'plural'   => 'redirects',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Define table columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'name'       => __( 'Name', 'astra-child' ),
			'old_url'    => __( 'Old URL', 'astra-child' ),
			'new_url'    => __( 'New URL', 'astra-child' ),
			'post_type'  => __( 'Post Type', 'astra-child' ),
			'status'     => __( 'Status', 'astra-child' ),
			'created_at' => __( 'Created At', 'astra-child' ),
			'updated_at' => __( 'Updated At', 'astra-child' ),
			'actions'    => __( 'Actions', 'astra-child' ),
		);
	}

	/**
	 * Define sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'name'       => array( 'name', false ),
			'old_url'    => array( 'old_url', false ),
			'new_url'    => array( 'new_url', false ),
			'post_type'  => array( 'post_type', false ),
			'status'     => array( 'status', false ),
			'created_at' => array( 'created_at', false ),
			'updated_at' => array( 'updated_at', false ),
		);
	}

	/**
	 * Define bulk actions.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return array(
			'bulk_delete' => __( 'Delete', 'astra-child' ),
		);
	}

	/**
	 * Prepare items for display.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$per_page = $this->get_items_per_page( 'redirects_per_page', 20 );
		$paged    = $this->get_pagenum();

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$args = array(
			'search'    => isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '',
			'post_type' => isset( $_REQUEST['post_type'] ) ? sanitize_key( wp_unslash( $_REQUEST['post_type'] ) ) : '',
			'orderby'   => isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'id',
			'order'     => isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'DESC',
			'per_page'  => $per_page,
			'paged'     => $paged,
		);

		$result = $this->db->find( $args );

		$this->items = $result['items'];

		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $result['total'] / $per_page ),
			)
		);
	}

	/**
	 * Default column renderer.
	 *
	 * @param array  $item        Current row.
	 * @param string $column_name Column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		if ( isset( $item[ $column_name ] ) ) {
			return esc_html( (string) $item[ $column_name ] );
		}

		return '&mdash;';
	}

	/**
	 * Render the checkbox column.
	 *
	 * @param array $item Current row.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="redirect_ids[]" value="%d" />',
			absint( $item['id'] )
		);
	}

	/**
	 * Render the name column.
	 *
	 * @param array $item Current row.
	 * @return string
	 */
	public function column_name( $item ) {
		$name = (string) $item['name'];

		if ( '' === $name ) {
			return '&mdash;';
		}

		return esc_html( $name );
	}

	/**
	 * Render the old URL column.
	 *
	 * @param array $item Current row.
	 * @return string
	 */
	public function column_old_url( $item ) {
		return '<code>' . esc_html( (string) $item['old_url'] ) . '</code>';
	}

	/**
	 * Render the new URL column.
	 *
	 * @param array $item Current row.
	 * @return string
	 */
	public function column_new_url( $item ) {
		$url = (string) $item['new_url'];

		return sprintf(
			'<a href="%1$s" target="_blank" rel="noopener">%1$s</a>',
			esc_url( $url )
		);
	}

	/**
	 * Render the post type column.
	 *
	 * @param array $item Current row.
	 * @return string
	 */
	public function column_post_type( $item ) {
		$type = (string) $item['post_type'];

		if ( '' === $type ) {
			return '&mdash;';
		}

		$type_object = get_post_type_object( $type );

		if ( $type_object && ! empty( $type_object->labels->singular_name ) ) {
			return esc_html( $type_object->labels->singular_name );
		}

		return esc_html( $type );
	}

	/**
	 * Render the status column.
	 *
	 * @param array $item Current row.
	 * @return string
	 */
	public function column_status( $item ) {
		$status = absint( $item['status'] );

		if ( isset( self::STATUS_LABELS[ $status ] ) ) {
			return esc_html( self::STATUS_LABELS[ $status ] );
		}

		return esc_html( (string) $status );
	}

	/**
	 * Render the created at column.
	 *
	 * @param array $item Current row.
	 * @return string
	 */
	public function column_created_at( $item ) {
		return $this->format_date( (string) $item['created_at'] );
	}

	/**
	 * Render the updated at column.
	 *
	 * @param array $item Current row.
	 * @return string
	 */
	public function column_updated_at( $item ) {
		return $this->format_date( (string) $item['updated_at'] );
	}

	/**
	 * Render the actions column.
	 *
	 * @param array $item Current row.
	 * @return string
	 */
	public function column_actions( $item ) {
		$id = absint( $item['id'] );

		$edit_url = add_query_arg(
			array(
				'page'   => 'redirects',
				'action' => 'edit',
				'id'     => $id,
			),
			admin_url( 'admin.php' )
		);

		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => 'redirects',
					'action' => 'delete',
					'id'     => $id,
				),
				admin_url( 'admin.php' )
			),
			'redirect_delete_' . $id
		);

		$confirm = __( 'Are you sure you want to delete this redirect?', 'astra-child' );

		return sprintf(
			'<a href="%1$s">%2$s</a> | <a href="%3$s" onclick="return confirm(%4$s);">%5$s</a>',
			esc_url( $edit_url ),
			esc_html__( 'Edit', 'astra-child' ),
			esc_url( $delete_url ),
			esc_js( $confirm ),
			esc_html__( 'Delete', 'astra-child' )
		);
	}

	/**
	 * Format a stored date for display.
	 *
	 * @param string $date Date string.
	 * @return string
	 */
	private function format_date( $date ) {
		$timestamp = strtotime( $date );

		if ( false === $timestamp || 0 === $timestamp ) {
			return '&mdash;';
		}

		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		return esc_html( wp_date( $format, $timestamp ) );
	}
}
