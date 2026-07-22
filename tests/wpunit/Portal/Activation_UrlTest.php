<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Portal;

use LiquidWeb\Harbor\Admin\Feature_Manager_Page;
use LiquidWeb\Harbor\Config;
use LiquidWeb\Harbor\Portal\Activation_Url;
use LiquidWeb\Harbor\Site\Data;
use LiquidWeb\Harbor\Tests\HarborTestCase;

final class Activation_UrlTest extends HarborTestCase {

	private const TEST_PORTAL_BASE = 'https://portal.test.example.com';
	private const TEST_DOMAIN      = 'site.example.com';

	protected function setUp(): void {
		parent::setUp();
		Config::set_portal_base_url( self::TEST_PORTAL_BASE );
	}

	protected function tearDown(): void {
		Config::reset();
		parent::tearDown();
	}

	private function make_builder( string $domain = self::TEST_DOMAIN ): Activation_Url {
		$site_data = $this->makeEmpty(
			Data::class,
			[ 'get_domain' => $domain ]
		);

		return new Activation_Url( $site_data );
	}

	/**
	 * Parses the query string of a URL into an associative array, with values
	 * already percent-decoded.
	 *
	 * @param string $url The URL to parse.
	 *
	 * @return array<string, string>
	 */
	private function query_params( string $url ): array {
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $params );

		return $params;
	}

	/**
	 * Tests that the base URL carries the referral, redirect and domain params.
	 *
	 * @return void
	 */
	public function test_get_base_includes_expected_params(): void {
		$url = $this->make_builder()->get_base();

		$this->assertStringStartsWith( self::TEST_PORTAL_BASE . '/subscriptions/?', $url );

		$params = $this->query_params( $url );

		$this->assertSame( 'plugin', $params['portal-referral'] );
		$this->assertSame( self::TEST_DOMAIN, $params['domain'] );
	}

	/**
	 * Tests that the redirect defaults to the Software Manager page with a
	 * refresh, so newly activated products appear without a manual reload.
	 *
	 * @return void
	 */
	public function test_get_base_defaults_redirect_to_feature_manager_page(): void {
		$params = $this->query_params( $this->make_builder()->get_base() );

		$this->assertSame(
			admin_url( 'options-general.php?page=' . Feature_Manager_Page::PAGE_SLUG . '&refresh=auto' ),
			$params['redirect_url']
		);
	}

	/**
	 * Tests that the default redirect uses the page's canonical address. The
	 * Software Manager is a submenu of Settings, and every other link to it in
	 * the codebase uses the options-general.php form. WordPress resolves an
	 * admin.php URL to the same page, so this pins consistency, not behaviour.
	 *
	 * @return void
	 */
	public function test_get_base_uses_the_canonical_page_url(): void {
		$params = $this->query_params( $this->make_builder()->get_base() );

		$this->assertStringContainsString( 'options-general.php', $params['redirect_url'] );
	}

	/**
	 * Tests that a caller-supplied redirect replaces the default, which is what
	 * lets a host plugin return the user to its own onboarding screen.
	 *
	 * @return void
	 */
	public function test_get_base_accepts_a_custom_redirect(): void {
		$redirect = admin_url( 'admin.php?page=my-onboarding&step=2' );
		$params   = $this->query_params( $this->make_builder()->get_base( $redirect ) );

		$this->assertSame( $redirect, $params['redirect_url'] );
	}

	/**
	 * Tests that the redirect URL is percent-encoded, so its own query string
	 * does not leak into the portal URL as separate params.
	 *
	 * @return void
	 */
	public function test_get_base_encodes_the_redirect_url(): void {
		$redirect = admin_url( 'admin.php?page=my-onboarding&step=2' );
		$url      = $this->make_builder()->get_base( $redirect );

		$this->assertStringContainsString( 'redirect_url=' . rawurlencode( $redirect ), $url );

		// The nested params must not appear at the top level.
		$this->assertArrayNotHasKey( 'step', $this->query_params( $url ) );
	}

	/**
	 * Tests that for_product appends the sku in slug:tier form.
	 *
	 * @return void
	 */
	public function test_for_product_appends_the_sku(): void {
		$params = $this->query_params(
			$this->make_builder()->for_product( 'givewp', 'elite' )
		);

		$this->assertSame( 'givewp:elite', $params['sku'] );
	}

	/**
	 * Tests that for_product keeps every param the base URL sets.
	 *
	 * @return void
	 */
	public function test_for_product_preserves_the_base_params(): void {
		$redirect = admin_url( 'admin.php?page=my-onboarding' );
		$params   = $this->query_params(
			$this->make_builder()->for_product( 'givewp', 'elite', $redirect )
		);

		$this->assertSame( 'plugin', $params['portal-referral'] );
		$this->assertSame( self::TEST_DOMAIN, $params['domain'] );
		$this->assertSame( $redirect, $params['redirect_url'] );
	}

	/**
	 * Tests that the sku separator is encoded, matching what the JS helper
	 * produces via URLSearchParams. The two implementations must agree.
	 *
	 * @return void
	 */
	public function test_for_product_encodes_the_sku_separator(): void {
		$this->assertStringContainsString(
			'sku=givewp%3Aelite',
			$this->make_builder()->for_product( 'givewp', 'elite' )
		);
	}

	/**
	 * Tests that a missing domain still yields a usable URL. The portal can
	 * recover from an absent domain; returning an empty string would leave the
	 * caller with a dead link and no way to tell why.
	 *
	 * @return void
	 */
	public function test_get_base_handles_an_empty_domain(): void {
		$url = $this->make_builder( '' )->get_base();

		$this->assertStringStartsWith( self::TEST_PORTAL_BASE . '/subscriptions/?', $url );
		$this->assertSame( '', $this->query_params( $url )['domain'] );
	}
}
