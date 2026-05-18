<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Portal;

use LiquidWeb\Harbor\Config;
use LiquidWeb\Harbor\Legacy\License_Repository as Legacy_License_Repository;
use LiquidWeb\Harbor\Licensing\Repositories\License_Repository;
use LiquidWeb\Harbor\Portal\Contracts\Download_Url_Builder;
use LiquidWeb\Harbor\Site\Data;

/**
 * Builds Herald download URLs for catalog features.
 *
 * Herald is the StellarWP download service. Two URL formats are produced depending
 * on which license type covers the requested slug:
 *
 * - Unified license:  {herald_base_url}/download/{slug}/latest/{license_key}/zip?site={domain}
 * - Legacy license:   {herald_base_url}/legacy/download?plugin={slug}&key={legacy_key}&site={domain}
 *
 * Legacy keys take precedence when both are present so a legacy-only customer's
 * stored key drives their downloads even when a Unified key is also installed.
 *
 * @since 1.0.0
 */
final class Herald_Url_Builder implements Download_Url_Builder {

	/**
	 * The Unified license key provider.
	 *
	 * @since 1.0.0
	 *
	 * @var License_Repository
	 */
	private License_Repository $license_repository;

	/**
	 * The legacy license repository.
	 *
	 * @since TBD
	 *
	 * @var Legacy_License_Repository
	 */
	private Legacy_License_Repository $legacy_repository;

	/**
	 * Site data provider.
	 *
	 * @since 1.0.0
	 *
	 * @var Data
	 */
	private Data $site_data;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param License_Repository        $license_repository The Unified license key provider.
	 * @param Legacy_License_Repository $legacy_repository  The legacy license repository.
	 * @param Data                      $site_data          Site data provider.
	 */
	public function __construct(
		License_Repository $license_repository,
		Legacy_License_Repository $legacy_repository,
		Data $site_data
	) {
		$this->license_repository = $license_repository;
		$this->legacy_repository  = $legacy_repository;
		$this->site_data          = $site_data;
	}

	/**
	 * Builds a Herald download URL for the given feature slug.
	 *
	 * Returns the legacy `/legacy/download` URL when a matching active legacy
	 * license exists for the slug. Otherwise falls back to the Unified
	 * `/download/{slug}/latest/{key}/zip` URL. Returns an empty string when
	 * neither a license nor a domain is available.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The catalog feature slug.
	 *
	 * @return string
	 */
	public function build( string $slug ): string {
		$domain = $this->site_data->get_domain();

		if ( $domain === '' ) {
			return '';
		}

		$legacy_key = $this->resolve_active_legacy_key( $slug );

		if ( $legacy_key !== null ) {
			return $this->build_legacy_url( $slug, $legacy_key, $domain );
		}

		$license_key = $this->license_repository->get_key();

		if ( $license_key === null ) {
			return '';
		}

		return $this->build_unified_url( $slug, $license_key, $domain );
	}

	/**
	 * Returns the active legacy license key for a slug, or null when none applies.
	 *
	 * @since TBD
	 *
	 * @param string $slug The catalog feature slug.
	 *
	 * @return string|null
	 */
	private function resolve_active_legacy_key( string $slug ): ?string {
		$legacy = $this->legacy_repository->find( $slug );

		if ( $legacy === null || ! $legacy->is_active || $legacy->key === '' ) {
			return null;
		}

		return $legacy->key;
	}

	/**
	 * Builds the legacy Herald download URL for a slug + legacy key + domain.
	 *
	 * @since TBD
	 *
	 * @param string $slug   The catalog feature slug.
	 * @param string $key    The legacy license key.
	 * @param string $domain The site domain.
	 *
	 * @return string
	 */
	private function build_legacy_url( string $slug, string $key, string $domain ): string {
		return $this->herald_url(
			'/legacy/download',
			[
				'plugin' => rawurlencode( $slug ),
				'key'    => rawurlencode( $key ),
				'site'   => rawurlencode( $domain ),
			]
		);
	}

	/**
	 * Builds the Unified Herald download URL for a slug + Unified key + domain.
	 *
	 * @since TBD
	 *
	 * @param string $slug   The catalog feature slug.
	 * @param string $key    The Unified license key.
	 * @param string $domain The site domain.
	 *
	 * @return string
	 */
	private function build_unified_url( string $slug, string $key, string $domain ): string {
		$path = '/download/' . rawurlencode( $slug ) . '/latest/' . rawurlencode( $key ) . '/zip';

		return $this->herald_url( $path, [ 'site' => rawurlencode( $domain ) ] );
	}

	/**
	 * Composes a Herald URL from the configured base URL, a path, and query args.
	 *
	 * @since TBD
	 *
	 * @param string                $path  The path appended to the Herald base URL.
	 * @param array<string, string> $query The query string arguments (already encoded by the caller).
	 *
	 * @return string
	 */
	private function herald_url( string $path, array $query ): string {
		return add_query_arg( $query, Config::get_herald_base_url() . $path );
	}
}
