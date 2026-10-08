<?php
/**
 * Local resolver acceptance checks. Run with wp eval-file on the Harbor lab.
 */

use LiquidWeb\Harbor\Features\Resolve_Feature_Collection;
use LiquidWeb\Harbor\Licensing\License_Manager;
use LiquidWeb\Harbor\Licensing\Product_Collection;
use LiquidWeb\Harbor\Portal\Catalog_Collection;
use LiquidWeb\Harbor\Portal\Catalog_Repository;
use LiquidWeb\Harbor\Portal\Results\Catalog_Tier;
use LiquidWeb\Harbor\Portal\Results\Product_Catalog;
use LiquidWeb\Harbor\Site\Data;

foreach ( [ 'herald_slugs', 'capabilities' ] as $field ) {
	$tier = Catalog_Tier::from_array( [ $field => [ 'kadence-blocks-pro' ] ] );
	if ( $tier->get_herald_slugs() !== [ 'kadence-blocks-pro' ] || Catalog_Tier::from_array( $tier->to_array() )->get_herald_slugs() !== [ 'kadence-blocks-pro' ] ) {
		WP_CLI::error( 'Catalog capability input or serialized round trip failed: ' . $field );
	}
	WP_CLI::success( 'Catalog input and serialized round trip: ' . $field );
}

$catalog   = new class() extends Catalog_Repository {
	/**
	 * Skip parent setup so this check never reads the site's stored licenses or catalog.
	 */
	public function __construct() {} // phpcs:ignore StellarWP.NamingConventions.ValidFunctionName.FunctionDoubleUnderscore -- The sniff does not recognize anonymous-class constructors.
	public function get() {
		$result = new Catalog_Collection();
		$result->add(
			Product_Catalog::from_array(
				[
					'product_slug' => 'kadence',
					'product_name' => 'Kadence',
					'tiers'        => [
						[
							'tier_slug' => 'pro',
							'rank'      => 2,
						],
					],
					'features'     => [
						[
							'slug'         => 'kadence-blocks-pro',
							'kind'         => 'plugin',
							'minimum_tier' => 'pro',
						],
					],
				]
			)
		);
		return $result;
	}
};
$licensing = new class() extends License_Manager {
	public array $entries = [];
	/**
	 * Skip parent setup so this check never reads the site's stored licenses or catalog.
	 */
	public function __construct() {} // phpcs:ignore StellarWP.NamingConventions.ValidFunctionName.FunctionDoubleUnderscore -- The sniff does not recognize anonymous-class constructors.
	public function get_products( string $domain ) {
		return Product_Collection::from_array( $this->entries );
	}
};
$resolver  = new Resolve_Feature_Collection( $catalog, $licensing, new Data() );
$package   = [
	'product_slug'      => 'any-package-name',
	'tier'              => 'complete',
	'status'            => 'active',
	'activated_here'    => true,
	'validation_status' => 'valid',
	'capabilities'      => [ 'kadence-blocks-pro' ],
];
$cases     = [
	'activated package grants a brand feature'  => [ [ $package ], true ],
	'unactivated package does not grant access' => [ [ array_replace( $package, [ 'activated_here' => false ] ) ], false ],
	'expired package does not grant access'     => [ [ array_replace( $package, [ 'validation_status' => 'expired' ] ) ], false ],
	'missing capability does not grant access'  => [ [ array_replace( $package, [ 'capabilities' => [] ] ) ], false ],
	'retained brand survives expired package'   => [
		[
			array_replace( $package, [ 'validation_status' => 'expired' ] ),
			array_replace(
				$package,
				[
					'product_slug' => 'kadence',
					'tier'         => 'pro',
				]
			),
		],
		true,
	],
];
foreach ( $cases as $label => [ $entries, $expected ] ) {
	$licensing->entries = $entries;
	$feature            = $resolver()->get( 'kadence-blocks-pro' );
	if ( $feature->is_available() !== $expected || $feature->is_in_catalog_tier() !== $expected ) {
		WP_CLI::error( $label );
	}
	WP_CLI::success( $label );
}
