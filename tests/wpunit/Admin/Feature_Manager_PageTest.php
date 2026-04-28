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

		$this->page = $this->make_page();
	}

	protected function tearDown(): void {
		if ( function_exists( 'uopz_allow_exit' ) ) {
			uopz_allow_exit( true );
		}

		unset( $_GET['refresh'], $_GET['page'] );

		parent::tearDown();
	}

	private function make_page( array $license_manager_overrides = [], array $catalog_overrides = [] ): Feature_Manager_Page {
		$site_data       = $this->makeEmpty( Data::class, [ 'get_domain' => 'example.com' ] );
		$license_manager = $this->makeEmpty( License_Manager::class, $license_manager_overrides );
		$catalog         = $this->makeEmpty( Catalog_Repository::class, $catalog_overrides );

		return new Feature_Manager_Page( $site_data, $license_manager, $catalog );
	}

	/**
	 * @test
	 */
	public function it_should_render_when_it_has_the_highest_version(): void {
		global $menu;
		$menu = [];

		set_current_screen( 'dashboard' );
		$this->page->maybe_register_page();

		$slugs = array_column( $menu, 2 );
		$this->assertContains( 'lw-software-manager', $slugs );
	}

	/**
	 * @test
	 */
	public function it_should_not_render_when_a_higher_version_exists(): void {
		global $menu;
		$menu = [];

		set_current_screen( 'dashboard' );

		// In production the higher-version instance claims the action first.
		// Simulate that here so this instance defers correctly.
		do_action( 'lw-harbor/handled/admin_page' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		$this->page->maybe_register_page();

		$slugs = array_column( $menu, 2 );
		$this->assertNotContains( 'lw-software-manager', $slugs );
	}

	/**
	 * @test
	 */
	public function it_should_not_render_when_page_already_registered(): void {
		global $menu;
		$menu = [];

		set_current_screen( 'dashboard' );

		do_action( 'lw-harbor/handled/admin_page' );

		$this->page->maybe_register_page();

		$slugs = array_column( $menu, 2 );
		$this->assertNotContains( 'lw-software-manager', $slugs );
	}

	/**
	 * @test
	 */
	public function it_should_register_menu_page(): void {
		global $menu;
		$menu = [];

		set_current_screen( 'dashboard' );
		$this->page->maybe_register_page();

		$slugs = array_column( $menu, 2 );
		$this->assertContains( 'lw-software-manager', $slugs );
	}

	/**
	 * @test
	 */
	public function it_should_only_register_page_once(): void {
		global $menu;
		$menu = [];

		set_current_screen( 'dashboard' );

		$page_a = $this->make_page();
		$page_b = $this->make_page();

		$page_a->maybe_register_page();
		$page_b->maybe_register_page();

		$slugs = array_filter(
			array_column( $menu, 2 ),
			static function ( $s ) {
				return $s === 'lw-software-manager';
			}
		);

		$this->assertCount( 1, $slugs );
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
		$catalog = $this->makeEmpty( Catalog_Repository::class, [ 'refresh' => null ] );

		$page = new Feature_Manager_Page( $site_data, $license_manager, $catalog );
		$page->maybe_redirect_after_refresh();

		$this->assertSame( 'mysite.com', $refreshed_with );
	}
}
