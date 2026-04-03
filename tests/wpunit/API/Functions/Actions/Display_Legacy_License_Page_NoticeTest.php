<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\API\Functions\Actions;

use LiquidWeb\Harbor\Admin\Feature_Manager_Page;
use LiquidWeb\Harbor\API\Functions\Actions\Display_Legacy_License_Page_Notice;
use LiquidWeb\Harbor\Tests\HarborTestCase;

/**
 * @since 1.0.0
 */
final class Display_Legacy_License_Page_NoticeTest extends HarborTestCase {

	private Display_Legacy_License_Page_Notice $action;

	protected function setUp(): void {
		parent::setUp();

		$this->action = new Display_Legacy_License_Page_Notice();
	}

	private function invoke( string $product_name = '' ): string {
		ob_start();
		( $this->action )( $product_name );
		return (string) ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// Generic (no product name)
	// -------------------------------------------------------------------------

	public function test_outputs_a_notice_without_product_name(): void {
		$output = $this->invoke();

		$this->assertNotEmpty( $output );
	}

	public function test_generic_notice_contains_info_class(): void {
		$output = $this->invoke();

		$this->assertStringContainsString( 'notice-info', $output );
	}

	public function test_generic_notice_contains_software_manager_link(): void {
		$expected_url = admin_url( 'admin.php?page=' . Feature_Manager_Page::PAGE_SLUG );
		$output       = $this->invoke();

		$this->assertStringContainsString( $expected_url, $output );
		$this->assertStringContainsString( 'Liquid Web Software Manager', $output );
	}

	public function test_generic_notice_contains_expected_messaging(): void {
		$output = $this->invoke();

		$this->assertStringContainsString( 'part of Liquid Web\'s software offerings', $output );
		$this->assertStringContainsString( 'managing legacy licenses from your previous account', $output );
		$this->assertStringContainsString( 'If you purchased a new plan through Liquid Web', $output );
	}

	public function test_generic_notice_does_not_contain_product_name(): void {
		$output = $this->invoke();

		$this->assertStringNotContainsString( 'GiveWP', $output );
	}

	// -------------------------------------------------------------------------
	// Product-specific (with product name)
	// -------------------------------------------------------------------------

	public function test_outputs_a_notice_with_product_name(): void {
		$output = $this->invoke( 'GiveWP' );

		$this->assertNotEmpty( $output );
	}

	public function test_product_notice_contains_info_class(): void {
		$output = $this->invoke( 'GiveWP' );

		$this->assertStringContainsString( 'notice-info', $output );
	}

	public function test_product_notice_contains_product_name(): void {
		$output = $this->invoke( 'GiveWP' );

		$this->assertStringContainsString( 'GiveWP', $output );
	}

	public function test_product_notice_contains_software_manager_link(): void {
		$expected_url = admin_url( 'admin.php?page=' . Feature_Manager_Page::PAGE_SLUG );
		$output       = $this->invoke( 'GiveWP' );

		$this->assertStringContainsString( $expected_url, $output );
		$this->assertStringContainsString( 'Liquid Web Software Manager', $output );
	}

	public function test_product_notice_contains_expected_messaging(): void {
		$output = $this->invoke( 'GiveWP' );

		$this->assertStringContainsString( 'GiveWP is now part of Liquid Web\'s software offerings', $output );
		$this->assertStringContainsString( 'managing legacy licenses from your previous GiveWP account', $output );
		$this->assertStringContainsString( 'If you purchased a new plan through Liquid Web', $output );
	}

	public function test_product_name_is_escaped_in_output(): void {
		$output = $this->invoke( '<script>alert(1)</script>' );

		$this->assertStringNotContainsString( '<script>', $output );
	}
}
