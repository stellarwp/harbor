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

		$this->assertSame( 'pro', $collection->get( 'learndash' )->get_tier() );
	}

	public function test_add_first_wins_when_neither_entry_is_activated_here(): void {
		$collection = new Product_Collection();
		$collection->add( $this->make_entry( 'learndash', 'essentials', false ) );
		$collection->add( $this->make_entry( 'learndash', 'pro', false ) );

		$this->assertSame( 'essentials', $collection->get( 'learndash' )->get_tier() );
	}

	public function test_add_replaces_first_entry_when_later_entry_is_activated_here(): void {
		$collection = new Product_Collection();
		$collection->add( $this->make_entry( 'learndash', 'essentials', false ) );
		$collection->add( $this->make_entry( 'learndash', 'pro', true ) );

		$this->assertSame( 'pro', $collection->get( 'learndash' )->get_tier() );
	}

	public function test_add_does_not_replace_activated_here_entry_with_non_activated(): void {
		$collection = new Product_Collection();
		$collection->add( $this->make_entry( 'learndash', 'pro', true ) );
		$collection->add( $this->make_entry( 'learndash', 'elite', false ) );

		$this->assertSame( 'pro', $collection->get( 'learndash' )->get_tier() );
	}

	public function test_add_first_wins_when_both_entries_are_activated_here(): void {
		$collection = new Product_Collection();
		$collection->add( $this->make_entry( 'learndash', 'pro', true ) );
		$collection->add( $this->make_entry( 'learndash', 'elite', true ) );

		$this->assertSame( 'pro', $collection->get( 'learndash' )->get_tier() );
	}

	public function test_add_picks_activated_entry_from_three_tier_response(): void {
		// Mirrors the real server response: three entitlements for the same product,
		// only the middle one (pro) is activated on the current domain.
		$collection = new Product_Collection();
		$collection->add( $this->make_entry( 'learndash', 'essentials', false ) );
		$collection->add( $this->make_entry( 'learndash', 'pro', true ) );
		$collection->add( $this->make_entry( 'learndash', 'elite', false ) );

		$this->assertSame( 'pro', $collection->get( 'learndash' )->get_tier() );
		$this->assertCount( 1, $collection );
	}

	public function test_add_treats_different_slugs_independently(): void {
		$collection = new Product_Collection();
		$collection->add( $this->make_entry( 'learndash', 'pro', true ) );
		$collection->add( $this->make_entry( 'kadence', 'essentials', true ) );

		$this->assertSame( 'pro', $collection->get( 'learndash' )->get_tier() );
		$this->assertSame( 'essentials', $collection->get( 'kadence' )->get_tier() );
		$this->assertCount( 2, $collection );
	}

	public function test_add_handles_null_activated_here_as_not_activated(): void {
		// activated_here is null when no domain was sent in the request.
		// It should behave the same as false — first-wins, not treated as activated.
		$collection = new Product_Collection();
		$collection->add( $this->make_entry( 'learndash', 'essentials', null ) );
		$collection->add( $this->make_entry( 'learndash', 'pro', true ) );

		$this->assertSame( 'pro', $collection->get( 'learndash' )->get_tier() );
	}
}
