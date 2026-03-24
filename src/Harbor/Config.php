<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor;

use RuntimeException;
use StellarWP\ContainerContract\ContainerInterface;

class Config {

	/**
	 * The default base URL for the StellarWP licensing and catalog API.
	 *
	 * @since 1.0.0
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
	 * The base URL for the StellarWP licensing and catalog API.
	 *
	 * @since 1.0.0
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
				__( 'You must provide a container via LiquidWeb\Harbor\Config::set_container() before attempting to fetch it.', '%TEXTDOMAIN%' )
			);
		}

		return self::$container;
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
		static::$api_base_url = self::DEFAULT_API_BASE_URL;
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
	 * Set the base URL for the StellarWP licensing and catalog API.
	 *
	 * @since 1.0.0
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
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function get_api_base_url(): string {
		return static::$api_base_url;
	}
}
