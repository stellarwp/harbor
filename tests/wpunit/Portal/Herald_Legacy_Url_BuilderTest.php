<?php declare( strict_types=1 );

// cspell:ignore rawurlencoded rawurlencodes .

namespace LiquidWeb\Harbor\Tests\Portal;

use LiquidWeb\Harbor\Config;
use LiquidWeb\Harbor\Legacy\License_Repository as Legacy_License_Repository;
use LiquidWeb\Harbor\Portal\Herald_Legacy_Url_Builder;
use LiquidWeb\Harbor\Site\Data;
use LiquidWeb\Harbor\Tests\HarborTestCase;

final class Herald_Legacy_Url_BuilderTest extends HarborTestCase {

	private const TEST_HERALD_BASE = 'https://herald.test.example.com';
	private const TEST_DOMAIN      = 'site.example.com';

	protected function setUp(): void {
		parent::setUp();
		Config::set_herald_base_url( self::TEST_HERALD_BASE );
	}

	protected function tearDown(): void {
		remove_all_filters( 'lw-harbor/legacy_licenses' );
		Config::reset();
		parent::tearDown();
	}

	private function make_builder( string $domain ): Herald_Legacy_Url_Builder {
		$site_data = $this->makeEmpty(
			Data::class,
			[ 'get_domain' => $domain ]
		);

		return new Herald_Legacy_Url_Builder( new Legacy_License_Repository(), $site_data );
	}

	/**
	 * Registers a single legacy license entry via the filter.
	 *
	 * @param array<string, mixed> $overrides Field overrides on the default entry.
	 */
	private function register_legacy_license( array $overrides = [] ): void {
		$defaults = [
			'key'             => 'legacy-key-1234',
			'slug'            => 'kad-blocks-pro',
			'name'            => 'Kadence Blocks Pro',
			'product'         => 'kadence',
			'is_active'       => true,
			'use_for_updates' => true,
			'page_url'        => 'https://example.com/manage',
			'expires_at'      => '',
		];

		$entry = array_merge( $defaults, $overrides );

		add_filter(
			'lw-harbor/legacy_licenses',
			static function ( array $licenses ) use ( $entry ) {
				$licenses[] = $entry;

				return $licenses;
			}
		);
	}

	/**
	 * Tests that the builder composes the expected legacy Herald URL for an active matching entry.
	 *
	 * @return void
	 */
	public function test_build_returns_legacy_url_for_matching_active_legacy_license(): void {
		$this->register_legacy_license(
			[
				'key'  => 'legacy-key-abc',
				'slug' => 'kad-blocks-pro',
			]
		);

		$builder = $this->make_builder( self::TEST_DOMAIN );
		$url     = $builder->build( 'kad-blocks-pro' );

		$this->assertSame(
			self::TEST_HERALD_BASE . '/legacy/download?plugin=kad-blocks-pro&key=legacy-key-abc&site=' . self::TEST_DOMAIN,
			$url
		);
	}

	/**
	 * Tests that an inactive legacy entry produces no URL.
	 *
	 * @return void
	 */
	public function test_build_returns_empty_when_legacy_is_inactive(): void {
		$this->register_legacy_license(
			[
				'key'       => 'legacy-key-abc',
				'slug'      => 'kad-blocks-pro',
				'is_active' => false,
			]
		);

		$builder = $this->make_builder( self::TEST_DOMAIN );

		$this->assertSame( '', $builder->build( 'kad-blocks-pro' ) );
	}

	/**
	 * Tests that a legacy entry with an empty key produces no URL.
	 *
	 * @return void
	 */
	public function test_build_returns_empty_when_legacy_key_is_empty(): void {
		$this->register_legacy_license(
			[
				'key'  => '',
				'slug' => 'kad-blocks-pro',
			]
		);

		$builder = $this->make_builder( self::TEST_DOMAIN );

		$this->assertSame( '', $builder->build( 'kad-blocks-pro' ) );
	}

	/**
	 * Tests that a legacy entry with a non-matching slug produces no URL.
	 *
	 * @return void
	 */
	public function test_build_returns_empty_when_slug_does_not_match(): void {
		$this->register_legacy_license(
			[
				'key'  => 'legacy-key-abc',
				'slug' => 'some-other-plugin',
			]
		);

		$builder = $this->make_builder( self::TEST_DOMAIN );

		$this->assertSame( '', $builder->build( 'kad-blocks-pro' ) );
	}

	/**
	 * Tests that an empty legacy repository produces no URL.
	 *
	 * @return void
	 */
	public function test_build_returns_empty_when_no_legacy_entries_registered(): void {
		$builder = $this->make_builder( self::TEST_DOMAIN );

		$this->assertSame( '', $builder->build( 'kad-blocks-pro' ) );
	}

	/**
	 * Tests that an empty domain short-circuits even when a matching legacy entry exists.
	 *
	 * @return void
	 */
	public function test_build_returns_empty_when_domain_is_empty(): void {
		$this->register_legacy_license(
			[
				'key'  => 'legacy-key-abc',
				'slug' => 'kad-blocks-pro',
			]
		);

		$builder = $this->make_builder( '' );

		$this->assertSame( '', $builder->build( 'kad-blocks-pro' ) );
	}

	/**
	 * Tests that the slug, key, and domain are rawurlencoded into the URL.
	 *
	 * @return void
	 */
	public function test_build_rawurlencodes_all_params(): void {
		$this->register_legacy_license(
			[
				'key'  => 'KEY WITH SPACES',
				'slug' => 'slug with spaces',
			]
		);

		$builder = $this->make_builder( 'my site.example.com' );
		$url     = $builder->build( 'slug with spaces' );

		$this->assertStringContainsString( 'plugin=slug%20with%20spaces', $url );
		$this->assertStringContainsString( 'key=KEY%20WITH%20SPACES', $url );
		$this->assertStringContainsString( 'site=my%20site.example.com', $url );
	}

	/**
	 * Tests that an entry without `use_for_updates` opt-in produces no URL,
	 * even when otherwise active and matching.
	 *
	 * @return void
	 */
	public function test_build_returns_empty_when_legacy_has_not_opted_into_updates(): void {
		$this->register_legacy_license(
			[
				'key'             => 'legacy-key-abc',
				'slug'            => 'kad-blocks-pro',
				'use_for_updates' => false,
			]
		);

		$builder = $this->make_builder( self::TEST_DOMAIN );

		$this->assertSame( '', $builder->build( 'kad-blocks-pro' ) );
	}

	/**
	 * Tests that the builder uses the Herald base URL configured on Config.
	 *
	 * @return void
	 */
	public function test_build_uses_configured_herald_base_url(): void {
		Config::set_herald_base_url( 'https://custom-herald.example.com' );
		$this->register_legacy_license(
			[
				'key'  => 'legacy-key-abc',
				'slug' => 'kad-blocks-pro',
			]
		);

		$builder = $this->make_builder( self::TEST_DOMAIN );
		$url     = $builder->build( 'kad-blocks-pro' );

		$this->assertStringStartsWith( 'https://custom-herald.example.com/legacy/download', $url );
	}
}
