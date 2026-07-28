<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Portal\Activation;

use LiquidWeb\Harbor\Harbor;
use LiquidWeb\Harbor\Utils\Assets;
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
 * Consumers attach it to their own script through the global function, which
 * keeps them out of Harbor's classes — the copy they bundled is not necessarily
 * the copy that registered the script:
 *
 *     wp_register_script( 'my-onboarding', $url, [], $ver, true );
 *     lw_harbor_add_activation_script_dependency( 'my-onboarding' );
 *
 * Only one instance registers the script. Every active Harbor copy runs this
 * code, so registration is claimed by the highest version via
 * `Version::should_handle()`, matching how the admin page and REST routes are
 * claimed.
 *
 * @since TBD
 */
final class Script {

	/**
	 * The registered script handle.
	 *
	 * Deliberately not vendor-prefixed. Strauss rewrites class names, not
	 * strings, so every Harbor copy on the site agrees on this handle — that
	 * is what allows a single registration to serve all of them.
	 *
	 * Internal to Harbor. It is `public` only because Harbor reads it across
	 * class boundaries and PHP has no narrower visibility for that; consumers
	 * should call `lw_harbor_add_activation_script_dependency()` rather than
	 * read this, so they are never coupled to their own bundled copy.
	 *
	 * @since TBD
	 */
	public const HANDLE = 'lw-harbor-activation';

	/**
	 * Registers the script if this instance is the version leader.
	 *
	 * WordPress resolves dependencies when scripts are printed rather than when
	 * they are enqueued, and `admin_enqueue_scripts` always runs before
	 * printing, so consumers are in time whatever priority they use. Priority 0
	 * is defensive: it also covers anything that prints scripts by hand.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function maybe_register(): void {
		if ( ! Version::should_handle( 'activation_script' ) ) {
			return;
		}

		wp_register_script(
			self::HANDLE,
			Assets::url( 'activation.js' ),
			[],
			Harbor::VERSION,
			[ 'in_footer' => false ]
		);
	}
}
