<?php

namespace AstraChild\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Automatic redirect creation on slug (post_name) changes.
 *
 * Works for posts, pages, public custom post types and any post types
 * registered later by themes or plugins. Autosaves, revisions and non-public
 * statuses are ignored.
 *
 * @since 1.0.0
 */
final class Redirect_Hooks {

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

		add_action( 'post_updated', array( $this, 'maybe_create_redirect' ), 10, 3 );
	}

	/**
	 * Create a redirect when a post slug changes between two published states.
	 *
	 * @param int      $post_id     Post ID.
	 * @param WP_Post  $post_after  Post object after the update.
	 * @param WP_Post  $post_before Post object before the update.
	 * @return void
	 */
	public function maybe_create_redirect( $post_id, $post_after, $post_before ) {
		if ( 'publish' !== $post_before->post_status || 'publish' !== $post_after->post_status ) {
			return;
		}

		if ( $post_before->post_name === $post_after->post_name ) {
			return;
		}

		if ( ! apply_filters( 'astra_child_create_redirect_on_slug_change', true, $post_after, $post_before ) ) {
			return;
		}

		$post_type_object = get_post_type_object( $post_after->post_type );

		if ( ! $post_type_object || empty( $post_type_object->public ) ) {
			return;
		}

		$old_permalink = get_permalink( $post_before );
		$new_permalink = get_permalink( $post_after );

		if ( ! $old_permalink || ! $new_permalink || $old_permalink === $new_permalink ) {
			return;
		}

		$this->db->create_from_slug_change(
			array(
				'name'      => $post_before->post_title,
				'old_url'   => $old_permalink,
				'new_url'   => $new_permalink,
				'post_id'   => (int) $post_id,
				'post_type' => $post_after->post_type,
			)
		);

		$this->maybe_create_child_redirects( $post_before, $post_after );
	}

	/**
	 * Create redirects for published descendants of hierarchical post types
	 * when a parent slug changes.
	 *
	 * @param WP_Post $post_before Post object before the update.
	 * @param WP_Post $post_after  Post object after the update.
	 * @return void
	 */
	private function maybe_create_child_redirects( $post_before, $post_after ) {
		$post_type_object = get_post_type_object( $post_after->post_type );

		if ( ! $post_type_object || empty( $post_type_object->hierarchical ) ) {
			return;
		}

		$children = $this->get_published_descendants( (int) $post_after->ID, $post_after->post_type );

		if ( empty( $children ) ) {
			return;
		}

		foreach ( $children as $child ) {
			$new_permalink = get_permalink( $child );
			$old_permalink = $this->swap_slug_segment(
				(string) $new_permalink,
				(string) $post_after->post_name,
				(string) $post_before->post_name
			);

			if ( '' === $old_permalink || $old_permalink === $new_permalink ) {
				continue;
			}

			$this->db->create_from_slug_change(
				array(
					'name'      => $child->post_title,
					'old_url'   => $old_permalink,
					'new_url'   => $new_permalink,
					'post_id'   => (int) $child->ID,
					'post_type' => $child->post_type,
				)
			);
		}
	}

	/**
	 * Get all published descendants (recursively) of a post.
	 *
	 * @param int    $post_id   Parent post ID.
	 * @param string $post_type Post type.
	 * @return array List of WP_Post objects.
	 */
	private function get_published_descendants( $post_id, $post_type ) {
		global $wpdb;

		$found    = array();
		$frontier = array( $post_id );

		while ( ! empty( $frontier ) ) {
			$ids          = array_map( 'absint', $frontier );
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

			$children = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_parent IN ({$placeholders}) AND post_status = 'publish'",
					array_merge( array( $post_type ), $ids )
				)
			);

			if ( empty( $children ) ) {
				break;
			}

			$frontier = $children;

			foreach ( $children as $child_id ) {
				$found[] = (int) $child_id;
			}
		}

		if ( empty( $found ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'post__in'       => $found,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		return is_array( $posts ) ? $posts : array();
	}

	/**
	 * Replace the first occurrence of a parent slug segment in a URL path.
	 *
	 * @param string $url      Current URL (new permalink).
	 * @param string $new_slug New parent slug.
	 * @param string $old_slug Old parent slug.
	 * @return string
	 */
	private function swap_slug_segment( $url, $new_slug, $old_slug ) {
		if ( '' === $new_slug || '' === $old_slug || '' === $url ) {
			return '';
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );

		if ( '' === $path ) {
			return '';
		}

		$needle = '/' . $new_slug . '/';
		$pos    = strpos( $path, $needle );

		if ( false === $pos ) {
			return '';
		}

		$new_path = substr_replace( $path, '/' . $old_slug . '/', $pos, strlen( $needle ) );

		$scheme = (string) wp_parse_url( $url, PHP_URL_SCHEME );
		$host   = (string) wp_parse_url( $url, PHP_URL_HOST );
		$query  = (string) wp_parse_url( $url, PHP_URL_QUERY );

		$rebuilt = $scheme . '://' . $host . $new_path;

		if ( '' !== $query ) {
			$rebuilt .= '?' . $query;
		}

		return $rebuilt;
	}
}
