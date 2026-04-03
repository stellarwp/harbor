<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor;

use LiquidWeb\Harbor\Utils\Cast;
use RuntimeException;
use StellarWP\ContainerContract\ContainerInterface;

class Config {

	/**
	 * The default base URL for the StellarWP licensing service.
	 *
	 * @since 1.0.0
	 */
	public const DEFAULT_LICENSING_BASE_URL = 'https://licensing.stellarwp.com';

	/**
	 * The default base URL for the Commerce Portal (catalog API).
	 *
	 * @since 1.0.0
	 */
	public const DEFAULT_PORTAL_BASE_URL = 'https://my.software.stellarwp.com';

	/**
	 * Container object.
	 *
	 * @since 1.0.0
	 *
	 * @var ContainerInterface
	 */
	protected static $container;

	/**
	 * The base URL for the StellarWP licensing service.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected static $licensing_base_url = self::DEFAULT_LICENSING_BASE_URL;

	/**
	 * The base URL for the Commerce Portal (catalog API).
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected static $portal_base_url = self::DEFAULT_PORTAL_BASE_URL;

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
		static::$licensing_base_url = self::DEFAULT_LICENSING_BASE_URL;
		static::$portal_base_url   = self::DEFAULT_PORTAL_BASE_URL;
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
	 * Set the base URL for the StellarWP licensing service.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The API base URL (no trailing slash).
	 *
	 * @return void
	 */
	public static function set_licensing_base_url( string $url ): void {
		static::$licensing_base_url = rtrim( $url, '/' );
	}

	/**
	 * Get the base URL for the StellarWP licensing service.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function get_licensing_base_url(): string {
		if ( defined( 'LW_HARBOR_LICENSING_BASE_URL' ) ) {
			$url = Cast::to_string( LW_HARBOR_LICENSING_BASE_URL );

			return rtrim( $url, '/' );
		}

		return static::$licensing_base_url;
	}

	/**
	 * Set the base URL for the Commerce Portal (catalog API).
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The portal base URL (no trailing slash).
	 *
	 * @return void
	 */
	public static function set_portal_base_url( string $url ): void {
		static::$portal_base_url = rtrim( $url, '/' );
	}

	/**
	 * Get the base URL for the Commerce Portal (catalog API).
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function get_portal_base_url(): string {
		if ( defined( 'LW_HARBOR_PORTAL_BASE_URL' ) ) {
			$url = Cast::to_string( LW_HARBOR_PORTAL_BASE_URL );

			return rtrim( $url, '/' );
		}

		return static::$portal_base_url;
	}
}
