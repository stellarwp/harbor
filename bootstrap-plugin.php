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

// WordPress 7.1 registers its default icon collections and icons on `init` and
// calls _doing_it_wrong() when either is registered twice. A test that fires
// `init` a second time re-runs those callbacks and trips the notice, which the
// base test case then reports as an unexpected incorrect usage.
//
// WordPress's own test suite handles this by unhooking the callbacks once init
// has run (_unhook_icon_registration() in tests/phpunit/includes/functions.php,
// added in 7.1), alongside the same treatment for fonts and connectors.
// wp-browser bundles its own copy of those core includes, and as of 4.7.1 that
// copy has the font guard but not the icon one, so add it here. Drop this once
// wp-browser ships the upstream version.
add_action(
	'init',
	static function () {
		remove_action( 'init', '_wp_register_default_icon_collections', 0 );
		remove_action( 'init', '_wp_register_default_icons' );
	},
	1000
);

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
