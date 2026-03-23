<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Licensing;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use LiquidWeb\Harbor\Config;
use LiquidWeb\Harbor\Contracts\Abstract_Provider;
use LiquidWeb\Harbor\Licensing\Clients\Http_Client;
use LiquidWeb\Harbor\Licensing\Clients\Licensing_Client;
use LiquidWeb\Harbor\Licensing\Registry\Product_Registry;
use LiquidWeb\Harbor\Licensing\Repositories\License_Repository;

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
			Licensing_Client::class,
			function () {
				return new Http_Client(
					$this->container->get( ClientInterface::class ),
					$this->container->get( RequestFactoryInterface::class ),
					$this->container->get( StreamFactoryInterface::class ),
					Config::get_api_base_url()
				);
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
	}
}
