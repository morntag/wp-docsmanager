<?php

namespace Morntag\WpDocsManager\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Phase 2 acceptance: the "Rescan now" action handler deletes both FileScanner
 * transients so the next page load rebuilds them:
 *   - morntag_docs_module_readmes_cache
 *   - morntag_docs_files_cache
 *
 * ASSUMPTION: the implementer exposes a static or instance method
 *   Morntag\WpDocsManager\Services\RescanHandler::handle(): void
 * which performs the transient deletion. If the implementer instead hangs
 * this off SettingsPage, DocsManager, or a different class name, flag and
 * update these tests. The bootstrap's delete_transient() stub records every
 * delete into $GLOBALS['wp_test_deleted_transients'] so we can assert either
 * shape without WordPress.
 */
class RescanActionTest extends TestCase {

	private const CLASS_FQN = 'Morntag\\WpDocsManager\\Services\\RescanHandler';

	protected function setUp(): void {
		wp_test_reset_recorders();
	}

	public function test_rescan_handler_class_exists(): void {
		$this->assertTrue(
			class_exists( self::CLASS_FQN ),
			'Expected class Morntag\\WpDocsManager\\Services\\RescanHandler with a handle() method.'
		);
	}

	public function test_handle_method_exists(): void {
		$this->assertTrue( class_exists( self::CLASS_FQN ) );
		$this->assertTrue(
			method_exists( self::CLASS_FQN, 'handle' ),
			'Expected RescanHandler::handle() method.'
		);
	}

	public function test_admin_post_action_is_registered_by_docsmanager(): void {
		$this->assertTrue( class_exists( self::CLASS_FQN ) );
		$this->assertTrue( class_exists( 'Morntag\\WpDocsManager\\DocsManager' ) );

		// Booting DocsManager::instance() registers the admin-post hook during init().
		\Morntag\WpDocsManager\DocsManager::instance();

		$expected_hook = 'admin_post_morntag_docs_rescan';
		$match         = null;
		foreach ( $GLOBALS['wp_test_actions'] as $entry ) {
			if ( ( $entry['hook'] ?? '' ) !== $expected_hook ) {
				continue;
			}
			$callback = $entry['callback'] ?? null;
			if ( is_array( $callback ) && isset( $callback[0], $callback[1] )
				&& $callback[0] === self::CLASS_FQN
				&& 'handle_request' === $callback[1]
			) {
				$match = $entry;
				break;
			}
		}

		$this->assertNotNull(
			$match,
			'Expected add_action("admin_post_morntag_docs_rescan", [RescanHandler::class, "handle_request"]).'
		);
	}

	public function test_handle_deletes_both_scanner_transients(): void {
		$this->assertTrue( class_exists( self::CLASS_FQN ) );

		// Invoke statically first, fall back to instance if the implementer chose non-static.
		$callable = array( self::CLASS_FQN, 'handle' );
		if ( ! is_callable( $callable ) ) {
			$instance = new ( self::CLASS_FQN )();
			$instance->handle();
		} else {
			$method = new \ReflectionMethod( self::CLASS_FQN, 'handle' );
			if ( $method->isStatic() ) {
				call_user_func( $callable );
			} else {
				$instance = new ( self::CLASS_FQN )();
				$instance->handle();
			}
		}

		$expected = array(
			'morntag_docs_module_readmes_cache',
			'morntag_docs_files_cache',
		);

		foreach ( $expected as $transient ) {
			$this->assertContains(
				$transient,
				$GLOBALS['wp_test_deleted_transients'],
				"RescanHandler::handle() must delete_transient('{$transient}')."
			);
		}
	}
}
