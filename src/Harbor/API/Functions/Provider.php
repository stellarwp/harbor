<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\API\Functions;

use LiquidWeb\Harbor\Contracts\Abstract_Provider;
use LiquidWeb\Harbor\Harbor;

/**
 * Registers global (non-namespaced) Uplink helper functions.
 *
 * @since 1.0.0
 */
final class Provider extends Abstract_Provider {

	/**
	 * @inheritDoc
	 */
	public function register(): void {
		require_once dirname( __DIR__, 2 ) . '/global-functions.php';
		Global_Function_Registry::register( Harbor::VERSION );
	}
}
