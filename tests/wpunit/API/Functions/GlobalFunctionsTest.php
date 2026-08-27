<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\API\Functions;

use LiquidWeb\Harbor\Licensing\Repositories\License_Repository;
use LiquidWeb\Harbor\Licensing\Product_Collection;
use LiquidWeb\Harbor\Licensing\Results\Product_Entry;
use LiquidWeb\Harbor\Portal\Catalog_Collection;
use LiquidWeb\Harbor\Portal\Catalog_Repository;
use LiquidWeb\Harbor\Tests\HarborTestCase;
use LiquidWeb\Harbor\Tests\Traits\With_Uopz;
use LiquidWeb\Harbor\Harbor;
use WP_Error;

/**
 * Tests for the global helper functions defined in global-functions.php.
 *
 * These functions are the public API for StellarWP products to check licensing
 * and feature state. They delegate to version-keyed closures in _lw_harbor_global_function_registry()
 * so that the highest-version Harbor instance's logic always runs.
 *
 * @since 1.0.0
 */
final class GlobalFunctionsTest extends HarborTestCase {

	use With_Uopz;

	protected function setUp(): void {
		parent::setUp();

		delete_option( License_Repository::KEY_OPTION_NAME );
		delete_option( License_Repository::PRODUCTS_STATE_OPTION_NAME );
	}

	protected function tearDown(): void {
		delete_option( License_Repository::KEY_OPTION_NAME );
		delete_option( License_Repository::PRODUCTS_STATE_OPTION_NAME );

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// _lw_harbor_global_function_registry()
	// -------------------------------------------------------------------------

	public function test_registry_returns_null_for_unregistered_key(): void {
		$this->assertNull( _lw_harbor_global_function_registry( 'nonexistent_key_global_functions_test' ) );
	}

	public function test_registry_write_returns_null(): void {
		$result = _lw_harbor_global_function_registry( 'test_write_global_functions', '1.0.0', fn() => true );

		$this->assertNull( $result );
	}

	public function test_registry_returns_callable_for_bootstrap_registered_key(): void {
		// Callbacks registered before wp_loaded (by the bootstrap plugin via Harbor::init())
		// should be accessible through the registry.
		$callback = _lw_harbor_global_function_registry( 'lw_harbor_has_unified_license_key' );

		$this->assertNotNull( $callback );
		$this->assertIsCallable( $callback );
	}

	public function test_registry_silently_ignores_writes_after_wp_loaded(): void {
		// Writes after wp_loaded are blocked to prevent late injection.
		// The write returns null (same as a successful write) but the callback is not stored.
		_lw_harbor_global_function_registry( 'test_post_lock_key', Harbor::VERSION, fn() => 'new' );

		$callback = _lw_harbor_global_function_registry( 'test_post_lock_key' );

		$this->assertNull( $callback );
	}

	public function test_registry_returns_null_when_callback_registered_below_leader_version(): void {
		// Register only at a version lower than the leader — the registry
		// resolves to the leader version, which has no callback for this key.
		_lw_harbor_global_function_registry( 'test_lower_version_only', '1.0.0', fn() => 'old' );

		$callback = _lw_harbor_global_function_registry( 'test_lower_version_only' );

		$this->assertNull( $callback );
	}

	// -------------------------------------------------------------------------
	// lw_harbor_has_unified_license_key()
	// -------------------------------------------------------------------------

	public function test_has_unified_license_key_returns_false_without_stored_key(): void {
		$this->assertFalse( lw_harbor_has_unified_license_key() );
	}

	public function test_has_unified_license_key_returns_true_with_stored_key(): void {
		update_option( License_Repository::KEY_OPTION_NAME, 'LWSW-UNIFIED-PRO-2026' );

		$this->assertTrue( lw_harbor_has_unified_license_key() );
	}

	public function test_has_unified_license_key_returns_false_after_key_is_deleted(): void {
		update_option( License_Repository::KEY_OPTION_NAME, 'LWSW-UNIFIED-PRO-2026' );
		delete_option( License_Repository::KEY_OPTION_NAME );

		$this->assertFalse( lw_harbor_has_unified_license_key() );
	}

	// -------------------------------------------------------------------------
	// lw_harbor_is_product_license_active()
	// -------------------------------------------------------------------------

	public function test_is_product_license_active_returns_false_without_cached_products(): void {
		$this->assertFalse( lw_harbor_is_product_license_active( 'give' ) );
	}

	public function test_is_product_license_active_returns_true_for_valid_product(): void {
		$collection = Product_Collection::from_array(
			[
				Product_Entry::from_array(
					[
						'product_slug'      => 'give',
						'tier'              => 'pro',
						'status'            => 'active',
						'expires'           => '2030-12-31 23:59:59',
						'validation_status' => 'valid',
					]
				),
			]
		);

		update_option(
			License_Repository::PRODUCTS_STATE_OPTION_NAME,
			[
				'collection'      => $collection->to_array(),
				'last_success_at' => null,
				'last_error'      => null,
			] 
		);

		$this->assertTrue( lw_harbor_is_product_license_active( 'give' ) );
	}

	public function test_is_product_license_active_returns_false_for_invalid_product(): void {
		$collection = Product_Collection::from_array(
			[
				Product_Entry::from_array(
					[
						'product_slug'      => 'give',
						'tier'              => 'pro',
						'status'            => 'active',
						'expires'           => '2030-12-31 23:59:59',
						'validation_status' => 'not_activated',
					]
				),
			]
		);

		update_option(
			License_Repository::PRODUCTS_STATE_OPTION_NAME,
			[
				'collection'      => $collection->to_array(),
				'last_success_at' => null,
				'last_error'      => null,
			] 
		);

		$this->assertFalse( lw_harbor_is_product_license_active( 'give' ) );
	}

	public function test_is_product_license_active_returns_false_for_unknown_product(): void {
		$collection = Product_Collection::from_array(
			[
				Product_Entry::from_array(
					[
						'product_slug'      => 'give',
						'tier'              => 'pro',
						'status'            => 'active',
						'expires'           => '2030-12-31 23:59:59',
						'validation_status' => 'valid',
					]
				),
			]
		);

		update_option(
			License_Repository::PRODUCTS_STATE_OPTION_NAME,
			[
				'collection'      => $collection->to_array(),
				'last_success_at' => null,
				'last_error'      => null,
			] 
		);

		$this->assertFalse( lw_harbor_is_product_license_active( 'learndash' ) );
	}

	// -------------------------------------------------------------------------
	// lw_harbor_is_feature_enabled() / lw_harbor_is_feature_available()
	// -------------------------------------------------------------------------

	public function test_is_feature_enabled_returns_false_when_no_license_key_stored(): void {
		$this->assertFalse( lw_harbor_is_feature_enabled( 'any-feature' ) );
	}

	public function test_is_feature_available_returns_false_when_no_license_key_stored(): void {
		$this->assertFalse( lw_harbor_is_feature_available( 'any-feature' ) );
	}

	// -------------------------------------------------------------------------
	// lw_harbor_get_unified_license_key()
	// -------------------------------------------------------------------------

	public function test_get_unified_license_key_returns_null_without_stored_key(): void {
		$this->assertNull( lw_harbor_get_unified_license_key() );
	}

	public function test_get_unified_license_key_returns_stored_key(): void {
		update_option( License_Repository::KEY_OPTION_NAME, 'LWSW-UNIFIED-PRO-2026' );

		$this->assertSame( 'LWSW-UNIFIED-PRO-2026', lw_harbor_get_unified_license_key() );
	}

	public function test_get_unified_license_key_returns_null_after_key_is_deleted(): void {
		update_option( License_Repository::KEY_OPTION_NAME, 'LWSW-UNIFIED-PRO-2026' );
		delete_option( License_Repository::KEY_OPTION_NAME );

		$this->assertNull( lw_harbor_get_unified_license_key() );
	}

	// -------------------------------------------------------------------------
	// lw_harbor_get_licensed_domain()
	// -------------------------------------------------------------------------

	public function test_get_licensed_domain_matches_site_url_host(): void {
		$parsed   = wp_parse_url( get_option( 'siteurl', '' ) );
		$expected = strtolower( $parsed['host'] ?? '' );

		$domain = lw_harbor_get_licensed_domain();

		$this->assertNotEmpty( $domain );
		$this->assertSame( $expected, $domain );
	}

	// -------------------------------------------------------------------------
	// lw_harbor_refresh_catalog()
	// -------------------------------------------------------------------------

	public function test_refresh_catalog_invokes_repository_refresh_and_returns_true_on_success(): void {
		$called = false;

		$catalog = $this->makeEmpty(
			Catalog_Repository::class,
			[
				'refresh' => static function () use ( &$called ): Catalog_Collection {
					$called = true;

					return new Catalog_Collection();
				},
			]
		);

		$this->container->singleton( Catalog_Repository::class, $catalog );

		$result = lw_harbor_refresh_catalog();

		$this->assertTrue( $called );
		$this->assertTrue( $result );
	}

	public function test_refresh_catalog_returns_false_when_refresh_returns_wp_error(): void {
		$catalog = $this->makeEmpty(
			Catalog_Repository::class,
			[
				'refresh' => static function (): WP_Error {
					return new WP_Error( 'catalog_error', 'API unavailable.' );
				},
			]
		);

		$this->container->singleton( Catalog_Repository::class, $catalog );

		$this->assertFalse( lw_harbor_refresh_catalog() );
	}

	// -------------------------------------------------------------------------
	// lw_harbor_get_product_activation_base_url() / lw_harbor_get_product_activation_url()
	// -------------------------------------------------------------------------

	public function test_get_product_activation_base_url_targets_the_subscriptions_screen(): void {
		$url = lw_harbor_get_product_activation_base_url();

		$this->assertStringContainsString( '/subscriptions/', $url );
		$this->assertStringContainsString( 'portal-referral=plugin', $url );
		$this->assertStringNotContainsString( 'sku=', $url );
	}

	public function test_get_product_activation_base_url_carries_the_given_return_destination(): void {
		$url = lw_harbor_get_product_activation_base_url( 'https://example.test/onboarding' );

		$this->assertStringContainsString( rawurlencode( 'https://example.test/onboarding' ), $url );
		// The return trip is tagged so Harbor refreshes its cache on the way back.
		$this->assertStringContainsString( 'lw-harbor-activated', $url );
	}

	public function test_get_product_activation_base_url_encodes_the_query_per_rfc3986(): void {
		$url = lw_harbor_get_product_activation_base_url( 'https://example.test/on boarding~step' );

		// The query is built with PHP_QUERY_RFC3986, not PHP's RFC1738 default.
		// It matters because redirect_url carries a whole URL: RFC1738 encodes a
		// space as "+" and a tilde as "%7E", which is also what add_query_arg()
		// would do, and is why it is not used to build this query.
		$this->assertStringContainsString( 'on%20boarding', $url );
		$this->assertStringNotContainsString( 'on+boarding', $url );

		$this->assertStringContainsString( '~step', $url );
		$this->assertStringNotContainsString( '%7Estep', $url );
	}

	public function test_get_product_activation_url_scopes_to_the_product_and_tier(): void {
		$this->store_products( [ [ 'learndash', 'elite', 'not_activated' ] ] );

		$url = lw_harbor_get_product_activation_url( 'learndash' );

		$this->assertStringContainsString( '/subscriptions/', $url );
		// The sku is RFC3986-encoded, so the colon becomes %3A.
		$this->assertStringContainsString( 'sku=learndash%3Aelite', $url );
	}

	/**
	 * With no license to read a tier from, the portal shows a product and tier
	 * picker, still scoped to the activating domain. That is a worse screen than a
	 * pre-selected product, but a working one — which is what lets the SKU go out
	 * unscoped at all.
	 */
	public function test_get_product_activation_url_sends_a_bare_slug_when_no_tier_resolves(): void {
		$url = lw_harbor_get_product_activation_url( 'learndash' );

		$this->assertStringContainsString( 'sku=learndash', $url );
		// No trailing separator: "learndash%3A" would be a tier named empty string.
		$this->assertStringNotContainsString( 'sku=learndash%3A', $url );
	}

	/**
	 * The tier is resolved from the license, so a single covered tier scopes the
	 * SKU without the caller looking it up or passing it.
	 */
	public function test_get_product_activation_url_resolves_the_licensed_tier(): void {
		$this->store_products( [ [ 'give', 'essentials', 'not_activated' ] ] );

		$this->assertStringContainsString(
			'sku=give%3Aessentials',
			lw_harbor_get_product_activation_url( 'give' )
		);
	}

	/**
	 * The ambiguity rule, enforced inside the URL builder. A license covering
	 * the product at several tiers scopes to none of them, so the portal offers
	 * its own picker rather than us sending the user to a tier they may not have
	 * meant.
	 */
	public function test_get_product_activation_url_sends_a_bare_slug_for_an_ambiguous_tier(): void {
		$this->store_products(
			[
				[ 'give', 'essentials', 'not_activated' ],
				[ 'give', 'pro', 'not_activated' ],
			]
		);

		$url = lw_harbor_get_product_activation_url( 'give' );

		$this->assertStringContainsString( 'sku=give', $url );
		$this->assertStringNotContainsString( 'sku=give%3A', $url );
	}

	/**
	 * Null, not an empty string, is what "there is no URL for you" looks like —
	 * it is the one answer a consumer must not paste into an href.
	 */
	public function test_activation_urls_are_null_when_no_instance_is_active(): void {
		// An empty registry is what a site with no active Harbor looks like.
		$this->set_fn_return( '_lw_harbor_global_function_registry', null );

		$this->assertNull( lw_harbor_get_product_activation_base_url() );
		$this->assertNull( lw_harbor_get_product_activation_url( 'learndash' ) );
	}

	// -------------------------------------------------------------------------
	// lw_harbor_is_product_licensed()
	// -------------------------------------------------------------------------

	public function test_is_product_licensed_returns_false_without_cached_products(): void {
		$this->assertFalse( lw_harbor_is_product_licensed( 'give' ) );
	}

	/**
	 * The point of this function next to lw_harbor_is_product_license_active():
	 * a product the key covers but which is not activated here is licensed, and
	 * that is exactly the state an activation prompt exists for.
	 */
	public function test_is_product_licensed_returns_true_for_an_unactivated_product(): void {
		$this->store_products( [ [ 'give', 'pro', 'not_activated' ] ] );

		$this->assertTrue( lw_harbor_is_product_licensed( 'give' ) );
		$this->assertFalse( lw_harbor_is_product_license_active( 'give' ) );
	}

	public function test_is_product_licensed_returns_false_for_an_uncovered_product(): void {
		$this->store_products( [ [ 'give', 'pro', 'valid' ] ] );

		$this->assertFalse( lw_harbor_is_product_licensed( 'learndash' ) );
	}

	public function test_is_product_licensed_returns_false_when_no_instance_is_active(): void {
		$this->set_fn_return( '_lw_harbor_global_function_registry', null );

		$this->assertFalse( lw_harbor_is_product_licensed( 'give' ) );
	}

	/**
	 * Stores a product catalog in the option the repository reads.
	 *
	 * @param array<int,array{0:string,1:string,2:string}> $products Each entry as
	 *                                                               [ slug, tier, validation status ].
	 *
	 * @return void
	 */
	private function store_products( array $products ): void {
		$entries = [];

		foreach ( $products as list( $slug, $tier, $validation_status ) ) {
			$entries[] = Product_Entry::from_array(
				[
					'product_slug'      => $slug,
					'tier'              => $tier,
					'status'            => 'active',
					'expires'           => '2030-12-31 23:59:59',
					'validation_status' => $validation_status,
				]
			);
		}

		update_option(
			License_Repository::PRODUCTS_STATE_OPTION_NAME,
			[
				'collection'      => Product_Collection::from_array( $entries )->to_array(),
				'last_success_at' => null,
				'last_error'      => null,
			]
		);
	}
}
