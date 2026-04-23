<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Licensing;

use LiquidWeb\Harbor\Licensing\Error_Code;
use LiquidWeb\LicensingApiClient\Contracts\LicensingClientInterface;
use LiquidWeb\LicensingApiClient\Exceptions\NotFoundException;
use LiquidWeb\LicensingApiClient\Resources\Contracts\CreditsResourceInterface;
use LiquidWeb\LicensingApiClient\Resources\Contracts\EntitlementsResourceInterface;
use LiquidWeb\LicensingApiClient\Resources\Contracts\LicensesResourceInterface;
use LiquidWeb\LicensingApiClient\Resources\Contracts\ProductsResourceInterface;
use LiquidWeb\LicensingApiClient\Resources\Contracts\TokensResourceInterface;
use LiquidWeb\LicensingApiClient\Responses\Product\Catalog;
use LiquidWeb\LicensingApiClient\Tracing\TraceContext;
use LiquidWeb\LicensingApiClient\Tracing\TraceParent;
use Nyholm\Psr7\Response;

/**
 * A fixture-based licensing client that reads from JSON files.
 *
 * Each license key maps to a JSON file in the fixture directory.
 * The filename is the kebab-case lowercase of the key.
 *
 * @since 1.0.0
 */
final class Fixture_Client implements LicensingClientInterface, ProductsResourceInterface {

	/**
	 * The directory containing fixture JSON files.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected string $fixture_dir;

	/**
	 * In-memory cache of parsed catalogs keyed by lowercase key.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, Catalog>
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
	 * @inheritDoc
	 */
	public function products(): ProductsResourceInterface {
		return $this;
	}

	/**
	 * Fetch the product catalog for a license key from a fixture file.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $licenseKey    License key.
	 * @param string|null $domain Site domain (accepted but ignored by fixture).
	 *
	 * @throws NotFoundException When no fixture file exists for the given key.
	 *
	 * @return Catalog
	 */
	public function catalog( string $licenseKey, ?string $domain = null ): Catalog {
		$cache_key = strtolower( $licenseKey );

		if ( isset( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ];
		}

		$file = $this->fixture_dir . '/' . $cache_key . '.json';

		if ( ! file_exists( $file ) ) {
			throw new NotFoundException(
				sprintf( 'License key not recognized: %s', $licenseKey ),
				new Response( 404 ),
				404,
				Error_Code::INVALID_KEY
			);
		}

		$json = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		/** @var array{products: list<array{product_slug: string, tier: string, status: string, expires: string, capabilities: list<string>, activations: array{site_limit: int, active_count: int, over_limit: bool, domains: list<string>}, activated_here?: bool, validation_status?: string}>} $data */
		$data = json_decode( (string) $json, true );

		$catalog = Catalog::from( $data );

		$this->cache[ $cache_key ] = $catalog;

		return $catalog;
	}

	/**
	 * @inheritDoc
	 *
	 * @throws \BadMethodCallException Always.
	 */
	public function entitlements(): EntitlementsResourceInterface {
		throw new \BadMethodCallException( 'Fixture_Client does not support entitlements().' );
	}

	/**
	 * @inheritDoc
	 *
	 * @throws \BadMethodCallException Always.
	 */
	public function licenses(): LicensesResourceInterface {
		throw new \BadMethodCallException( 'Fixture_Client does not support licenses().' );
	}

	/**
	 * @inheritDoc
	 *
	 * @throws \BadMethodCallException Always.
	 */
	public function credits(): CreditsResourceInterface {
		throw new \BadMethodCallException( 'Fixture_Client does not support credits().' );
	}

	/**
	 * @inheritDoc
	 */
	public function withoutAuth(): LicensingClientInterface {
		return $this;
	}

	/**
	 * @inheritDoc
	 */
	public function withConfiguredToken(): LicensingClientInterface {
		return $this;
	}

	/**
	 * @inheritDoc
	 *
	 * @param string $token The token (unused).
	 */
	public function withToken( string $token ): LicensingClientInterface {
		return $this;
	}

	/**
	 * @inheritDoc
	 *
	 * @throws \BadMethodCallException Always.
	 */
	public function tokens(): TokensResourceInterface {
		throw new \BadMethodCallException( 'Fixture_Client does not support tokens().' );
	}

	/**
	 * @inheritDoc
	 *
	 * @param array<string, string|int|float|bool> $headers Headers (unused).
	 */
	public function withHeaders( array $headers ): LicensingClientInterface {
		return $this;
	}

	/**
	 * @inheritDoc
	 *
	 * @param TraceParent $traceParent Trace parent (unused).
	 */
	public function withTraceParent( TraceParent $traceParent ): LicensingClientInterface {
		return $this;
	}

	/**
	 * @inheritDoc
	 *
	 * @param TraceContext $traceContext Trace context (unused).
	 */
	public function withTraceContext( TraceContext $traceContext ): LicensingClientInterface {
		return $this;
	}

	/**
	 * @inheritDoc
	 */
	public function withoutHeaders(): LicensingClientInterface {
		return $this;
	}
}
