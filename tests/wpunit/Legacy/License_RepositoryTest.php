<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Legacy;

use LiquidWeb\Harbor\Legacy\License_Repository;
use LiquidWeb\Harbor\Legacy\Legacy_License;
use LiquidWeb\Harbor\Tests\HarborTestCase;

/**
 * @since 1.0.0
 */
final class License_RepositoryTest extends HarborTestCase {

	/**
	 * @var License_Repository
	 */
	private $repository;

	protected function setUp(): void {
		parent::setUp();
		$this->repository = new License_Repository();
	}

	protected function tearDown(): void {
		remove_all_filters( 'lw-harbor/legacy_licenses' );
		parent::tearDown();
	}

	/**
	 * @since 1.0.0
	 */
	public function test_returns_empty_array_when_no_filter_adds_licenses(): void {
		$this->assertSame( [], $this->repository->all() );
	}

	/**
	 * @since 1.0.0
	 */
	public function test_normalizes_array_items_to_legacy_license_instances(): void {
		add_filter(
			'lw-harbor/legacy_licenses',
			static function ( array $licenses ) {
				return array_merge(
					$licenses,
					[
						[
							'key'       => 'key-1',
							'slug'      => 'plugin-one',
							'name'      => 'Plugin One',
							'product'   => 'Product',
							'is_active' => true,
							'page_url'  => 'https://example.com/license',
						],
					]
				);
			}
		);

		$result = $this->repository->all();

		$this->assertCount( 1, $result );
		$this->assertInstanceOf( Legacy_License::class, $result[0] );
		$this->assertSame( 'key-1', $result[0]->key );
		$this->assertSame( 'plugin-one', $result[0]->slug );
	}

	/**
	 * @since 1.0.0
	 */
	public function test_merges_licenses_from_multiple_filter_callbacks(): void {
		add_filter(
			'lw-harbor/legacy_licenses',
			static function ( array $licenses ) {
				return array_merge(
					$licenses,
					[
						[
							'key'     => 'key-a',
							'slug'    => 'plugin-a',
							'name'    => 'A',
							'product' => 'Product',
						],
					]
				);
			}
		);
		add_filter(
			'lw-harbor/legacy_licenses',
			static function ( array $licenses ) {
				return array_merge(
					$licenses,
					[
						[
							'key'     => 'key-b',
							'slug'    => 'plugin-b',
							'name'    => 'B',
							'product' => 'Product',
						],
					]
				);
			}
		);

		$result = $this->repository->all();

		$this->assertCount( 2, $result );
		$this->assertSame( 'plugin-a', $result[0]->slug );
		$this->assertSame( 'plugin-b', $result[1]->slug );
	}

	/**
	 * @since 1.0.0
	 */
	public function test_ignores_non_array_items(): void {
		add_filter(
			'lw-harbor/legacy_licenses',
			static function ( array $licenses ) {
				$licenses[] = (object) [ 'slug' => 'invalid' ];
				$licenses[] = [
					'key'     => 'valid-key',
					'slug'    => 'valid-plugin',
					'name'    => 'Valid',
					'product' => 'Product',
				];

				return $licenses;
			}
		);

		$result = $this->repository->all();

		$this->assertCount( 1, $result );
		$this->assertSame( 'valid-plugin', $result[0]->slug );
	}

	/**
	 * Tests that legacy entries missing either `key` or `slug` are dropped at repository intake.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function test_drops_entries_with_empty_key_or_slug(): void {
		add_filter(
			'lw-harbor/legacy_licenses',
			static function ( array $licenses ) {
				$licenses[] = [
					'key'     => '',
					'slug'    => 'missing-key',
					'name'    => 'Missing Key',
					'product' => 'P',
				];
				$licenses[] = [
					'key'     => 'orphan',
					'slug'    => '',
					'name'    => 'Missing Slug',
					'product' => 'P',
				];
				$licenses[] = [
					'key'     => 'valid-key',
					'slug'    => 'valid-plugin',
					'name'    => 'Valid',
					'product' => 'P',
				];

				return $licenses;
			}
		);

		$result = $this->repository->all();

		$this->assertCount( 1, $result, 'Malformed entries (empty key or empty slug) must be dropped at repository intake.' );
		$this->assertSame( 'valid-plugin', $result[0]->slug );
		$this->assertSame( 'valid-key', $result[0]->key );
	}

	/**
	 * @since 1.0.0
	 */
	public function test_finds_license_by_slug(): void {
		add_filter(
			'lw-harbor/legacy_licenses',
			static function ( array $licenses ) {
				return array_merge(
					$licenses,
					[
						[
							'key'     => 'k1',
							'slug'    => 'first',
							'name'    => 'First',
							'product' => 'B',
						],
						[
							'key'     => 'k2',
							'slug'    => 'target',
							'name'    => 'Target',
							'product' => 'B',
						],
						[
							'key'     => 'k3',
							'slug'    => 'third',
							'name'    => 'Third',
							'product' => 'B',
						],
					]
				);
			}
		);

		$found = $this->repository->find( 'target' );

		$this->assertInstanceOf( Legacy_License::class, $found );
		$this->assertSame( 'target', $found->slug );
		$this->assertSame( 'k2', $found->key );
	}

	/**
	 * @since 1.0.0
	 */
	public function test_returns_null_when_slug_not_found(): void {
		add_filter(
			'lw-harbor/legacy_licenses',
			static function ( array $licenses ) {
				return array_merge(
					$licenses,
					[
						[
							'key'     => 'k1',
							'slug'    => 'only-one',
							'name'    => 'Only',
							'product' => 'B',
						],
					]
				);
			}
		);

		$this->assertNull( $this->repository->find( 'nonexistent' ) );
	}

	/**
	 * @since 1.0.0
	 */
	public function test_caches_results_across_multiple_calls(): void {
		$call_count = 0;

		add_filter(
			'lw-harbor/legacy_licenses',
			static function ( array $licenses ) use ( &$call_count ) {
				$call_count++;

				return array_merge(
					$licenses,
					[
						[
							'key'     => 'k1',
							'slug'    => 's1',
							'name'    => 'N',
							'product' => 'B',
						],
					]
				);
			}
		);

		$this->repository->all();
		$this->repository->all();
		$this->repository->find( 's1' );
		$this->repository->has_any();

		$this->assertSame( 1, $call_count, 'Filter should only be applied once per request cycle.' );
	}

	/**
	 * @since 1.0.0
	 */
	public function test_all_active_returns_only_active_licenses(): void {
		add_filter(
			'lw-harbor/legacy_licenses',
			static function ( array $licenses ) {
				return array_merge(
					$licenses,
					[
						[
							'key'       => 'k1',
							'slug'      => 'active-plugin',
							'name'      => 'Active',
							'product'   => 'B',
							'is_active' => true,
						],
						[
							'key'       => 'k2',
							'slug'      => 'inactive-plugin',
							'name'      => 'Inactive',
							'product'   => 'B',
							'is_active' => false,
						],
					]
				);
			}
		);

		$result = $this->repository->all_active();

		$this->assertCount( 1, $result );
		$this->assertSame( 'active-plugin', $result[0]->slug );
	}

	/**
	 * @since 1.0.0
	 */
	public function test_all_inactive_returns_only_inactive_licenses(): void {
		add_filter(
			'lw-harbor/legacy_licenses',
			static function ( array $licenses ) {
				return array_merge(
					$licenses,
					[
						[
							'key'       => 'k1',
							'slug'      => 'active-plugin',
							'name'      => 'Active',
							'product'   => 'B',
							'is_active' => true,
						],
						[
							'key'       => 'k2',
							'slug'      => 'expired-plugin',
							'name'      => 'Expired',
							'product'   => 'B',
							'is_active' => false,
						],
						[
							'key'       => 'k3',
							'slug'      => 'inactive-plugin',
							'name'      => 'Inactive',
							'product'   => 'B',
							'is_active' => false,
						],
					]
				);
			}
		);

		$result = $this->repository->all_inactive();

		$this->assertCount( 2, $result );
		$this->assertSame( 'expired-plugin', $result[0]->slug );
		$this->assertSame( 'inactive-plugin', $result[1]->slug );
	}

	/**
	 * @since 1.0.0
	 */
	public function test_returns_false_for_has_any_when_empty(): void {
		$this->assertFalse( $this->repository->has_any() );
	}

	/**
	 * @since 1.0.0
	 */
	public function test_returns_true_for_has_any_when_licenses_exist(): void {
		add_filter(
			'lw-harbor/legacy_licenses',
			static function ( array $licenses ) {
				return array_merge(
					$licenses,
					[
						[
							'key'     => 'k1',
							'slug'    => 's1',
							'name'    => 'N',
							'product' => 'B',
						],
					]
				);
			}
		);

		$this->assertTrue( $this->repository->has_any() );
	}

	/**
	 * Tests that any_used_for_updates() returns false when every registered legacy entry omits or opts out of use_for_updates.
	 *
	 * @return void
	 */
	public function test_any_used_for_updates_returns_false_when_no_entry_opts_in(): void {
		add_filter(
			'lw-harbor/legacy_licenses',
			static function ( array $licenses ) {
				$licenses[] = [
					'key'             => 'k1',
					'slug'            => 's1',
					'name'            => 'N',
					'product'         => 'B',
					'is_active'       => true,
					'use_for_updates' => false,
				];
				$licenses[] = [
					'key'     => 'k2',
					'slug'    => 's2',
					'name'    => 'N',
					'product' => 'B',
				];

				return $licenses;
			}
		);

		$this->assertFalse( $this->repository->any_used_for_updates() );
	}

	/**
	 * Tests that any_used_for_updates() returns true when at least one registered legacy entry has use_for_updates set to true.
	 *
	 * @return void
	 */
	public function test_any_used_for_updates_returns_true_when_at_least_one_entry_opts_in(): void {
		add_filter(
			'lw-harbor/legacy_licenses',
			static function ( array $licenses ) {
				$licenses[] = [
					'key'             => 'k1',
					'slug'            => 's1',
					'name'            => 'N',
					'product'         => 'B',
					'is_active'       => true,
					'use_for_updates' => false,
				];
				$licenses[] = [
					'key'             => 'k2',
					'slug'            => 's2',
					'name'            => 'N',
					'product'         => 'B',
					'is_active'       => true,
					'use_for_updates' => true,
				];

				return $licenses;
			}
		);

		$this->assertTrue( $this->repository->any_used_for_updates() );
	}

	/**
	 * Tests that a filter callback which re-enters the repository (e.g. via feature
	 * resolution) does not trigger another filter dispatch, gets an empty array
	 * during the in-flight dispatch, and that the outer call still produces the
	 * real filtered result.
	 *
	 * Regression coverage for the Solid Backups updater chain:
	 * `apply_filters( 'lw-harbor/legacy_licenses' )` -> `is_product_managed()` ->
	 * `lw_harbor_is_feature_available()` -> feature resolution -> `all()` ->
	 * `apply_filters( 'lw-harbor/legacy_licenses' )` -> ...
	 *
	 * The callback caps its own re-entry depth at 3 so that a missing guard fails
	 * the assertion cleanly with a small count rather than blowing the PHP call
	 * stack and causing a segmentation fault and crashing the test runner.
	 *
	 * @return void
	 */
	public function test_filter_callback_that_calls_all_does_not_dispatch_filter_again(): void {
		$repository       = $this->repository;
		$reentrant_result = null;
		$dispatch_count   = 0;
		$bailout_depth    = 3;

		add_filter(
			'lw-harbor/legacy_licenses',
			static function ( array $licenses ) use ( $repository, &$reentrant_result, &$dispatch_count, $bailout_depth ) {
				++$dispatch_count;

				// Stop calling back into the repository once we've proved the dispatch
				// re-fires. Without this cap a missing guard would recurse until the
				// PHP stack blows up and the test process segfaults.
				if ( $dispatch_count < $bailout_depth ) {
					$reentrant_result = $repository->all();
				}

				return array_merge(
					$licenses,
					[
						[
							'key'     => 'outer-key',
							'slug'    => 'outer-plugin',
							'name'    => 'Outer',
							'product' => 'B',
						],
					]
				);
			}
		);

		$result = $this->repository->all();

		$this->assertSame(
			1,
			$dispatch_count,
			'Filter must dispatch exactly once even when its callback re-enters all() mid-dispatch. '
			. 'A count > 1 means the recursion guard is missing; the filter re-fired its own callbacks.'
		);
		$this->assertSame( [], $reentrant_result, 'The re-entrant all() call must return an empty array to break the recursion chain.' );
		$this->assertCount( 1, $result, 'Outer call must still return the real filtered result after re-entry.' );
		$this->assertSame( 'outer-plugin', $result[0]->slug );
	}

	/**
	 * Tests that the re-entry guard is cleared after filter dispatch completes,
	 * so a subsequent fresh call on a new repository instance still applies the filter.
	 *
	 * @return void
	 */
	public function test_recursion_guard_clears_after_dispatch(): void {
		$call_count = 0;

		add_filter(
			'lw-harbor/legacy_licenses',
			static function ( array $licenses ) use ( &$call_count ) {
				++$call_count;

				return array_merge(
					$licenses,
					[
						[
							'key'     => 'k1',
							'slug'    => 's1',
							'name'    => 'N',
							'product' => 'B',
						],
					]
				);
			}
		);

		// First instance dispatches; cache then short-circuits subsequent calls.
		$this->repository->all();
		$this->repository->all();

		// A fresh instance must be able to dispatch again; the guard is per-instance.
		$fresh  = new License_Repository();
		$result = $fresh->all();

		$this->assertSame( 2, $call_count, 'Filter must dispatch once per repository instance, not be stuck in the guarded state.' );
		$this->assertCount( 1, $result );
	}
}
