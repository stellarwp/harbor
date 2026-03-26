<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use LiquidWeb\Harbor\Contracts\Abstract_Provider;

/**
 * Registers shared PSR-17 HTTP message factories in the DI container.
 *
 * @since 1.0.0
 */
final class Provider extends Abstract_Provider {

	/**
	 * @inheritDoc
	 */
	public function register(): void {
		$this->container->singleton(
			Psr17Factory::class,
			static function () {
				return new Psr17Factory();
			}
		);

		$this->container->singleton(
			RequestFactoryInterface::class,
			static function () {
				return new Psr17Factory();
			}
		);

		$this->container->singleton(
			StreamFactoryInterface::class,
			static function () {
				return new Psr17Factory();
			}
		);
	}
}
