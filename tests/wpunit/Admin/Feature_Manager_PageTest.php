<?php declare( strict_types=1 );

namespace wpunit\Admin;

use LiquidWeb\Harbor\Admin\Feature_Manager_Page;
use LiquidWeb\Harbor\Tests\HarborTestCase;

class Feature_Manager_PageTest extends HarborTestCase {

	/**
	 * @var Feature_Manager_Page
	 */
	private $page;

	protected function setUp(): void {
		parent::setUp();

		$this->page = new Feature_Manager_Page();
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
		$this->assertContains( 'lws-feature-manager', $slugs );
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
		do_action( 'stellarwp/uplink/handled/admin_page' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		$this->page->maybe_register_page();

		$slugs = array_column( $menu, 2 );
		$this->assertNotContains( 'lws-feature-manager', $slugs );
	}

	/**
	 * @test
	 */
	public function it_should_not_render_when_page_already_registered(): void {
		global $menu;
		$menu = [];

		set_current_screen( 'dashboard' );

		do_action( 'stellarwp/uplink/handled/admin_page' );

		$this->page->maybe_register_page();

		$slugs = array_column( $menu, 2 );
		$this->assertNotContains( 'lws-feature-manager', $slugs );
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
		$this->assertContains( 'lws-feature-manager', $slugs );
	}

	/**
	 * @test
	 */
	public function it_should_only_register_page_once(): void {
		global $menu;
		$menu = [];

		set_current_screen( 'dashboard' );

		$page_a = new Feature_Manager_Page();
		$page_b = new Feature_Manager_Page();

		$page_a->maybe_register_page();
		$page_b->maybe_register_page();

		$slugs = array_filter(
			array_column( $menu, 2 ),
			static function ( $s ) {
				return $s === 'lws-feature-manager';
			}
		);

		$this->assertCount( 1, $slugs );
	}
}
