<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Features;

use LiquidWeb\Harbor\Features\Feature_Collection;
use LiquidWeb\Harbor\Features\Resolve_Feature_Collection;
use LiquidWeb\Harbor\Features\Types\Feature;
use LiquidWeb\Harbor\Features\Types\Plugin;
use LiquidWeb\Harbor\Features\Types\Theme;
use LiquidWeb\Harbor\Legacy\License_Repository as Legacy_License_Repository;
use LiquidWeb\Harbor\Licensing\License_Manager;
use LiquidWeb\Harbor\Licensing\Product_Collection;
use LiquidWeb\Harbor\Licensing\Results\Product_Entry;
use LiquidWeb\Harbor\Portal\Catalog_Collection;
use LiquidWeb\Harbor\Portal\Catalog_Repository;
use LiquidWeb\Harbor\Portal\Results\Product_Catalog;
use LiquidWeb\Harbor\Site\Data;
use LiquidWeb\Harbor\Tests\HarborTestCase;

/**
 * Unit tests for Resolve_Feature_Collection focused on the legacy-grant logic.
 *
 * @since TBD
 */
final class Resolve_Feature_CollectionTest extends HarborTestCase {

	protected function tearDown(): void {
		remove_all_filters( 'lw-harbor/legacy_licenses' );
		parent::tearDown();
	}

	/**
	 * Registers a single legacy license entry via the filter.
	 *
	 * @param array<string, mixed> $overrides Field overrides applied to the default entry.
	 *
	 * @return void
	 */
	private function register_legacy_license( array $overrides = [] ): void {
		$defaults = [
			'key'             => 'legacy-key-1234',
			'slug'            => 'kad-blocks-pro',
			'name'            => 'Kadence Blocks Pro',
			'product'         => 'kadence',
			'is_active'       => true,
			'use_for_updates' => true,
			'page_url'        => 'https://example.com/manage',
			'expires_at'      => '',
		];

		$entry = array_merge( $defaults, $overrides );

		add_filter(
			'lw-harbor/legacy_licenses',
			static function ( array $licenses ) use ( $entry ) {
				$licenses[] = $entry;

				return $licenses;
			}
		);
	}

	/**
	 * Builds a minimal Product_Catalog with one paid-tier plugin feature at the
	 * "basic" tier (rank 1) of the "kadence" product, and an optional free-tier
	 * feature so the wporg / free-tier path can be exercised.
	 *
	 * @return Catalog_Collection
	 */
	private function make_catalog(): Catalog_Collection {
		$product = Product_Catalog::from_array(
			[
				'product_id'   => 'prod-1',
				'product_slug' => 'kadence',
				'product_name' => 'Kadence',
				'tiers'        => [
					[
						'tier_slug' => 'free',
						'name'      => 'Free',
						'rank'      => 0,
					],
					[
						'tier_slug' => 'basic',
						'name'      => 'Basic',
						'rank'      => 1,
					],
					[
						'tier_slug' => 'pro',
						'name'      => 'Pro',
						'rank'      => 2,
					],
				],
				'features'     => [
					[
						'slug'         => 'kad-blocks-pro',
						'name'         => 'Kadence Blocks Pro',
						'description'  => 'Pro-only blocks.',
						'kind'         => 'plugin',
						'minimum_tier' => 'basic',
						'version'      => '1.0.0',
					],
					[
						'slug'         => 'kadence-blocks',
						'name'         => 'Kadence Blocks',
						'description'  => 'Free blocks.',
						'kind'         => 'plugin',
						'minimum_tier' => 'free',
						'wporg_slug'   => 'kadence-blocks',
						'version'      => '3.4.6',
					],
				],
			]
		);

		$catalog = new Catalog_Collection();
		$catalog->add( $product );

		return $catalog;
	}

	/**
	 * Builds a License_Manager stub whose get_products() returns the supplied
	 * Product_Collection regardless of domain.
	 *
	 * @param Product_Collection $products The product collection to return.
	 *
	 * @return License_Manager
	 */
	private function make_license_manager_returning( Product_Collection $products ): License_Manager {
		return $this->makeEmpty(
			License_Manager::class,
			[ 'get_products' => $products ]
		);
	}

	/**
	 * Builds a Catalog_Repository stub whose get() returns the supplied collection.
	 *
	 * @param Catalog_Collection $catalog The catalog collection to return.
	 *
	 * @return Catalog_Repository
	 */
	private function make_catalog_repository_returning( Catalog_Collection $catalog ): Catalog_Repository {
		return $this->makeEmpty(
			Catalog_Repository::class,
			[ 'get' => $catalog ]
		);
	}

	/**
	 * Builds a Resolve_Feature_Collection wired with the supplied catalog and
	 * licensing product collection. Uses a real Legacy_License_Repository so
	 * the `lw-harbor/legacy_licenses` filter drives behavior.
	 *
	 * @param Catalog_Collection $catalog  The catalog collection.
	 * @param Product_Collection $products The licensing products to expose.
	 *
	 * @return Resolve_Feature_Collection
	 */
	private function make_resolver(
		Catalog_Collection $catalog,
		Product_Collection $products
	): Resolve_Feature_Collection {
		$resolver = new Resolve_Feature_Collection(
			$this->make_catalog_repository_returning( $catalog ),
			$this->make_license_manager_returning( $products ),
			$this->makeEmpty( Data::class, [ 'get_domain' => 'example.com' ] ),
			new Legacy_License_Repository()
		);

		$resolver->register_type( Feature::TYPE_PLUGIN, Plugin::class );
		$resolver->register_type( Feature::TYPE_THEME, Theme::class );

		return $resolver;
	}

	/**
	 * @return Product_Entry
	 */
	private function make_product_entry( string $tier, array $capabilities, string $validation_status = 'valid' ): Product_Entry {
		return Product_Entry::from_array(
			[
				'product_slug'      => 'kadence',
				'tier'              => $tier,
				'status'            => 'active',
				'expires'           => '2026-12-31 23:59:59',
				'validation_status' => $validation_status,
				'activated_here'    => true,
				'capabilities'      => $capabilities,
			]
		);
	}

	/**
	 * Asserts the resolved feature for `$slug` has the expected availability flags.
	 *
	 * @param Resolve_Feature_Collection $resolver
	 * @param string                     $slug
	 * @param bool                       $expected_available
	 * @param bool                       $expected_in_tier
	 */
	private function assert_resolved_feature_flags(
		Resolve_Feature_Collection $resolver,
		string $slug,
		bool $expected_available,
		bool $expected_in_tier
	): void {
		$collection = ( $resolver )();

		$this->assertInstanceOf( Feature_Collection::class, $collection );

		$feature = $collection->get( $slug );

		$this->assertNotNull( $feature, sprintf( 'Feature "%s" should exist in the resolved collection.', $slug ) );
		$this->assertSame( $expected_available, $feature->is_available(), 'is_available mismatch.' );
		$this->assertSame( $expected_in_tier, $feature->is_in_catalog_tier(), 'in_catalog_tier mismatch.' );
	}

	/**
	 * Tests that an active legacy license alone (no Unified license) makes a paid feature available.
	 *
	 * @return void
	 */
	/**
	 * Tests that an active legacy license alone (no Unified license) makes a paid feature available.
	 *
	 * @return void
	 */
	public function test_paid_feature_active_legacy_with_no_unified_license_is_available(): void {
		$this->register_legacy_license(
			[
				'key'  => 'legacy-key-abc',
				'slug' => 'kad-blocks-pro',
			]
		);

		$resolver = $this->make_resolver( $this->make_catalog(), new Product_Collection() );

		$this->assert_resolved_feature_flags( $resolver, 'kad-blocks-pro', true, true );
	}

	/**
	 * Tests that an inactive legacy license does not grant availability when no Unified license covers the feature.
	 *
	 * @return void
	 */
	public function test_paid_feature_inactive_legacy_with_no_unified_license_is_unavailable(): void {
		$this->register_legacy_license(
			[
				'key'       => 'legacy-key-abc',
				'slug'      => 'kad-blocks-pro',
				'is_active' => false,
			]
		);

		$resolver = $this->make_resolver( $this->make_catalog(), new Product_Collection() );

		$this->assert_resolved_feature_flags( $resolver, 'kad-blocks-pro', false, false );
	}

	/**
	 * Tests that a legacy entry with an empty key does not grant availability.
	 *
	 * @return void
	 */
	public function test_paid_feature_legacy_with_empty_key_does_not_grant_availability(): void {
		$this->register_legacy_license(
			[
				'key'  => '',
				'slug' => 'kad-blocks-pro',
			]
		);

		$resolver = $this->make_resolver( $this->make_catalog(), new Product_Collection() );

		$this->assert_resolved_feature_flags( $resolver, 'kad-blocks-pro', false, false );
	}

	/**
	 * Tests that a legacy entry whose slug does not match the catalog feature is ignored.
	 *
	 * @return void
	 */
	public function test_paid_feature_legacy_slug_mismatch_does_not_grant_availability(): void {
		$this->register_legacy_license(
			[
				'key'  => 'legacy-key-abc',
				'slug' => 'some-other-plugin',
			]
		);

		$resolver = $this->make_resolver( $this->make_catalog(), new Product_Collection() );

		$this->assert_resolved_feature_flags( $resolver, 'kad-blocks-pro', false, false );
	}

	/**
	 * Tests that an active legacy entry grants availability even when the Unified license's capabilities omit the feature.
	 *
	 * @return void
	 */
	public function test_paid_feature_active_legacy_overrides_missing_capability(): void {
		$this->register_legacy_license(
			[
				'key'  => 'legacy-key-abc',
				'slug' => 'kad-blocks-pro',
			]
		);

		// Unified license at the right tier rank but capability list omits the feature.
		$products = new Product_Collection();
		$products->add( $this->make_product_entry( 'basic', [ 'some-other-capability' ] ) );

		$resolver = $this->make_resolver( $this->make_catalog(), $products );

		$this->assert_resolved_feature_flags( $resolver, 'kad-blocks-pro', true, true );
	}

	/**
	 * Tests that an active legacy entry grants in-tier status even when the Unified license is below the feature's minimum tier rank.
	 *
	 * @return void
	 */
	public function test_paid_feature_active_legacy_overrides_insufficient_tier_rank(): void {
		$this->register_legacy_license(
			[
				'key'  => 'legacy-key-abc',
				'slug' => 'kad-blocks-pro',
			]
		);

		// Unified license has the capability but is below the catalog minimum tier (free vs basic).
		$products = new Product_Collection();
		$products->add( $this->make_product_entry( 'free', [ 'kad-blocks-pro' ] ) );

		$resolver = $this->make_resolver( $this->make_catalog(), $products );

		$this->assert_resolved_feature_flags( $resolver, 'kad-blocks-pro', true, true );
	}

	/**
	 * Tests that free-tier (rank 0) features are unconditionally available regardless of legacy or Unified state.
	 *
	 * @return void
	 */
	public function test_free_tier_feature_is_available_regardless_of_legacy_state(): void {
		// No legacy license registered. The free-tier (rank 0) feature should still be available.
		$resolver = $this->make_resolver( $this->make_catalog(), new Product_Collection() );

		$this->assert_resolved_feature_flags( $resolver, 'kadence-blocks', true, true );
	}

	/**
	 * Tests that a paid feature is unavailable when neither a legacy entry nor a Unified license covers it.
	 *
	 * @return void
	 */
	public function test_paid_feature_without_legacy_or_unified_license_is_unavailable(): void {
		$resolver = $this->make_resolver( $this->make_catalog(), new Product_Collection() );

		$this->assert_resolved_feature_flags( $resolver, 'kad-blocks-pro', false, false );
	}

	/**
	 * Tests that an active legacy entry without `use_for_updates` opt-in does not grant availability.
	 *
	 * Covers the case where a plugin reports a legacy license whose backend is not
	 * compatible with Harbor's update pipeline (e.g. SolidWP API keys): Harbor must
	 * not advertise updates for these slugs because the download would fail validation.
	 *
	 * @return void
	 */
	public function test_paid_feature_legacy_without_use_for_updates_opt_in_is_unavailable(): void {
		$this->register_legacy_license(
			[
				'key'             => 'legacy-key-abc',
				'slug'            => 'kad-blocks-pro',
				'use_for_updates' => false,
			]
		);

		$resolver = $this->make_resolver( $this->make_catalog(), new Product_Collection() );

		$this->assert_resolved_feature_flags( $resolver, 'kad-blocks-pro', false, false );
	}

	/**
	 * Tests that the resolver still picks up legacy entries from the global filter
	 * when constructed without the optional Legacy_License_Repository argument.
	 *
	 * Guards the three-argument constructor compatibility shim: callers that
	 * predate the legacy feature must keep working, and the default repository
	 * the resolver builds for itself must read the same `lw-harbor/legacy_licenses`
	 * filter as the container-managed singleton.
	 *
	 * @return void
	 */
	public function test_resolver_without_legacy_repo_argument_uses_filter_backed_default(): void {
		$this->register_legacy_license(
			[
				'key'  => 'legacy-key-abc',
				'slug' => 'kad-blocks-pro',
			]
		);

		$resolver = new Resolve_Feature_Collection(
			$this->make_catalog_repository_returning( $this->make_catalog() ),
			$this->make_license_manager_returning( new Product_Collection() ),
			$this->makeEmpty( Data::class, [ 'get_domain' => 'example.com' ] )
		);

		$resolver->register_type( Feature::TYPE_PLUGIN, Plugin::class );
		$resolver->register_type( Feature::TYPE_THEME, Theme::class );

		$this->assert_resolved_feature_flags( $resolver, 'kad-blocks-pro', true, true );
	}
}
