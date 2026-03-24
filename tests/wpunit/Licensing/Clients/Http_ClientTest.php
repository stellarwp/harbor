<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Licensing\Clients;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use LiquidWeb\Harbor\Licensing\Clients\Http_Client;
use LiquidWeb\Harbor\Licensing\Error_Code;
use LiquidWeb\Harbor\Licensing\Results\Product_Entry;
use LiquidWeb\Harbor\Tests\Http\Mock_Client;
use LiquidWeb\Harbor\Tests\HarborTestCase;
use WP_Error;

final class Http_ClientTest extends HarborTestCase {

	private Mock_Client $mock;
	private Psr17Factory $factory;
	private Http_Client $client;

	protected function setUp(): void {
		parent::setUp();

		$this->mock    = new Mock_Client();
		$this->factory = new Psr17Factory();
		$this->client  = new Http_Client( $this->mock, $this->factory, $this->factory, 'https://api.example.com' );
	}

	// -------------------------------------------------------------------------
	// get_products()
	// -------------------------------------------------------------------------

	public function test_get_products_returns_array_on_success(): void {
		$this->mock->add_response( new Response( 200, [], $this->build_products_json() ) );

		$result = $this->client->get_products( 'LWSW-TEST-KEY', 'example.com' );

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );

		foreach ( $result as $entry ) {
			$this->assertInstanceOf( Product_Entry::class, $entry );
		}
	}

	public function test_get_products_sends_correct_request(): void {
		$this->mock->add_response( new Response( 200, [], $this->build_products_json() ) );

		$this->client->get_products( 'LWSW-TEST-KEY', 'example.com' );

		$request = $this->mock->get_last_request();

		$this->assertSame( 'GET', $request->getMethod() );

		$uri = (string) $request->getUri();

		$this->assertStringContainsString( 'https://api.example.com/stellarwp/v4/products?', $uri );
		$this->assertStringContainsString( 'key=LWSW-TEST-KEY', $uri );
		$this->assertStringContainsString( 'domain=example.com', $uri );
	}

	public function test_get_products_parses_entries_correctly(): void {
		$this->mock->add_response( new Response( 200, [], $this->build_products_json() ) );

		$result = $this->client->get_products( 'LWSW-TEST-KEY', 'example.com' );

		$this->assertSame( 'kadence', $result[0]->get_product_slug() );
		$this->assertSame( 'kadence-pro', $result[0]->get_tier() );
		$this->assertSame( 'active', $result[0]->get_status() );
		$this->assertSame( 3, $result[0]->get_site_limit() );
	}

	public function test_get_products_returns_error_on_http_500(): void {
		$this->mock->add_response( new Response( 500, [], '{}' ) );

		$result = $this->client->get_products( 'LWSW-TEST-KEY', 'example.com' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Error_Code::UNKNOWN_ERROR, $result->get_error_code() );
	}

	public function test_get_products_returns_error_on_http_404(): void {
		$this->mock->add_response( new Response( 404, [], '{}' ) );

		$result = $this->client->get_products( 'LWSW-TEST-KEY', 'example.com' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Error_Code::INVALID_KEY, $result->get_error_code() );
	}

	public function test_get_products_returns_error_on_invalid_json(): void {
		$this->mock->add_response( new Response( 200, [], 'not json' ) );

		$result = $this->client->get_products( 'LWSW-TEST-KEY', 'example.com' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Error_Code::INVALID_RESPONSE, $result->get_error_code() );
	}

	public function test_get_products_returns_error_when_products_key_missing(): void {
		$this->mock->add_response( new Response( 200, [], '{"data": []}' ) );

		$result = $this->client->get_products( 'LWSW-TEST-KEY', 'example.com' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Error_Code::INVALID_RESPONSE, $result->get_error_code() );
	}

	public function test_get_products_parses_structured_error_response(): void {
		$error_body = (string) wp_json_encode(
			[
				'code'    => Error_Code::EXPIRED,
				'message' => 'License has expired.',
			]
		);

		$this->mock->add_response( new Response( 422, [], $error_body ) );

		$result = $this->client->get_products( 'LWSW-TEST-KEY', 'example.com' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Error_Code::EXPIRED, $result->get_error_code() );
		$this->assertSame( 'License has expired.', $result->get_error_message() );
	}

		/**
	 * Build a minimal valid products JSON string.
	 *
	 * @return string
	 */
	private function build_products_json(): string {
		return (string) wp_json_encode(
			[
				'products' => [
					[
						'product_slug'      => 'kadence',
						'tier'              => 'kadence-pro',
						'status'            => 'active',
						'expires'           => '2026-12-31 23:59:59',
						'activations'       => [
							'site_limit'   => 3,
							'active_count' => 1,
							'over_limit'   => false,
						],
						'activated_here'    => true,
						'validation_status' => 'valid',
						'is_valid'          => true,
					],
					[
						'product_slug'      => 'give',
						'tier'              => 'give-pro',
						'status'            => 'active',
						'expires'           => '2026-12-31 23:59:59',
						'activations'       => [
							'site_limit'   => 3,
							'active_count' => 1,
							'over_limit'   => false,
						],
						'activated_here'    => true,
						'validation_status' => 'valid',
						'is_valid'          => true,
					],
				],
			]
		);
	}

}
