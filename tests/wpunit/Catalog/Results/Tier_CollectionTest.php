<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Catalog\Results;

use LiquidWeb\Harbor\Catalog\Results\Catalog_Tier;
use LiquidWeb\Harbor\Catalog\Results\Tier_Collection;
use LiquidWeb\Harbor\Tests\HarborTestCase;

final class Tier_CollectionTest extends HarborTestCase {

	public function test_it_adds_and_gets_tiers(): void {
		$collection = new Tier_Collection();
		$basic      = Catalog_Tier::from_array(
			[
				'slug'         => 'basic',
				'name'         => 'Basic',
				'rank'         => 1,
				'price'        => 0,
					'currency'     => 'USD',
					'features'     => [],
					'herald_slugs' => [],
			]
		);
		$pro        = Catalog_Tier::from_array(
			[
				'slug'         => 'pro',
				'name'         => 'Pro',
				'rank'         => 2,
				'price'        => 0,
					'currency'     => 'USD',
					'features'     => [],
					'herald_slugs' => [],
			]
		);

		$collection->add( $basic );
		$collection->add( $pro );

		$this->assertSame( 2, $collection->count() );
		$this->assertSame( $basic, $collection->get( 'basic' ) );
		$this->assertSame( $pro, $collection->get( 'pro' ) );
	}

	public function test_it_does_not_duplicate_tiers_with_same_slug(): void {
		$collection = new Tier_Collection();
		$first      = Catalog_Tier::from_array(
			[
				'slug'         => 'basic',
				'name'         => 'First',
				'rank'         => 1,
				'price'        => 0,
					'currency'     => 'USD',
					'features'     => [],
					'herald_slugs' => [],
			]
		);
		$second     = Catalog_Tier::from_array(
			[
				'slug'         => 'basic',
				'name'         => 'Second',
				'rank'         => 1,
				'price'        => 0,
					'currency'     => 'USD',
					'features'     => [],
					'herald_slugs' => [],
			]
		);

		$collection->add( $first );
		$collection->add( $second );

		$this->assertSame( 1, $collection->count() );
		$this->assertSame( 'First', $collection->get( 'basic' )->get_name() );
	}

	public function test_it_returns_null_for_unknown_slug(): void {
		$collection = new Tier_Collection();

		$this->assertNull( $collection->get( 'nonexistent' ) );
	}

	public function test_it_iterates_over_tiers(): void {
		$collection = new Tier_Collection();
		$collection->add(
			Catalog_Tier::from_array(
				[
					'slug'         => 'basic',
					'name'         => 'Basic',
					'rank'         => 1,
					'price'        => 0,
					'currency'     => 'USD',
					'features'     => [],
					'herald_slugs' => [],
				]
			)
		);
		$collection->add(
			Catalog_Tier::from_array(
				[
					'slug'         => 'pro',
					'name'         => 'Pro',
					'rank'         => 2,
					'price'        => 0,
					'currency'     => 'USD',
					'features'     => [],
					'herald_slugs' => [],
				]
			)
		);

		$slugs = [];

		foreach ( $collection as $slug => $tier ) {
			$slugs[] = $slug;
		}

		$this->assertSame( [ 'basic', 'pro' ], $slugs );
	}

	public function test_it_counts_tiers(): void {
		$collection = new Tier_Collection();

		$this->assertSame( 0, $collection->count() );

		$collection->add(
			Catalog_Tier::from_array(
				[
					'slug'         => 'basic',
					'name'         => 'Basic',
					'rank'         => 1,
					'price'        => 0,
					'currency'     => 'USD',
					'features'     => [],
					'herald_slugs' => [],
				]
			)
		);

		$this->assertSame( 1, $collection->count() );
	}
}
