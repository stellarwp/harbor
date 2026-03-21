<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Features;

use StellarWP\ContainerContract\ContainerInterface;
use LiquidWeb\Harbor\Catalog\Catalog_Repository;
use LiquidWeb\Harbor\Contracts\Abstract_Provider;
use LiquidWeb\Harbor\Features\Strategy\Strategy_Factory;
use LiquidWeb\Harbor\Features\Types\Feature;
use LiquidWeb\Harbor\Features\Types\Flag;
use LiquidWeb\Harbor\Features\Types\Plugin;
use LiquidWeb\Harbor\Features\Types\Theme;
use LiquidWeb\Harbor\Licensing\License_Manager;
use LiquidWeb\Harbor\Site\Data;

/**
 * Registers the Features subsystem in the DI container and hooks.
 *
 * @since 1.0.0
 */
class Provider extends Abstract_Provider {

	/**
	 * Registers singletons and hooks for the Features subsystem.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		$this->container->singleton( Strategy_Factory::class, Strategy_Factory::class );

		$this->container->singleton(
			Resolve_Feature_Collection::class,
			function ( ContainerInterface $c ) {
				$resolver = new Resolve_Feature_Collection(
					$c->get( Catalog_Repository::class ),
					$c->get( License_Manager::class ),
					$c->get( Data::class )
				);

				$this->register_default_types( $resolver );

				return $resolver;
			}
		);

		$this->container->singleton(
			Feature_Repository::class,
			static function ( ContainerInterface $c ) {
				return new Feature_Repository(
					$c->get( Resolve_Feature_Collection::class )
				);
			}
		);

		$this->container->singleton( Feature_Collection::class, Feature_Collection::class );

		$this->container->singleton(
			Manager::class,
			static function ( ContainerInterface $c ) {
				return new Manager(
					$c->get( Feature_Repository::class ),
					$c->get( Strategy_Factory::class )
				);
			}
		);

		$this->container->singleton( Update\Provider::class, Update\Provider::class );
		$this->container->get( Update\Provider::class )->register();
	}

	/**
	 * Registers the default feature type to class mappings.
	 *
	 * @since 1.0.0
	 *
	 * @param Resolve_Feature_Collection $resolver The feature collection resolver.
	 *
	 * @return void
	 */
	private function register_default_types( Resolve_Feature_Collection $resolver ): void {
		$resolver->register_type( Feature::TYPE_PLUGIN, Plugin::class );
		$resolver->register_type( Feature::TYPE_FLAG, Flag::class );
		$resolver->register_type( Feature::TYPE_THEME, Theme::class );
	}
}
