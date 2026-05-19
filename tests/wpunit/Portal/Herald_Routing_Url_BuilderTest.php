<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Portal;

use LiquidWeb\Harbor\Config;
use LiquidWeb\Harbor\Legacy\License_Repository as Legacy_License_Repository;
use LiquidWeb\Harbor\Licensing\Repositories\License_Repository;
use LiquidWeb\Harbor\Portal\Contracts\Download_Url_Builder;
use LiquidWeb\Harbor\Portal\Herald_Legacy_Url_Builder;
use LiquidWeb\Harbor\Portal\Herald_Routing_Url_Builder;
use LiquidWeb\Harbor\Portal\Herald_Url_Builder;
use LiquidWeb\Harbor\Site\Data;
use LiquidWeb\Harbor\Tests\HarborTestCase;

final class Herald_Routing_Url_BuilderTest extends HarborTestCase {

	private const TEST_HERALD_BASE = 'https://herald.test.example.com';
	private const TEST_LICENSE_KEY = 'LWSW-TEST-KEY-9999';
	private const TEST_DOMAIN      = 'site.example.com';

	protected function setUp(): void {
		parent::setUp();
		Config::set_herald_base_url( self::TEST_HERALD_BASE );
	}

	protected function tearDown(): void {
		delete_option( License_Repository::KEY_OPTION_NAME );
		remove_all_filters( 'lw-harbor/legacy_licenses' );
		Config::reset();
		parent::tearDown();
	}

	/**
	 * Build a router around real Unified and Legacy builders.
	 *
	 * Both builders are exercised against the contract through their normal
	 * dependencies, so the routing test verifies real collaboration rather
	 * than mock interactions. Callers control which builder will produce a
	 * non-empty URL by deciding whether to set the Unified key, register a
	 * matching legacy entry, or both.
	 */
	private function make_router( ?string $unified_key, bool $with_matching_legacy ): Herald_Routing_Url_Builder {
		if ( $unified_key !== null ) {
			update_option( License_Repository::KEY_OPTION_NAME, $unified_key );
		} else {
			delete_option( License_Repository::KEY_OPTION_NAME );
		}

		if ( $with_matching_legacy ) {
			add_filter(
				'lw-harbor/legacy_licenses',
				static function ( array $licenses ): array {
					$licenses[] = [
						'key'             => 'legacy-key-abc',
						'slug'            => 'kad-blocks-pro',
						'name'            => 'Kadence Blocks Pro',
						'product'         => 'kadence',
						'is_active'       => true,
						'use_for_updates' => true,
						'page_url'        => 'https://example.com/manage',
						'expires_at'      => '',
					];

					return $licenses;
				}
			);
		}

		$site_data = $this->makeEmpty( Data::class, [ 'get_domain' => self::TEST_DOMAIN ] );

		$unified = new Herald_Url_Builder( new License_Repository(), $site_data );
		$legacy  = new Herald_Legacy_Url_Builder( new Legacy_License_Repository(), $site_data );

		return new Herald_Routing_Url_Builder( $unified, $legacy );
	}

	/**
	 * Tests that the legacy URL is returned when a matching legacy entry exists,
	 * even when a Unified key is also stored.
	 *
	 * @return void
	 */
	public function test_returns_legacy_url_when_legacy_matches(): void {
		$router = $this->make_router( self::TEST_LICENSE_KEY, true );

		$url = $router->build( 'kad-blocks-pro' );

		$this->assertStringContainsString( '/legacy/download', $url );
		$this->assertStringContainsString( 'key=legacy-key-abc', $url );
		$this->assertStringNotContainsString( self::TEST_LICENSE_KEY, $url );
	}

	/**
	 * Tests that the router returns the Unified URL when no legacy entry covers the slug.
	 *
	 * @return void
	 */
	public function test_falls_back_to_unified_when_no_legacy_match(): void {
		$router = $this->make_router( self::TEST_LICENSE_KEY, false );

		$url = $router->build( 'kad-blocks-pro' );

		$this->assertStringContainsString( '/download/kad-blocks-pro/latest/' . self::TEST_LICENSE_KEY . '/zip', $url );
		$this->assertStringNotContainsString( '/legacy/download', $url );
	}

	/**
	 * Tests that the router returns an empty string when neither builder can produce a URL.
	 *
	 * @return void
	 */
	public function test_returns_empty_when_neither_builder_produces_a_url(): void {
		$router = $this->make_router( null, false );

		$this->assertSame( '', $router->build( 'kad-blocks-pro' ) );
	}

	/**
	 * Tests that the routing class satisfies the Download_Url_Builder contract.
	 *
	 * @return void
	 */
	public function test_implements_download_url_builder_contract(): void {
		$router = $this->make_router( null, false );

		$this->assertInstanceOf( Download_Url_Builder::class, $router );
	}
}
