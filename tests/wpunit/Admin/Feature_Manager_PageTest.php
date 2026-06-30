<?php declare( strict_types=1 );

namespace wpunit\Admin;

use LiquidWeb\Harbor\Admin\Feature_Manager_Page;
use LiquidWeb\Harbor\Licensing\License_Manager;
use LiquidWeb\Harbor\Portal\Catalog_Repository;
use LiquidWeb\Harbor\Site\Data;
use LiquidWeb\Harbor\Tests\HarborTestCase;

class Feature_Manager_PageTest extends HarborTestCase {

	/**
	 * @var Feature_Manager_Page
	 */
	private $page;

	protected function setUp(): void {
		parent::setUp();

		if ( function_exists( 'uopz_allow_exit' ) ) {
			uopz_allow_exit( false );
		}

		// add_submenu_page() early-returns when the user lacks the required
		// capability, so menu-registration tests need an administrator to be
		// the current user before the call.
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$this->page = $this->make_page();
	}

	protected function tearDown(): void {
		if ( function_exists( 'uopz_allow_exit' ) ) {
			uopz_allow_exit( true );
		}

		wp_set_current_user( 0 );

		unset( $_GET['refresh'], $_GET['page'] );

		parent::tearDown();
	}

	private function make_page( array $license_manager_overrides = [], array $catalog_overrides = [] ): Feature_Manager_Page {
		$site_data       = $this->makeEmpty( Data::class, [ 'get_domain' => 'example.com' ] );
		$license_manager = $this->makeEmpty( License_Manager::class, $license_manager_overrides );
		$catalog         = $this->makeEmpty( Catalog_Repository::class, $catalog_overrides );

		return new Feature_Manager_Page( $site_data, $license_manager, $catalog );
	}

	private function get_settings_submenu_slugs(): array {
		global $submenu;

		if ( ! isset( $submenu['options-general.php'] ) ) {
			return [];
		}

		return array_column( $submenu['options-general.php'], 2 );
	}

	/**
	 * @test
	 */
	public function it_should_render_when_it_has_the_highest_version(): void {
		global $submenu;
		$submenu = [];

		set_current_screen( 'dashboard' );
		$this->page->maybe_register_page();

		$this->assertContains( 'nexcess-software-manager', $this->get_settings_submenu_slugs() );
	}

	/**
	 * @test
	 */
	public function it_should_not_render_when_a_higher_version_exists(): void {
		global $submenu;
		$submenu = [];

		set_current_screen( 'dashboard' );

		// In production the higher-version instance claims the action first.
		// Simulate that here so this instance defers correctly.
		do_action( 'lw-harbor/handled/admin_page' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		$this->page->maybe_register_page();

		$this->assertNotContains( 'nexcess-software-manager', $this->get_settings_submenu_slugs() );
	}

	/**
	 * @test
	 */
	public function it_should_not_render_when_page_already_registered(): void {
		global $submenu;
		$submenu = [];

		set_current_screen( 'dashboard' );

		do_action( 'lw-harbor/handled/admin_page' );

		$this->page->maybe_register_page();

		$this->assertNotContains( 'nexcess-software-manager', $this->get_settings_submenu_slugs() );
	}

	/**
	 * @test
	 */
	public function it_should_register_menu_page(): void {
		global $submenu;
		$submenu = [];

		set_current_screen( 'dashboard' );
		$this->page->maybe_register_page();

		$this->assertContains( 'nexcess-software-manager', $this->get_settings_submenu_slugs() );
	}

	/**
	 * @test
	 */
	public function it_should_only_register_page_once(): void {
		global $submenu;
		$submenu = [];

		set_current_screen( 'dashboard' );

		$page_a = $this->make_page();
		$page_b = $this->make_page();

		$page_a->maybe_register_page();
		$page_b->maybe_register_page();

		$slugs = array_filter(
			$this->get_settings_submenu_slugs(),
			static function ( $s ) {
				return $s === 'nexcess-software-manager';
			}
		);

		$this->assertCount( 1, $slugs );
	}

	/**
	 * @test
	 */
	public function it_should_hide_the_menu_item_when_filter_returns_true(): void {
		global $submenu;
		$submenu = [];

		set_current_screen( 'dashboard' );

		add_filter( 'lw-harbor/hide_menu_item', '__return_true' );

		try {
			$this->page->maybe_register_page();
		} finally {
			remove_filter( 'lw-harbor/hide_menu_item', '__return_true' );
		}

		$this->assertNotContains( 'nexcess-software-manager', $this->get_settings_submenu_slugs() );
	}

	/**
	 * @test
	 */
	public function it_should_keep_the_page_registered_when_menu_item_is_hidden(): void {
		global $submenu, $_registered_pages;
		$submenu           = [];
		$_registered_pages = [];

		set_current_screen( 'dashboard' );

		add_filter( 'lw-harbor/hide_menu_item', '__return_true' );

		try {
			$this->page->maybe_register_page();
		} finally {
			remove_filter( 'lw-harbor/hide_menu_item', '__return_true' );
		}

		// remove_submenu_page() only modifies $submenu; the slug remains in
		// $_registered_pages, which is what WordPress checks before serving
		// the page when the URL is visited directly.
		$hookname = get_plugin_page_hookname( Feature_Manager_Page::PAGE_SLUG, 'options-general.php' );
		$this->assertTrue( isset( $_registered_pages[ $hookname ] ) );
	}

	/**
	 * @test
	 */
	public function it_should_register_the_legacy_slug_as_a_hidden_page(): void {
		global $submenu, $_registered_pages;
		$submenu           = [];
		$_registered_pages = [];

		set_current_screen( 'dashboard' );
		$this->page->maybe_register_page();

		$this->assertNotContains( Feature_Manager_Page::PAGE_SLUG_LEGACY, $this->get_settings_submenu_slugs() );

		$legacy_hookname = get_plugin_page_hookname( Feature_Manager_Page::PAGE_SLUG_LEGACY, '' );
		$this->assertTrue( isset( $_registered_pages[ $legacy_hookname ] ) );
	}

	/**
	 * @test
	 */
	public function it_should_not_refresh_when_param_is_absent(): void {
		unset( $_GET['refresh'] );
		$_GET['page'] = Feature_Manager_Page::PAGE_SLUG;

		$refresh_called = false;
		$page           = $this->make_page(
			[
				'refresh_products' => static function () use ( &$refresh_called ) {
					$refresh_called = true;
				},
			]
		);

		$page->maybe_redirect_after_refresh();

		$this->assertFalse( $refresh_called );
	}

	/**
	 * @test
	 */
	public function it_should_not_refresh_when_param_is_not_auto(): void {
		$_GET['refresh'] = 'manual';
		$_GET['page']    = Feature_Manager_Page::PAGE_SLUG;

		$refresh_called = false;
		$page           = $this->make_page(
			[
				'refresh_products' => static function () use ( &$refresh_called ) {
					$refresh_called = true;
				},
			]
		);

		$page->maybe_redirect_after_refresh();

		$this->assertFalse( $refresh_called );
	}

	/**
	 * @test
	 */
	public function it_should_not_refresh_when_page_slug_does_not_match(): void {
		$_GET['refresh'] = 'auto';
		$_GET['page']    = 'some-other-page';

		$refresh_called = false;
		$page           = $this->make_page(
			[
				'refresh_products' => static function () use ( &$refresh_called ) {
					$refresh_called = true;
				},
			]
		);

		$page->maybe_redirect_after_refresh();

		$this->assertFalse( $refresh_called );
	}

	/**
	 * @test
	 */
	public function it_should_refresh_license_and_catalog_when_refresh_auto_is_present(): void {
		$_GET['refresh'] = 'auto';
		$_GET['page']    = Feature_Manager_Page::PAGE_SLUG;

		$license_refreshed = false;
		$catalog_refreshed = false;

		$page = $this->make_page(
			[
				'refresh_products' => static function () use ( &$license_refreshed ) {
					$license_refreshed = true;
				},
			],
			[
				'refresh' => static function () use ( &$catalog_refreshed ) {
					$catalog_refreshed = true;
				},
			]
		);

		$page->maybe_redirect_after_refresh();

		$this->assertTrue( $license_refreshed );
		$this->assertTrue( $catalog_refreshed );
	}

	/**
	 * @test
	 */
	public function it_should_refresh_license_and_catalog_on_the_legacy_page_slug(): void {
		$_GET['refresh'] = 'auto';
		$_GET['page']    = Feature_Manager_Page::PAGE_SLUG_LEGACY;

		$license_refreshed = false;
		$catalog_refreshed = false;

		$page = $this->make_page(
			[
				'refresh_products' => static function () use ( &$license_refreshed ) {
					$license_refreshed = true;
				},
			],
			[
				'refresh' => static function () use ( &$catalog_refreshed ) {
					$catalog_refreshed = true;
				},
			]
		);

		$page->maybe_redirect_after_refresh();

		$this->assertTrue( $license_refreshed );
		$this->assertTrue( $catalog_refreshed );
	}

	/**
	 * @test
	 */
	public function it_should_pass_site_domain_to_refresh_products(): void {
		$_GET['refresh'] = 'auto';
		$_GET['page']    = Feature_Manager_Page::PAGE_SLUG;

		$refreshed_with  = null;
		$site_data       = $this->makeEmpty( Data::class, [ 'get_domain' => 'mysite.com' ] ); // cspell:ignore mysite
		$license_manager = $this->makeEmpty(
			License_Manager::class,
			[
				'refresh_products' => static function ( string $domain ) use ( &$refreshed_with ) {
					$refreshed_with = $domain;
				},
			]
		);
		$catalog         = $this->makeEmpty( Catalog_Repository::class, [ 'refresh' => null ] );

		$page = new Feature_Manager_Page( $site_data, $license_manager, $catalog );
		$page->maybe_redirect_after_refresh();

		$this->assertSame( 'mysite.com', $refreshed_with );
	}
}
