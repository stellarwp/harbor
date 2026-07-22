<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Portal;

use LiquidWeb\Harbor\Licensing\License_Manager;
use LiquidWeb\Harbor\Site\Data;
use LiquidWeb\Harbor\Traits\With_Debugging;
use LiquidWeb\Harbor\Utils\Version;
use WP_Error;

/**
 * Refreshes cached licensing data when the portal returns a user to the site.
 *
 * Licensing data is cached, so a site that has just activated a product in the
 * portal still believes it is unlicensed. Anything gated on that data — an
 * "Activate" button that should now be gone, a feature that should now be
 * available — stays wrong until something refreshes it.
 *
 * `Activation_Url` tags every return URL it builds, whichever page the calling
 * plugin nominated. This watches for that tag on any admin screen, refreshes,
 * then strips the tag and redirects so a reload does not refresh again.
 *
 * Host plugins need no code for this. Sending a user through a URL from
 * `Activation_Url` is the whole opt-in.
 *
 * @since TBD
 */
final class Activation_Return {

	use With_Debugging;

	/**
	 * License manager.
	 *
	 * @since TBD
	 *
	 * @var License_Manager
	 */
	private License_Manager $license_manager;

	/**
	 * Catalog repository.
	 *
	 * @since TBD
	 *
	 * @var Catalog_Repository
	 */
	private Catalog_Repository $catalog;

	/**
	 * Site data provider.
	 *
	 * @since TBD
	 *
	 * @var Data
	 */
	private Data $site_data;

	/**
	 * Constructor.
	 *
	 * @since TBD
	 *
	 * @param License_Manager    $license_manager License manager.
	 * @param Catalog_Repository $catalog         Catalog repository.
	 * @param Data               $site_data       Site data provider.
	 */
	public function __construct( License_Manager $license_manager, Catalog_Repository $catalog, Data $site_data ) {
		$this->license_manager = $license_manager;
		$this->catalog         = $catalog;
		$this->site_data       = $site_data;
	}

	/**
	 * Refreshes licensing data and redirects to the same page without the tag.
	 *
	 * Runs on `admin_init` so headers have not been sent yet and the redirect
	 * can be issued. Refreshing later, once a page has begun rendering, would
	 * be too late to change what that page shows.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function maybe_refresh(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only refresh of the site's own data, guarded by capability below.
		if ( ! isset( $_GET[ Activation_Url::RETURN_PARAM ] ) ) {
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// The tag rides on a plugin-owned URL, so it can land on a screen with
		// no capability check of its own.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! Version::should_handle( 'activation_return' ) ) {
			return;
		}

		$this->refresh();

		wp_safe_redirect( remove_query_arg( Activation_Url::RETURN_PARAM ) );
		exit;
	}

	/**
	 * Refreshes the license products and the catalog.
	 *
	 * Failures are logged rather than surfaced. The user has just come back
	 * from activating and is looking at a product screen, not a licensing one,
	 * so an error notice there would be noise they cannot act on. The data
	 * stays stale until the next refresh, which is the pre-existing behavior.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	private function refresh(): void {
		$products = $this->license_manager->refresh_products( $this->site_data->get_domain() );

		if ( $products instanceof WP_Error ) {
			self::debug_log_wp_error( $products, 'Failed to refresh license products after portal activation.' );
		}

		$catalog = $this->catalog->refresh();

		if ( $catalog instanceof WP_Error ) {
			self::debug_log_wp_error( $catalog, 'Failed to refresh the catalog after portal activation.' );
		}
	}
}
