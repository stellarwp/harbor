<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\API\Functions\Actions;

use LiquidWeb\Harbor\Portal\Activation\Script;

/**
 * Declares Harbor's activation helper script as a dependency of a host script.
 *
 * A consumer that wants `window.lwHarbor.buildActivationUrl()` in the browser
 * has to name Harbor's script as a dependency of their own. Reaching for
 * `Script::HANDLE` to do that would mean reading a constant off whichever
 * Harbor copy the consumer bundled, which is not necessarily the copy that
 * registered the script — under the leader system those can be different
 * versions. Naming your own handle and letting Harbor wire itself in keeps the
 * consumer out of Harbor's classes entirely.
 *
 * The wiring is also retried at the end of `admin_enqueue_scripts`, so a caller
 * does not have to run after Harbor's own registration. Ordering was a non-issue
 * when consumers named the handle in their own `$deps` array — WordPress
 * resolves those at print time — and this keeps it that way.
 *
 * A no-op when no Harbor instance registered the script, which also means a
 * consumer's script is never held back by a dependency that will never resolve.
 *
 * @since TBD
 */
class Add_Activation_Script_Dependency {

	/**
	 * @param string $handle The consumer's script handle.
	 *
	 * @return void
	 */
	public function __invoke( string $handle ): void {
		// Attach now, in case both scripts are already registered and the enqueue
		// hook has been and gone...
		$this->attach( $handle );

		// ...and again once every other callback on the enqueue hook has run, in
		// case one of them had not registered yet. attach() is idempotent, so
		// doing both costs nothing and removes any ordering requirement on the
		// caller.
		add_action(
			'admin_enqueue_scripts',
			function () use ( $handle ): void {
				$this->attach( $handle );
			},
			PHP_INT_MAX
		);
	}

	/**
	 * Adds Harbor's handle to the consumer's dependency list.
	 *
	 * @param string $handle The consumer's script handle.
	 *
	 * @return void
	 */
	private function attach( string $handle ): void {
		if ( ! wp_script_is( Script::HANDLE, 'registered' ) ) {
			return;
		}

		$scripts = wp_scripts();

		if ( ! isset( $scripts->registered[ $handle ] ) ) {
			return;
		}

		if ( in_array( Script::HANDLE, $scripts->registered[ $handle ]->deps, true ) ) {
			return;
		}

		$scripts->registered[ $handle ]->deps[] = Script::HANDLE;
	}
}
