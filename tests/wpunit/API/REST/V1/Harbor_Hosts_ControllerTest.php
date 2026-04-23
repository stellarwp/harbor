<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\API\REST\V1;

use LiquidWeb\Harbor\API\REST\V1\Harbor_Hosts_Controller;
use LiquidWeb\Harbor\Tests\Traits\With_Uopz;
use LiquidWeb\Harbor\Tests\HarborTestCase;
use WP_REST_Request;
use WP_REST_Server;

final class Harbor_Hosts_ControllerTest extends HarborTestCase {

	use With_Uopz;

	private WP_REST_Server $server;

	protected function setUp(): void {
		parent::setUp();

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

		$controller = new Harbor_Hosts_Controller();
		$controller->register_routes();
	}

	protected function tearDown(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tearDown();
	}

	public function test_get_returns_200_for_admin(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$request  = new WP_REST_Request( 'GET', '/liquidweb/harbor/v1/hosts' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	public function test_get_returns_array(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$request  = new WP_REST_Request( 'GET', '/liquidweb/harbor/v1/hosts' );
		$response = $this->server->dispatch( $request );

		$this->assertIsArray( $response->get_data() );
	}

	public function test_get_requires_manage_options(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$request  = new WP_REST_Request( 'GET', '/liquidweb/harbor/v1/hosts' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_get_rejects_unauthenticated(): void {
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', '/liquidweb/harbor/v1/hosts' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	public function test_get_flattens_registry_into_basenames(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		// @phpstan-ignore function.internal
		$this->set_fn_return(
			'_lw_harbor_instance_registry',
			[
				'1.0.0' => [ 'give/give.php', 'kadence/kadence.php' ],
				'0.9.0' => [ 'stellar-wp/stellar-wp.php' ],
			]
		);

		$request  = new WP_REST_Request( 'GET', '/liquidweb/harbor/v1/hosts' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertContains( 'give/give.php', $data );
		$this->assertContains( 'kadence/kadence.php', $data );
		$this->assertContains( 'stellar-wp/stellar-wp.php', $data );
		$this->assertCount( 3, $data );
	}

	public function test_schema_is_array_type(): void {
		$controller = new Harbor_Hosts_Controller();
		$schema     = $controller->get_public_item_schema();

		$this->assertSame( 'array', $schema['type'] );
		$this->assertArrayHasKey( 'items', $schema );
		$this->assertSame( 'string', $schema['items']['type'] );
	}
}
