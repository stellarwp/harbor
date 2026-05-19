<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Features\Update;

use LiquidWeb\Harbor\Features\Update\Resolve_Update_Data;
use LiquidWeb\Harbor\Features\Feature_Repository;
use LiquidWeb\Harbor\Features\Feature_Collection;
use LiquidWeb\Harbor\Features\Types\Theme;
use LiquidWeb\Harbor\Features\Update\Theme_Handler;
use LiquidWeb\Harbor\Legacy\License_Repository as Legacy_License_Repository;
use LiquidWeb\Harbor\Licensing\License_Manager;
use LiquidWeb\Harbor\Tests\HarborTestCase;
use stdClass;
use WP_Error;

final class Theme_HandlerTest extends HarborTestCase {

	/**
	 * The handler under test.
	 *
	 * @var Theme_Handler
	 */
	private Theme_Handler $handler;

	/**
	 * Absolute path to the temporary theme directory created for tests.
	 *
	 * @var string|null
	 */
	private $theme_dir;

	/**
	 * Sets up the handler with mocked dependencies before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$resolver           = $this->makeEmpty( Resolve_Update_Data::class );
		$feature_repository = $this->makeEmpty( Feature_Repository::class, [ 'get' => new Feature_Collection() ] );

		$this->handler = new Theme_Handler(
			$resolver,
			$feature_repository,
			$this->container->get( License_Manager::class ),
			new Legacy_License_Repository()
		);

		$this->create_test_theme();
	}

	/**
	 * Removes the test theme directory after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$this->remove_test_theme();
		remove_all_filters( 'lw-harbor/legacy_licenses' );
		parent::tearDown();
	}

	/**
	 * Creates a minimal theme directory so wp_get_themes() recognizes 'my-theme'
	 * as installed. WordPress reads theme headers from style.css on disk, so a
	 * real directory is required — there is no filter hook on wp_get_themes().
	 *
	 * @return void
	 */
	private function create_test_theme(): void {
		$this->theme_dir = get_theme_root() . '/my-theme';

		if ( ! is_dir( $this->theme_dir ) ) {
			mkdir( $this->theme_dir, 0755, true );
		}

		file_put_contents(
			$this->theme_dir . '/style.css',
			"/*\nTheme Name: My Theme\nVersion: 1.0.0\n*/\n"
		);

		// WordPress requires index.php to consider a theme valid (no errors).
		// wp_get_themes() with the default errors=>false skips themes with errors.
		file_put_contents( $this->theme_dir . '/index.php', "<?php // silence\n" );

		// Clear the static theme-directory cache so wp_get_themes() picks up the new entry.
		wp_clean_themes_cache();
	}

	/**
	 * Removes the theme directory created by create_test_theme().
	 *
	 * @return void
	 */
	private function remove_test_theme(): void {
		if ( $this->theme_dir === null ) {
			return;
		}

		$style = $this->theme_dir . '/style.css';
		$index = $this->theme_dir . '/index.php';

		if ( file_exists( $style ) ) {
			unlink( $style );
		}

		if ( file_exists( $index ) ) {
			unlink( $index );
		}

		if ( is_dir( $this->theme_dir ) ) {
			rmdir( $this->theme_dir );
		}

		wp_clean_themes_cache();

		$this->theme_dir = null;
	}

	/**
	 * Creates a Theme_Handler with a Theme feature in the Feature_Repository.
	 *
	 * @param mixed $check_updates_return The return value for Resolve_Update_Data::__invoke().
	 *
	 * @return Theme_Handler
	 */
	private function handler_with_feature( $check_updates_return ): Theme_Handler {
		$feature = new Theme(
			[
				'slug'         => 'my-theme',
				'product'      => 'test',
				'tier'         => 'basic',
				'name'         => 'My Theme',
				'description'  => 'A test theme.',
				'is_available' => true,
			]
		);

		$features = new Feature_Collection();
		$features->add( $feature );

		$resolver           = $this->makeEmpty( Resolve_Update_Data::class, [ '__invoke' => $check_updates_return ] );
		$feature_repository = $this->makeEmpty( Feature_Repository::class, [ 'get' => $features ] );

		$license_manager = $this->container->get( License_Manager::class );
		$license_manager->store_key( 'LWSW-test-handler-key' );

		return new Theme_Handler(
			$resolver,
			$feature_repository,
			$license_manager,
			new Legacy_License_Repository()
		);
	}

	// -------------------------------------------------------------------------
	// filter_themes_api
	// -------------------------------------------------------------------------

	/**
	 * Tests filter_themes_api passes through for a non-theme_information action.
	 *
	 * @return void
	 */
	public function test_it_passes_through_for_non_theme_information_action(): void {
		$result = $this->handler->filter_themes_api( false, 'hot_tags', new stdClass() );

		$this->assertFalse( $result );
	}

	/**
	 * Tests filter_themes_api passes through when slug is missing from args.
	 *
	 * @return void
	 */
	public function test_it_passes_through_for_missing_slug(): void {
		$args = new stdClass();

		$result = $this->handler->filter_themes_api( false, 'theme_information', $args );

		$this->assertFalse( $result );
	}

	/**
	 * Tests filter_themes_api passes through for null action.
	 *
	 * @return void
	 */
	public function test_it_passes_through_for_null_action(): void {
		$result = $this->handler->filter_themes_api( false, null, null );

		$this->assertFalse( $result );
	}

	/**
	 * Tests filter_themes_api passes through when args is not an object.
	 *
	 * @return void
	 */
	public function test_it_passes_through_for_non_object_args(): void {
		$result = $this->handler->filter_themes_api( false, 'theme_information', 'not-an-object' );

		$this->assertFalse( $result );
	}

	/**
	 * Tests filter_themes_api passes through when the slug is not a known feature.
	 *
	 * @return void
	 */
	public function test_it_passes_through_when_no_matching_feature(): void {
		$args       = new stdClass();
		$args->slug = 'my-theme';

		$result = $this->handler->filter_themes_api( false, 'theme_information', $args );

		$this->assertFalse( $result );
	}

	/**
	 * Tests filter_themes_api passes through for an unknown slug not in the response.
	 *
	 * @return void
	 */
	public function test_it_passes_through_for_unknown_slug(): void {
		$handler = $this->handler_with_feature( [ 'other-theme' => [ 'version' => '2.0.0' ] ] );

		$args       = new stdClass();
		$args->slug = 'unknown-theme';

		$result = $handler->filter_themes_api( false, 'theme_information', $args );

		$this->assertFalse( $result );
	}

	/**
	 * Tests filter_themes_api passes through when the update client returns a WP_Error.
	 *
	 * @return void
	 */
	public function test_it_passes_through_when_update_client_errors(): void {
		$handler = $this->handler_with_feature( new WP_Error( 'fail', 'Error' ) );

		$args       = new stdClass();
		$args->slug = 'my-theme';

		$result = $handler->filter_themes_api( false, 'theme_information', $args );

		$this->assertFalse( $result );
	}

	/**
	 * Tests filter_themes_api returns a WP-format object for a known feature.
	 *
	 * @return void
	 */
	public function test_it_returns_wp_format_for_feature(): void {
		$update_data = [
			'my-theme' => [
				'version' => '2.0.0',
				'package' => 'https://example.com/my-theme.zip',
				'name'    => 'My Theme',
			],
		];

		$handler = $this->handler_with_feature( $update_data );

		$args       = new stdClass();
		$args->slug = 'my-theme';

		$result = $handler->filter_themes_api( false, 'theme_information', $args );

		$this->assertInstanceOf( stdClass::class, $result );
		$this->assertSame( 'my-theme', $result->slug );
		$this->assertSame( '2.0.0', $result->version );
		$this->assertSame( 'My Theme', $result->name );
		$this->assertSame( 'https://example.com/my-theme.zip', $result->download_link );
	}

	// -------------------------------------------------------------------------
	// filter_update_check
	// -------------------------------------------------------------------------

	/**
	 * Tests filter_update_check passes through for non-object transient.
	 *
	 * @return void
	 */
	public function test_filter_update_check_passes_through_for_non_object(): void {
		$result = $this->handler->filter_update_check( false );

		$this->assertInstanceOf( stdClass::class, $result );
	}

	/**
	 * Tests filter_update_check returns the transient when there are no features.
	 *
	 * @return void
	 */
	public function test_filter_update_check_returns_transient_when_no_features(): void {
		$transient           = new stdClass();
		$transient->response = [];

		$result = $this->handler->filter_update_check( $transient );

		$this->assertSame( $transient, $result );
	}

	/**
	 * Tests filter_update_check handles an update client error gracefully.
	 *
	 * @return void
	 */
	public function test_filter_update_check_handles_client_error_gracefully(): void {
		$handler = $this->handler_with_feature( new WP_Error( 'fail', 'Error' ) );

		$transient           = new stdClass();
		$transient->response = [];

		$result = $handler->filter_update_check( $transient );

		$this->assertSame( $transient, $result );
	}

	/**
	 * Tests filter_update_check adds an update to transient->response when a newer version is available.
	 *
	 * @return void
	 */
	public function test_filter_update_check_adds_to_response_when_update_available(): void {
		$update_data = [
			'my-theme' => [
				'version'    => '2.0.0',
				'package'    => 'https://example.com/my-theme.zip',
				'has_update' => true,
			],
		];

		$handler = $this->handler_with_feature( $update_data );

		$transient = new stdClass();

		$result = $handler->filter_update_check( $transient );

		$this->assertObjectHasProperty( 'response', $result );
		$this->assertArrayHasKey( 'my-theme', $result->response );
		$this->assertIsArray( $result->response['my-theme'], 'Theme update transient entries should be arrays, not objects.' );
		$this->assertSame( '2.0.0', $result->response['my-theme']['new_version'] );
		$this->assertSame( 'my-theme', $result->response['my-theme']['theme'] );
	}

	/**
	 * Tests filter_update_check adds to transient->no_update when no newer version exists.
	 *
	 * @return void
	 */
	public function test_filter_update_check_adds_to_no_update_when_no_newer_version(): void {
		$update_data = [
			'my-theme' => [
				'version' => '',
				'package' => 'https://example.com/my-theme.zip',
			],
		];

		$handler = $this->handler_with_feature( $update_data );

		$transient = new stdClass();

		$result = $handler->filter_update_check( $transient );

		$this->assertObjectHasProperty( 'no_update', $result );
		$this->assertArrayHasKey( 'my-theme', $result->no_update );
		$this->assertIsArray( $result->no_update['my-theme'], 'Theme no_update transient entries should be arrays, not objects.' );
		$this->assertSame( '', $result->no_update['my-theme']['new_version'] );
	}

	/**
	 * Tests filter_update_check preserves an existing update from another system
	 * when our data says no newer version is available.
	 *
	 * @return void
	 */
	public function test_filter_update_check_preserves_existing_update_from_other_system(): void {
		$update_data = [
			'my-theme' => [
				'version' => '',
				'package' => 'https://example.com/my-theme.zip',
			],
		];

		$handler = $this->handler_with_feature( $update_data );

		$existing_update = [
			'theme'       => 'my-theme',
			'new_version' => '1.5.0',
			'package'     => 'https://legacy.example.com/my-theme.zip',
		];

		$transient                       = new stdClass();
		$transient->response             = [];
		$transient->response['my-theme'] = $existing_update;
		$transient->no_update            = [];

		$result = $handler->filter_update_check( $transient );

		$this->assertArrayHasKey( 'my-theme', $result->response, 'Existing update from another system should be preserved in response.' );
		$this->assertSame( $existing_update, $result->response['my-theme'], 'The existing update array should not be modified.' );
		$this->assertArrayNotHasKey( 'my-theme', $result->no_update, 'Theme should not appear in no_update when it has an existing update.' );
	}

	/**
	 * Tests filter_update_check does not inject update data for a theme that is
	 * not installed on the site.
	 *
	 * @return void
	 */
	public function test_filter_update_check_skips_uninstalled_theme(): void {
		$update_data = [
			'my-theme' => [
				'version' => '2.0.0',
				'package' => 'https://example.com/my-theme.zip',
			],
		];

		$handler = $this->handler_with_feature( $update_data );

		// Remove the theme from disk so wp_get_themes() no longer reports it as installed.
		$this->remove_test_theme();

		$transient            = new stdClass();
		$transient->response  = [];
		$transient->no_update = [];

		$result = $handler->filter_update_check( $transient );

		$this->assertArrayNotHasKey( 'my-theme', $result->response, 'Uninstalled theme must not appear in response.' );
		$this->assertArrayNotHasKey( 'my-theme', $result->no_update, 'Uninstalled theme must not appear in no_update.' );
	}

	/**
	 * Registers a single active legacy license entry for a given slug.
	 *
	 * @param string $slug The slug to report.
	 * @param string $key  The license key value.
	 *
	 * @return void
	 */
	private function register_legacy_license( string $slug, string $key = 'legacy-key-123', bool $use_for_updates = true ): void {
		add_filter(
			'lw-harbor/legacy_licenses',
			static function ( array $licenses ) use ( $slug, $key, $use_for_updates ) {
				$licenses[] = [
					'key'             => $key,
					'slug'            => $slug,
					'name'            => 'Legacy ' . $slug,
					'product'         => 'legacy-product',
					'is_active'       => true,
					'use_for_updates' => $use_for_updates,
					'page_url'        => 'https://example.com/manage',
					'expires_at'      => '',
				];

				return $licenses;
			}
		);
	}

	/**
	 * Builds a Theme_Handler that has NO Unified key but DOES expose the
	 * given feature through the Feature_Repository.
	 *
	 * @param array<string, mixed>|WP_Error $check_updates_return The Resolve_Update_Data return value.
	 *
	 * @return Theme_Handler
	 */
	private function handler_with_feature_and_no_unified_key( $check_updates_return ): Theme_Handler {
		$feature = new Theme(
			[
				'slug'         => 'my-theme',
				'product'      => 'test',
				'tier'         => 'basic',
				'name'         => 'My Theme',
				'description'  => 'A test theme.',
				'is_available' => true,
			]
		);

		$features = new Feature_Collection();
		$features->add( $feature );

		$resolver           = $this->makeEmpty( Resolve_Update_Data::class, [ '__invoke' => $check_updates_return ] );
		$feature_repository = $this->makeEmpty( Feature_Repository::class, [ 'get' => $features ] );

		$license_manager = $this->container->get( License_Manager::class );
		$license_manager->delete_key();

		return new Theme_Handler(
			$resolver,
			$feature_repository,
			$license_manager,
			new Legacy_License_Repository()
		);
	}

	/**
	 * Tests filter_themes_api proceeds past the early-return guard when no
	 * Unified key is stored but at least one legacy license entry exists.
	 *
	 * @return void
	 */
	public function test_filter_themes_api_proceeds_when_only_legacy_license_present(): void {
		$this->register_legacy_license( 'my-theme' );

		$update_data = [
			'my-theme' => [
				'version' => '2.0.0',
				'package' => 'https://example.com/my-theme.zip',
				'name'    => 'My Theme',
			],
		];

		$handler = $this->handler_with_feature_and_no_unified_key( $update_data );

		$args       = new stdClass();
		$args->slug = 'my-theme';

		$result = $handler->filter_themes_api( false, 'theme_information', $args );

		$this->assertInstanceOf( stdClass::class, $result, 'Legacy-only state should let themes_api populate a response.' );
		$this->assertSame( 'my-theme', $result->slug );
		$this->assertSame( '2.0.0', $result->version );
	}

	/**
	 * Tests filter_update_check proceeds past the early-return guard when no
	 * Unified key is stored but at least one legacy license entry exists.
	 *
	 * @return void
	 */
	public function test_filter_update_check_proceeds_when_only_legacy_license_present(): void {
		$this->register_legacy_license( 'my-theme' );

		$update_data = [
			'my-theme' => [
				'version'    => '2.0.0',
				'package'    => 'https://example.com/my-theme.zip',
				'has_update' => true,
			],
		];

		$handler = $this->handler_with_feature_and_no_unified_key( $update_data );

		$result = $handler->filter_update_check( new stdClass() );

		$this->assertObjectHasProperty( 'response', $result );
		$this->assertArrayHasKey( 'my-theme', $result->response );
		$this->assertSame( '2.0.0', $result->response['my-theme']['new_version'] );
	}

	/**
	 * Tests filter_update_check short-circuits when neither a Unified key nor
	 * any legacy entries exist, leaving the transient untouched.
	 *
	 * @return void
	 */
	public function test_filter_update_check_returns_transient_when_no_unified_key_and_no_legacy(): void {
		$transient           = new stdClass();
		$transient->response = [];

		$result = $this->handler->filter_update_check( $transient );

		$this->assertSame( $transient, $result );
	}

	/**
	 * Tests filter_update_check short-circuits when no Unified key is stored and
	 * the only legacy entries present have not opted into updates.
	 *
	 * @return void
	 */
	public function test_filter_update_check_returns_transient_when_legacy_has_not_opted_in(): void {
		$this->register_legacy_license( 'my-theme', 'legacy-key-123', false );

		$update_data = [
			'my-theme' => [
				'version'    => '2.0.0',
				'package'    => 'https://example.com/my-theme.zip',
				'has_update' => true,
			],
		];

		$handler = $this->handler_with_feature_and_no_unified_key( $update_data );

		$transient = new stdClass();

		$result = $handler->filter_update_check( $transient );

		$this->assertObjectNotHasProperty( 'response', $result, 'Handler should bail before populating response when only opt-out legacy entries exist.' );
	}
}
