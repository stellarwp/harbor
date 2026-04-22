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

add_action(
	'plugins_loaded',
	static function () {
		$container = new Container();
		$container->singleton( ContainerInterface::class, $container );
		Config::set_plugin_file( plugin_basename( __FILE__ ) );
		Config::set_container( $container );
		Harbor::init();
	},
	0
);
