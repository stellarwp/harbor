<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Portal;

use LiquidWeb\Harbor\Licensing\License_Manager;
use LiquidWeb\Harbor\Portal\Activation_Return;
use LiquidWeb\Harbor\Portal\Activation_Url;
use LiquidWeb\Harbor\Portal\Catalog_Repository;
use LiquidWeb\Harbor\Site\Data;
use LiquidWeb\Harbor\Tests\HarborTestCase;
use LiquidWeb\Harbor\Tests\TestException;
use LiquidWeb\Harbor\Tests\Traits\With_Uopz;
use LiquidWeb\Harbor\Utils\Version;

final class Activation_ReturnTest extends HarborTestCase {

	use With_Uopz;

	/**
	 * Message carried by the exception that stands in for the success path's exit().
	 *
	 * @var string
	 */
	private const REDIRECT_REACHED = 'Reached the redirect on the success path.';

	/**
	 * @var int
	 */
	private $user_id;

	/**
	 * @var string|null
	 */
	private $original_request_uri;

	protected function setUp(): void {
		parent::setUp();

		$this->user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->user_id );

		// Always act as leader unless a test says otherwise.
		$this->set_class_fn_return( Version::class, 'should_handle', true );

		$this->original_request_uri = $_SERVER['REQUEST_URI'] ?? null;
		$_SERVER['REQUEST_URI']     = '/wp-admin/admin.php?page=some-plugin-page&' . Activation_Url::RETURN_PARAM . '=1';
	}

	protected function tearDown(): void {
		// Restore rather than unset: the test bootstrap reads this after teardown.
		if ( $this->original_request_uri === null ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->original_request_uri;
		}
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * Builds a handler whose collaborators record whether they were called.
	 *
	 * @param bool|null   $license_refreshed Set to true when the license refresh runs.
	 * @param bool|null   $catalog_refreshed Set to true when the catalog refresh runs.
	 * @param string|null $refreshed_with    Receives the domain passed to the license refresh.
	 * @param string      $domain            The domain the site reports.
	 *
	 * @return Activation_Return
	 */
	private function make_handler( &$license_refreshed, &$catalog_refreshed, &$refreshed_with, string $domain = 'site.example.com' ): Activation_Return {
		$license_manager = $this->makeEmpty(
			License_Manager::class,
			[
				'refresh_products' => static function ( string $passed ) use ( &$license_refreshed, &$refreshed_with ) {
					$license_refreshed = true;
					$refreshed_with    = $passed;
				},
			]
		);

		$catalog = $this->makeEmpty(
			Catalog_Repository::class,
			[
				'refresh' => static function () use ( &$catalog_refreshed ) {
					$catalog_refreshed = true;
				},
			]
		);

		$site_data = $this->makeEmpty( Data::class, [ 'get_domain' => $domain ] );

		return new Activation_Return( $license_manager, $catalog, $site_data );
	}

	/**
	 * Stops execution where the success path would have called exit(), by making
	 * the redirect immediately before it throw.
	 *
	 * Suppressing exit() itself lets a failing test carry on past the point it
	 * should have stopped, which can leave the failure unreported, so the test
	 * expects the exception rather than the exit.
	 *
	 * @return void
	 */
	private function stop_at_the_redirect(): void {
		// The message is captured rather than read from the constant inside the
		// closure: uopz runs it with no class scope, so self:: is unavailable.
		$message = self::REDIRECT_REACHED;

		$this->set_fn_return(
			'wp_safe_redirect',
			static function ( $location = null ) use ( $message ) {
				throw new TestException( $message );
			},
			true
		);

		$this->expectException( TestException::class );
		$this->expectExceptionMessage( $message );
	}

	/**
	 * Tests that an ordinary admin request is left alone. The handler runs on
	 * every admin_init, so it must cost nothing when the user is not returning
	 * from the portal.
	 *
	 * @return void
	 */
	public function test_does_not_refresh_when_the_param_is_absent(): void {
		unset( $_GET[ Activation_Url::RETURN_PARAM ] );

		$license = false;
		$catalog = false;
		$domain  = null;

		$this->make_handler( $license, $catalog, $domain )->maybe_refresh();

		$this->assertFalse( $license );
		$this->assertFalse( $catalog );
	}

	/**
	 * Tests that the refresh runs when the user returns from the portal.
	 *
	 * @return void
	 */
	public function test_refreshes_license_and_catalog_on_return(): void {
		$_GET[ Activation_Url::RETURN_PARAM ] = '1';

		$license = false;
		$catalog = false;
		$domain  = null;

		$handler = $this->make_handler( $license, $catalog, $domain );

		$this->stop_at_the_redirect();

		try {
			$handler->maybe_refresh();
		} catch ( TestException $e ) {
			$this->assertTrue( $license );
			$this->assertTrue( $catalog );

			throw $e;
		}
	}

	/**
	 * Tests that the site's own domain is what gets refreshed.
	 *
	 * @return void
	 */
	public function test_passes_the_site_domain_to_the_license_refresh(): void {
		$_GET[ Activation_Url::RETURN_PARAM ] = '1';

		$license = false;
		$catalog = false;
		$domain  = null;

		$handler = $this->make_handler( $license, $catalog, $domain, 'other-site.example.com' );

		$this->stop_at_the_redirect();

		try {
			$handler->maybe_refresh();
		} catch ( TestException $e ) {
			$this->assertSame( 'other-site.example.com', $domain );

			throw $e;
		}
	}

	/**
	 * Tests that a user without the managing capability cannot trigger the
	 * refresh. The param rides on a URL owned by the calling plugin, so it can
	 * land on a screen that does not gate on this capability itself.
	 *
	 * @return void
	 */
	public function test_does_not_refresh_without_the_required_capability(): void {
		$_GET[ Activation_Url::RETURN_PARAM ] = '1';

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$license = false;
		$catalog = false;
		$domain  = null;

		$this->make_handler( $license, $catalog, $domain )->maybe_refresh();

		$this->assertFalse( $license );
		$this->assertFalse( $catalog );
	}

	/**
	 * Tests that only one Harbor instance refreshes. Every active copy runs
	 * this on admin_init, so without the leader gate a site with four Liquid
	 * Web plugins would make four identical API calls per return trip.
	 *
	 * @return void
	 */
	public function test_does_not_refresh_when_not_the_version_leader(): void {
		$_GET[ Activation_Url::RETURN_PARAM ] = '1';

		$this->set_class_fn_return( Version::class, 'should_handle', false );

		$license = false;
		$catalog = false;
		$domain  = null;

		$this->make_handler( $license, $catalog, $domain )->maybe_refresh();

		$this->assertFalse( $license );
		$this->assertFalse( $catalog );
	}
}
