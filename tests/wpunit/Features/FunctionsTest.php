<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Features;

use LiquidWeb\Harbor\Features\Feature_Repository;
use LiquidWeb\Harbor\Features\Feature_Collection;
use LiquidWeb\Harbor\Features\Contracts\Strategy;
use LiquidWeb\Harbor\Features\Manager;
use LiquidWeb\Harbor\Features\Strategy\Strategy_Factory;
use LiquidWeb\Harbor\Features\Types\Flag;
use LiquidWeb\Harbor\Tests\HarborTestCase;
use WP_Error;

final class FunctionsTest extends HarborTestCase {

	/**
	 * Sets up a manager with a mocked active feature before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$collection = new Feature_Collection();
		$collection->add(
			Flag::from_array(
				[
					'slug'         => 'test-feature',
					'name'         => 'Test Feature',
					'description'  => '',
					'product'        => 'test',
					'tier'         => 'free',
					'is_available' => true,
				]
			)
		);

		$mock_strategy = $this->makeEmpty(
			Strategy::class,
			[
				'enable'    => true,
				'disable'   => true,
				'is_active' => true,
			]
		);

		$factory = $this->makeEmpty(
			Strategy_Factory::class,
			[
				'make' => $mock_strategy,
			]
		);

		$repository = $this->makeEmpty(
			Feature_Repository::class,
			[
				'get' => $collection,
			]
		);

		$manager = new Manager( $repository, $factory );

		$this->container->bind(
			Manager::class,
			static function () use ( $manager ) {
				return $manager;
			}
		);
	}

	/**
	 * Tests is_feature_enabled returns true for an active feature in the catalog.
	 *
	 * @return void
	 */
	public function test_is_feature_enabled_returns_true_for_active_feature(): void {
		$this->assertTrue( lw_harbor_is_feature_enabled( 'test-feature' ) );
	}

	/**
	 * Tests is_feature_enabled returns false for a feature not in the catalog.
	 *
	 * @return void
	 */
	public function test_is_feature_enabled_returns_false_for_unknown_feature(): void {
		$this->assertFalse( lw_harbor_is_feature_enabled( 'nonexistent' ) );
	}

	/**
	 * Tests is_feature_available returns true for a feature with is_available set.
	 *
	 * @return void
	 */
	public function test_is_feature_available_returns_true_for_available_feature(): void {
		$this->assertTrue( lw_harbor_is_feature_available( 'test-feature' ) );
	}

	/**
	 * Tests is_feature_available returns false for a feature with is_available unset.
	 *
	 * @return void
	 */
	public function test_is_feature_available_returns_false_for_unavailable_feature(): void {
		$collection = new Feature_Collection();
		$collection->add(
			Flag::from_array(
				[
					'slug'         => 'locked-feature',
					'name'         => 'Locked Feature',
					'description'  => '',
					'product'        => 'test',
					'tier'         => 'pro',
					'is_available' => false,
				]
			)
		);

		$mock_strategy = $this->makeEmpty(
			Strategy::class,
			[
				'is_active' => false,
			]
		);

		$factory = $this->makeEmpty(
			Strategy_Factory::class,
			[
				'make' => $mock_strategy,
			]
		);

		$repository = $this->makeEmpty(
			Feature_Repository::class,
			[
				'get' => $collection,
			]
		);

		$manager = new Manager( $repository, $factory );

		$this->container->bind(
			Manager::class,
			static function () use ( $manager ) {
				return $manager;
			}
		);

		$this->assertFalse( lw_harbor_is_feature_available( 'locked-feature' ) );
	}

	/**
	 * Tests is_feature_available returns false for a feature not in the catalog.
	 *
	 * @return void
	 */
	public function test_is_feature_available_returns_false_for_unknown_feature(): void {
		$this->assertFalse( lw_harbor_is_feature_available( 'nonexistent' ) );
	}

	/**
	 * Tests that is_feature_enabled returns false when the catalog returns a WP_Error.
	 *
	 * @return void
	 */
	public function test_is_feature_enabled_returns_false_when_catalog_errors(): void {
		$error = new WP_Error( 'api_error', 'Could not fetch features.' );

		$repository = $this->makeEmpty(
			Feature_Repository::class,
			[
				'get' => $error,
			]
		);

		$factory = $this->makeEmpty( Strategy_Factory::class );

		$manager = new Manager( $repository, $factory );

		$this->container->bind(
			Manager::class,
			static function () use ( $manager ) {
				return $manager;
			}
		);

		$this->assertFalse( lw_harbor_is_feature_enabled( 'test-feature' ) );
	}

	/**
	 * Tests that is_feature_available returns false when the catalog returns a WP_Error.
	 *
	 * @return void
	 */
	public function test_is_feature_available_returns_false_when_catalog_errors(): void {
		$error = new WP_Error( 'api_error', 'Could not fetch features.' );

		$repository = $this->makeEmpty(
			Feature_Repository::class,
			[
				'get' => $error,
			]
		);

		$factory = $this->makeEmpty( Strategy_Factory::class );

		$manager = new Manager( $repository, $factory );

		$this->container->bind(
			Manager::class,
			static function () use ( $manager ) {
				return $manager;
			}
		);

		$this->assertFalse( lw_harbor_is_feature_available( 'test-feature' ) );
	}
}
