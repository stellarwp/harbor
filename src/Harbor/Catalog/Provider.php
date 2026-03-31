<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Catalog;

use LiquidWeb\Harbor\Catalog\Clients\Catalog_Client;
use LiquidWeb\Harbor\Catalog\Clients\Http_Client;
use LiquidWeb\Harbor\Config;
use LiquidWeb\Harbor\Contracts\Abstract_Provider;
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
			Catalog_Client::class,
			function () {
				return new Http_Client(
					$this->container->get( WordPressHttpClient::class ),
					$this->container->get( Psr17Factory::class ),
					Config::get_api_base_url()
				);
			}
		);

		$this->container->singleton( Catalog_Repository::class );

		add_action(
			'lw-harbor/unified_license_key_changed',
			function () {
				$this->container->get( Catalog_Repository::class )->delete_catalog();
			}
		);
	}
}
