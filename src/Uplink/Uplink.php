<?php declare( strict_types=1 );

namespace StellarWP\Uplink;

use RuntimeException;
use StellarWP\ContainerContract\ContainerInterface;
use StellarWP\Uplink\Utils\Version;

class Uplink {

	/**
	 * The Uplink library version.
	 *
	 * @since 3.0.0
	 *
	 * @var string
	 */
	public const VERSION = '3.0.0';

	/**
	 * Initializes the service provider.
	 *
	 * @since 1.0.0
	 *
	 * @throws RuntimeException If the container has not been configured.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( ! Config::has_container() ) {
			throw new RuntimeException(
				__( 'You must call StellarWP\Uplink\Config::set_container() before calling StellarWP\Uplink::init().', '%TEXTDOMAIN%' )
			);
		}

		$container = Config::get_container();

		$container->bind( ContainerInterface::class, $container );
		$container->singleton( View\Provider::class, View\Provider::class );
		$container->singleton( Site\Data::class, Site\Data::class );
		$container->singleton( Admin\Provider::class, Admin\Provider::class );
		$container->singleton( Legacy\Provider::class, Legacy\Provider::class );
		$container->singleton( Features\Provider::class, Features\Provider::class );
		$container->singleton( Http\Provider::class, Http\Provider::class );
		$container->singleton( Licensing\Provider::class, Licensing\Provider::class );
		$container->singleton( Catalog\Provider::class, Catalog\Provider::class );
		$container->singleton( API\REST\V1\Provider::class, API\REST\V1\Provider::class );
		$container->singleton( API\Functions\Provider::class, API\Functions\Provider::class );
		$container->singleton( CLI\Provider::class, CLI\Provider::class );
		$container->singleton( Cron\Provider::class, Cron\Provider::class );

		$container->get( View\Provider::class )->register();
		$container->get( Admin\Provider::class )->register();
		$container->get( Legacy\Provider::class )->register();
		$container->get( Features\Provider::class )->register();
		$container->get( Http\Provider::class )->register();
		$container->get( Licensing\Provider::class )->register();
		$container->get( Catalog\Provider::class )->register();
		$container->get( API\REST\V1\Provider::class )->register();
		$container->get( API\Functions\Provider::class )->register();
		$container->get( CLI\Provider::class )->register();
		$container->get( Cron\Provider::class )->register();

		static::register_instance_hooks();
	}

	/**
	 * Registers shared, non-prefixed WordPress hooks that enable cross-instance
	 * communication between vendor-prefixed copies of Uplink.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	protected static function register_instance_hooks(): void {
		_stellarwp_uplink_instance_registry( self::VERSION );

		Version::register_debug_info();
	}
}
