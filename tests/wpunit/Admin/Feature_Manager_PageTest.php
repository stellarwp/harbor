<?php declare( strict_types=1 );

namespace wpunit\Admin;

use LiquidWeb\Harbor\Admin\Feature_Manager_Page;
use LiquidWeb\Harbor\Licensing\License_Manager;
use LiquidWeb\Harbor\Portal\Activation_Url;
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

		return new Feature_Manager_Page( $site_data, $license_manager, $catalog, new Activation_Url( $site_data ) );
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

		$this->assertContains( 'lw-software-manager', $this->get_settings_submenu_slugs() );
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

		$this->assertNotContains( 'lw-software-manager', $this->get_settings_submenu_slugs() );
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

		$this->assertNotContains( 'lw-software-manager', $this->get_settings_submenu_slugs() );
	}

	/**
	 * @test
	 */
	public function it_should_register_menu_page(): void {
		global $submenu;
		$submenu = [];

		set_current_screen( 'dashboard' );
		$this->page->maybe_register_page();

		$this->assertContains( 'lw-software-manager', $this->get_settings_submenu_slugs() );
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
				return $s === 'lw-software-manager';
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

		$this->assertNotContains( 'lw-software-manager', $this->get_settings_submenu_slugs() );
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
}
