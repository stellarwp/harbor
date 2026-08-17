<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\API\Functions\Actions;

use LiquidWeb\Harbor\Config;
use LiquidWeb\Harbor\Licensing\License_Manager;
use LiquidWeb\Harbor\Site\Data;
use LiquidWeb\Harbor\Traits\With_Debugging;
use Throwable;

/**
 * Validates a unified license key against the portal and stores it.
 *
 * Refuses outright when a key is already stored. A host plugin handing Harbor a
 * key from its own onboarding screen must not silently replace the key another
 * plugin, or the site owner, already activated this site with. Callers that
 * genuinely want to replace one have the REST and WP-CLI surfaces, both of
 * which are admin-authenticated and say so.
 *
 * @since TBD
 */
class Store_Unified_License_Key {

	use With_Debugging;

	/**
	 * @since TBD
	 *
	 * @param string $key The unified license key to validate and store.
	 *
	 * @return bool Whether the key was validated and stored.
	 */
	public function __invoke( string $key ): bool {
		try {
			$container = Config::get_container();

			/** @var License_Manager $manager */
			$manager = $container->get( License_Manager::class );

			// Checked before the API call, not after: validate_and_store() writes
			// the key unconditionally on success and fires the changed action that
			// wipes the cached products, so a guard placed after it would be
			// deciding what to do about a key it had already destroyed.
			if ( $manager->key_exists() ) {
				static::debug_log( 'Refused to store a unified license key: one is already stored.' );

				return false;
			}

			/** @var Data $site_data */
			$site_data = $container->get( Data::class );

			$result = $manager->validate_and_store( $key, $site_data->get_domain() );

			if ( is_wp_error( $result ) ) {
				// The bool return deliberately discards why this failed, so the
				// debug log is all an integrator has left to tell an invalid key
				// apart from a throttled one.
				static::debug_log_wp_error( $result, 'Error storing unified license key' );

				return false;
			}

			return true;
		} catch ( Throwable $e ) {
			static::debug_log_throwable( $e, 'Error storing unified license key' );

			return false;
		}
	}
}
