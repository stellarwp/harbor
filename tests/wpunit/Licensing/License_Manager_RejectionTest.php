<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Licensing;

use LiquidWeb\Harbor\Licensing\License_Manager;
use LiquidWeb\Harbor\Licensing\Product_Collection;
use LiquidWeb\Harbor\Licensing\Registry\Product_Registry;
use LiquidWeb\Harbor\Licensing\Repositories\License_Repository;
use LiquidWeb\Harbor\Tests\HarborTestCase;
use LiquidWeb\LicensingApiClient\Contracts\LicensingClientInterface;
use LiquidWeb\LicensingApiClient\Http\Factories\ResponseExceptionFactory;
use LiquidWeb\LicensingApiClient\Http\JsonDecoder;
use LiquidWeb\LicensingApiClient\Resources\Contracts\ProductsResourceInterface;
use Nyholm\Psr7\Response;
use WP_Error;

/**
 * Exercise real HTTP error decoding, including ambiguous infrastructure failures.
 */
final class License_Manager_RejectionTest extends HarborTestCase {

	private License_Repository $repository;
	private License_Manager $working_manager;
	private Product_Collection $cached;

	protected function setUp(): void {
		parent::setUp();
		$this->repository = new License_Repository();
		$this->repository->delete_products();
		delete_option( License_Repository::VALIDATION_STATE_OPTION_NAME );
		$this->repository->store_key( 'LWSW-UNIFIED-PRO-2026' );
		$this->working_manager = new License_Manager(
			$this->repository,
			new Product_Registry( [] ),
			new Fixture_Client( codecept_data_dir( 'licensing' ) )
		);
		$this->cached          = $this->working_manager->get_products( 'example.com' );
	}

	/**
	 * @return array<string, array{int, string}>
	 */
	public static function rejected_responses(): array {
		return [
			'missing license'  => [ 404, '{"error":{"code":"not_found","message":"License not found."}}' ],
			'inactive license' => [ 403, '{"error":{"code":"license_inactive","message":"License is not active."}}' ],
			'no products'      => [ 404, '{"error":{"code":"products_not_found","message":"No products found for this license."}}' ],
		];
	}

	/**
	 * @return array<string, array{int, string}>
	 */
	public static function outage_responses(): array {
		return [
			'service unavailable'        => [ 503, '{"error":{"code":"service_unavailable","message":"Unavailable"}}' ],
			'missing route'              => [ 404, '{"code":"rest_no_route","message":"No route","data":{"status":404}}' ],
			'proxy 404'                  => [ 404, '<html>Not Found</html>' ],
			'empty 404'                  => [ 404, '' ],
			'malformed error object'     => [ 404, '{"error":"not_found"}' ],
			'missing error code'         => [ 404, '{"error":{"message":"Not found"}}' ],
			'generic forbidden'          => [ 403, '{"error":{"code":"forbidden","message":"Forbidden"}}' ],
			'rate limited'               => [ 429, '{"error":{"code":"rate_limited","message":"Slow down"}}' ],
			'wrong status for rejection' => [ 503, '{"error":{"code":"not_found","message":"Not found"}}' ],
		];
	}

	/**
	 * @dataProvider rejected_responses
	 */
	public function test_refresh_discards_rejected_purchases_and_can_recover( int $status, string $body ): void {
		$success_at = $this->repository->get_products_last_success_at();
		$manager    = $this->failing_manager( $status, $body );
		$error      = $manager->refresh_products( 'example.com' );

		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertEquals( $error, $this->repository->get_products() );
		$this->assertEquals( $error, $manager->get_products( 'example.com' ) );
		$this->assertSame( 'LWSW-UNIFIED-PRO-2026', $manager->get_key() );
		$this->assertSame( $success_at, $this->repository->get_products_last_success_at() );
		$this->assertNotNull( $this->repository->get_products_last_failure_at() );

		$this->assertEquals( $this->cached, $this->working_manager->refresh_products( 'example.com' ) );
		$this->assertNull( $this->repository->get_products_last_error() );
	}

	/**
	 * @dataProvider outage_responses
	 */
	public function test_ambiguous_failures_preserve_purchases( int $status, string $body ): void {
		$manager = $this->failing_manager( $status, $body );
		$this->assertInstanceOf( WP_Error::class, $manager->refresh_products( 'example.com' ) );
		$this->assertEquals( $this->cached, $manager->get_products( 'example.com' ) );
	}

	/**
	 * @dataProvider rejected_responses
	 */
	public function test_validating_the_stored_key_discards_rejected_purchases( int $status, string $body ): void {
		$manager = $this->failing_manager( $status, $body );
		$error   = $manager->validate_and_store( 'LWSW-UNIFIED-PRO-2026', 'example.com' );
		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertEquals( $error, $manager->get_products( 'example.com' ) );
	}

	/**
	 * @dataProvider rejected_responses
	 */
	public function test_rejected_replacement_key_preserves_existing_purchases( int $status, string $body ): void {
		$manager = $this->failing_manager( $status, $body, 'LWSW-REPLACEMENT-KEY' );
		$this->assertInstanceOf( WP_Error::class, $manager->validate_and_store( 'LWSW-REPLACEMENT-KEY', 'example.com' ) );
		$this->assertEquals( $this->cached, $manager->get_products( 'example.com' ) );
		$this->assertSame( 'LWSW-UNIFIED-PRO-2026', $manager->get_key() );
	}

	/**
	 * @dataProvider rejected_responses
	 */
	public function test_lookup_remains_read_only( int $status, string $body ): void {
		$manager = $this->failing_manager( $status, $body );
		$this->assertInstanceOf( WP_Error::class, $manager->lookup_products( 'LWSW-UNIFIED-PRO-2026', 'example.com' ) );
		$this->assertEquals( $this->cached, $this->repository->get_products() );
		$this->assertNull( $this->repository->get_products_last_error() );
	}

	private function failing_manager( int $status, string $body, string $key = 'LWSW-UNIFIED-PRO-2026' ): License_Manager {
		$exception = ( new ResponseExceptionFactory( new JsonDecoder() ) )->make( new Response( $status, [], $body ) );
		$products  = $this->createMock( ProductsResourceInterface::class );
		$products->expects( $this->once() )->method( 'catalog' )->with( $key, 'example.com' )->willThrowException( $exception );
		$client = $this->createMock( LicensingClientInterface::class );
		$client->method( 'products' )->willReturn( $products );

		return new License_Manager( $this->repository, new Product_Registry( [] ), $client );
	}
}
