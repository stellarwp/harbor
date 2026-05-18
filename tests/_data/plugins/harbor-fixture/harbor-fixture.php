<?php
/**
 * Plugin Name: Harbor E2E Fixture
 * Plugin URI:  https://github.com/stellarwp/harbor
 * Description: Boots Harbor with fixture catalog and licensing data for E2E tests. Not for production use.
 * Version:     1.0.0
 * Author:      Liquid Web
 */

defined( 'ABSPATH' ) || exit;

$harbor_autoloader = WP_PLUGIN_DIR . '/harbor/vendor/autoload.php';

if ( ! file_exists( $harbor_autoloader ) ) {
	return;
}

require_once $harbor_autoloader;
require_once WP_PLUGIN_DIR . '/harbor/tests/_support/Helper/Licensing/Fixture_Client.php';

use lucatume\DI52\Container as DI52Container;
use LiquidWeb\Harbor\Config;
use LiquidWeb\Harbor\Harbor;
use LiquidWeb\Harbor\Portal\Clients\Portal_Client;
use LiquidWeb\Harbor\Portal\Clients\Fixture_Client as Portal_Fixture_Client;
use LiquidWeb\LicensingApiClient\Contracts\LicensingClientInterface;
use LiquidWeb\Harbor\Tests\Licensing\Fixture_Client as Licensing_Fixture_Client;
use StellarWP\ContainerContract\ContainerInterface;

// Satisfies both DI52 and StellarWP's ContainerInterface.
class Harbor_E2E_Container extends DI52Container implements ContainerInterface {}

// Mock a premium plugin so Premium_Plugin_Registry::any() returns true and
// Harbor::init() registers its providers. Without this Harbor short-circuits
// and the admin page is never registered.
//
// Visiting any URL with ?lw_harbor_no_premium_exists=1 disables the mock for that
// request — used by the smoke test that asserts the menu is hidden when no
// premium plugin reports itself.
add_filter(
	'lw_harbor/premium_plugin_exists',
	static function () {
		return ! isset( $_GET['lw_harbor_no_premium_exists'] );
	}
);

add_action(
	'plugins_loaded',
	static function () {
		$container = new Harbor_E2E_Container();
		$container->singleton( ContainerInterface::class, $container );

		Config::set_container( $container );
		Config::set_plugin_basename( plugin_basename( __FILE__ ) );

		Harbor::init();

		$catalog_fixture       = WP_PLUGIN_DIR . '/harbor/tests/_data/catalog/default.json';
		$licensing_fixture_dir = WP_PLUGIN_DIR . '/harbor/tests/_data/licensing';

		// Rebind after init to replace the real HTTP clients with fixture readers.
		// DI52 singletons haven't been resolved yet at this point, so rebinding works.
		$container->singleton(
			Portal_Client::class,
			static function () use ( $catalog_fixture ) {
				return new Portal_Fixture_Client( $catalog_fixture );
			}
		);

		$container->singleton(
			LicensingClientInterface::class,
			static function () use ( $licensing_fixture_dir ) {
				return new Licensing_Fixture_Client( $licensing_fixture_dir );
			}
		);
	},
	5
);

// Seed the pro fixture license key so the UI renders with licensed product data.
// The key maps to tests/_data/licensing/lwsw-unified-pro-2026.json via strtolower().
//
// Two gates:
//   - ?lw_harbor_no_license=1 on the request URL skips seeding for that
//     request (mirrors the premium filter gate above).
//   - lw_harbor_initial_license_seeded marks that the initial seed has run.
//     Once set, subsequent inits don't re-seed, so callers that delete the
//     license option (via REST or otherwise) keep it deleted instead of
//     having it re-added on the next request.
add_action(
	'init',
	static function () {
		if ( isset( $_GET['lw_harbor_no_license'] ) ) {
			return;
		}
		if ( get_option( 'lw_harbor_initial_license_seeded' ) ) {
			return;
		}
		add_option( 'lw_harbor_unified_license_key', 'LWSW-UNIFIED-PRO-2026' );
		update_option( 'lw_harbor_initial_license_seeded', '1' );
	}
);

// Test-only reset endpoint. Wipes the unified license key AND the licensing
// products state — the latter carries the 60s validate_and_store throttle
// cache, so without resetting it a failed validation in one test will return
// the cached invalid-key error for the next 60s (License_Manager.php:153).
// Specs call this in beforeEach to start each test from a clean slate.
add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'lw-harbor-fixture/v1',
			'/reset',
			[
				'methods'             => 'POST',
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'callback'            => static function () {
					delete_option( 'lw_harbor_unified_license_key' );
					delete_option( 'lw_harbor_licensing_products_state' );
					return new WP_REST_Response( null, 204 );
				},
			]
		);
	}
);
