<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Features;

use LiquidWeb\Harbor\Features\Feature_Collection;
use LiquidWeb\Harbor\Features\Feature_Resource;
use LiquidWeb\Harbor\Features\Resolve_Feature_Collection;
use LiquidWeb\Harbor\Legacy\Legacy_License;
use LiquidWeb\Harbor\Legacy\License_Repository;
use LiquidWeb\Harbor\Licensing\License_Manager;
use LiquidWeb\Harbor\Licensing\Product_Collection;
use LiquidWeb\Harbor\Portal\Catalog_Collection;
use LiquidWeb\Harbor\Portal\Catalog_Repository;
use LiquidWeb\Harbor\Portal\Results\Catalog_Tier;
use LiquidWeb\Harbor\Site\Data;
use LiquidWeb\Harbor\Tests\HarborTestCase;

/**
 * Package capabilities grant brand features without replacing existing purchases.
 */
final class Package_AccessTest extends HarborTestCase {

	/**
	 * Capability field aliases survive serialization for existing catalog consumers.
	 */
	public function test_catalog_capability_fields_round_trip(): void {
		foreach ( [ 'capabilities', 'herald_slugs' ] as $field ) {
			$tier = Catalog_Tier::from_array( [ $field => [ 'blocks-pro' ] ] );
			$this->assertSame( [ 'blocks-pro' ], $tier->get_herald_slugs() );
			$this->assertSame( [ 'blocks-pro' ], Catalog_Tier::from_array( $tier->to_array() )->get_herald_slugs() );
		}
	}

	/**
	 * Only usable purchases appear in the access explanation and grant availability.
	 */
	public function test_package_and_brand_grants_remain_independent(): void {
		$package = [
			'product_slug'      => 'any-package',
			'tier'              => 'complete',
			'status'            => 'active',
			'activated_here'    => true,
			'validation_status' => 'valid',
			'capabilities'      => [ 'blocks-pro' ],
		];
		$brand   = array_replace(
			$package,
			[
				'product_slug' => 'kadence',
				'tier'         => 'pro',
			]
		);
		$cases   = [
			'package only'        => [ [ $package ], [ 'any-package' ] ],
			'brand only'          => [ [ $brand ], [ 'kadence' ] ],
			'overlap'             => [ [ $package, $brand ], [ 'any-package', 'kadence' ] ],
			'expired package'     => [ [ array_replace( $package, [ 'validation_status' => 'expired' ] ), $brand ], [ 'kadence' ] ],
			'unactivated package' => [ [ array_replace( $package, [ 'activated_here' => false ] ) ], [] ],
			'suspended package'   => [ [ array_replace( $package, [ 'validation_status' => 'suspended' ] ) ], [] ],
			'no capability'       => [ [ array_replace( $package, [ 'capabilities' => [] ] ) ], [] ],
			'repeated capability' => [ [ array_replace( $package, [ 'capabilities' => [ 'blocks-pro', 'blocks-pro' ] ] ) ], [ 'any-package' ] ],
		];
		$catalog = Catalog_Collection::from_array(
			[
				[
					'product_slug' => 'kadence',
					'product_name' => 'Kadence',
					'tiers'        => [
						[
							'tier_slug' => 'pro',
							'name'      => 'Pro',
							'rank'      => 1,
						],
					],
					'features'     => [
						[
							'slug'         => 'blocks-pro',
							'kind'         => 'plugin',
							'minimum_tier' => 'pro',
						],
					],
				],
			]
		);
		foreach ( $cases as $label => [ $entries, $expected ] ) {
			$resolver = new Resolve_Feature_Collection(
				$this->makeEmpty( Catalog_Repository::class, [ 'get' => $catalog ] ),
				$this->makeEmpty( License_Manager::class, [ 'get_products' => Product_Collection::from_array( $entries ) ] ),
				$this->makeEmpty( Data::class, [ 'get_domain' => 'example.org' ] ),
				$this->makeEmpty( License_Repository::class, [ 'all' => [] ] )
			);
			$feature  = $resolver()->get( 'blocks-pro' );
			$this->assertSame( $expected !== [], $feature->is_available(), $label );
			$this->assertSame( $expected !== [], $feature->is_in_catalog_tier(), $label );
			$resource = ( new Feature_Resource( $feature, null ) )->to_array();
			$this->assertSame( $expected, array_column( $resource['access_sources'], 'product_slug' ), $label );
			$this->assertArrayNotHasKey( 'key', $resource, 'Access explanations must not disclose credentials.' );
		}
	}

	/**
	 * A retained brand purchase keeps its exclusive features until that grant expires.
	 */
	public function test_brand_exclusive_access_is_independent_of_package_access(): void {
		$package = $this->purchase( 'any-package', [ 'blocks-pro' ] );
		$brand   = $this->purchase( 'kadence', [ 'blocks-pro', 'conversions' ] );
		$cases   = [
			'both active'     => [ [ $package, $brand ], true, [ 'any-package', 'kadence' ] ],
			'brand expired'   => [ [ $package, array_replace( $brand, [ 'validation_status' => 'expired' ] ) ], false, [ 'any-package' ] ],
			'package expired' => [ [ array_replace( $package, [ 'validation_status' => 'expired' ] ), $brand ], true, [ 'kadence' ] ],
		];

		foreach ( $cases as $label => [ $purchases, $owns_conversions, $block_sources ] ) {
			$features    = $this->resolve_purchase_features( $purchases );
			$blocks      = ( new Feature_Resource( $features->get( 'blocks-pro' ), null ) )->to_array();
			$conversions = ( new Feature_Resource( $features->get( 'conversions' ), null ) )->to_array();

			$this->assertTrue( $blocks['is_available'], $label );
			$this->assertSame( $block_sources, array_column( $blocks['access_sources'], 'product_slug' ), $label );
			$this->assertSame( $owns_conversions, $conversions['is_available'], $label );
			$this->assertSame( $owns_conversions ? [ 'kadence' ] : [], array_column( $conversions['access_sources'], 'product_slug' ), $label );
		}
	}

	/**
	 * Service responses use catalog names and preserve the same source shape as plugins.
	 */
	public function test_service_access_sources_use_catalog_display_names(): void {
		$features = $this->resolve_purchase_features( [ $this->purchase( 'any-package', [ 'calendar-service' ] ) ] );
		$resource = ( new Feature_Resource( $features->get( 'calendar-service' ), null ) )->to_array();

		$this->assertTrue( $resource['is_available'] );
		$this->assertTrue( $resource['in_catalog_tier'] );
		$this->assertSame(
			[
				[
					'type'         => 'purchase',
					'product_slug' => 'any-package',
					'tier'         => 'complete',
					'product_name' => 'Example Suite',
					'tier_name'    => '10 sites',
				],
			],
			$resource['access_sources']
		);
	}

	/**
	 * Free and opted-in legacy access are explained without exposing the legacy key.
	 */
	public function test_free_and_legacy_sources_do_not_require_a_package(): void {
		foreach ( [ true, false ] as $opted_in ) {
			$legacy   = Legacy_License::from_data(
				[
					'slug'            => 'blocks-pro',
					'key'             => 'private-legacy-fixture',
					'is_active'       => true,
					'use_for_updates' => $opted_in,
				]
			);
			$features = $this->resolve_purchase_features( [], [ $legacy ] );
			$paid     = ( new Feature_Resource( $features->get( 'blocks-pro' ), null ) )->to_array();
			$free     = ( new Feature_Resource( $features->get( 'blocks' ), null ) )->to_array();

			$this->assertSame( $opted_in, $paid['is_available'] );
			$this->assertSame( $opted_in, $paid['in_catalog_tier'] );
			$this->assertSame( $opted_in ? [ [ 'type' => 'legacy' ] ] : [], $paid['access_sources'] );
			$this->assertStringNotContainsString( 'private-legacy-fixture', json_encode( $paid ) );
			$this->assertTrue( $free['is_available'] );
			$this->assertTrue( $free['in_catalog_tier'] );
			$this->assertSame( [ [ 'type' => 'free' ] ], $free['access_sources'] );
		}
	}

	/**
	 * A brand bonus remains outside its tier while a package can independently include it.
	 */
	public function test_package_grant_does_not_change_brand_bonus_tier_semantics(): void {
		$brand = array_replace( $this->purchase( 'kadence', [ 'blocks-pro' ] ), [ 'tier' => 'basic' ] );
		$cases = [
			'brand bonus only'            => [ [ $brand ], false ],
			'package also includes bonus' => [ [ $brand, $this->purchase( 'any-package', [ 'blocks-pro' ] ) ], true ],
		];

		foreach ( $cases as $label => [ $purchases, $in_tier ] ) {
			$feature = $this->resolve_purchase_features( $purchases )->get( 'blocks-pro' );

			$this->assertTrue( $feature->is_available(), $label );
			$this->assertSame( $in_tier, $feature->is_in_catalog_tier(), $label );
		}
	}

	/**
	 * Supplies a valid purchase activated on the test site.
	 *
	 * @param string   $product_slug Purchased product identifier.
	 * @param string[] $capabilities Included feature identifiers.
	 *
	 * @return array<string, mixed>
	 */
	private function purchase( string $product_slug, array $capabilities ): array {
		return [
			'product_slug'      => $product_slug,
			'tier'              => $product_slug === 'kadence' ? 'pro' : 'complete',
			'status'            => 'active',
			'activated_here'    => true,
			'validation_status' => 'valid',
			'capabilities'      => $capabilities,
		];
	}

	/**
	 * Resolves a catalog containing package-covered, brand-only, and free features.
	 *
	 * @param array<int, array<string, mixed>> $purchases Effective licensing response entries.
	 * @param Legacy_License[]                $legacy    Existing plugin license entries.
	 *
	 * @return Feature_Collection
	 */
	private function resolve_purchase_features( array $purchases, array $legacy = [] ): Feature_Collection {
		$catalog  = Catalog_Collection::from_array(
			[
				[
					'product_slug' => 'kadence',
					'product_name' => 'Kadence',
					'tiers'        => [
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
							'slug'         => 'blocks-pro',
							'kind'         => 'plugin',
							'minimum_tier' => 'pro',
						],
						[
							'slug'         => 'conversions',
							'kind'         => 'plugin',
							'minimum_tier' => 'pro',
						],
						[
							'slug'       => 'blocks',
							'kind'       => 'plugin',
							'wporg_slug' => 'blocks',
						],
						[
							'slug'         => 'calendar-service',
							'kind'         => 'service',
							'minimum_tier' => 'pro',
						],
					],
				],
				[
					'product_slug' => 'any-package',
					'product_name' => 'Example Suite',
					'tiers'        => [
						[
							'tier_slug' => 'complete',
							'name'      => '10 sites',
							'rank'      => 1,
						],
					],
					'features'     => [],
				],
			]
		);
		$resolver = new Resolve_Feature_Collection(
			$this->makeEmpty( Catalog_Repository::class, [ 'get' => $catalog ] ),
			$this->makeEmpty( License_Manager::class, [ 'get_products' => Product_Collection::from_array( $purchases ) ] ),
			$this->makeEmpty( Data::class, [ 'get_domain' => 'example.org' ] ),
			$this->makeEmpty( License_Repository::class, [ 'all' => $legacy ] )
		);

		return $resolver();
	}
}
