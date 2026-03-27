<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Licensing\Clients;

use LiquidWeb\Harbor\Licensing\Error_Code;
use LiquidWeb\Harbor\Licensing\Results\Product_Entry;
use WP_Error;

/**
 * A fixture-based licensing client that reads from JSON files.
 *
 * Each license key maps to a JSON file in the fixture directory.
 * The filename is the kebab-case lowercase of the key.
 *
 * @since 1.0.0
 *
 * @phpstan-type FixtureData array{products: list<array<string, mixed>>}
 */
final class Fixture_Client implements Licensing_Client {

	/**
	 * The directory containing fixture JSON files.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected string $fixture_dir;

	/**
	 * In-memory cache of parsed products keyed by lowercase key.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, Product_Entry[]|WP_Error>
	 */
	protected array $cache = [];

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string $fixture_dir Path to the directory containing fixture JSON files.
	 */
	public function __construct( string $fixture_dir ) {
		$this->fixture_dir = $fixture_dir;
	}

	/**
	 * Fetch the product catalog for a license key.
	 *
	 * Resolves the key to a kebab-case JSON filename in the fixture directory.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key    License key.
	 * @param string $domain Site domain (accepted but ignored by fixture).
	 *
	 * @return Product_Entry[]|WP_Error
	 */
	public function get_products( string $key, string $domain ) {
		$cache_key = strtolower( $key );

		if ( isset( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ];
		}

		$file = $this->fixture_dir . '/' . $cache_key . '.json';

		if ( ! file_exists( $file ) ) {
			$this->cache[ $cache_key ] = new WP_Error(
				Error_Code::INVALID_KEY,
				sprintf( 'License key not recognized: %s', $key ),
				[ 'status' => Error_Code::http_status( Error_Code::INVALID_KEY ) ]
			);

			return $this->cache[ $cache_key ];
		}

		$json = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- local fixture file.

		if ( $json === false ) {
			$this->cache[ $cache_key ] = new WP_Error(
				Error_Code::INVALID_RESPONSE,
				sprintf( 'License response could not be read: %s', $file ),
				[ 'status' => Error_Code::http_status( Error_Code::INVALID_RESPONSE ) ]
			);

			return $this->cache[ $cache_key ];
		}

		$data = json_decode( $json, true );

		if ( ! is_array( $data ) || ! isset( $data['products'] ) || ! is_array( $data['products'] ) ) {
			$this->cache[ $cache_key ] = new WP_Error(
				Error_Code::INVALID_RESPONSE,
				sprintf( 'License response could not be decoded: %s', $file ),
				[ 'status' => Error_Code::http_status( Error_Code::INVALID_RESPONSE ) ]
			);

			return $this->cache[ $cache_key ];
		}

		/** @var FixtureData $data */

		$this->cache[ $cache_key ] = array_map(
			[ Product_Entry::class, 'from_array' ],
			$data['products']
		);

		return $this->cache[ $cache_key ];
	}
}
