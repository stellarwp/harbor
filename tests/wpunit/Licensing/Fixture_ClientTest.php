<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Licensing;

use LiquidWeb\Harbor\Licensing\Error_Code;
use LiquidWeb\Harbor\Licensing\Enums\Validation_Status;
use LiquidWeb\Harbor\Licensing\Clients\Fixture_Client;
use LiquidWeb\Harbor\Licensing\Results\Product_Entry;
use LiquidWeb\Harbor\Tests\HarborTestCase;
use WP_Error;

final class Fixture_ClientTest extends HarborTestCase {

	private Fixture_Client $client;

	protected function setUp(): void {
		parent::setUp();

		$this->client = new Fixture_Client( codecept_data_dir( 'licensing' ) );
	}

	public function test_get_products_unified_pro_returns_four_entries(): void {
		$products = $this->client->get_products( 'LWSW-UNIFIED-PRO-2026', 'example.com' );

		$this->assertIsArray( $products );
		$this->assertCount( 4, $products );

		foreach ( $products as $entry ) {
			$this->assertInstanceOf( Product_Entry::class, $entry );
		}
	}

	public function test_get_products_unified_pro_returns_correct_slugs(): void {
		$products = $this->client->get_products( 'LWSW-UNIFIED-PRO-2026', 'example.com' );

		$slugs = array_map(
			static function ( Product_Entry $entry ): string {
				return $entry->get_product_slug();
			},
			$products
		);

		$this->assertSame( [ 'give', 'the-events-calendar', 'learndash', 'kadence' ], $slugs );
	}

	public function test_get_products_unified_pro_all_tiers_are_pro(): void {
		$products = $this->client->get_products( 'LWSW-UNIFIED-PRO-2026', 'example.com' );

		foreach ( $products as $entry ) {
			$expected = $entry->get_product_slug() . '-pro';
			$this->assertSame( $expected, $entry->get_tier(), sprintf( '%s should be %s tier', $entry->get_product_slug(), $expected ) );
		}
	}

	public function test_get_products_unified_basic_all_tiers_are_basic(): void {
		$products = $this->client->get_products( 'LWSW-UNIFIED-BASIC-2026', 'example.com' );

		$this->assertIsArray( $products );
		$this->assertCount( 4, $products );

		foreach ( $products as $entry ) {
			$expected = $entry->get_product_slug() . '-basic';
			$this->assertSame( $expected, $entry->get_tier(), sprintf( '%s should be %s tier', $entry->get_product_slug(), $expected ) );
		}
	}

	public function test_get_products_unified_agency_unlimited_seats(): void {
		$products = $this->client->get_products( 'LWSW-UNIFIED-AGENCY-2026', 'example.com' );

		$this->assertIsArray( $products );

		foreach ( $products as $entry ) {
			$expected = $entry->get_product_slug() . '-agency';
			$this->assertSame( $expected, $entry->get_tier() );
			$this->assertSame( 0, $entry->get_site_limit(), sprintf( '%s should have unlimited seats', $entry->get_product_slug() ) );
			$this->assertFalse( $entry->is_over_limit() );
		}
	}

	public function test_get_products_expired_key(): void {
		$products = $this->client->get_products( 'LWSW-UNIFIED-PRO-EXPIRED', 'example.com' );

		$this->assertIsArray( $products );
		$this->assertCount( 4, $products );

		foreach ( $products as $entry ) {
			$this->assertSame( Validation_Status::EXPIRED, $entry->get_validation_status() );
			$this->assertFalse( $entry->is_valid() );
			$this->assertSame( 'expired', $entry->get_status() );
		}
	}

	public function test_get_products_single_product_key(): void {
		$products = $this->client->get_products( 'LWSW-UNIFIED-KAD-PRO-2026', 'example.com' );

		$this->assertIsArray( $products );
		$this->assertCount( 1, $products );
		$this->assertSame( 'kadence', $products[0]->get_product_slug() );
		$this->assertSame( 'kadence-pro', $products[0]->get_tier() );
	}

	public function test_get_products_two_product_key(): void {
		$products = $this->client->get_products( 'LWSW-UNIFIED-KAD-GIVE-2026', 'example.com' );

		$this->assertIsArray( $products );
		$this->assertCount( 2, $products );

		$slugs = array_map(
			static function ( Product_Entry $entry ): string {
				return $entry->get_product_slug();
			},
			$products
		);

		$this->assertContains( 'kadence', $slugs );
		$this->assertContains( 'give', $slugs );
	}

	public function test_key_to_filename_conversion(): void {
		$products = $this->client->get_products( 'LWSW-UNIFIED-PRO-2026', 'example.com' );

		$this->assertIsArray( $products );
		$this->assertCount( 4, $products );
	}

	public function test_get_products_unknown_key_returns_error(): void {
		$result = $this->client->get_products( 'NON-EXISTENT-KEY', 'example.com' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Error_Code::INVALID_KEY, $result->get_error_code() );
	}
}
