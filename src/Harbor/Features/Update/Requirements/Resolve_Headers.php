<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Features\Update\Requirements;

use LiquidWeb\Harbor\Features\Types\Feature;
use LiquidWeb\Harbor\Features\Types\Plugin;
use LiquidWeb\Harbor\Features\Types\Theme;
use LiquidWeb\Harbor\Utils\Cast;

/**
 * Resolves the requirement headers of the update waiting to be installed for
 * a plugin or theme feature, by reading its entry back from WordPress's
 * `update_plugins` and `update_themes` transients.
 *
 * Update\Resolve_Update_Data builds the update data Harbor offers from the
 * catalog and licensing, and the handlers write it to those transients. This
 * class reads what ended up there, which also includes the entries
 * WordPress.org writes for WordPress.org-hosted features.
 *
 * The `response` list holds updates WordPress offers. WordPress.org also
 * returns an update whose `requires` the site does not meet, but in the
 * `no_update` list, with `new_version` and `requires` still set. That entry
 * is returned too when its version is newer than the installed one and the
 * site does not meet its requirements, so callers can report the update and
 * the reason the site cannot install it.
 *
 * from_response() and all_from_response() read the `response` list only,
 * for callers that act on the updates WordPress offers and installs.
 *
 * @since TBD
 */
final class Resolve_Headers {

	/**
	 * The transient WordPress stores plugin updates in.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	public const PLUGINS = 'update_plugins';

	/**
	 * The transient WordPress stores theme updates in.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	public const THEMES = 'update_themes';

	/**
	 * Resolves the requirement headers of the feature's pending update.
	 *
	 * @since TBD
	 *
	 * @param Feature $feature The feature. Only plugins and themes have updates.
	 *
	 * @return Headers|null Null when no update is pending.
	 */
	public function __invoke( Feature $feature ): ?Headers {
		if ( $feature instanceof Plugin ) {
			return $this->find( self::PLUGINS, $feature->get_plugin_file(), $this->get_installed_version( $feature ) );
		}

		if ( $feature instanceof Theme ) {
			return $this->find( self::THEMES, $feature->get_slug(), $this->get_installed_version( $feature ) );
		}

		return null;
	}

	/**
	 * Resolves the requirement headers of the update in the transient's `response` list.
	 *
	 * @since TBD
	 *
	 * @param string $transient_name self::PLUGINS or self::THEMES.
	 * @param string $key            The plugin file or theme stylesheet the transient is keyed by.
	 *
	 * @return Headers|null Null when the list has no update for the key.
	 */
	public function from_response( string $transient_name, string $key ): ?Headers {
		return $this->all_from_response( $transient_name )[ $key ] ?? null;
	}

	/**
	 * Resolves the requirement headers of every update in the transient's `response` list.
	 *
	 * @since TBD
	 *
	 * @param string $transient_name self::PLUGINS or self::THEMES.
	 *
	 * @return array<string, Headers> Keyed by plugin file or theme stylesheet.
	 */
	public function all_from_response( string $transient_name ): array {
		return $this->read_list( $transient_name, 'response' );
	}

	/**
	 * Gets the installed version of a plugin or theme feature.
	 *
	 * Uses the version feature resolution stored, and reads the plugin or
	 * theme header only when the feature was built without one.
	 *
	 * @since TBD
	 *
	 * @param Plugin|Theme $feature The feature.
	 *
	 * @return string An empty string when the feature is not installed.
	 */
	private function get_installed_version( $feature ): string {
		$installed_version = $feature->to_array()['installed_version'] ?? null;

		if ( $installed_version === null ) {
			$installed_version = $feature->get_installed_version();
		}

		return Cast::to_string( $installed_version );
	}

	/**
	 * Gets the pending update's headers from the given transient.
	 *
	 * @since TBD
	 *
	 * @param string $transient_name    self::PLUGINS or self::THEMES.
	 * @param string $key               The plugin file or theme stylesheet the transient is keyed by.
	 * @param string $installed_version The installed version.
	 *
	 * @return Headers|null
	 */
	private function find( string $transient_name, string $key, string $installed_version ): ?Headers {
		if ( $key === '' ) {
			return null;
		}

		$response = $this->from_response( $transient_name, $key );

		if ( $response !== null ) {
			return $response;
		}

		$no_update = $this->read_list( $transient_name, 'no_update' )[ $key ] ?? null;

		if ( $no_update === null || $installed_version === '' || $no_update->is_met() ) {
			return null;
		}

		if ( ! version_compare( $no_update->get_version(), $installed_version, '>' ) ) {
			return null;
		}

		return $no_update;
	}

	/**
	 * Reads the headers of every entry with a version in one list on the transient.
	 *
	 * @since TBD
	 *
	 * @param string $transient_name self::PLUGINS or self::THEMES.
	 * @param string $list_name      `response` or `no_update`.
	 *
	 * @return array<string, Headers> Keyed by plugin file or theme stylesheet.
	 */
	private function read_list( string $transient_name, string $list_name ): array {
		$transient = get_site_transient( $transient_name );
		$entries   = is_object( $transient ) ? ( $transient->{$list_name} ?? null ) : null;

		if ( ! is_array( $entries ) ) {
			return [];
		}

		$headers = [];

		foreach ( $entries as $key => $entry ) {
			if ( ! is_object( $entry ) && ! is_array( $entry ) ) {
				continue;
			}

			$entry_headers = Headers::from_entry( $entry );

			if ( $entry_headers->get_version() !== '' ) {
				$headers[ (string) $key ] = $entry_headers;
			}
		}

		return $headers;
	}
}
