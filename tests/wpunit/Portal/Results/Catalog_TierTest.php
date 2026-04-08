<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Catalog\Results;

use LiquidWeb\Harbor\Portal\Results\Catalog_Tier;
use LiquidWeb\Harbor\Tests\HarborTestCase;

final class Catalog_TierTest extends HarborTestCase {

	private array $valid_data = [
		'tier_slug'    => 'kadence-pro',
		'name'         => 'Pro',
		'rank'         => 2,
		'price'        => 14900,
		'currency'     => 'USD',
		'features'     => [ 'Premium blocks', 'Priority support' ],
		'herald_slugs' => [ 'kadence-blocks-pro' ],
		'purchase_url' => 'https://example.com/checkout/?add-to-cart=123',
	];

	public function test_from_array_hydrates_all_fields(): void {
		$tier = Catalog_Tier::from_array( $this->valid_data );

		$this->assertSame( 'kadence-pro', $tier->get_slug() );
		$this->assertSame( 'Pro', $tier->get_name() );
		$this->assertSame( 2, $tier->get_rank() );
		$this->assertSame( 14900, $tier->get_price() );
		$this->assertSame( 'USD', $tier->get_currency() );
		$this->assertSame( [ 'Premium blocks', 'Priority support' ], $tier->get_features() );
		$this->assertSame( [ 'kadence-blocks-pro' ], $tier->get_herald_slugs() );
		$this->assertSame( 'https://example.com/checkout/?add-to-cart=123', $tier->get_purchase_url() );
	}

	public function test_to_array_produces_expected_shape(): void {
		$tier   = Catalog_Tier::from_array( $this->valid_data );
		$result = $tier->to_array();

		$this->assertSame( 'kadence-pro', $result['slug'] );
		$this->assertSame( 'Pro', $result['name'] );
		$this->assertSame( 2, $result['rank'] );
		$this->assertSame( 14900, $result['price'] );
		$this->assertSame( 'USD', $result['currency'] );
		$this->assertSame( [ 'Premium blocks', 'Priority support' ], $result['features'] );
		$this->assertSame( [ 'kadence-blocks-pro' ], $result['herald_slugs'] );
		$this->assertSame( 'https://example.com/checkout/?add-to-cart=123', $result['purchase_url'] );
	}

	public function test_missing_fields_default(): void {
		$tier = Catalog_Tier::from_array( [] );

		$this->assertSame( '', $tier->get_slug() );
		$this->assertSame( '', $tier->get_name() );
		$this->assertSame( 0, $tier->get_rank() );
		$this->assertSame( 0, $tier->get_price() );
		$this->assertSame( '', $tier->get_currency() );
		$this->assertSame( [], $tier->get_features() );
		$this->assertSame( [], $tier->get_herald_slugs() );
		$this->assertSame( '', $tier->get_purchase_url() );
	}
}
