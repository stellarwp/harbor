<?php
/**
 * Plugin Name: Harbor Test Bootstrap
 * Description: Bootstraps LiquidWeb Harbor during plugins_loaded, before wp_loaded fires.
 * Version: 1.0.0
 * Author: Liquid Web
 */

use StellarWP\ContainerContract\ContainerInterface;
use LiquidWeb\Harbor\Config;
use LiquidWeb\Harbor\Tests\Container;
use LiquidWeb\Harbor\Harbor;

// In the Codeception/slic test environment the autoloader is already required
// before WordPress boots. When this file is activated as a plugin on a real
// site (e.g. a Local dev install) nothing loads Harbor's Composer autoloader,
// so the Config/Harbor/Container classes below can't be found. require_once is
// idempotent, so loading it here is safe in both contexts.
if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Mock a premium plugin so Premium_Plugin_Registry::any() returns true during
// the WP boot phase and Harbor::init() actually registers its providers. Without
// this the premium-plugin gate inside Harbor::init() short-circuits and tests
// that depend on provider bindings (License_Repository, Portal_Client, etc.)
// or on hooks added by provider register_hooks() observe an empty container.
add_filter(
	'lw_harbor/premium_plugin_exists',
	'__return_true'
);

add_action(
	'plugins_loaded',
	static function () {
		$container = new Container();
		$container->singleton( ContainerInterface::class, $container );
		Config::set_plugin_basename( plugin_basename( __FILE__ ) );
		Config::set_container( $container );
		Harbor::init();
	},
	0
);
