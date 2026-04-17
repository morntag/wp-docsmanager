<?php
/**
 * Rescan action handler.
 *
 * @package Morntag\WpDocsManager\Services
 */

namespace Morntag\WpDocsManager\Services;

/**
 * Clears the two FileScanner transients so the next page load rebuilds them
 * from disk. Wired to a nonce-guarded "Rescan now" action on the
 * Documentation admin page.
 */
class RescanHandler {

	/**
	 * Admin-post action slug the "Rescan now" form submits to.
	 */
	public const ACTION = 'morntag_docs_rescan';

	/**
	 * Nonce action used by the rescan form.
	 */
	public const NONCE_ACTION = 'morntag_docs_rescan';

	/**
	 * FileScanner transient names cleared on rescan.
	 *
	 * @var string[]
	 */
	private const TRANSIENTS = array(
		'morntag_docs_module_readmes_cache',
		'morntag_docs_files_cache',
	);

	/**
	 * Invalidate the scanner caches.
	 */
	public static function handle(): void {
		foreach ( self::TRANSIENTS as $transient ) {
			delete_transient( $transient );
		}
	}

	/**
	 * Handle the `admin-post.php?action=morntag_docs_rescan` submission.
	 *
	 * Verifies capability + nonce, invokes handle(), then redirects back to
	 * the Documentation page with a success query argument. Exits on
	 * completion.
	 */
	public static function handle_request(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to rescan documentation.', 'wp-docsmanager' ) );
		}

		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed. Please try again.', 'wp-docsmanager' ) );
		}

		self::handle();

		$redirect_url = add_query_arg(
			array(
				'page'      => 'mcc-documentation',
				'rescanned' => '1',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}
}
