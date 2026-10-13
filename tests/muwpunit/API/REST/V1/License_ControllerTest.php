<?php declare( strict_types=1 );

namespace muwpunit\API\REST\V1;

use LiquidWeb\Harbor\API\REST\V1\License_Controller;
use LiquidWeb\Harbor\Licensing\License_Manager;
use LiquidWeb\Harbor\Licensing\Registry\Product_Registry;
use LiquidWeb\Harbor\Licensing\Repositories\License_Repository;
use LiquidWeb\Harbor\Site\Data;
use LiquidWeb\Harbor\Tests\HarborTestCase;
use LiquidWeb\Harbor\Tests\Licensing\Fixture_Client;
use LiquidWeb\Harbor\Tests\Traits\With_Uopz;
use WP_REST_Request;
use WP_REST_Server;

/**
 * The network-level key applies to every site, so changing it needs network authority.
 *
 * @since 2.0.2
 */
final class License_ControllerTest extends HarborTestCase {

	use With_Uopz;

	private WP_REST_Server $server;

	protected function setUp(): void {
		parent::setUp();

		delete_network_option( null, License_Repository::KEY_OPTION_NAME );
		delete_option( License_Repository::KEY_OPTION_NAME );
		delete_option( License_Repository::PRODUCTS_STATE_OPTION_NAME );
		delete_option( License_Repository::VALIDATION_STATE_OPTION_NAME );

		$manager = new License_Manager(
			new License_Repository(),
			new Product_Registry(),
			new Fixture_Client( codecept_data_dir( 'licensing' ) )
		);

		/** @var WP_REST_Server $wp_rest_server */
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;

		// Allow registering routes outside of the rest_api_init hook.
		$this->set_fn_return(
			'did_action',
			function ( $hook_name ) {
				if ( $hook_name !== 'rest_api_init' ) {
					return \did_action( $hook_name );
				}
				return true;
			},
			true
		);

		( new License_Controller( $manager, new Data() ) )->register_routes();
	}

	protected function tearDown(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		delete_network_option( null, License_Repository::KEY_OPTION_NAME );
		delete_option( License_Repository::KEY_OPTION_NAME );
		delete_option( License_Repository::PRODUCTS_STATE_OPTION_NAME );
		delete_option( License_Repository::VALIDATION_STATE_OPTION_NAME );

		parent::tearDown();
	}

	public function test_site_admin_cannot_store_the_network_key(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$request = new WP_REST_Request( 'POST', '/liquidweb/harbor/v1/license' );
		$request->set_param( 'key', 'LWSW-UNIFIED-PRO-2026' );
		$request->set_param( 'network', true );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
		$this->assertEmpty( get_network_option( null, License_Repository::KEY_OPTION_NAME ) );
	}

	public function test_site_admin_cannot_delete_the_network_key(): void {
		update_network_option( null, License_Repository::KEY_OPTION_NAME, 'LWSW-UNIFIED-PRO-2026' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$request = new WP_REST_Request( 'DELETE', '/liquidweb/harbor/v1/license' );
		$request->set_param( 'network', true );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'LWSW-UNIFIED-PRO-2026', get_network_option( null, License_Repository::KEY_OPTION_NAME ) );
	}

	public function test_site_admin_can_still_store_a_site_key(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$request = new WP_REST_Request( 'POST', '/liquidweb/harbor/v1/license' );
		$request->set_param( 'key', 'LWSW-UNIFIED-PRO-2026' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'LWSW-UNIFIED-PRO-2026', get_option( License_Repository::KEY_OPTION_NAME ) );
	}

	public function test_super_admin_can_store_and_delete_the_network_key(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		grant_super_admin( $user_id );
		wp_set_current_user( $user_id );

		$store = new WP_REST_Request( 'POST', '/liquidweb/harbor/v1/license' );
		$store->set_param( 'key', 'LWSW-UNIFIED-PRO-2026' );
		$store->set_param( 'network', true );

		$this->assertSame( 200, $this->server->dispatch( $store )->get_status() );
		$this->assertSame( 'LWSW-UNIFIED-PRO-2026', get_network_option( null, License_Repository::KEY_OPTION_NAME ) );

		$delete = new WP_REST_Request( 'DELETE', '/liquidweb/harbor/v1/license' );
		$delete->set_param( 'network', true );

		$this->assertSame( 204, $this->server->dispatch( $delete )->get_status() );
		$this->assertEmpty( get_network_option( null, License_Repository::KEY_OPTION_NAME ) );
	}
}
