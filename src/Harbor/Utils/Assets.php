<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Utils;

/**
 * Resolves paths and URLs into Harbor's compiled asset directory.
 *
 * Which directory that is depends on `WP_DEBUG`: `build-dev/` carries source
 * maps and readable output, `build/` is minified. Every place that registers a
 * script or style needs the same answer, so it is worked out here once rather
 * than repeated at each call site — a second copy is how the two drift apart
 * when the build pipeline changes.
 *
 * @since TBD
 */
final class Assets {

	/**
	 * The build directory currently in use.
	 *
	 * @since TBD
	 *
	 * @return string Either "build-dev" or "build".
	 */
	public static function build_dir(): string {
		return ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? 'build-dev' : 'build';
	}

	/**
	 * An absolute filesystem path inside the build directory.
	 *
	 * @since TBD
	 *
	 * @param string $file File relative to the build directory, e.g. "index.asset.php".
	 *
	 * @return string
	 */
	public static function path( string $file = '' ): string {
		return self::plugin_root_dir() . '/' . self::build_dir() . ( '' === $file ? '' : '/' . $file );
	}

	/**
	 * A public URL inside the build directory.
	 *
	 * @since TBD
	 *
	 * @param string $file File relative to the build directory, e.g. "index.js".
	 *
	 * @return string
	 */
	public static function url( string $file = '' ): string {
		$base = trailingslashit( plugin_dir_url( self::plugin_root_dir() . '/index.php' ) ) . self::build_dir();

		return '' === $file ? $base : $base . '/' . $file;
	}

	/**
	 * Harbor's own plugin root.
	 *
	 * Resolved from this file rather than from a constant so it stays correct
	 * inside a Strauss-prefixed copy, where Harbor lives under whichever plugin
	 * bundled it rather than in its own directory.
	 *
	 * Path resolution from this file:
	 *   __DIR__                            → src/Harbor/Utils
	 *   dirname(__DIR__)                   → src/Harbor
	 *   dirname(dirname(__DIR__))          → src
	 *   dirname(dirname(dirname(__DIR__))) → plugin root
	 *
	 * @since TBD
	 *
	 * @return string
	 */
	private static function plugin_root_dir(): string {
		return dirname( dirname( dirname( __DIR__ ) ) );
	}
}
