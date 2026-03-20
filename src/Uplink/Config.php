<?php declare( strict_types=1 );

namespace StellarWP\Uplink;

use RuntimeException;
use StellarWP\ContainerContract\ContainerInterface;
use StellarWP\Uplink\Storage\Contracts\Storage;
use StellarWP\Uplink\Storage\Drivers\Option_Storage;

class Config {

	/**
	 * The default authorization cache time in seconds (6 hours).
	 */
	public const DEFAULT_AUTH_CACHE = 21600;

	/**
	 * The default base URL for the StellarWP licensing and catalog API.
	 *
	 * @since 3.0.0
	 */
	public const DEFAULT_API_BASE_URL = 'https://licensing.stellarwp.com';

	/**
	 * Container object.
	 *
	 * @since 1.0.0
	 *
	 * @var ContainerInterface
	 */
	protected static $container;

	/**
	 * Prefix for hook names.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected static $hook_prefix = '';

	/**
	 * How long in seconds we cache successful authorization
	 * token requests.
	 *
	 * @var int
	 */
	protected static $auth_cache_expiration = self::DEFAULT_AUTH_CACHE;

	/**
	 * The storage driver FQCN to use.
	 *
	 * @var class-string<Storage>
	 */
	protected static $storage_driver = Option_Storage::class;

	/**
	 * The base URL for the StellarWP licensing and catalog API.
	 *
	 * @since 3.0.0
	 *
	 * @var string
	 */
	protected static $api_base_url = self::DEFAULT_API_BASE_URL;

	/**
	 * Get the container.
	 *
	 * @since 1.0.0
	 *
	 * @throws RuntimeException If the container has not been set.
	 *
	 * @return ContainerInterface
	 */
	public static function get_container() {
		if ( self::$container === null ) {
			throw new RuntimeException(
				__( 'You must provide a container via StellarWP\Uplink\Config::set_container() before attempting to fetch it.', '%TEXTDOMAIN%' )
			);
		}

		return self::$container;
	}

	/**
	 * Gets the hook prefix.
	 *
	 * @since 1.0.0
	 *
	 * @throws RuntimeException If the hook prefix has not been set.
	 *
	 * @return string
	 */
	public static function get_hook_prefix(): string {
		if ( self::$hook_prefix === null ) {
			throw new RuntimeException(
				__( 'You must provide a hook prefix via StellarWP\Uplink\Config::set_hook_prefix() before attempting to fetch it.', '%TEXTDOMAIN%' )
			);
		}

		return static::$hook_prefix;
	}

	/**
	 * Gets the hook underscored prefix.
	 *
	 * @since 1.0.0
	 *
	 * @throws RuntimeException If the hook prefix has not been set.
	 *
	 * @return string
	 */
	public static function get_hook_prefix_underscored(): string {
		if ( self::$hook_prefix === null ) {
			throw new RuntimeException(
				__( 'You must provide a hook prefix via StellarWP\Uplink\Config::set_hook_prefix() before attempting to fetch it.', '%TEXTDOMAIN%' )
			);
		}

		return strtolower( str_replace( '-', '_', sanitize_title( static::$hook_prefix ) ) );
	}

	/**
	 * Returns whether the container has been set.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function has_container(): bool {
		return self::$container !== null;
	}

	/**
	 * Resets this class back to the defaults.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function reset(): void {
		static::$hook_prefix           = '';
		static::$auth_cache_expiration = self::DEFAULT_AUTH_CACHE;
		static::$api_base_url          = self::DEFAULT_API_BASE_URL;
	}

	/**
	 * Set the container object.
	 *
	 * @since 1.0.0
	 *
	 * @param ContainerInterface $container Container object.
	 *
	 * @return void
	 */
	public static function set_container( ContainerInterface $container ): void {
		self::$container = $container;
	}

	/**
	 * Sets the hook prefix.
	 *
	 * @since 1.0.0
	 *
	 * @param string $prefix The hook prefix.
	 *
	 * @return void
	 */
	public static function set_hook_prefix( string $prefix ): void {
		static::$hook_prefix = $prefix;
	}

	/**
	 * Set the token authorization expiration.
	 *
	 * @param int $seconds  The time seconds the cache will exist for.
	 *                       -1 = disabled, 0 = no expiration.
	 *
	 * @return void
	 */
	public static function set_auth_cache_expiration( int $seconds ): void {
		static::$auth_cache_expiration = $seconds;
	}

	/**
	 * Get the token authorization expiration.
	 *
	 * @return int
	 */
	public static function get_auth_cache_expiration(): int {
		return static::$auth_cache_expiration;
	}

	/**
	 * Set the underlying storage driver.
	 *
	 * @param string $class_name The FQCN to a storage driver.
	 *
	 * @phpstan-param class-string<Storage> $class_name
	 *
	 * @return void
	 */
	public static function set_storage_driver( string $class_name ): void {
		static::$storage_driver = $class_name;
	}

	/**
	 * Get the underlying storage driver.
	 *
	 * @return class-string<Storage>
	 */
	public static function get_storage_driver(): string {
		$driver = static::$storage_driver;

		return $driver ?: Option_Storage::class;
	}

	/**
	 * Set the base URL for the StellarWP licensing and catalog API.
	 *
	 * @since 3.0.0
	 *
	 * @param string $url The API base URL (no trailing slash).
	 *
	 * @return void
	 */
	public static function set_api_base_url( string $url ): void {
		static::$api_base_url = rtrim( $url, '/' );
	}

	/**
	 * Get the base URL for the StellarWP licensing and catalog API.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public static function get_api_base_url(): string {
		return static::$api_base_url;
	}
}
