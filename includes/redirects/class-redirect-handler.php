<?php

namespace AstraChild\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end redirect executor.
 *
 * Runs before the 404 page is rendered, looks up the requested URL in the
 * redirect table and performs the configured redirect while preserving query
 * parameters.
 *
 * @since 1.0.0
 */
final class Redirect_Handler {

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

		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 0 );
	}

	/**
	 * Redirect a matching 404 request if a redirect record exists.
	 *
	 * @return void
	 */
	public function maybe_redirect() {
		if ( is_admin() || ! is_404() ) {
			return;
		}

		$path  = $this->get_request_path();
		$query = $this->get_request_query();

		if ( '' === $path || '/' === $path ) {
			return;
		}

		$record = $this->db->find_by_path( $path );

		if ( empty( $record ) || empty( $record['new_url'] ) ) {
			return;
		}

		$target = $this->build_target_url( (string) $record['new_url'], $query );
		$status = $this->sanitize_status( isset( $record['status'] ) ? $record['status'] : 301 );

		if ( $this->is_same_target( $path, $query, $target ) ) {
			return;
		}

		$this->allow_redirect_host( $target );

		wp_safe_redirect( $target, $status );
		exit;
	}

	/**
	 * Get the decoded request path.
	 *
	 * @return string
	 */
	private function get_request_path() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';

		if ( ! is_string( $request_uri ) ) {
			$request_uri = '';
		}

		$path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		$path = rawurldecode( $path );

		return '' !== $path ? $path : '/';
	}

	/**
	 * Get the request query string (without leading "?").
	 *
	 * @return string
	 */
	private function get_request_query() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';

		if ( ! is_string( $request_uri ) ) {
			$request_uri = '';
		}

		return (string) wp_parse_url( $request_uri, PHP_URL_QUERY );
	}

	/**
	 * Build the final redirect target, preserving the request query string.
	 *
	 * @param string $new_url Destination URL from the redirect record.
	 * @param string $query   Original query string.
	 * @return string
	 */
	private function build_target_url( $new_url, $query ) {
		if ( '' === $query ) {
			return $new_url;
		}

		$separator = ( false !== strpos( $new_url, '?' ) ) ? '&' : '?';

		return $new_url . $separator . $query;
	}

	/**
	 * Guard against redirect loops.
	 *
	 * @param string $path   Request path.
	 * @param string $query  Request query string.
	 * @param string $target Redirect target URL.
	 * @return bool
	 */
	private function is_same_target( $path, $query, $target ) {
		$target_path  = rawurldecode( (string) wp_parse_url( $target, PHP_URL_PATH ) );
		$target_query = (string) wp_parse_url( $target, PHP_URL_QUERY );

		return $this->normalize_path( $path ) === $this->normalize_path( $target_path )
			&& $query === $target_query;
	}

	/**
	 * Normalize a path for comparison.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private function normalize_path( $path ) {
		$path = rawurldecode( $path );
		$path = '/' . ltrim( $path, '/' );

		return rtrim( $path, '/' );
	}

	/**
	 * Sanitize and validate the redirect status code.
	 *
	 * @param mixed $status Status code.
	 * @return int
	 */
	private function sanitize_status( $status ) {
		$status = absint( $status );

		return in_array( $status, Redirect_DB::STATUSES, true ) ? $status : 301;
	}

	/**
	 * Allow the redirect target host (supports external destinations with
	 * wp_safe_redirect()).
	 *
	 * @param string $target Redirect target URL.
	 * @return void
	 */
	private function allow_redirect_host( $target ) {
		$host = (string) wp_parse_url( $target, PHP_URL_HOST );

		if ( '' === $host ) {
			return;
		}

		add_filter(
			'allowed_redirect_hosts',
			function ( $hosts ) use ( $host ) {
				$hosts[] = $host;

				return array_unique( $hosts );
			}
		);
	}
}
