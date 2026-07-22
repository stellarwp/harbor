<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Portal;

use LiquidWeb\Harbor\Harbor;
use LiquidWeb\Harbor\Portal\Activation_Script;
use LiquidWeb\Harbor\Tests\HarborTestCase;
use LiquidWeb\Harbor\Tests\Traits\With_Uopz;
use LiquidWeb\Harbor\Utils\Version;

final class Activation_ScriptTest extends HarborTestCase {

	use With_Uopz;

	protected function tearDown(): void {
		wp_deregister_script( Activation_Script::HANDLE );
		parent::tearDown();
	}

	/**
	 * Tests that the leader registers the shared handle.
	 *
	 * @return void
	 */
	public function test_registers_the_script_when_leader(): void {
		$this->set_class_fn_return( Version::class, 'should_handle', true );

		( new Activation_Script() )->maybe_register();

		$this->assertTrue( wp_script_is( Activation_Script::HANDLE, 'registered' ) );
	}

	/**
	 * Tests that a non-leading instance stays out of the way. Without this,
	 * every active Harbor copy would overwrite the same handle and the version
	 * that ends up serving the file would depend on plugin load order.
	 *
	 * @return void
	 */
	public function test_does_not_register_the_script_when_not_leader(): void {
		$this->set_class_fn_return( Version::class, 'should_handle', false );

		( new Activation_Script() )->maybe_register();

		$this->assertFalse( wp_script_is( Activation_Script::HANDLE, 'registered' ) );
	}

	/**
	 * Tests that the script carries no dependencies. It loads on admin screens
	 * that have nothing to do with Harbor's own UI, so it must not drag in
	 * React or the data store.
	 *
	 * @return void
	 */
	public function test_registers_without_dependencies(): void {
		$this->set_class_fn_return( Version::class, 'should_handle', true );

		( new Activation_Script() )->maybe_register();

		$this->assertSame( [], wp_scripts()->registered[ Activation_Script::HANDLE ]->deps );
	}

	/**
	 * Tests that the reported version is attached after the bundle loads, so
	 * consumers can feature-detect against the copy that actually won.
	 *
	 * @return void
	 */
	public function test_appends_the_version_to_the_global(): void {
		$this->set_class_fn_return( Version::class, 'should_handle', true );

		( new Activation_Script() )->maybe_register();

		$after = wp_scripts()->get_data( Activation_Script::HANDLE, 'after' );

		$this->assertNotEmpty( $after );
		$this->assertStringContainsString(
			'window.lwHarbor.version = "' . Harbor::VERSION . '"',
			implode( "\n", (array) $after )
		);
	}

	/**
	 * Tests that the handle is not vendor-prefixed. Strauss rewrites class
	 * names but not strings, so this literal is what lets every Harbor copy on
	 * the site agree on a single registration.
	 *
	 * @return void
	 */
	public function test_handle_is_not_vendor_prefixed(): void {
		$this->assertSame( 'lw-harbor-activation', Activation_Script::HANDLE );
	}
}
