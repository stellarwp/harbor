<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Licensing;

use LiquidWeb\Harbor\Tests\Licensing\Fixture_Client;
use LiquidWeb\Harbor\Licensing\Enums\Validation_Status;
use LiquidWeb\LicensingApiClient\Exceptions\NotFoundException;
use LiquidWeb\LicensingApiClient\Responses\Product\Catalog;
use LiquidWeb\Harbor\Tests\HarborTestCase;

final class Fixture_ClientTest extends HarborTestCase {

	private Fixture_Client $client;

	protected function setUp(): void {
		parent::setUp();

		$this->client = new Fixture_Client( codecept_data_dir( 'licensing' ) );
	}

	public function test_catalog_unified_pro_returns_four_entries(): void {
		$catalog = $this->client->products()->catalog( 'LWSW-UNIFIED-PRO-2026', 'example.com' );

		$this->assertInstanceOf( Catalog::class, $catalog );
		$this->assertCount( 4, $catalog->products );
	}

	public function test_catalog_unified_pro_returns_correct_slugs(): void {
		$catalog = $this->client->products()->catalog( 'LWSW-UNIFIED-PRO-2026', 'example.com' );

		$slugs = array_map(
			static function ( $entry ): string {
				return $entry->productSlug;
			},
			iterator_to_array( $catalog->products )
		);

		$this->assertSame( [ 'give', 'the-events-calendar', 'learndash', 'kadence' ], $slugs );
	}

	public function test_catalog_unified_pro_all_tiers_are_pro(): void {
		$catalog = $this->client->products()->catalog( 'LWSW-UNIFIED-PRO-2026', 'example.com' );

		foreach ( $catalog->products as $entry ) {
			$expected = $entry->productSlug . '-pro';
			$this->assertSame( $expected, $entry->tier, sprintf( '%s should be %s tier', $entry->productSlug, $expected ) );
		}
	}

	public function test_catalog_unified_basic_all_tiers_are_basic(): void {
		$catalog = $this->client->products()->catalog( 'LWSW-UNIFIED-BASIC-2026', 'example.com' );

		$this->assertCount( 4, $catalog->products );

		foreach ( $catalog->products as $entry ) {
			$expected = $entry->productSlug . '-basic';
			$this->assertSame( $expected, $entry->tier, sprintf( '%s should be %s tier', $entry->productSlug, $expected ) );
		}
	}

	public function test_catalog_unified_agency_unlimited_seats(): void {
		$catalog = $this->client->products()->catalog( 'LWSW-UNIFIED-AGENCY-2026', 'example.com' );

		foreach ( $catalog->products as $entry ) {
			$expected = $entry->productSlug . '-agency';
			$this->assertSame( $expected, $entry->tier );
			$this->assertSame( 0, $entry->activations->siteLimit, sprintf( '%s should have unlimited seats', $entry->productSlug ) );
		}
	}

	public function test_catalog_expired_key(): void {
		$catalog = $this->client->products()->catalog( 'LWSW-UNIFIED-PRO-EXPIRED', 'example.com' );

		$this->assertCount( 4, $catalog->products );

		foreach ( $catalog->products as $entry ) {
			$this->assertSame( Validation_Status::EXPIRED, $entry->validationStatus );
			$this->assertFalse( $entry->isValid );
			$this->assertSame( 'expired', $entry->status );
		}
	}

	public function test_catalog_single_product_key(): void {
		$catalog = $this->client->products()->catalog( 'LWSW-UNIFIED-KAD-PRO-2026', 'example.com' );

		$this->assertCount( 1, $catalog->products );
		$this->assertSame( 'kadence', $catalog->products[0]->productSlug );
		$this->assertSame( 'kadence-pro', $catalog->products[0]->tier );
	}

	public function test_catalog_two_product_key(): void {
		$catalog = $this->client->products()->catalog( 'LWSW-UNIFIED-KAD-GIVE-2026', 'example.com' );

		$this->assertCount( 2, $catalog->products );

		$slugs = array_map(
			static function ( $entry ): string {
				return $entry->productSlug;
			},
			iterator_to_array( $catalog->products )
		);

		$this->assertContains( 'kadence', $slugs );
		$this->assertContains( 'give', $slugs );
	}

	public function test_catalog_unknown_key_throws_not_found(): void {
		$this->expectException( NotFoundException::class );

		$this->client->products()->catalog( 'NON-EXISTENT-KEY', 'example.com' );
	}

	public function test_catalog_result_is_cached(): void {
		$first  = $this->client->products()->catalog( 'LWSW-UNIFIED-PRO-2026', 'example.com' );
		$second = $this->client->products()->catalog( 'LWSW-UNIFIED-PRO-2026', 'example.com' );

		$this->assertSame( $first, $second );
	}
}
