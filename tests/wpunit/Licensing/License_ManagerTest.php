<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Licensing;

use LiquidWeb\Harbor\Licensing\Enums\Validation_Status;
use LiquidWeb\Harbor\Licensing\Error_Code;
use LiquidWeb\Harbor\Licensing\Clients\Fixture_Client;
use LiquidWeb\Harbor\Licensing\License_Manager;
use LiquidWeb\Harbor\Licensing\Product_Collection;
use LiquidWeb\Harbor\Licensing\Registry\Product_Registry;
use LiquidWeb\Harbor\Licensing\Repositories\License_Repository;
use LiquidWeb\Harbor\Tests\Traits\With_Uopz;
use LiquidWeb\Harbor\Tests\HarborTestCase;
use WP_Error;

/**
 * @since 1.0.0
 */
final class License_ManagerTest extends HarborTestCase {

	use With_Uopz;

	private License_Manager $manager;
	private License_Repository $repository;

	protected function setUp(): void {
		parent::setUp();

		$this->repository = new License_Repository();
		$this->manager    = new License_Manager(
			$this->repository,
			new Product_Registry(),
			new Fixture_Client( codecept_data_dir( 'licensing' ) )
		);

		delete_option( License_Repository::KEY_OPTION_NAME );
	}

	protected function tearDown(): void {
		remove_all_filters( Product_Registry::FILTER );
		delete_option( License_Repository::KEY_OPTION_NAME );
		delete_option( License_Repository::PRODUCTS_STATE_OPTION_NAME );
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// get()
	// -------------------------------------------------------------------------

	public function test_get_returns_null_when_no_key_stored_and_no_registry(): void {
		$this->assertNull( $this->manager->get_key() );
	}

	public function test_get_returns_stored_key(): void {
		$this->manager->store_key( 'LWSW-UNIFIED-PRO-2026' );

		$this->assertSame( 'LWSW-UNIFIED-PRO-2026', $this->manager->get_key() );
	}

	public function test_get_falls_back_to_embedded_key_from_registry(): void {
		add_filter(
			Product_Registry::FILTER,
			static function ( array $products ) {
				$products[] = [
					'slug'         => 'give',
					'embedded_key' => 'LWSW-EMBEDDED-KEY',
					'name'         => 'GiveWP',
					'version'      => '3.0.0',
					'product'        => 'givewp',
				];
				return $products;
			} 
		);

		$this->assertSame( 'LWSW-EMBEDDED-KEY', $this->manager->get_key() );
	}

	public function test_get_auto_stores_embedded_key_on_discovery(): void {
		add_filter(
			Product_Registry::FILTER,
			static function ( array $products ) {
				$products[] = [
					'slug'         => 'give',
					'embedded_key' => 'LWSW-EMBEDDED-KEY',
				];
				return $products;
			} 
		);

		$this->manager->get_key();

		$this->assertSame( 'LWSW-EMBEDDED-KEY', get_option( License_Repository::KEY_OPTION_NAME ) );
	}

	public function test_stored_key_takes_precedence_over_registry(): void {
		$this->manager->store_key( 'LWSW-STORED-KEY' );

		add_filter(
			Product_Registry::FILTER,
			static function ( array $products ) {
				$products[] = [
					'slug'         => 'give',
					'embedded_key' => 'LWSW-EMBEDDED-KEY',
				];
				return $products;
			} 
		);

		$this->assertSame( 'LWSW-STORED-KEY', $this->manager->get_key() );
	}

	public function test_registry_filter_not_applied_when_stored_key_exists(): void {
		$this->manager->store_key( 'LWSW-STORED-KEY' );

		$filter_called = false;
		add_filter(
			Product_Registry::FILTER,
			static function ( array $products ) use ( &$filter_called ) {
				$filter_called = true;
				return $products;
			} 
		);

		$this->manager->get_key();

		$this->assertFalse( $filter_called );
	}

	public function test_get_returns_null_when_registry_has_no_embedded_keys(): void {
		add_filter(
			Product_Registry::FILTER,
			static function ( array $products ) {
				$products[] = [
					'slug' => 'give',
					'name' => 'GiveWP',
				];
				return $products;
			} 
		);

		$this->assertNull( $this->manager->get_key() );
	}

	// -------------------------------------------------------------------------
	// store() / delete()
	// -------------------------------------------------------------------------

	public function test_store_persists_key(): void {
		$this->manager->store_key( 'LWSW-UNIFIED-PRO-2026' );

		$this->assertSame( 'LWSW-UNIFIED-PRO-2026', get_option( License_Repository::KEY_OPTION_NAME ) );
	}

	public function test_store_returns_true_on_success(): void {
		$this->assertTrue( $this->manager->store_key( 'LWSW-UNIFIED-PRO-2026' ) );
	}

	public function test_store_returns_false_for_key_without_lwsw_prefix(): void {
		$this->assertFalse( $this->manager->store_key( 'INVALID-KEY' ) );
	}

	public function test_store_does_not_persist_invalid_key(): void {
		$this->manager->store_key( 'INVALID-KEY' );

		$this->assertEmpty( get_option( License_Repository::KEY_OPTION_NAME ) );
	}

	public function test_delete_removes_stored_key(): void {
		$this->manager->store_key( 'LWSW-UNIFIED-PRO-2026' );
		$this->manager->delete_key();

		$this->assertNull( $this->manager->get_key() );
	}

	// -------------------------------------------------------------------------
	// exists()
	// -------------------------------------------------------------------------

	public function test_exists_returns_false_when_no_key(): void {
		$this->assertFalse( $this->manager->key_exists() );
	}

	public function test_exists_returns_true_when_key_stored(): void {
		$this->manager->store_key( 'LWSW-UNIFIED-PRO-2026' );

		$this->assertTrue( $this->manager->key_exists() );
	}

	public function test_exists_returns_true_when_registry_provides_embedded_key(): void {
		add_filter(
			Product_Registry::FILTER,
			static function ( array $products ) {
				$products[] = [
					'slug'         => 'give',
					'embedded_key' => 'LWSW-EMBEDDED-KEY',
				];
				return $products;
			}
		);

		$this->assertTrue( $this->manager->key_exists() );
	}

	// -------------------------------------------------------------------------
	// validate_and_store()
	// -------------------------------------------------------------------------

	public function test_validate_and_store_returns_products_for_recognized_key(): void {
		$result = $this->manager->validate_and_store( 'LWSW-UNIFIED-PRO-2026', 'example.com' );

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );
	}

	public function test_validate_and_store_persists_key_on_success(): void {
		$this->manager->validate_and_store( 'LWSW-UNIFIED-PRO-2026', 'example.com' );

		$this->assertSame( 'LWSW-UNIFIED-PRO-2026', get_option( License_Repository::KEY_OPTION_NAME ) );
	}

	public function test_validate_and_store_returns_error_for_unrecognized_key(): void {
		$result = $this->manager->validate_and_store( 'LWSW-DOES-NOT-EXIST', 'example.com' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Error_Code::INVALID_KEY, $result->get_error_code() );
	}

	public function test_validate_and_store_does_not_persist_unrecognized_key(): void {
		$this->manager->validate_and_store( 'LWSW-DOES-NOT-EXIST', 'example.com' );

		$this->assertEmpty( get_option( License_Repository::KEY_OPTION_NAME ) );
	}

	public function test_validate_and_store_returns_error_for_invalid_format(): void {
		$result = $this->manager->validate_and_store( 'INVALID-KEY', 'example.com' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Error_Code::INVALID_KEY, $result->get_error_code() );
	}

	// -------------------------------------------------------------------------
	// get_products()
	// -------------------------------------------------------------------------

	public function test_get_products_returns_error_when_no_key_stored(): void {
		$result = $this->manager->get_products( 'example.com' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Error_Code::INVALID_KEY, $result->get_error_code() );
	}

	public function test_get_products_returns_collection_for_valid_key(): void {
		$this->manager->store_key( 'LWSW-UNIFIED-PRO-2026' );

		$result = $this->manager->get_products( 'example.com' );

		$this->assertInstanceOf( Product_Collection::class, $result );
	}

	public function test_get_products_collection_contains_expected_products(): void {
		$this->manager->store_key( 'LWSW-UNIFIED-PRO-2026' );

		/** @var Product_Collection $result */
		$result = $this->manager->get_products( 'example.com' );

		$this->assertNotNull( $result->get( 'give' ) );
		$this->assertNotNull( $result->get( 'the-events-calendar' ) );
		$this->assertNotNull( $result->get( 'kadence' ) );
	}

	// -------------------------------------------------------------------------
	// Error throttling
	// -------------------------------------------------------------------------

	public function test_get_products_returns_cached_error_when_within_throttle_window(): void {
		$this->manager->store_key( 'LWSW-UNIFIED-PRO-2026' );

		// Write error state at a fixed time.
		$this->set_fn_return( 'time', 1000000 );
		$this->repository->set_products( new WP_Error( Error_Code::INVALID_KEY, 'API failure' ) );

		// Advance to 30 s later — still within the 60 s TTL.
		$this->set_fn_return( 'time', 1000030 );

		$result = $this->manager->get_products( 'example.com' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Error_Code::INVALID_KEY, $result->get_error_code() );
	}

	public function test_get_products_retries_api_after_throttle_window_expires(): void {
		$this->manager->store_key( 'LWSW-UNIFIED-PRO-2026' );

		// Write error state at a fixed time.
		$this->set_fn_return( 'time', 1000000 );
		$this->repository->set_products( new WP_Error( Error_Code::INVALID_KEY, 'API failure' ) );

		// Advance past the 60 s TTL.
		$this->set_fn_return( 'time', 1000061 );

		$result = $this->manager->get_products( 'example.com' );

		$this->assertInstanceOf( Product_Collection::class, $result );
	}

	public function test_validate_and_store_returns_cached_error_when_within_throttle_window(): void {
		// Write error state at a fixed time.
		$this->set_fn_return( 'time', 1000000 );
		$this->repository->set_products( new WP_Error( Error_Code::INVALID_KEY, 'API failure' ) );

		// Advance to 30 s later — still within the 60 s TTL.
		$this->set_fn_return( 'time', 1000030 );

		$result = $this->manager->validate_and_store( 'LWSW-UNIFIED-PRO-2026', 'example.com' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Error_Code::INVALID_KEY, $result->get_error_code() );
	}

	public function test_validate_and_store_retries_api_after_throttle_window_expires(): void {
		// Write error state at a fixed time.
		$this->set_fn_return( 'time', 1000000 );
		$this->repository->set_products( new WP_Error( Error_Code::INVALID_KEY, 'API failure' ) );

		// Advance past the 60 s TTL.
		$this->set_fn_return( 'time', 1000061 );

		$result = $this->manager->validate_and_store( 'LWSW-UNIFIED-PRO-2026', 'example.com' );

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );
	}

	public function test_successful_call_clears_error_state(): void {
		$this->manager->store_key( 'LWSW-UNIFIED-PRO-2026' );

		// Write error state at a fixed time.
		$this->set_fn_return( 'time', 1000000 );
		$this->repository->set_products( new WP_Error( Error_Code::INVALID_KEY, 'API failure' ) );

		$this->assertNotNull( $this->repository->get_products_last_failure_at() );
		$this->assertNotNull( $this->repository->get_products_last_error() );

		// Advance past TTL so the request is not throttled and reaches the API.
		$this->set_fn_return( 'time', 1000061 );

		$this->manager->get_products( 'example.com' );

		$this->assertNull( $this->repository->get_products_last_failure_at() );
		$this->assertNull( $this->repository->get_products_last_error() );
	}
}
