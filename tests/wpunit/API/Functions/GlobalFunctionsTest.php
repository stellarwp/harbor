<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\API\Functions;

use LiquidWeb\Harbor\Licensing\Repositories\License_Repository;
use LiquidWeb\Harbor\Licensing\Product_Collection;
use LiquidWeb\Harbor\Licensing\Results\Product_Entry;
use LiquidWeb\Harbor\Portal\Activation\Script;
use LiquidWeb\Harbor\Portal\Catalog_Collection;
use LiquidWeb\Harbor\Portal\Catalog_Repository;
use LiquidWeb\Harbor\Tests\HarborTestCase;
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

	protected function setUp(): void {
		parent::setUp();

		delete_option( License_Repository::KEY_OPTION_NAME );
		delete_option( License_Repository::PRODUCTS_STATE_OPTION_NAME );
	}

	protected function tearDown(): void {
		delete_option( License_Repository::KEY_OPTION_NAME );
		delete_option( License_Repository::PRODUCTS_STATE_OPTION_NAME );

		// wp_scripts() is a global that outlives a single test method.
		wp_deregister_script( Script::HANDLE );
		wp_deregister_script( 'consumer-onboarding' );

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
	// lw_harbor_get_activation_base_url() / lw_harbor_get_product_activation_url()
	// -------------------------------------------------------------------------

	public function test_get_activation_base_url_targets_the_subscriptions_screen(): void {
		$url = lw_harbor_get_activation_base_url();

		$this->assertStringContainsString( '/subscriptions/', $url );
		$this->assertStringContainsString( 'portal-referral=plugin', $url );
		$this->assertStringNotContainsString( 'sku=', $url );
	}

	public function test_get_activation_base_url_carries_the_given_return_destination(): void {
		$url = lw_harbor_get_activation_base_url( 'https://example.test/onboarding' );

		$this->assertStringContainsString( rawurlencode( 'https://example.test/onboarding' ), $url );
		// The return trip is tagged so Harbor refreshes its cache on the way back.
		$this->assertStringContainsString( 'lw-harbor-activated', $url );
	}

	public function test_get_activation_base_url_encodes_the_query_per_rfc3986(): void {
		$url = lw_harbor_get_activation_base_url( 'https://example.test/on boarding~step' );

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
		$url = lw_harbor_get_product_activation_url( 'learndash', 'elite' );

		$this->assertStringContainsString( '/subscriptions/', $url );
		// The sku is RFC3986-encoded, so the colon becomes %3A.
		$this->assertStringContainsString( 'sku=learndash%3Aelite', $url );
	}

	// -------------------------------------------------------------------------
	// lw_harbor_add_activation_script_dependency()
	// -------------------------------------------------------------------------

	/**
	 * Empties admin_enqueue_scripts so firing it only runs what the test added.
	 *
	 * WordPress core hooks WP_Site_Health onto it, which reads the current
	 * screen and there is not one here. Must run before the function under test,
	 * which registers its own callback on the same hook.
	 *
	 * @return void
	 */
	private function isolate_enqueue_hook(): void {
		remove_all_actions( 'admin_enqueue_scripts' );
	}

	public function test_add_activation_script_dependency_attaches_harbors_handle(): void {
		$this->isolate_enqueue_hook();

		wp_register_script( Script::HANDLE, 'https://example.test/activation.js', [], '1.0.0', true );
		wp_register_script( 'consumer-onboarding', 'https://example.test/onboarding.js', [], '1.0.0', true );

		lw_harbor_add_activation_script_dependency( 'consumer-onboarding' );
		do_action( 'admin_enqueue_scripts' );

		$this->assertContains(
			Script::HANDLE,
			wp_scripts()->registered['consumer-onboarding']->deps
		);
	}

	/**
	 * The caller must not have to run after Harbor's own registration. Naming the
	 * handle in a $deps array never cared about order, and neither should this.
	 */
	public function test_add_activation_script_dependency_does_not_depend_on_call_order(): void {
		$this->isolate_enqueue_hook();

		wp_register_script( 'consumer-onboarding', 'https://example.test/onboarding.js', [], '1.0.0', true );

		// Asked for before Harbor has registered anything.
		lw_harbor_add_activation_script_dependency( 'consumer-onboarding' );

		wp_register_script( Script::HANDLE, 'https://example.test/activation.js', [], '1.0.0', true );
		do_action( 'admin_enqueue_scripts' );

		$this->assertContains(
			Script::HANDLE,
			wp_scripts()->registered['consumer-onboarding']->deps
		);
	}

	/**
	 * A dependency that will never resolve stops WordPress printing the
	 * consumer's script at all, so no Harbor means no dependency added.
	 */
	public function test_add_activation_script_dependency_is_a_noop_without_harbors_script(): void {
		$this->isolate_enqueue_hook();

		wp_register_script( 'consumer-onboarding', 'https://example.test/onboarding.js', [], '1.0.0', true );

		lw_harbor_add_activation_script_dependency( 'consumer-onboarding' );
		do_action( 'admin_enqueue_scripts' );

		$this->assertSame( [], wp_scripts()->registered['consumer-onboarding']->deps );
	}

	public function test_add_activation_script_dependency_does_not_duplicate(): void {
		$this->isolate_enqueue_hook();

		wp_register_script( Script::HANDLE, 'https://example.test/activation.js', [], '1.0.0', true );
		wp_register_script( 'consumer-onboarding', 'https://example.test/onboarding.js', [], '1.0.0', true );

		lw_harbor_add_activation_script_dependency( 'consumer-onboarding' );
		lw_harbor_add_activation_script_dependency( 'consumer-onboarding' );
		do_action( 'admin_enqueue_scripts' );

		$this->assertSame(
			[ Script::HANDLE ],
			wp_scripts()->registered['consumer-onboarding']->deps
		);
	}
}
