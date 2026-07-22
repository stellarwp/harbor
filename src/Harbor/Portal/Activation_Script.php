<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Portal;

use LiquidWeb\Harbor\Harbor;
use LiquidWeb\Harbor\Utils\Version;

/**
 * Registers the shared activation helper script.
 *
 * Host plugins that render their own onboarding screens need to build
 * activation URLs in the browser — for example when the user picks a tier
 * client-side. This script exposes `window.lwHarbor.buildActivationUrl()` so
 * they can do that without bundling their own copy, which also works from an
 * inline script on a PHP-rendered page with no build step.
 *
 * Consumers declare `lw-harbor-activation` as a script dependency:
 *
 *     wp_enqueue_script( 'my-onboarding', $url, [ Activation_Script::HANDLE ], $ver, true );
 *
 * Only one instance registers the script. Every active Harbor copy runs this
 * code, so registration is claimed by the highest version via
 * `Version::should_handle()`, matching how the admin page and REST routes are
 * claimed.
 *
 * @since TBD
 */
final class Activation_Script {

	/**
	 * The registered script handle.
	 *
	 * Deliberately not vendor-prefixed. Strauss rewrites class names, not
	 * strings, so every Harbor copy on the site agrees on this handle — that
	 * is what allows a single registration to serve all of them.
	 *
	 * @since TBD
	 */
	public const HANDLE = 'lw-harbor-activation';

	/**
	 * Registers the script if this instance is the version leader.
	 *
	 * Runs early on `admin_enqueue_scripts` so the handle exists before
	 * consumers enqueue at the default priority. A consumer that declares a
	 * dependency on an unregistered handle is silently skipped by WordPress,
	 * so registration must not lose that race.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function maybe_register(): void {
		if ( ! Version::should_handle( 'activation_script' ) ) {
			return;
		}

		$build_dir       = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? 'build-dev' : 'build';
		$plugin_root_dir = dirname( dirname( dirname( __DIR__ ) ) );
		$plugin_root_url = trailingslashit(
			plugin_dir_url( $plugin_root_dir . '/index.php' )
		);

		wp_register_script(
			self::HANDLE,
			$plugin_root_url . $build_dir . '/activation.js',
			[],
			Harbor::VERSION,
			[ 'in_footer' => false ]
		);

		// Reported at runtime rather than compiled in, so it always matches the
		// instance that actually won registration.
		wp_add_inline_script(
			self::HANDLE,
			sprintf( 'window.lwHarbor.version = %s;', wp_json_encode( Harbor::VERSION ) ),
			'after'
		);
	}
}
