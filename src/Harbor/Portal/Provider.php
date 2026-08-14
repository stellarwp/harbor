<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Portal;

use LiquidWeb\Harbor\Portal\Clients\Portal_Client;
use LiquidWeb\Harbor\Portal\Clients\Http_Client;
use LiquidWeb\Harbor\Config;
use LiquidWeb\Harbor\Contracts\Abstract_Provider;
use LiquidWeb\Harbor\Portal\Activation\Return_Handler;
use LiquidWeb\Harbor\Portal\Activation\Url;
use LiquidWeb\Harbor\Portal\Contracts\Download_Url_Builder;
use LiquidWeb\LicensingApiClientWordPress\Http\WordPressHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;

/**
 * Registers the Catalog subsystem in the DI container.
 *
 * @since 1.0.0
 */
final class Provider extends Abstract_Provider {

	/**
	 * @inheritDoc
	 */
	public function register(): void {
		$this->container->singleton(
			Portal_Client::class,
			function () {
				return new Http_Client(
					$this->container->get( WordPressHttpClient::class ),
					$this->container->get( Psr17Factory::class ),
					Config::get_portal_base_url()
				);
			}
		);

		$this->container->singleton( Catalog_Repository::class );
		$this->container->singleton( Url::class );
		$this->container->singleton( Return_Handler::class );
		$this->container->singleton( Herald_Url_Builder::class );
		$this->container->singleton( Herald_Legacy_Url_Builder::class );
		$this->container->singleton( Herald_Routing_Url_Builder::class );
		$this->container->singleton( Download_Url_Builder::class, Herald_Routing_Url_Builder::class );

		add_action(
			'lw-harbor/unified_license_key_changed',
			function () {
				$this->container->get( Catalog_Repository::class )->delete_catalog();
			}
		);

		// Priority 0: the return trip has to be handled before any screen reads the
		// licensing data it is about to refresh.
		add_action( 'admin_init', [ $this, 'maybe_refresh_after_activation' ], 0, 0 );
	}

	/**
	 * Refreshes cached licensing data when the portal returns a user to the site.
	 *
	 * Whether this request is a return trip at all is the handler's own first
	 * question, so it is not asked again here.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function maybe_refresh_after_activation(): void {
		$this->container->get( Return_Handler::class )->maybe_refresh();
	}

}
