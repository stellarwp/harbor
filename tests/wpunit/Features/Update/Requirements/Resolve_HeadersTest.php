<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Features\Update\Requirements;

use LiquidWeb\Harbor\Features\Types\Plugin;
use LiquidWeb\Harbor\Features\Types\Service;
use LiquidWeb\Harbor\Features\Types\Theme;
use LiquidWeb\Harbor\Features\Update\Requirements\Resolve_Headers;
use LiquidWeb\Harbor\Tests\HarborTestCase;
use stdClass;

final class Resolve_HeadersTest extends HarborTestCase {

	private const PLUGIN_FILE = 'kadence-blocks/kadence-blocks.php';

	private Resolve_Headers $resolve;

	protected function setUp(): void {
		parent::setUp();

		$this->resolve = new Resolve_Headers();

		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );
	}

	protected function tearDown(): void {
		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );

		parent::tearDown();
	}

	/**
	 * Tests that null is returned when the transient is missing.
	 *
	 * @return void
	 */
	public function test_returns_null_without_a_transient(): void {
		$this->assertNull( ( $this->resolve )( $this->plugin( '1.0.0' ) ) );
		$this->assertNull( ( $this->resolve )( $this->theme( 'kadence', '1.0.0' ) ) );
	}

	/**
	 * Tests that the `response` entry is returned, whether or not the site meets its requirements.
	 *
	 * @return void
	 */
	public function test_returns_the_response_entry(): void {
		$this->set_plugins_transient(
			[
				self::PLUGIN_FILE => (object) [
					'new_version' => '3.0.0',
					'requires'    => '99.0',
				],
			]
		);

		$update = ( $this->resolve )( $this->plugin( '1.0.0' ) );

		$this->assertNotNull( $update );
		$this->assertSame( '3.0.0', $update->get_version() );
		$this->assertSame( '99.0', $update->get_wp() );
	}

	/**
	 * Tests that a `response` entry without a version is ignored.
	 *
	 * @return void
	 */
	public function test_ignores_a_response_entry_without_a_version(): void {
		$this->set_plugins_transient( [ self::PLUGIN_FILE => (object) [ 'new_version' => '' ] ] );

		$this->assertNull( ( $this->resolve )( $this->plugin( '1.0.0' ) ) );
	}

	/**
	 * Tests that a newer `no_update` entry whose requirements the site does not meet is returned.
	 *
	 * This is how WordPress.org lists an update that needs a newer WordPress.
	 *
	 * @return void
	 */
	public function test_returns_a_newer_no_update_entry_with_unmet_requirements(): void {
		$this->set_plugins_transient(
			[],
			[
				self::PLUGIN_FILE => (object) [
					'id'          => 'w.org/plugins/kadence-blocks',
					'new_version' => '3.8.0',
					'requires'    => '99.0',
				],
			]
		);

		$update = ( $this->resolve )( $this->plugin( '3.0.0' ) );

		$this->assertNotNull( $update );
		$this->assertSame( '3.8.0', $update->get_version() );
		$this->assertFalse( $update->is_wp_met() );
	}

	/**
	 * Tests that `no_update` entries are ignored when they are not newer, the site meets them, or the installed version is unknown.
	 *
	 * @dataProvider ignored_no_update_cases
	 *
	 * @param string $new_version       The entry's version.
	 * @param string $requires          The entry's required WordPress version.
	 * @param string $installed_version The installed version.
	 *
	 * @return void
	 */
	public function test_ignores_no_update_entries( string $new_version, string $requires, string $installed_version ): void {
		$this->set_plugins_transient(
			[],
			[
				self::PLUGIN_FILE => (object) [
					'new_version' => $new_version,
					'requires'    => $requires,
				],
			]
		);

		$this->assertNull( ( $this->resolve )( $this->plugin( $installed_version ) ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public function ignored_no_update_cases(): array {
		return [
			'same version'      => [ '3.0.0', '99.0', '3.0.0' ],
			'requirements met'  => [ '3.8.0', '1.0', '3.0.0' ],
			'unknown installed' => [ '3.8.0', '99.0', '' ],
		];
	}

	/**
	 * Tests that theme entries (arrays) are read from both lists.
	 *
	 * @return void
	 */
	public function test_reads_theme_entries(): void {
		$transient            = new stdClass();
		$transient->response  = [
			'kadence' => [
				'new_version'  => '2.0.0',
				'requires_php' => '99.0',
			],
		];
		$transient->no_update = [
			'other' => [
				'new_version' => '5.0.0',
				'requires'    => '99.0',
			],
		];
		set_site_transient( 'update_themes', $transient );

		$response = ( $this->resolve )( $this->theme( 'kadence', '1.0.0' ) );
		$this->assertNotNull( $response );
		$this->assertSame( '99.0', $response->get_php() );

		$no_update = ( $this->resolve )( $this->theme( 'other', '4.0.0' ) );
		$this->assertNotNull( $no_update );
		$this->assertSame( '5.0.0', $no_update->get_version() );
	}

	/**
	 * Tests that from_response() and all_from_response() read only the `response` list.
	 *
	 * @return void
	 */
	public function test_reads_only_the_response_list(): void {
		$this->set_plugins_transient(
			[
				self::PLUGIN_FILE  => (object) [
					'new_version' => '3.0.0',
					'requires'    => '99.0',
				],
				'no-version/x.php' => (object) [ 'new_version' => '' ],
			],
			[
				'other/other.php' => (object) [
					'new_version' => '5.0.0',
					'requires'    => '99.0',
				],
			]
		);

		$all = ( new Resolve_Headers() )->all_from_response( Resolve_Headers::PLUGINS );

		$this->assertSame( [ self::PLUGIN_FILE ], array_keys( $all ) );
		$this->assertSame( '99.0', $all[ self::PLUGIN_FILE ]->get_wp() );
		$this->assertSame( '3.0.0', ( new Resolve_Headers() )->from_response( Resolve_Headers::PLUGINS, self::PLUGIN_FILE )->get_version() );
		$this->assertNull( ( new Resolve_Headers() )->from_response( Resolve_Headers::PLUGINS, 'other/other.php' ) );
		$this->assertSame( [], ( new Resolve_Headers() )->all_from_response( Resolve_Headers::THEMES ) );
	}

	/**
	 * Tests that from_catalog() reads the requirements feature resolution stored from the catalog.
	 *
	 * @return void
	 */
	public function test_from_catalog_reads_the_catalog_requirements(): void {
		$plugin = Plugin::from_array(
			[
				'slug'         => 'kadence-blocks',
				'name'         => 'Kadence Blocks',
				'plugin_file'  => self::PLUGIN_FILE,
				'version'      => '3.0.0',
				'requires_wp'  => '99.0',
				'requires_php' => '7.4',
			]
		);

		$headers = ( new Resolve_Headers() )->from_catalog( $plugin );

		$this->assertNotNull( $headers );
		$this->assertSame( '3.0.0', $headers->get_version() );
		$this->assertSame( '99.0', $headers->get_wp() );
		$this->assertSame( '7.4', $headers->get_php() );
		$this->assertFalse( $headers->is_wp_met() );
	}

	/**
	 * Tests that from_catalog() returns null for a service and for a feature without a catalog version.
	 *
	 * @return void
	 */
	public function test_from_catalog_returns_null_without_a_catalog_version(): void {
		$service = Service::from_array(
			[
				'slug' => 'promoter',
				'name' => 'Promoter',
			]
		);

		$this->assertNull( ( new Resolve_Headers() )->from_catalog( $service ) );
		$this->assertNull( ( new Resolve_Headers() )->from_catalog( $this->theme( 'kadence', '' ) ) );
	}

	/**
	 * Tests that a service feature has no pending update.
	 *
	 * @return void
	 */
	public function test_returns_null_for_a_service(): void {
		$service = Service::from_array(
			[
				'slug' => 'promoter',
				'name' => 'Promoter',
			]
		);

		$this->assertNull( ( $this->resolve )( $service ) );
	}

	/**
	 * Builds a plugin feature with the given installed version.
	 *
	 * @param string $installed_version The installed version.
	 *
	 * @return Plugin
	 */
	private function plugin( string $installed_version ): Plugin {
		return Plugin::from_array(
			[
				'slug'              => 'kadence-blocks',
				'name'              => 'Kadence Blocks',
				'plugin_file'       => self::PLUGIN_FILE,
				'installed_version' => $installed_version,
			]
		);
	}

	/**
	 * Builds a theme feature with the given installed version.
	 *
	 * @param string $slug              The theme stylesheet.
	 * @param string $installed_version The installed version.
	 *
	 * @return Theme
	 */
	private function theme( string $slug, string $installed_version ): Theme {
		return Theme::from_array(
			[
				'slug'              => $slug,
				'name'              => 'Theme',
				'installed_version' => $installed_version,
			]
		);
	}

	/**
	 * Writes the update_plugins transient.
	 *
	 * @param array<string, object> $response  The `response` list.
	 * @param array<string, object> $no_update The `no_update` list.
	 *
	 * @return void
	 */
	private function set_plugins_transient( array $response, array $no_update = [] ): void {
		$transient            = new stdClass();
		$transient->response  = $response;
		$transient->no_update = $no_update;

		set_site_transient( 'update_plugins', $transient );
	}
}
