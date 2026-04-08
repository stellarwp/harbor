<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Catalog\Results;

use LiquidWeb\Harbor\Portal\Results\Catalog_Feature;
use LiquidWeb\Harbor\Portal\Results\Catalog_Tier;
use LiquidWeb\Harbor\Portal\Results\Product_Catalog;
use LiquidWeb\Harbor\Portal\Results\Tier_Collection;
use LiquidWeb\Harbor\Tests\HarborTestCase;

final class Product_CatalogTest extends HarborTestCase {

	private array $valid_data = [
		'product_id'   => 'kadence-001',
		'product_slug' => 'kadence',
		'product_name' => 'Kadence',
		'tiers'        => [
			[
				'tier_slug'    => 'kadence-basic',
				'name'         => 'Basic',
				'rank'         => 1,
				'price'        => 0,
				'currency'     => 'USD',
				'features'     => [],
				'herald_slugs' => [],
			],
			[
				'tier_slug'    => 'kadence-pro',
				'name'         => 'Pro',
				'rank'         => 2,
				'price'        => 14900,
				'currency'     => 'USD',
				'features'     => [],
				'herald_slugs' => [],
			],
			[
				'tier_slug'    => 'kadence-agency',
				'name'         => 'Agency',
				'rank'         => 3,
				'price'        => 29900,
				'currency'     => 'USD',
				'features'     => [],
				'herald_slugs' => [],
			],
		],
		'features'     => [
			[
				'slug'              => 'kad-blocks-pro',
				'kind'              => 'plugin',
				'minimum_tier'      => 'kadence-basic',
				'main_file'         => 'kadence-blocks-pro/kadence-blocks-pro.php',
				'wporg_slug'        => null,
				'download_url'      => 'https://licensing.stellarwp.com/api/plugins/kad-blocks-pro',
				'name'              => 'Blocks Pro',
				'description'       => 'Premium Gutenberg blocks for advanced page building.',
				'category'          => 'blocks',
				'authors'           => [ 'KadenceWP' ],
				'documentation_url' => 'https://www.kadencewp.com/help-center/',
			],
			[
				'slug'              => 'kad-pattern-hub',
				'kind'              => 'plugin',
				'minimum_tier'      => 'kadence-basic',
				'wporg_slug'        => null,
				'main_file'         => 'kad-pattern-hub/kad-pattern-hub.php',
				'name'              => 'Pattern Hub',
				'description'       => 'Access to premium design patterns and starter templates.',
				'category'          => 'design',
				'authors'           => [ 'KadenceWP' ],
				'documentation_url' => 'https://www.kadencewp.com/help-center/',
			],
		],
	];

	public function test_from_array_hydrates_all_fields(): void {
		$catalog = Product_Catalog::from_array( $this->valid_data );

		$this->assertSame( 'kadence', $catalog->get_product_slug() );
	}

	public function test_get_tiers_returns_hydrated_objects(): void {
		$catalog = Product_Catalog::from_array( $this->valid_data );
		$tiers   = $catalog->get_tiers();

		$this->assertInstanceOf( Tier_Collection::class, $tiers );
		$this->assertCount( 3, $tiers );

		foreach ( $tiers as $tier ) {
			$this->assertInstanceOf( Catalog_Tier::class, $tier );
		}

		$basic = $tiers->get( 'kadence-basic' );
		$this->assertSame( 'kadence-basic', $basic->get_slug() );
		$this->assertSame( 'Basic', $basic->get_name() );
		$this->assertSame( 1, $basic->get_rank() );

		$agency = $tiers->get( 'kadence-agency' );
		$this->assertSame( 'kadence-agency', $agency->get_slug() );
		$this->assertSame( 3, $agency->get_rank() );
	}

	public function test_get_features_returns_hydrated_objects(): void {
		$catalog  = Product_Catalog::from_array( $this->valid_data );
		$features = $catalog->get_features();

		$this->assertCount( 2, $features );

		foreach ( $features as $feature ) {
			$this->assertInstanceOf( Catalog_Feature::class, $feature );
		}

		$this->assertSame( 'kad-blocks-pro', $features[0]->get_slug() );
		$this->assertSame( 'plugin', $features[0]->get_kind() );
		$this->assertSame( 'kad-pattern-hub', $features[1]->get_slug() );
		$this->assertSame( 'plugin', $features[1]->get_kind() );
	}

	public function test_to_array_produces_expected_shape(): void {
		$catalog = Product_Catalog::from_array( $this->valid_data );
		$result  = $catalog->to_array();

		$this->assertSame( 'kadence-001', $result['product_id'] );
		$this->assertSame( 'kadence', $result['product_slug'] );
		$this->assertSame( 'Kadence', $result['product_name'] );
		$this->assertCount( 3, $result['tiers'] );
		$this->assertSame( 'kadence-basic', $result['tiers'][0]['slug'] );
		$this->assertSame( 'Basic', $result['tiers'][0]['name'] );
		$this->assertSame( 1, $result['tiers'][0]['rank'] );
		$this->assertSame( 0, $result['tiers'][0]['price'] );
		$this->assertSame( 'USD', $result['tiers'][0]['currency'] );
		$this->assertCount( 2, $result['features'] );
		$this->assertSame( 'kad-blocks-pro', $result['features'][0]['slug'] );
	}

	public function test_tier_rank_ordering(): void {
		$catalog = Product_Catalog::from_array( $this->valid_data );
		$tiers   = $catalog->get_tiers();

		$prev = null;

		foreach ( $tiers as $tier ) {
			if ( $prev !== null ) {
				$this->assertLessThan( $tier->get_rank(), $prev->get_rank() );
			}

			$prev = $tier;
		}
	}

	public function test_get_tiers_sorts_by_rank(): void {
		$catalog = Product_Catalog::from_array(
			[
				'product_id'   => '',
				'product_slug' => 'test',
				'product_name' => 'Test',
				'tiers'        => [
					[
						'tier_slug'    => 'agency',
						'name'         => 'Agency',
						'rank'         => 3,
						'price'        => 0,
						'currency'     => 'USD',
						'features'     => [],
						'herald_slugs' => [],
					],
					[
						'tier_slug'    => 'basic',
						'name'         => 'Basic',
						'rank'         => 1,
						'price'        => 0,
						'currency'     => 'USD',
						'features'     => [],
						'herald_slugs' => [],
					],
					[
						'tier_slug'    => 'pro',
						'name'         => 'Pro',
						'rank'         => 2,
						'price'        => 0,
						'currency'     => 'USD',
						'features'     => [],
						'herald_slugs' => [],
					],
				],
				'features'     => [],
			]
		);

		$slugs = array_keys( iterator_to_array( $catalog->get_tiers() ) );

		$this->assertSame( [ 'basic', 'pro', 'agency' ], $slugs );
	}

	public function test_get_tier_by_slug_returns_tier(): void {
		$catalog = Product_Catalog::from_array(
			[
				'product_id'   => '',
				'product_slug' => 'test',
				'product_name' => 'Test',
				'tiers'        => [
					[
						'tier_slug'    => 'basic',
						'name'         => 'Basic',
						'rank'         => 1,
						'price'        => 0,
						'currency'     => 'USD',
						'features'     => [],
						'herald_slugs' => [],
					],
					[
						'tier_slug'    => 'pro',
						'name'         => 'Pro',
						'rank'         => 2,
						'price'        => 0,
						'currency'     => 'USD',
						'features'     => [],
						'herald_slugs' => [],
					],
				],
				'features'     => [],
			]
		);

		$tier = $catalog->get_tier_by_slug( 'pro' );

		$this->assertInstanceOf( Catalog_Tier::class, $tier );
		$this->assertSame( 'pro', $tier->get_slug() );
		$this->assertSame( 2, $tier->get_rank() );
	}

	public function test_get_tier_by_slug_returns_null_for_unknown(): void {
		$catalog = Product_Catalog::from_array(
			[
				'product_id'   => '',
				'product_slug' => 'test',
				'product_name' => 'Test',
				'tiers'        => [
					[
						'tier_slug'    => 'basic',
						'name'         => 'Basic',
						'rank'         => 1,
						'price'        => 0,
						'currency'     => 'USD',
						'features'     => [],
						'herald_slugs' => [],
					],
				],
				'features'     => [],
			]
		);

		$this->assertNull( $catalog->get_tier_by_slug( 'enterprise' ) );
	}

	public function test_missing_optional_arrays_default_to_empty(): void {
		$data = [
			'product_slug' => 'minimal',
		];

		$catalog = Product_Catalog::from_array( $data );

		$this->assertSame( 'minimal', $catalog->get_product_slug() );
		$this->assertInstanceOf( Tier_Collection::class, $catalog->get_tiers() );
		$this->assertCount( 0, $catalog->get_tiers() );
		$this->assertSame( [], $catalog->get_features() );
	}
}
