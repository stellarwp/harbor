<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests;

use Codeception\TestCase\WPTestCase;
use RuntimeException;
use StellarWP\ContainerContract\ContainerInterface;
use LiquidWeb\Harbor\Config;
use LiquidWeb\Harbor\Harbor;
use WP_Screen;

/**
 * @mixin \Codeception\Test\Unit
 * @mixin \PHPUnit\Framework\TestCase
 * @mixin \Codeception\PHPUnit\TestCase
 */
class HarborTestCase extends WPTestCase {

	/**
	 * @var ContainerInterface|\lucatume\DI52\Container
	 */
	protected $container;

	protected function setUp(): void {
		parent::setUp();

		// Harbor::init() calls _lw_harbor_instance_registry() to register this
		// instance into the cross-copy registry. In production this happens during
		// the bootstrap window (before wp_loaded). The WP test environment fires
		// wp_loaded before any test runs, so the registry's bootstrap-window guard
		// triggers _doing_it_wrong here. Expect it so individual tests don't fail.
		$this->setExpectedIncorrectUsage( '_lw_harbor_instance_registry' );

		$container = new Container();
		$container->singleton( ContainerInterface::class, $container );
		Config::set_container( $container );

		Harbor::init();

		$this->container = Config::get_container();
	}

	/**
	 * WordPress 7.1 registers its icon collections on `init` and flags a second
	 * registration as incorrect usage. Any test that fires `init` again, or loads
	 * an admin include that re-runs the registration, records those notices, and
	 * the base test case then fails the test for a notice no Harbor code caused.
	 *
	 * Drop core's icon-registry notices before the base class asserts on them.
	 * Runs after the test body and before tearDown(), and only unsets what is
	 * actually there, so nothing changes on WordPress versions that never
	 * register icons.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function assert_post_conditions() {
		// Read, modify, write back: on multisite the base class proxies this
		// property to a wrapped core test case, where editing it in place is a
		// no-op ("indirect modification of overloaded property").
		$caught = $this->caught_doing_it_wrong;

		unset(
			$caught['WP_Icon_Collections_Registry::register'],
			$caught['WP_Icons_Registry::register']
		);

		$this->caught_doing_it_wrong = $caught;

		parent::assert_post_conditions();
	}

	protected function tearDown(): void {
		Config::reset();

		// Reset any current screen implementations.
		$GLOBALS['current_screen'] = null;

		parent::tearDown();
	}

	/**
	 * @param string  $path          The path to the plugin file, e.g. my-plugin/my-plugin.php
	 * @param bool    $network_wide  Whether this should happen network wide.
	 *
	 * @return void
	 */
	protected function mock_activate_plugin( string $path, bool $network_wide = false ): void {
		if ( $network_wide ) {
			if ( ! is_multisite() ) {
				throw new RuntimeException( 'Multisite is not enabled!, try running with slic run wpunit --env multisite' );
			}

			$current          = get_site_option( 'active_sitewide_plugins', [] );
			$current[ $path ] = time();

			update_site_option( 'active_sitewide_plugins', $current );
		} else {
			update_option(
				'active_plugins',
				array_merge( get_option( 'active_plugins', [] ), [ $path ] )
			);
		}
	}

	/**
	 * Mock we're inside the wp-admin dashboard and fire off the admin_init hook.
	 *
	 * @param bool  $network  Whether we're in the network dashboard.
	 *
	 * @return void
	 */
	protected function admin_init( bool $network = false ): void {
		$screen                    = WP_Screen::get( $network ? 'dashboard-network' : 'dashboard' );
		$GLOBALS['current_screen'] = $screen;

		if ( $network ) {
			$this->assertTrue( $screen->in_admin( 'network' ) );
		}

		$this->assertTrue( $screen->in_admin() );

		// Fire off admin_init to run any of our events hooked into this action.
		do_action( 'admin_init' );
	}
}
