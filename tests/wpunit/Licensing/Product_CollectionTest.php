<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Licensing;

use LiquidWeb\Harbor\Licensing\Product_Collection;
use LiquidWeb\Harbor\Licensing\Results\Product_Entry;
use LiquidWeb\Harbor\Tests\HarborTestCase;

final class Product_CollectionTest extends HarborTestCase {

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function make_entry( string $slug, string $tier, ?bool $activated_here ): Product_Entry {
		$data = [
			'product_slug'      => $slug,
			'tier'              => $tier,
			'status'            => 'active',
			'expires'           => '2027-12-31 23:59:59',
			'activations'       => [
				'site_limit'   => 1,
				'active_count' => 0,
				'over_limit'   => false,
				'domains'      => [],
			],
			'validation_status' => 'valid',
			'is_valid'          => true,
		];

		if ( $activated_here !== null ) {
			$data['activated_here'] = $activated_here;
		}

		return Product_Entry::from_array( $data );
	}

	// -------------------------------------------------------------------------
	// add()
	// -------------------------------------------------------------------------

	public function test_add_stores_a_single_entry(): void {
		$collection = new Product_Collection();
		$collection->add( $this->make_entry( 'learndash', 'pro', true ) );

		$this->assertCount( 1, $collection );
	}

	public function test_add_stores_all_tiers_for_the_same_slug(): void {
		$collection = new Product_Collection();
		$collection->add( $this->make_entry( 'learndash', 'essentials', false ) );
		$collection->add( $this->make_entry( 'learndash', 'pro', false ) );
		$collection->add( $this->make_entry( 'learndash', 'elite', true ) );

		$this->assertCount( 3, $collection );
	}

	public function test_add_treats_different_slugs_independently(): void {
		$collection = new Product_Collection();
		$collection->add( $this->make_entry( 'learndash', 'pro', true ) );
		$collection->add( $this->make_entry( 'kadence', 'essentials', true ) );

		$this->assertCount( 2, $collection );
	}

	// -------------------------------------------------------------------------
	// get_all_by_slug()
	// -------------------------------------------------------------------------

	public function test_get_all_by_slug_returns_all_entries_for_slug(): void {
		$collection = new Product_Collection();
		$collection->add( $this->make_entry( 'learndash', 'essentials', false ) );
		$collection->add( $this->make_entry( 'learndash', 'pro', false ) );
		$collection->add( $this->make_entry( 'learndash', 'elite', true ) );
		$collection->add( $this->make_entry( 'kadence', 'pro', false ) );

		$entries = $collection->get_all_by_slug( 'learndash' );

		$this->assertCount( 3, $entries );
	}

	public function test_get_all_by_slug_returns_empty_array_for_unknown_slug(): void {
		$collection = new Product_Collection();
		$collection->add( $this->make_entry( 'learndash', 'pro', false ) );

		$this->assertSame( [], $collection->get_all_by_slug( 'unknown' ) );
	}

	public function test_get_all_by_slug_does_not_return_entries_for_other_slugs(): void {
		$collection = new Product_Collection();
		$collection->add( $this->make_entry( 'learndash', 'pro', false ) );
		$collection->add( $this->make_entry( 'kadence', 'pro', false ) );

		$entries = $collection->get_all_by_slug( 'learndash' );

		$this->assertCount( 1, $entries );
		$this->assertSame( 'learndash', $entries[0]->get_product_slug() );
	}

	// -------------------------------------------------------------------------
	// get_activated_entry()
	// -------------------------------------------------------------------------

	public function test_get_activated_entry_returns_the_activated_here_entry(): void {
		$collection = new Product_Collection();
		$collection->add( $this->make_entry( 'learndash', 'essentials', false ) );
		$collection->add( $this->make_entry( 'learndash', 'pro', true ) );
		$collection->add( $this->make_entry( 'learndash', 'elite', false ) );

		$entry = $collection->get_activated_entry( 'learndash' );

		$this->assertNotNull( $entry );
		$this->assertSame( 'pro', $entry->get_tier() );
	}

	public function test_get_activated_entry_returns_null_when_none_is_activated_here(): void {
		$collection = new Product_Collection();
		$collection->add( $this->make_entry( 'learndash', 'essentials', false ) );
		$collection->add( $this->make_entry( 'learndash', 'pro', false ) );

		$this->assertNull( $collection->get_activated_entry( 'learndash' ) );
	}

	public function test_get_activated_entry_returns_null_for_unknown_slug(): void {
		$collection = new Product_Collection();
		$collection->add( $this->make_entry( 'learndash', 'pro', true ) );

		$this->assertNull( $collection->get_activated_entry( 'unknown' ) );
	}

	public function test_get_activated_entry_treats_null_activated_here_as_not_activated(): void {
		$collection = new Product_Collection();
		$collection->add( $this->make_entry( 'learndash', 'essentials', null ) );
		$collection->add( $this->make_entry( 'learndash', 'pro', null ) );

		$this->assertNull( $collection->get_activated_entry( 'learndash' ) );
	}
}
