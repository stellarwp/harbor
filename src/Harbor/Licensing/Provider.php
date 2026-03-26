<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Licensing;

use LiquidWeb\Harbor\Config;
use LiquidWeb\Harbor\Contracts\Abstract_Provider;
use LiquidWeb\Harbor\Licensing\Registry\Product_Registry;
use LiquidWeb\Harbor\Licensing\Repositories\License_Repository;
use LiquidWeb\LicensingApiClient\Config as LicensingConfig;
use LiquidWeb\LicensingApiClient\Contracts\LicensingClientInterface;
use LiquidWeb\LicensingApiClientWordPress\Http\WordPressHttpClient;
use LiquidWeb\LicensingApiClientWordPress\WordPressApiFactory;
use Nyholm\Psr7\Factory\Psr17Factory;

/**
 * Registers the Licensing subsystem in the DI container.
 *
 * @since 1.0.0
 */
final class Provider extends Abstract_Provider {

	/**
	 * @inheritDoc
	 */
	public function register(): void {
		$this->container->singleton(
			LicensingClientInterface::class,
			function () {
				$psr17 = new Psr17Factory();
				return ( new WordPressApiFactory(
					new WordPressHttpClient(),
					$psr17,
					$psr17
				) )->make( new LicensingConfig(
					Config::get_api_base_url(),
					null,
					'lwsw-harbor/1.0'
				) );
			}
		);

		$this->container->singleton( License_Repository::class, License_Repository::class );
		$this->container->singleton( Product_Registry::class, Product_Registry::class );
		$this->container->singleton( License_Manager::class, License_Manager::class );

		add_action(
			'lw-harbor/unified_license_key_changed',
			function () {
				/** @var License_Repository $license_repository */
				$license_repository = $this->container->get( License_Repository::class );
				$license_repository->delete_products();
			}
		);

		add_action(
			'activated_plugin',
			function () {
				/** @var License_Manager $license_manager */
				$license_manager = $this->container->get( License_Manager::class );
				$license_manager->store_embedded_key_if_present();
			}
		);
	}
}
