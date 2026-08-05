<?php

namespace AstraChild\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI for the redirect management system.
 *
 * Registers the top-level menu, renders the WP_List_Table screen and the
 * add/edit form, and handles create/update/delete actions.
 *
 * @since 1.0.0
 */
final class Redirect_Admin {

	/**
	 * Available HTTP status codes.
	 *
	 * @var array
	 */
	private $statuses = array(
		301 => '301 - Moved Permanently',
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
	 */
	public function __construct() {
		$this->db = Redirect_DB::get_instance();

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_post_redirect_save', array( $this, 'handle_form_submission' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );
	}

	/**
	 * Register the top-level Redirects menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Redirects', 'astra-child' ),
			__( 'Redirects', 'astra-child' ),
			'manage_options',
			'redirects',
			array( $this, 'render_admin_page' ),
			'dashicons-randomize',
			25
		);
	}

	/**
	 * Render the admin page (list or edit form).
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'astra-child' ) );
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		if ( 'edit' === $action ) {
			$this->render_edit_form();
			return;
		}

		$this->render_list_table();
	}

	/**
	 * Render the list table screen.
	 *
	 * @return void
	 */
	private function render_list_table() {
		$list_table = new Redirect_List_Table( $this->db );
		$list_table->prepare_items();

		$post_types = $this->db->get_post_types();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Redirects', 'astra-child' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=redirects&action=edit' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'astra-child' ); ?></a>
			<hr class="wp-header-end" />

			<form method="get">
				<input type="hidden" name="page" value="redirects" />
				<?php $list_table->search_box( __( 'Search redirects', 'astra-child' ), 'redirect_search' ); ?>

				<?php if ( ! empty( $post_types ) ) : ?>
					<label class="screen-reader-text" for="redirect_post_type_filter"><?php esc_html_e( 'Filter by post type', 'astra-child' ); ?></label>
					<select name="post_type" id="redirect_post_type_filter">
						<option value=""><?php esc_html_e( 'All post types', 'astra-child' ); ?></option>
						<?php foreach ( $post_types as $post_type ) : ?>
							<option value="<?php echo esc_attr( $post_type ); ?>" <?php selected( isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '', $post_type ); ?>>
								<?php echo esc_html( $post_type ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php submit_button( __( 'Filter', 'astra-child' ), 'secondary', 'filter_redirects', false ); ?>
				<?php endif; ?>

				<?php wp_nonce_field( 'redirect_bulk_delete', '_wpnonce' ); ?>
				<?php $list_table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the add/edit form.
	 *
	 * @return void
	 */
	private function render_edit_form() {
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$record = $id > 0 ? $this->db->get( $id ) : null;

		$name    = '';
		$old_url = '';
		$new_url = '';
		$status  = 301;

		if ( is_array( $record ) ) {
			$name    = (string) $record['name'];
			$old_url = (string) $record['old_url'];
			$new_url = (string) $record['new_url'];
			$status  = absint( $record['status'] );
		}

		$is_edit = is_array( $record ) && $id > 0;
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">
				<?php echo $is_edit ? esc_html__( 'Edit Redirect', 'astra-child' ) : esc_html__( 'Add New Redirect', 'astra-child' ); ?>
			</h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=redirects' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Back to Redirects', 'astra-child' ); ?></a>
			<hr class="wp-header-end" />

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="redirect-form">
				<input type="hidden" name="action" value="redirect_save" />
				<?php if ( $is_edit ) : ?>
					<input type="hidden" name="redirect_id" value="<?php echo esc_attr( $id ); ?>" />
				<?php endif; ?>
				<?php wp_nonce_field( 'redirect_save', 'redirect_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="redirect_name"><?php esc_html_e( 'Name', 'astra-child' ); ?></label>
						</th>
						<td>
							<input type="text" name="name" id="redirect_name" class="regular-text" value="<?php echo esc_attr( $name ); ?>" placeholder="<?php esc_attr_e( 'Optional description', 'astra-child' ); ?>" />
							<p class="description"><?php esc_html_e( 'An optional descriptive title for this redirect.', 'astra-child' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="old_url"><?php esc_html_e( 'Old URL', 'astra-child' ); ?> <span class="required">*</span></label>
						</th>
						<td>
							<input type="text" name="old_url" id="old_url" class="regular-text large-text" value="<?php echo esc_attr( $old_url ); ?>" required />
							<p class="description"><?php esc_html_e( 'The source URL to redirect from. Example: https://example.com/old-page/', 'astra-child' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="new_url"><?php esc_html_e( 'New URL', 'astra-child' ); ?> <span class="required">*</span></label>
						</th>
						<td>
							<input type="text" name="new_url" id="new_url" class="regular-text large-text" value="<?php echo esc_attr( $new_url ); ?>" required />
							<p class="description"><?php esc_html_e( 'The destination URL to redirect to. Example: https://example.com/new-page/', 'astra-child' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="status"><?php esc_html_e( 'Status Code', 'astra-child' ); ?></label>
						</th>
						<td>
							<select name="status" id="status">
								<?php foreach ( $this->statuses as $code => $label ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $status, $code ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( '301 for permanent moves. 302 for temporary. 307/308 preserve the request method.', 'astra-child' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button( $is_edit ? __( 'Update Redirect', 'astra-child' ) : __( 'Add Redirect', 'astra-child' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle the add/edit form submission (admin-post.php).
	 *
	 * @return void
	 */
	public function handle_form_submission() {
		$redirect_url = admin_url( 'admin.php?page=redirects' );

		if ( ! isset( $_POST['redirect_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['redirect_nonce'] ) ), 'redirect_save' ) ) {
			$this->redirect_with_notice( $redirect_url, 'nonce_failed', 'error' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'astra-child' ) );
		}

		$id      = isset( $_POST['redirect_id'] ) ? absint( $_POST['redirect_id'] ) : 0;
		$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$old_url = isset( $_POST['old_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['old_url'] ) ) ) : '';
		$new_url = isset( $_POST['new_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['new_url'] ) ) ) : '';
		$status  = isset( $_POST['status'] ) ? absint( $_POST['status'] ) : 301;

		if ( ! in_array( $status, Redirect_DB::STATUSES, true ) ) {
			$this->redirect_with_notice( $redirect_url, 'invalid_status', 'error' );
		}

		if ( '' === $old_url || '' === $new_url ) {
			$this->redirect_with_notice( $redirect_url, 'missing_urls', 'error' );
		}

		if ( ! $this->is_valid_url( $old_url ) ) {
			$this->redirect_with_notice( $redirect_url, 'invalid_old_url', 'error' );
		}

		if ( ! $this->is_valid_url( $new_url ) ) {
			$this->redirect_with_notice( $redirect_url, 'invalid_new_url', 'error' );
		}

		if ( $old_url === $new_url ) {
			$this->redirect_with_notice( $redirect_url, 'same_url', 'error' );
		}

		if ( $this->db->exists_old_url( $old_url, $id ) ) {
			$this->redirect_with_notice( $redirect_url, 'duplicate_old_url', 'error' );
		}

		$data = array(
			'name'    => $name,
			'old_url' => $old_url,
			'new_url' => $new_url,
			'status'  => $status,
		);

		if ( $id > 0 ) {
			$saved  = $this->db->update( $id, $data );
			$notice = $saved ? 'updated' : 'update_failed';
		} else {
			$data['post_id']   = 0;
			$data['post_type'] = '';
			$saved             = (bool) $this->db->insert( $data );
			$notice            = $saved ? 'added' : 'insert_failed';
		}

		$this->redirect_with_notice( $redirect_url, $notice, $saved ? 'success' : 'error' );
	}

	/**
	 * Handle GET actions (single delete, bulk delete).
	 *
	 * @return void
	 */
	public function handle_actions() {
		if ( ! isset( $_GET['page'] ) || 'redirects' !== $_GET['page'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

		if ( 'delete' === $action ) {
			$this->handle_single_delete();
		}

		if ( 'bulk_delete' === $action ) {
			$this->handle_bulk_delete();
		}
	}

	/**
	 * Handle a single delete request.
	 *
	 * @return void
	 */
	private function handle_single_delete() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		if ( $id <= 0 ) {
			$this->redirect_with_notice( admin_url( 'admin.php?page=redirects' ), 'invalid_id', 'error' );
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'redirect_delete_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'astra-child' ) );
		}

		$deleted = $this->db->delete( $id );

		$this->redirect_with_notice(
			admin_url( 'admin.php?page=redirects' ),
			$deleted ? 'deleted' : 'delete_failed',
			$deleted ? 'success' : 'error'
		);
	}

	/**
	 * Handle a bulk delete request.
	 *
	 * @return void
	 */
	private function handle_bulk_delete() {
		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_key( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'redirect_bulk_delete' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'astra-child' ) );
		}

		if ( ! isset( $_REQUEST['redirect_ids'] ) || ! is_array( $_REQUEST['redirect_ids'] ) ) {
			$this->redirect_with_notice( admin_url( 'admin.php?page=redirects' ), 'no_selection', 'error' );
		}

		$ids = array_filter( array_map( 'absint', wp_unslash( $_REQUEST['redirect_ids'] ) ) );

		if ( empty( $ids ) ) {
			$this->redirect_with_notice( admin_url( 'admin.php?page=redirects' ), 'no_selection', 'error' );
		}

		$this->db->bulk_delete( $ids );

		$this->redirect_with_notice( admin_url( 'admin.php?page=redirects' ), 'bulk_deleted', 'success' );
	}

	/**
	 * Validate that a URL has a supported scheme and a host.
	 *
	 * @param string $url URL to validate.
	 * @return bool
	 */
	private function is_valid_url( $url ) {
		$parsed = wp_parse_url( $url );

		if ( ! isset( $parsed['scheme'], $parsed['host'] ) ) {
			return false;
		}

		if ( ! in_array( $parsed['scheme'], array( 'http', 'https' ), true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Redirect back with a transient admin notice.
	 *
	 * @param string $url    Target URL.
	 * @param string $notice Notice key.
	 * @param string $type   Notice type (success|error).
	 * @return void
	 */
	private function redirect_with_notice( $url, $notice, $type ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'redirect_message' => $notice,
					'redirect_type'    => $type,
				),
				$url
			)
		);
		exit;
	}

	/**
	 * Render admin notices for redirect actions.
	 *
	 * @return void
	 */
	public function render_admin_notices() {
		if ( ! isset( $_GET['page'] ) || 'redirects' !== $_GET['page'] ) {
			return;
		}

		$message = isset( $_GET['redirect_message'] ) ? sanitize_key( wp_unslash( $_GET['redirect_message'] ) ) : '';
		$type    = isset( $_GET['redirect_type'] ) && 'error' === $_GET['redirect_type'] ? 'error' : 'success';

		if ( '' === $message ) {
			return;
		}

		$messages = array(
			'added'             => __( 'Redirect added successfully.', 'astra-child' ),
			'updated'           => __( 'Redirect updated successfully.', 'astra-child' ),
			'deleted'           => __( 'Redirect deleted.', 'astra-child' ),
			'bulk_deleted'      => __( 'Redirects deleted.', 'astra-child' ),
			'no_selection'      => __( 'No redirects selected.', 'astra-child' ),
			'invalid_id'        => __( 'Invalid redirect ID.', 'astra-child' ),
			'nonce_failed'      => __( 'Security check failed.', 'astra-child' ),
			'invalid_status'    => __( 'Invalid redirect status.', 'astra-child' ),
			'missing_urls'      => __( 'Both Old URL and New URL are required.', 'astra-child' ),
			'invalid_old_url'   => __( 'The Old URL is not valid.', 'astra-child' ),
			'invalid_new_url'   => __( 'The New URL is not valid.', 'astra-child' ),
			'duplicate_old_url' => __( 'A redirect with this Old URL already exists.', 'astra-child' ),
			'same_url'          => __( 'Old URL and New URL cannot be the same.', 'astra-child' ),
			'insert_failed'     => __( 'Failed to add the redirect.', 'astra-child' ),
			'update_failed'     => __( 'Failed to update the redirect.', 'astra-child' ),
			'delete_failed'     => __( 'Failed to delete the redirect.', 'astra-child' ),
		);

		if ( ! isset( $messages[ $message ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $messages[ $message ] )
		);
	}
}
