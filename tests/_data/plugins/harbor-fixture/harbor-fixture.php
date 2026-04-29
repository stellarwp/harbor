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
add_action(
	'init',
	static function () {
		update_option( 'lw_harbor_unified_license_key', 'LWSW-UNIFIED-PRO-2026', false );
	}
);
