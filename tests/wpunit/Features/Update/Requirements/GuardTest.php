<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Features\Update\Requirements;

use LiquidWeb\Harbor\Features\Update\Requirements\Guard;
use LiquidWeb\Harbor\Features\Update\Requirements\Resolve_Headers;
use LiquidWeb\Harbor\Tests\HarborTestCase;
use stdClass;
use WP_Error;

final class GuardTest extends HarborTestCase {

	private const PLUGIN_FILE = 'kad-blocks-pro/kad-blocks-pro.php';

	private Guard $guard;

	protected function setUp(): void {
		parent::setUp();

		$this->guard = new Guard( new Resolve_Headers() );

		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );
	}

	protected function tearDown(): void {
		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );
		remove_all_actions( 'in_plugin_update_message-' . self::PLUGIN_FILE );

		parent::tearDown();
	}

	/**
	 * Tests that a Harbor plugin update whose requirements the site does not meet is refused with WordPress's error code.
	 *
	 * @dataProvider unmet_cases
	 *
	 * @param string $requires      The required WordPress version.
	 * @param string $requires_php  The required PHP version.
	 * @param string $expected_code The expected error code.
	 *
	 * @return void
	 */
	public function test_refuses_a_harbor_plugin_update_with_unmet_requirements( string $requires, string $requires_php, string $expected_code ): void {
		$this->set_plugin_entry( 'stellarwp/plugins/kad-blocks-pro', $requires, $requires_php );

		$result = $this->guard->filter_pre_download( false, 'https://example.com/package.zip', null, [ 'plugin' => self::PLUGIN_FILE ] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( $expected_code, $result->get_error_code() );
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public function unmet_cases(): array {
		return [
			'wordpress' => [ '99.0', '', 'incompatible_wp_required_version' ],
			'php'       => [ '', '99.0', 'incompatible_php_required_version' ],
			'both'      => [ '99.0', '99.0', 'incompatible_wp_required_version' ],
		];
	}

	/**
	 * Tests that a Harbor theme update whose requirements the site does not meet is refused.
	 *
	 * @return void
	 */
	public function test_refuses_a_harbor_theme_update_with_unmet_requirements(): void {
		$transient           = new stdClass();
		$transient->response = [
			'kadence-pro-theme' => [
				'id'           => 'stellarwp/themes/kadence-pro-theme',
				'theme'        => 'kadence-pro-theme',
				'new_version'  => '2.0.0',
				'requires_php' => '99.0',
			],
		];
		set_site_transient( 'update_themes', $transient );

		$result = $this->guard->filter_pre_download( false, 'https://example.com/package.zip', null, [ 'theme' => 'kadence-pro-theme' ] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'incompatible_php_required_version', $result->get_error_code() );
	}

	/**
	 * Tests that downloads continue for met requirements, other updaters' entries, installs and earlier replies.
	 *
	 * @return void
	 */
	public function test_passes_through_other_downloads(): void {
		$package = 'https://example.com/package.zip';

		$this->set_plugin_entry( 'stellarwp/plugins/kad-blocks-pro', '1.0', '1.0' );
		$this->assertFalse( $this->guard->filter_pre_download( false, $package, null, [ 'plugin' => self::PLUGIN_FILE ] ), 'Met requirements' );

		$this->set_plugin_entry( 'w.org/plugins/kadence-blocks', '99.0', '' );
		$this->assertFalse( $this->guard->filter_pre_download( false, $package, null, [ 'plugin' => self::PLUGIN_FILE ] ), 'WordPress.org entry' );

		$this->set_plugin_entry( 'stellarwp/plugins/kad-blocks-pro', '99.0', '' );
		$this->assertFalse(
			$this->guard->filter_pre_download(
				false,
				$package,
				null,
				[
					'type'   => 'plugin',
					'action' => 'install',
				] 
			),
			'Install' 
		);
		$this->assertFalse( $this->guard->filter_pre_download( false, $package, null, [ 'plugin' => 'other/other.php' ] ), 'Plugin without an entry' );
		$this->assertTrue( $this->guard->filter_pre_download( true, $package, null, [ 'plugin' => self::PLUGIN_FILE ] ), 'Earlier reply' );
	}

	/**
	 * Tests that the plugin-row message is added only for Harbor entries whose WordPress requirement is not met.
	 *
	 * @return void
	 */
	public function test_registers_the_update_message_only_for_unmet_wordpress_requirements(): void {
		$hook = 'in_plugin_update_message-' . self::PLUGIN_FILE;

		$this->set_plugin_entry( 'stellarwp/plugins/kad-blocks-pro', '', '99.0' );
		$this->guard->register_update_messages();
		$this->assertFalse( has_action( $hook, [ $this->guard, 'render_update_message' ] ), 'PHP requirement' );

		$this->set_plugin_entry( 'w.org/plugins/kadence-blocks', '99.0', '' );
		$this->guard->register_update_messages();
		$this->assertFalse( has_action( $hook, [ $this->guard, 'render_update_message' ] ), 'WordPress.org entry' );

		$this->set_plugin_entry( 'stellarwp/plugins/kad-blocks-pro', '99.0', '' );
		$this->guard->register_update_messages();
		$this->assertSame( 10, has_action( $hook, [ $this->guard, 'render_update_message' ] ), 'WordPress requirement' );
	}

	/**
	 * Tests that the plugin-row message names the required WordPress version and links to the WordPress update screen.
	 *
	 * @return void
	 */
	public function test_renders_the_update_message(): void {
		ob_start();
		$this->guard->render_update_message(
			[],
			(object) [
				'new_version' => '2.0.0',
				'requires'    => '99.0',
			]
		);
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'This update requires WordPress 99.0 or newer.', $output );
		$this->assertStringContainsString( esc_url( self_admin_url( 'update-core.php' ) ), $output );
	}

	/**
	 * Writes a single plugin entry to the update_plugins transient.
	 *
	 * @param string $id           The entry id.
	 * @param string $requires     The required WordPress version.
	 * @param string $requires_php The required PHP version.
	 *
	 * @return void
	 */
	private function set_plugin_entry( string $id, string $requires, string $requires_php ): void {
		$transient           = new stdClass();
		$transient->response = [
			self::PLUGIN_FILE => (object) [
				'id'           => $id,
				'plugin'       => self::PLUGIN_FILE,
				'new_version'  => '2.0.0',
				'requires'     => $requires,
				'requires_php' => $requires_php,
			],
		];
		set_site_transient( 'update_plugins', $transient );
	}
}
