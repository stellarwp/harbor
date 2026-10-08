<?php
/** Local checks that access explanations follow the actual grant decisions. */

use LiquidWeb\Harbor\Features\Feature_Resource;
use LiquidWeb\Harbor\Features\Resolve_Feature_Collection;
use LiquidWeb\Harbor\Licensing\License_Manager;
use LiquidWeb\Harbor\Licensing\Product_Collection;
use LiquidWeb\Harbor\Legacy\Legacy_License;
use LiquidWeb\Harbor\Legacy\License_Repository;
use LiquidWeb\Harbor\Portal\Catalog_Collection;
use LiquidWeb\Harbor\Portal\Catalog_Repository;
use LiquidWeb\Harbor\Site\Data;

$catalog = new class extends Catalog_Repository {
	public function __construct() {}
	public function get() {
		return Catalog_Collection::from_array( [
			[
				'product_slug' => 'kadence', 'product_name' => 'Kadence',
				'tiers' => [ [ 'tier_slug' => 'pro', 'name' => 'Pro', 'rank' => 2 ] ],
				'features' => [
					[ 'slug' => 'kadence-blocks-pro', 'kind' => 'plugin', 'minimum_tier' => 'pro' ],
					[ 'slug' => 'kadence-conversions', 'kind' => 'plugin', 'minimum_tier' => 'pro' ],
					[ 'slug' => 'kadence-blocks', 'kind' => 'plugin', 'wporg_slug' => 'kadence-blocks' ],
					[ 'slug' => 'demo-service', 'kind' => 'service', 'minimum_tier' => 'pro' ],
				],
			],
			[
				'product_slug' => 'any-package', 'product_name' => 'Example Suite',
				'tiers' => [ [ 'tier_slug' => 'complete', 'name' => '1 site', 'rank' => 1 ] ],
				'features' => [],
			],
		] );
	}
};
$licensing = new class extends License_Manager {
	public array $entries = [];
	public function __construct() {}
	public function get_products( string $domain ) { return Product_Collection::from_array( $this->entries ); }
};
$legacy = new class extends License_Repository {
	public array $entries = [];
	public function all(): array { return $this->entries; }
};
$resolver = new Resolve_Feature_Collection( $catalog, $licensing, new Data(), $legacy );
$suite = [ 'product_slug' => 'any-package', 'tier' => 'complete', 'status' => 'active', 'activated_here' => true, 'validation_status' => 'valid', 'capabilities' => [ 'kadence-blocks-pro', 'demo-service' ] ];
$brand = array_replace( $suite, [ 'product_slug' => 'kadence', 'tier' => 'pro', 'capabilities' => [ 'kadence-blocks-pro', 'kadence-conversions' ] ] );
$cases = [
	'overlapping purchases' => [ [ $suite, $brand ], 'kadence-blocks-pro', [ 'any-package:complete', 'kadence:pro' ] ],
	'outside package' => [ [ $suite, $brand ], 'kadence-conversions', [ 'kadence:pro' ] ],
	'expired brand retains package access' => [ [ $suite, array_replace( $brand, [ 'validation_status' => 'expired' ] ) ], 'kadence-blocks-pro', [ 'any-package:complete' ] ],
	'expired sole source removes access' => [ [ $suite, array_replace( $brand, [ 'validation_status' => 'expired' ] ) ], 'kadence-conversions', [] ],
	'unactivated package is not a source' => [ [ array_replace( $suite, [ 'activated_here' => false ] ), $brand ], 'kadence-blocks-pro', [ 'kadence:pro' ] ],
	'suspended package is not a source' => [ [ array_replace( $suite, [ 'validation_status' => 'suspended' ] ), $brand ], 'kadence-blocks-pro', [ 'kadence:pro' ] ],
	'free without purchase' => [ [], 'kadence-blocks', [ 'free' ] ],
	'service serialization preserves sources' => [ [ $suite ], 'demo-service', [ 'any-package:complete' ] ],
	'missing catalog purchase uses identifiers' => [ [ array_replace( $suite, [ 'product_slug' => 'new-offer' ] ) ], 'kadence-blocks-pro', [ 'new-offer:complete' ] ],
	'repeated capabilities do not duplicate sources' => [ [ array_replace( $suite, [ 'capabilities' => [ 'kadence-blocks-pro', 'kadence-blocks-pro' ] ] ) ], 'kadence-blocks-pro', [ 'any-package:complete' ] ],
	'empty capabilities do not grant access' => [ [ array_replace( $suite, [ 'capabilities' => [] ] ) ], 'kadence-blocks-pro', [] ],
];
foreach ( $cases as $label => [ $entries, $slug, $expected ] ) {
	$licensing->entries = $entries;
	$feature = $resolver()->get( $slug );
	$data = ( new Feature_Resource( $feature, null ) )->to_array();
	$actual = array_map( static fn( $source ) => $source['type'] === 'purchase' ? $source['product_slug'] . ':' . $source['tier'] : $source['type'], $data['access_sources'] ?? [] );
	if ( $actual !== $expected || $feature->is_available() !== ( $expected !== [] ) ) {
		WP_CLI::error( $label . ': availability or source list does not match' );
	}
	if ( $label === 'overlapping purchases' && ( $data['access_sources'][0]['product_name'] !== 'Example Suite' || $data['access_sources'][1]['tier_name'] !== 'Pro' ) ) {
		WP_CLI::error( 'Source display names must come from the catalog.' );
	}
	WP_CLI::success( $label );
}

$licensing->entries = [];
foreach ( [ true, false ] as $opt_in ) {
	$legacy->entries = [ Legacy_License::from_data( [ 'slug' => 'kadence-blocks-pro', 'key' => 'synthetic-legacy-key', 'is_active' => true, 'use_for_updates' => $opt_in ] ) ];
	$data = ( new Feature_Resource( $resolver()->get( 'kadence-blocks-pro' ), null ) )->to_array();
	$expected = $opt_in ? [ [ 'type' => 'legacy' ] ] : [];
	if ( $data['access_sources'] !== $expected || $data['is_available'] !== $opt_in ) {
		WP_CLI::error( 'Legacy access explanation must respect the update opt-in and omit license keys.' );
	}
}
WP_CLI::success( 'Legacy grants honor opt-in without exposing keys' );
