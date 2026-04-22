<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Features;

use LiquidWeb\Harbor\Features\Feature_Resource;
use LiquidWeb\Harbor\Features\Types\Plugin;
use LiquidWeb\Harbor\Features\Types\Theme;
use LiquidWeb\Harbor\Tests\HarborTestCase;

final class Feature_ResourceTest extends HarborTestCase {

	/**
	 * Clears update transients before and after each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );
	}

	public function tearDown(): void {
		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Constructor / from_feature() — non-installable feature
	// -------------------------------------------------------------------------

	/**
	 * get_update_version() returns null for an unknown feature type (no transient read).
	 *
	 * @return void
	 */
	public function test_update_version_is_null_when_directly_constructed_with_null(): void {
		$plugin   = $this->make_plugin();
		$resource = new Feature_Resource( $plugin, null );

		$this->assertNull( $resource->get_update_version() );
	}

	/**
	 * get_feature() returns the decorated feature.
	 *
	 * @return void
	 */
	public function test_get_feature_returns_decorated_feature(): void {
		$plugin   = $this->make_plugin();
		$resource = new Feature_Resource( $plugin, null );

		$this->assertSame( $plugin, $resource->get_feature() );
	}

	// -------------------------------------------------------------------------
	// Plugin — no transient
	// -------------------------------------------------------------------------

	/**
	 * update_version is null when the update_plugins transient is absent.
	 *
	 * @return void
	 */
	public function test_plugin_update_version_null_when_transient_absent(): void {
		$resource = Feature_Resource::from_feature( $this->make_plugin() );

		$this->assertNull( $resource->get_update_version() );
	}

	/**
	 * update_version is null when the plugin is not in the transient response.
	 *
	 * @return void
	 */
	public function test_plugin_update_version_null_when_not_in_transient(): void {
		$this->set_plugin_transient( 'other-plugin/other-plugin.php', '2.0.0' );

		$resource = Feature_Resource::from_feature( $this->make_plugin() );

		$this->assertNull( $resource->get_update_version() );
	}

	// -------------------------------------------------------------------------
	// Plugin — transient present
	// -------------------------------------------------------------------------

	/**
	 * update_version is returned from the update_plugins transient.
	 *
	 * @return void
	 */
	public function test_plugin_update_version_from_transient(): void {
		$this->set_plugin_transient( 'stellar-export/stellar-export.php', '2.1.0' );

		$resource = Feature_Resource::from_feature( $this->make_plugin() );

		$this->assertSame( '2.1.0', $resource->get_update_version() );
	}

	// -------------------------------------------------------------------------
	// Theme — no transient
	// -------------------------------------------------------------------------

	/**
	 * update_version is null when the update_themes transient is absent.
	 *
	 * @return void
	 */
	public function test_theme_update_version_null_when_transient_absent(): void {
		$resource = Feature_Resource::from_feature( $this->make_theme() );

		$this->assertNull( $resource->get_update_version() );
	}

	/**
	 * update_version is null when the theme is not in the transient response.
	 *
	 * @return void
	 */
	public function test_theme_update_version_null_when_not_in_transient(): void {
		$this->set_theme_transient( 'other-theme', '3.0.0' );

		$resource = Feature_Resource::from_feature( $this->make_theme() );

		$this->assertNull( $resource->get_update_version() );
	}

	// -------------------------------------------------------------------------
	// Theme — transient present
	// -------------------------------------------------------------------------

	/**
	 * update_version is returned from the update_themes transient.
	 *
	 * @return void
	 */
	public function test_theme_update_version_from_transient(): void {
		$this->set_theme_transient( 'kadence', '3.2.0' );

		$resource = Feature_Resource::from_feature( $this->make_theme() );

		$this->assertSame( '3.2.0', $resource->get_update_version() );
	}

	// -------------------------------------------------------------------------
	// to_array()
	// -------------------------------------------------------------------------

	/**
	 * to_array() includes is_harbor_host as true when the plugin file matches the registered host.
	 *
	 * harbor/bootstrap-plugin.php is the active test plugin (wpunit.suite.dist.yml). During
	 * bootstrap, Config::detect_plugin_file() resolves to it because the test Container class
	 * file lives inside the harbor/ plugin directory. The instance registry is populated from
	 * bootstrap-plugin.php during plugins_loaded, before wp_loaded fires.
	 *
	 * @return void
	 */
	public function test_to_array_includes_is_harbor_host_true_for_registered_host(): void {
		$resource = Feature_Resource::from_feature( $this->make_plugin( 'harbor/bootstrap-plugin.php' ) );
		$data     = $resource->to_array();

		$this->assertArrayHasKey( 'is_harbor_host', $data );
		$this->assertTrue( $data['is_harbor_host'] );
	}

	/**
	 * to_array() includes is_harbor_host as false when the plugin file is not in the instance registry.
	 *
	 * @return void
	 */
	public function test_to_array_includes_is_harbor_host_false_when_not_registered(): void {
		$resource = Feature_Resource::from_feature( $this->make_plugin() );
		$data     = $resource->to_array();

		$this->assertArrayHasKey( 'is_harbor_host', $data );
		$this->assertFalse( $data['is_harbor_host'] );
	}

	/**
	 * to_array() does not include is_harbor_host for themes.
	 *
	 * @return void
	 */
	public function test_to_array_does_not_include_is_harbor_host_for_themes(): void {
		$resource = Feature_Resource::from_feature( $this->make_theme() );
		$data     = $resource->to_array();

		$this->assertArrayNotHasKey( 'is_harbor_host', $data );
	}

	/**
	 * to_array() includes update_version merged with all feature attributes.
	 *
	 * @return void
	 */
	public function test_to_array_includes_update_version(): void {
		$this->set_plugin_transient( 'stellar-export/stellar-export.php', '2.5.0' );

		$resource = Feature_Resource::from_feature( $this->make_plugin() );
		$data     = $resource->to_array();

		$this->assertArrayHasKey( 'update_version', $data );
		$this->assertSame( '2.5.0', $data['update_version'] );
		$this->assertArrayHasKey( 'slug', $data );
		$this->assertSame( 'stellar-export', $data['slug'] );
	}

	/**
	 * to_array() includes update_version as null when no update is pending.
	 *
	 * @return void
	 */
	public function test_to_array_includes_null_update_version_when_no_update(): void {
		$resource = Feature_Resource::from_feature( $this->make_plugin() );
		$data     = $resource->to_array();

		$this->assertArrayHasKey( 'update_version', $data );
		$this->assertNull( $data['update_version'] );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * @param string $plugin_file Plugin file path relative to the plugins directory.
	 *
	 * @return Plugin
	 */
	private function make_plugin( string $plugin_file = 'stellar-export/stellar-export.php' ): Plugin {
		return Plugin::from_array(
			[
				'slug'         => 'stellar-export',
				'product'      => 'LearnDash',
				'tier'         => 'Tier 1',
				'name'         => 'Stellar Export',
				'plugin_file'  => $plugin_file,
				'is_available' => true,
			]
		);
	}

	/**
	 * @return Theme
	 */
	private function make_theme(): Theme {
		return Theme::from_array(
			[
				'slug'         => 'kadence',
				'product'      => 'Kadence',
				'tier'         => 'Tier 1',
				'name'         => 'Kadence Theme',
				'is_available' => true,
			]
		);
	}

	/**
	 * Writes an entry into the update_plugins site transient.
	 *
	 * @param string $plugin_file The plugin file key.
	 * @param string $version     The new version string.
	 *
	 * @return void
	 */
	private function set_plugin_transient( string $plugin_file, string $version ): void {
		$transient = get_site_transient( 'update_plugins' );

		if ( ! is_object( $transient ) ) {
			$transient           = new \stdClass();
			$transient->response = [];
		}

		$item                                = new \stdClass();
		$item->new_version                   = $version;
		$transient->response[ $plugin_file ] = $item;

		set_site_transient( 'update_plugins', $transient );
	}

	/**
	 * Writes an entry into the update_themes site transient.
	 *
	 * @param string $slug    The theme slug.
	 * @param string $version The new version string.
	 *
	 * @return void
	 */
	private function set_theme_transient( string $slug, string $version ): void {
		$transient = get_site_transient( 'update_themes' );

		if ( ! is_object( $transient ) ) {
			$transient           = new \stdClass();
			$transient->response = [];
		}

		$transient->response[ $slug ] = [ 'new_version' => $version ];

		set_site_transient( 'update_themes', $transient );
	}
}
