<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests;

use Codeception\Module;
use Codeception\TestInterface;
use stdClass;

/**
 * Codeception module for seeding WordPress update transients.
 *
 * Injects plugin/theme update entries via late-priority filters on the
 * pre_site_transient_* hooks. This is necessary because Airplane Mode
 * (bundled with wp-browser) hooks pre_site_transient_update_plugins and
 * pre_site_transient_update_themes to return cached objects, which
 * short-circuits get_site_transient() before the site_transient_* filter
 * ever fires.
 *
 * Registered in wpunit.suite.dist.yml. Cleanup fires automatically
 * after each test via _after().
 */
class Updater extends Module {

	/**
	 * Priority high enough to run after Airplane Mode (priority 10).
	 */
	private const PRIORITY = 999;

	/**
	 * Filters registered by seed helpers, cleaned up after each test.
	 *
	 * Each entry is [ hook_name, callback, priority ].
	 *
	 * @var array<int, array{string, callable, int}>
	 */
	private static $filters = [];

	/**
	 * Inject a plugin update entry into the update_plugins transient.
	 *
	 * @param string $plugin_file The plugin file path (e.g. "my-plugin/my-plugin.php").
	 * @param string $new_version The available version.
	 * @param string $package     Download URL (auto-generated if empty).
	 */
	public static function seedPluginUpdate(
		string $plugin_file,
		string $new_version = '2.0.0',
		string $package = ''
	): void {
		if ( $package === '' ) {
			$slug    = basename( dirname( $plugin_file ) );
			$package = sprintf( 'https://example.com/%s-%s.zip', $slug, $new_version );
		}

		$callback = static function ( $transient ) use ( $plugin_file, $new_version, $package ) {
			if ( ! is_object( $transient ) ) {
				$transient = new stdClass();
			}

			if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
				$transient->response = [];
			}

			$transient->response[ $plugin_file ] = (object) [
				'slug'        => dirname( $plugin_file ),
				'new_version' => $new_version,
				'package'     => $package,
			];

			return $transient;
		};

		add_filter( 'pre_site_transient_update_plugins', $callback, self::PRIORITY );
		self::$filters[] = [ 'pre_site_transient_update_plugins', $callback, self::PRIORITY ];
	}

	/**
	 * Inject a theme update entry into the update_themes transient.
	 *
	 * @param string $stylesheet  The theme stylesheet (directory name).
	 * @param string $new_version The available version.
	 * @param string $package     Download URL (auto-generated if empty).
	 */
	public static function seedThemeUpdate(
		string $stylesheet,
		string $new_version = '2.0.0',
		string $package = ''
	): void {
		if ( $package === '' ) {
			$package = sprintf( 'https://example.com/%s-%s.zip', $stylesheet, $new_version );
		}

		$callback = static function ( $transient ) use ( $stylesheet, $new_version, $package ) {
			if ( ! is_object( $transient ) ) {
				$transient = new stdClass();
			}

			if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
				$transient->response = [];
			}

			$transient->response[ $stylesheet ] = [
				'theme'       => $stylesheet,
				'new_version' => $new_version,
				'package'     => $package,
			];

			return $transient;
		};

		add_filter( 'pre_site_transient_update_themes', $callback, self::PRIORITY );
		self::$filters[] = [ 'pre_site_transient_update_themes', $callback, self::PRIORITY ];
	}

	/**
	 * Remove all filters registered by seed helpers.
	 */
	public static function clearSeededUpdates(): void {
		foreach ( self::$filters as $filter ) {
			remove_filter( $filter[0], $filter[1], $filter[2] );
		}

		self::$filters = [];
	}

	/**
	 * Auto-cleanup after each test.
	 */
	public function _after( TestInterface $test ): void {
		self::clearSeededUpdates();
	}
}
