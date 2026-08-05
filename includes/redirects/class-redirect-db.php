<?php

namespace AstraChild\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Database layer for the redirect management system.
 *
 * Handles table creation, CRUD operations, lookups and caching.
 * All queries use prepared statements.
 *
 * @since 1.0.0
 */
final class Redirect_DB {

	const TABLE              = 'astra_redirects';
	const VERSION            = '1.0.0';
	const DB_VERSION_OPTION  = 'astra_child_redirects_db_version';
	const CACHE_GROUP        = 'astra_child_redirects';
	const CACHE_TTL          = DAY_IN_SECONDS;

	const STATUSES = array(
		301,
		302,
		307,
		308,
	);

	/**
	 * Singleton instance.
	 *
	 * @var Redirect_DB|null
	 */
	private static $instance;

	/**
	 * Full prefixed table name.
	 *
	 * @var string|null
	 */
	private $table_name;

	/**
	 * Get the singleton instance.
	 *
	 * @return Redirect_DB
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {
		global $wpdb;

		$this->table_name = $wpdb->prefix . self::TABLE;
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
	 * Get the prefixed table name.
	 *
	 * @return string
	 */
	public function table_name() {
		return (string) $this->table_name;
	}

	/**
	 * Create/upgrade the table when required (runs at most once per version).
	 *
	 * @return bool True when the table is present after this call.
	 */
	public function maybe_create_table() {
		$installed = get_option( self::DB_VERSION_OPTION, '' );

		if ( self::VERSION === $installed ) {
			return true;
		}

		$created = $this->create_table();
		update_option( self::DB_VERSION_OPTION, self::VERSION );

		return $created;
	}

	/**
	 * Create the database table using dbDelta().
	 *
	 * @return bool
	 */
	private function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$table           = $this->table_name();

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL DEFAULT '',
			old_url VARCHAR(2048) NOT NULL DEFAULT '',
			new_url VARCHAR(2048) NOT NULL DEFAULT '',
			post_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			post_type VARCHAR(64) NOT NULL DEFAULT '',
			status SMALLINT(5) UNSIGNED NOT NULL DEFAULT 301,
			created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
			PRIMARY KEY  (id),
			KEY old_url (old_url(191)),
			KEY new_url (new_url(191)),
			KEY post_id (post_id),
			KEY post_type (post_type),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );

		return $this->table_exists();
	}

	/**
	 * Check whether the table exists.
	 *
	 * @return bool
	 */
	private function table_exists() {
		global $wpdb;

		$table = $this->table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ), ARRAY_N );

		return null !== $row;
	}

	/**
	 * Fetch a single redirect by ID.
	 *
	 * @param int $id Redirect ID.
	 * @return array|null
	 */
	public function get( $id ) {
		global $wpdb;

		$id = absint( $id );

		if ( $id <= 0 ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table_name()} WHERE id = %d LIMIT 1", $id ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get the ID for a given old URL (first match).
	 *
	 * @param string $old_url Old URL.
	 * @return int
	 */
	public function get_id_by_old_url( $old_url ) {
		global $wpdb;

		$id = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$this->table_name()} WHERE old_url = %s LIMIT 1", $old_url )
		);

		return (int) $id;
	}

	/**
	 * Check whether an old URL already exists.
	 *
	 * @param string $old_url    Old URL to check.
	 * @param int    $exclude_id Optional redirect ID to exclude (used when editing).
	 * @return bool
	 */
	public function exists_old_url( $old_url, $exclude_id = 0 ) {
		global $wpdb;

		$sql   = "SELECT id FROM {$this->table_name()} WHERE old_url = %s";
		$args  = array( $old_url );
		$exclude_id = absint( $exclude_id );

		if ( $exclude_id > 0 ) {
			$sql   .= ' AND id != %d';
			$args[] = $exclude_id;
		}

		$sql  .= ' LIMIT 1';
		$found = $wpdb->get_var( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return ! empty( $found );
	}

	/**
	 * Insert a new redirect record.
	 *
	 * @param array $data Redirect data (name, old_url, new_url, post_id, post_type, status).
	 * @return int|false The new ID, or false on failure.
	 */
	public function insert( array $data ) {
		global $wpdb;

		$clean = $this->sanitize_data( $data );
		$now   = current_time( 'mysql' );

		$row = array_merge(
			array(
				'name'       => '',
				'old_url'    => '',
				'new_url'    => '',
				'post_id'    => 0,
				'post_type'  => '',
				'status'     => 301,
				'created_at' => $now,
				'updated_at' => $now,
			),
			$clean
		);

		$result = $wpdb->insert( $this->table_name(), $row );

		if ( false === $result ) {
			return false;
		}

		$this->flush_cache();

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing redirect record.
	 *
	 * @param int   $id   Redirect ID.
	 * @param array $data Redirect data to update.
	 * @return bool
	 */
	public function update( $id, array $data ) {
		global $wpdb;

		$id = absint( $id );

		if ( $id <= 0 ) {
			return false;
		}

		$row = $this->sanitize_data( $data );

		if ( empty( $row ) ) {
			return false;
		}

		$row['updated_at'] = current_time( 'mysql' );

		$result = $wpdb->update(
			$this->table_name(),
			$row,
			array( 'id' => $id ),
			null,
			array( '%d' )
		);

		if ( false !== $result ) {
			$this->flush_cache();
		}

		return false !== $result;
	}

	/**
	 * Delete a single redirect record.
	 *
	 * @param int $id Redirect ID.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;

		$id = absint( $id );

		if ( $id <= 0 ) {
			return false;
		}

		$deleted = $wpdb->delete( $this->table_name(), array( 'id' => $id ), array( '%d' ) );

		if ( $deleted ) {
			$this->flush_cache();
		}

		return (bool) $deleted;
	}

	/**
	 * Delete multiple redirect records.
	 *
	 * @param array $ids List of redirect IDs.
	 * @return int Number of deleted rows.
	 */
	public function bulk_delete( array $ids ) {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

		if ( empty( $ids ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql          = $wpdb->prepare(
			"DELETE FROM {$this->table_name()} WHERE id IN ({$placeholders})",
			$ids
		);

		$deleted = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		if ( $deleted ) {
			$this->flush_cache();
		}

		return (int) $deleted;
	}

	/**
	 * Get distinct post types present in the table.
	 *
	 * @return array
	 */
	public function get_post_types() {
		global $wpdb;

		$types = $wpdb->get_col(
			"SELECT DISTINCT post_type FROM {$this->table_name()} WHERE post_type != '' ORDER BY post_type ASC"
		);

		return array_map( 'strval', $types );
	}

	/**
	 * Query redirects with search, filtering, sorting and pagination.
	 *
	 * @param array $args {
	 *     @type string $search    Free-text search term.
	 *     @type string $post_type Post type filter.
	 *     @type string $orderby   Sort column (whitelisted).
	 *     @type string $order     ASC or DESC.
	 *     @type int    $per_page  Items per page.
	 *     @type int    $paged     Current page.
	 * }
	 * @return array{items: array, total: int}
	 */
	public function find( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'search'    => '',
			'post_type' => '',
			'orderby'   => 'id',
			'order'     => 'DESC',
			'per_page'  => 20,
			'paged'     => 1,
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$params = array();

		if ( '' !== $args['search'] ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '(name LIKE %s OR old_url LIKE %s OR new_url LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		if ( '' !== $args['post_type'] ) {
			$where[]  = 'post_type = %s';
			$params[] = $args['post_type'];
		}

		$orderby = $this->sanitize_orderby( $args['orderby'] );
		$order   = ( 'ASC' === strtoupper( (string) $args['order'] ) ) ? 'ASC' : 'DESC';

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$this->table_name()} WHERE {$where_sql}";
		if ( ! empty( $params ) ) {
			$count_sql = $wpdb->prepare( $count_sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		$per_page = max( 1, absint( $args['per_page'] ) );
		$paged    = max( 1, absint( $args['paged'] ) );
		$offset   = ( $paged - 1 ) * $per_page;

		$select_sql = "SELECT * FROM {$this->table_name()} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT {$offset}, {$per_page}";
		if ( ! empty( $params ) ) {
			$select_sql = $wpdb->prepare( $select_sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$items = $wpdb->get_results( $select_sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		return array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Look up a redirect by request path.
	 *
	 * Reconstructs candidate absolute URLs (http/https, with/without trailing
	 * slash) so the lookup can use the old_url index. Results are cached.
	 *
	 * @param string $path Request path, e.g. "/old-page".
	 * @return array|null Redirect row or null.
	 */
	public function find_by_path( $path ) {
		$path = $this->normalize_lookup_path( (string) $path );

		if ( '' === $path ) {
			return null;
		}

		$cache_key = 'path_' . md5( $path );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : null;
		}

		$candidates = $this->build_candidate_urls( $path );

		if ( empty( $candidates ) ) {
			return null;
		}

		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $candidates ), '%s' ) );
		$row          = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, old_url, new_url, status FROM {$this->table_name()} WHERE old_url IN ({$placeholders}) ORDER BY id ASC LIMIT 1",
				$candidates
			),
			ARRAY_A
		);

		$result = is_array( $row ) ? $row : null;

		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Create a redirect from an automatic slug change.
	 *
	 * Updates the destination when the old URL already exists instead of
	 * inserting a duplicate. Preserves an existing manually-set status.
	 *
	 * @param array $data Redirect data.
	 * @return void
	 */
	public function create_from_slug_change( array $data ) {
		$old_url = isset( $data['old_url'] ) ? esc_url_raw( (string) $data['old_url'] ) : '';
		$new_url = isset( $data['new_url'] ) ? esc_url_raw( (string) $data['new_url'] ) : '';

		if ( '' === $old_url || '' === $new_url || $old_url === $new_url ) {
			return;
		}

		$existing_id = $this->get_id_by_old_url( $old_url );

		$row = array(
			'name'      => isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '',
			'old_url'   => $old_url,
			'new_url'   => $new_url,
			'post_id'   => isset( $data['post_id'] ) ? absint( $data['post_id'] ) : 0,
			'post_type' => isset( $data['post_type'] ) ? sanitize_key( (string) $data['post_type'] ) : '',
			'status'    => 301,
		);

		if ( $existing_id > 0 ) {
			$existing = $this->get( $existing_id );

			if ( is_array( $existing ) ) {
				$row['status'] = absint( $existing['status'] );
			}

			$this->update( $existing_id, $row );

			return;
		}

		$this->insert( $row );
	}

	/**
	 * Flush the redirect cache group.
	 *
	 * @return void
	 */
	public function flush_cache() {
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( self::CACHE_GROUP );
			return;
		}

		wp_cache_flush();
	}

	/**
	 * Normalize a lookup path (strip home sub-directory, decode, leading slash).
	 *
	 * @param string $path Raw request path.
	 * @return string
	 */
	private function normalize_lookup_path( $path ) {
		$path = rawurldecode( $path );

		$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

		if ( '' !== $home_path && '/' !== $home_path && 0 === strpos( $path, $home_path ) ) {
			$path = substr( $path, strlen( $home_path ) - 1 );
		}

		return '/' . ltrim( $path, '/' );
	}

	/**
	 * Build candidate absolute URLs for a given path.
	 *
	 * @param string $path Normalized path.
	 * @return array
	 */
	private function build_candidate_urls( $path ) {
		$path       = rtrim( $path, '/' );
		$candidates = array();

		foreach ( array( $path, $path . '/' ) as $candidate_path ) {
			$candidates[] = home_url( $candidate_path );
			$candidates[] = set_url_scheme( home_url( $candidate_path ), 'http' );
			$candidates[] = set_url_scheme( home_url( $candidate_path ), 'https' );
		}

		return array_values( array_unique( $candidates ) );
	}

	/**
	 * Whitelist allowed sort columns.
	 *
	 * @param string $orderby Requested sort column.
	 * @return string
	 */
	private function sanitize_orderby( $orderby ) {
		$allowed = array( 'id', 'name', 'old_url', 'new_url', 'post_type', 'status', 'created_at', 'updated_at' );

		return in_array( $orderby, $allowed, true ) ? $orderby : 'id';
	}

	/**
	 * Sanitize redirect data before it touches the database.
	 *
	 * @param array $data Raw data.
	 * @return array
	 */
	private function sanitize_data( array $data ) {
		$allowed = array( 'name', 'old_url', 'new_url', 'post_id', 'post_type', 'status' );
		$clean   = array();

		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}

			switch ( $key ) {
				case 'name':
					$clean[ $key ] = sanitize_text_field( (string) $data[ $key ] );
					break;

				case 'old_url':
				case 'new_url':
					$clean[ $key ] = esc_url_raw( (string) $data[ $key ] );
					break;

				case 'post_id':
					$clean[ $key ] = absint( $data[ $key ] );
					break;

				case 'post_type':
					$clean[ $key ] = sanitize_key( (string) $data[ $key ] );
					break;

				case 'status':
					$clean[ $key ] = absint( $data[ $key ] );
					break;
			}
		}

		return $clean;
	}
}
