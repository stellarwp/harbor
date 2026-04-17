<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Licensing;

use LiquidWeb\Harbor\Licensing\Results\Product_Entry;
use LiquidWeb\Harbor\Utils\Collection;

/**
 * A collection of Product_Entry objects, keyed by product slug.
 *
 * @since 1.0.0
 *
 * @extends Collection<Product_Entry>
 */
final class Product_Collection extends Collection {

	/**
	 * Adds a product entry to the collection, keyed by its slug.
	 *
	 * When the licensing server returns multiple entitlements for the same
	 * product slug (one per tier), the entry where activated_here is true
	 * takes precedence over one where it is false or null.
	 *
	 * @since 1.0.0
	 *
	 * @param Product_Entry $entry Product entry instance.
	 *
	 * @return Product_Entry
	 */
	public function add( Product_Entry $entry ): Product_Entry {
		$slug = $entry->get_product_slug();

		if ( ! $this->offsetExists( $slug ) ) {
			$this->offsetSet( $slug, $entry );
		} else {
			// Always replace the entry if it is activated here.
			$existing = $this->offsetGet( $slug );

			if ( $entry->get_activated_here() && ! ( $existing && $existing->get_activated_here() ) ) {
				$this->offsetSet( $slug, $entry );
			}
		}

		return $this->offsetGet( $slug ) ?? $entry;
	}

	/**
	 * Retrieves a product entry by slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $offset The product slug.
	 *
	 * @return Product_Entry|null
	 */
	public function get( $offset ): ?Product_Entry { // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found -- Narrows return type for IDE support.
		return parent::get( $offset );
	}

	/**
	 * Converts the collection to an array of raw data arrays.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function to_array(): array {
		$data = [];

		foreach ( $this as $entry ) {
			$data[] = $entry->to_array();
		}

		return $data;
	}

	/**
	 * Creates a Product_Collection from an array of Product_Entry objects or raw data arrays.
	 *
	 * @since 1.0.0
	 *
	 * @param array<Product_Entry|array<string, mixed>> $entries Product entries or raw arrays.
	 *
	 * @return self
	 */
	public static function from_array( array $entries ): self {
		$collection = new self();

		foreach ( $entries as $entry ) {
			if ( $entry instanceof Product_Entry ) {
				$collection->add( $entry );
			} elseif ( is_array( $entry ) ) {
				$collection->add( Product_Entry::from_array( $entry ) );
			}
		}

		return $collection;
	}
}
