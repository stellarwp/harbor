<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Features\Update\Requirements;

use LiquidWeb\Harbor\Utils\Cast;
use WP_Error;

use function get_plugin_data;
use function self_admin_url;
use function wp_get_theme;

/**
 * Stops WordPress from installing a Harbor update the site's WordPress or PHP
 * version cannot run, and tells the admin on the Plugins screen why.
 *
 * Plugin_Upgrader::upgrade() and Theme_Upgrader::upgrade() read only `package`
 * from the update transient. They do not check `requires` or `requires_php`
 * and do not check the downloaded zip's headers. WordPress uses them for
 * automatic updates and `update.php?action=upgrade-plugin`, so the guard
 * refuses the download on `upgrader_pre_download` instead.
 *
 * On the Plugins screen, wp_plugin_update_row() checks only `requires_php`.
 * When that is not met, WordPress already shows its own "does not work with
 * your version of PHP" message and hides the update link. Nothing there checks
 * `requires`, so the guard adds a message for the WordPress requirement only.
 * Themes need no message: wp_theme_update_row() checks both `requires` and
 * `requires_php` and shows its own message for each.
 *
 * The guard acts only on entries Harbor writes. WordPress.org already moves an
 * update whose `requires` the site does not meet to `no_update`, and sets
 * `requires_php` on the updates it keeps in `response`.
 *
 * @since TBD
 */
final class Guard {

	/**
	 * Prefixes of the `id` Harbor sets on the update entries it writes.
	 *
	 * @since TBD
	 *
	 * @var string[]
	 */
	private const HARBOR_ID_PREFIXES = [
		'stellarwp/plugins/',
		'stellarwp/themes/',
	];

	/**
	 * Resolves the requirement headers of updates from WordPress's update transients.
	 *
	 * @since TBD
	 *
	 * @var Resolve_Headers
	 */
	private Resolve_Headers $resolve_headers;

	/**
	 * Constructor.
	 *
	 * @since TBD
	 *
	 * @param Resolve_Headers $resolve_headers Resolves the requirement headers of updates from WordPress's update transients.
	 */
	public function __construct( Resolve_Headers $resolve_headers ) {
		$this->resolve_headers = $resolve_headers;
	}

	/**
	 * Refuses to download a Harbor update whose requirements the site does not meet.
	 *
	 * Returns a WP_Error with WordPress's own requirement error codes, which
	 * the upgrader skin collects, so Installable_Strategy::run_upgrader()
	 * reports Error_Code::REQUIREMENTS_NOT_MET for Harbor's Update button too.
	 *
	 * @since TBD
	 *
	 * @param bool|WP_Error $reply      Whether to bail without downloading. False to continue.
	 * @param string        $package    The package URL.
	 * @param mixed         $upgrader   The WP_Upgrader instance.
	 * @param mixed         $hook_extra Extra arguments passed to the upgrader.
	 *
	 * @return bool|WP_Error
	 */
	public function filter_pre_download( $reply, $package, $upgrader = null, $hook_extra = [] ) {
		if ( $reply !== false || ! is_array( $hook_extra ) ) {
			return $reply;
		}

		if ( isset( $hook_extra['plugin'] ) ) {
			$plugin_file  = Cast::to_string( $hook_extra['plugin'] );
			$requirements = $this->resolve_headers->from_response( Resolve_Headers::PLUGINS, $plugin_file );
			$name         = $this->get_plugin_name( $plugin_file );
		} elseif ( isset( $hook_extra['theme'] ) ) {
			$slug         = Cast::to_string( $hook_extra['theme'] );
			$requirements = $this->resolve_headers->from_response( Resolve_Headers::THEMES, $slug );
			$name         = $this->get_theme_name( $slug );
		} else {
			return $reply;
		}

		if ( $requirements === null || ! $this->is_harbor( $requirements ) || $requirements->is_met() ) {
			return $reply;
		}

		$code = $requirements->is_wp_met()
			? 'incompatible_php_required_version'
			: 'incompatible_wp_required_version';

		return $requirements->to_wp_error( $name, $code );
	}

	/**
	 * Adds the WordPress requirement message to the plugin row of each Harbor
	 * update whose `requires` the site does not meet.
	 *
	 * Runs on `load-plugins.php`. Covers the WordPress requirement only:
	 * wp_plugin_update_row() already shows WordPress's own message and hides
	 * the update link when `requires_php` is not met. Themes need no message
	 * because wp_theme_update_row() checks both `requires` and `requires_php`.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function register_update_messages(): void {
		foreach ( $this->resolve_headers->all_from_response( Resolve_Headers::PLUGINS ) as $plugin_file => $requirements ) {
			if ( ! $this->is_harbor( $requirements ) || $requirements->is_wp_met() ) {
				continue;
			}

			add_action( "in_plugin_update_message-{$plugin_file}", [ $this, 'render_update_message' ], 10, 2 );
		}
	}

	/**
	 * Prints the WordPress requirement message after WordPress's update notice in the plugin row.
	 *
	 * @since TBD
	 *
	 * @param mixed $plugin_data The plugin header data.
	 * @param mixed $response    The plugin's entry in the update transient.
	 *
	 * @return void
	 */
	public function render_update_message( $plugin_data, $response ): void {
		if ( ! is_object( $response ) && ! is_array( $response ) ) {
			return;
		}

		$requirements = Headers::from_entry( $response );

		if ( $requirements->is_wp_met() ) {
			return;
		}

		printf(
			'<br /><strong>%s</strong> <a href="%s">%s</a>',
			esc_html(
				sprintf(
					/* translators: %s: required WordPress version */
					__( 'This update requires WordPress %s or newer.', '%TEXTDOMAIN%' ),
					$requirements->get_wp()
				)
			),
			esc_url( self_admin_url( 'update-core.php' ) ),
			esc_html__( 'Please update WordPress first.', '%TEXTDOMAIN%' )
		);
	}

	/**
	 * Whether Harbor wrote the update entry, as opposed to WordPress.org or another updater.
	 *
	 * @since TBD
	 *
	 * @param Headers $headers The update's requirement headers.
	 *
	 * @return bool
	 */
	private function is_harbor( Headers $headers ): bool {
		foreach ( self::HARBOR_ID_PREFIXES as $prefix ) {
			if ( strpos( $headers->get_id(), $prefix ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Gets the plugin's display name, or its file when the header cannot be read.
	 *
	 * @since TBD
	 *
	 * @param string $plugin_file The plugin file.
	 *
	 * @return string
	 */
	private function get_plugin_name( string $plugin_file ): string {
		$path = WP_PLUGIN_DIR . '/' . $plugin_file;

		if ( ! function_exists( 'get_plugin_data' ) || ! file_exists( $path ) ) {
			return $plugin_file;
		}

		$name = Cast::to_string( get_plugin_data( $path, false, false )['Name'] ?? '' );

		return $name !== '' ? $name : $plugin_file;
	}

	/**
	 * Gets the theme's display name, or its stylesheet when the theme cannot be read.
	 *
	 * @since TBD
	 *
	 * @param string $slug The theme stylesheet.
	 *
	 * @return string
	 */
	private function get_theme_name( string $slug ): string {
		$name = Cast::to_string( wp_get_theme( $slug )->get( 'Name' ) );

		return $name !== '' ? $name : $slug;
	}
}
