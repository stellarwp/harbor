<?php declare( strict_types=1 );

namespace wpunit;

use LiquidWeb\Harbor\Harbor;
use LiquidWeb\Harbor\Tests\HarborTestCase;

/**
 * Tests for _lw_harbor_instance_registry().
 *
 * These tests only cover observable behavior from within the test environment,
 * where wp_loaded has already fired. Registration and reset require the
 * production bootstrap window (before wp_loaded) and are covered by integration tests.
 *
 * @since 1.0.0
 */
class InstanceRegistryTest extends HarborTestCase {

	public function test_it_returns_an_array(): void {
		// @phpstan-ignore function.internal
		$this->assertIsArray( _lw_harbor_instance_registry() );
	}

	public function test_it_rejects_registrations_after_wp_loaded(): void {
		$this->setExpectedIncorrectUsage( '_lw_harbor_instance_registry' );

		$unique_version = '99.99.99';

		// @phpstan-ignore function.internal
		_lw_harbor_instance_registry( $unique_version, 'some-plugin/some-plugin.php' );

		// @phpstan-ignore function.internal
		$versions = _lw_harbor_instance_registry();

		$this->assertArrayNotHasKey( $unique_version, $versions );
	}

	/**
	 * A host that boots inside its activation hook calls Harbor::init() after wp_loaded.
	 * WPTestCase fails this test if that call reports incorrect usage.
	 */
	public function test_harbor_init_after_wp_loaded_skips_registration(): void {
		$this->assertGreaterThan( 0, did_action( 'wp_loaded' ) );

		// @phpstan-ignore function.internal
		$before = _lw_harbor_instance_registry();

		Harbor::init();

		// @phpstan-ignore function.internal
		$this->assertSame( $before, _lw_harbor_instance_registry() );
	}

	public function test_it_returns_plugin_files_as_array_for_registered_version(): void {
		// The bootstrap plugin registered itself before wp_loaded; its value is an array of plugin_file strings.
		// @phpstan-ignore function.internal
		$registry = _lw_harbor_instance_registry();

		foreach ( $registry as $version => $plugin_files ) {
			$this->assertIsString( $version );
			$this->assertIsArray( $plugin_files );
			foreach ( $plugin_files as $plugin_file ) {
				$this->assertIsString( $plugin_file );
			}
		}
	}

	public function test_it_ignores_empty_version_string(): void {
		// @phpstan-ignore function.internal
		$before = _lw_harbor_instance_registry();

		// @phpstan-ignore function.internal
		_lw_harbor_instance_registry( '' );

		// @phpstan-ignore function.internal
		$after = _lw_harbor_instance_registry();

		$this->assertSame( $before, $after );
	}
}
