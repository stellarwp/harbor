<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\API\Functions\Actions;

use LiquidWeb\Harbor\API\Functions\Actions\Store_Unified_License_Key;
use LiquidWeb\Harbor\Licensing\License_Manager;
use LiquidWeb\Harbor\Licensing\Registry\Product_Registry;
use LiquidWeb\Harbor\Licensing\Repositories\License_Repository;
use LiquidWeb\Harbor\Site\Data;
use LiquidWeb\Harbor\Tests\HarborTestCase;
use LiquidWeb\Harbor\Tests\Licensing\Fixture_Client;
use RuntimeException;
use WP_Error;

/**
 * @since TBD
 */
final class Store_Unified_License_KeyTest extends HarborTestCase {

	/**
	 * A key the fixture client recognizes (tests/_data/licensing/lwsw-unified-pro-2026.json).
	 */
	private const GOOD_KEY = 'LWSW-UNIFIED-PRO-2026';

	/**
	 * Well-formed, but no fixture exists, so the client 404s.
	 */
	private const UNKNOWN_KEY = 'LWSW-DOES-NOT-EXIST';

	private Store_Unified_License_Key $action;

	protected function setUp(): void {
		parent::setUp();

		$this->forget_license_state();

		// A real manager over the fixture client, so the "portal" answers from disk
		// instead of the network. Same wiring as License_ManagerTest.
		$this->container->singleton(
			License_Manager::class,
			new License_Manager(
				new License_Repository(),
				new Product_Registry( [] ),
				new Fixture_Client( codecept_data_dir( 'licensing' ) )
			)
		);

		$this->action = new Store_Unified_License_Key();
	}

	protected function tearDown(): void {
		$this->forget_license_state();

		parent::tearDown();
	}

	/**
	 * The validation state carries the per-key throttle and the rolling failure
	 * counter, so leaving it behind lets one test rate-limit the next.
	 */
	private function forget_license_state(): void {
		delete_option( License_Repository::KEY_OPTION_NAME );
		delete_option( License_Repository::PRODUCTS_STATE_OPTION_NAME );
		delete_option( License_Repository::VALIDATION_STATE_OPTION_NAME );
	}

	private function stored_key(): string {
		return (string) get_option( License_Repository::KEY_OPTION_NAME );
	}

	// -------------------------------------------------------------------------
	// Storing
	// -------------------------------------------------------------------------

	public function test_stores_a_key_the_portal_recognizes(): void {
		$this->assertTrue( ( $this->action )( self::GOOD_KEY ) );
		$this->assertSame( self::GOOD_KEY, $this->stored_key() );
	}

	public function test_returns_false_for_a_key_the_portal_does_not_recognize(): void {
		$this->assertFalse( ( $this->action )( self::UNKNOWN_KEY ) );
	}

	/**
	 * A rejected key must leave nothing behind — a half-written key would put the
	 * site in a state the guard then refuses to correct.
	 */
	public function test_does_not_store_a_key_the_portal_does_not_recognize(): void {
		( $this->action )( self::UNKNOWN_KEY );

		$this->assertEmpty( $this->stored_key() );
	}

	public function test_returns_false_for_a_malformed_key(): void {
		$this->assertFalse( ( $this->action )( 'NOT-A-LWSW-KEY' ) );
		$this->assertEmpty( $this->stored_key() );
	}

	// -------------------------------------------------------------------------
	// Refusing to overwrite
	// -------------------------------------------------------------------------

	/**
	 * Deliberately submits a key the portal would accept, so the refusal is
	 * provably about the guard rather than about validity.
	 */
	public function test_returns_false_when_a_key_is_already_stored(): void {
		update_option( License_Repository::KEY_OPTION_NAME, 'LWSW-ALREADY-HERE' );

		$this->assertFalse( ( $this->action )( self::GOOD_KEY ) );
	}

	public function test_does_not_overwrite_an_already_stored_key(): void {
		update_option( License_Repository::KEY_OPTION_NAME, 'LWSW-ALREADY-HERE' );

		( $this->action )( self::GOOD_KEY );

		$this->assertSame( 'LWSW-ALREADY-HERE', $this->stored_key() );
	}

	/**
	 * The guard has to run before the API call, not after it. validate_and_store()
	 * writes the key unconditionally on success, so checking afterwards would mean
	 * reporting on a key that had already been replaced.
	 */
	public function test_does_not_call_the_portal_when_a_key_is_already_stored(): void {
		$called = false;

		$this->container->singleton(
			License_Manager::class,
			$this->makeEmpty(
				License_Manager::class,
				[
					'key_exists'         => true,
					'validate_and_store' => static function () use ( &$called ) {
						$called = true;

						return new WP_Error( 'unexpected', 'The portal should not have been called.' );
					},
				]
			)
		);

		$this->assertFalse( ( $this->action )( self::GOOD_KEY ) );
		$this->assertFalse( $called );
	}

	/**
	 * Storing is not idempotent: the second call sees a stored key and refuses,
	 * so a caller must not treat false as "retry".
	 */
	public function test_re_storing_the_same_key_returns_false(): void {
		$this->assertTrue( ( $this->action )( self::GOOD_KEY ) );
		$this->assertFalse( ( $this->action )( self::GOOD_KEY ) );
	}

	// -------------------------------------------------------------------------
	// Collaborators
	// -------------------------------------------------------------------------

	public function test_sends_the_licensed_domain_to_the_manager(): void {
		$received = '';

		$this->container->singleton(
			License_Manager::class,
			$this->makeEmpty(
				License_Manager::class,
				[
					'key_exists'         => false,
					'validate_and_store' => static function ( string $key, string $domain ) use ( &$received ) {
						$received = $domain;

						return new WP_Error( 'stop', 'Only the domain matters here.' );
					},
				]
			)
		);

		( $this->action )( self::GOOD_KEY );

		$this->assertSame( $this->container->get( Data::class )->get_domain(), $received );
	}

	/**
	 * Registration happens outside Harbor's is_enabled() gate, so the licensing
	 * services may not be bound at all when a consumer calls this.
	 */
	public function test_returns_false_when_the_manager_cannot_be_used(): void {
		$this->container->singleton(
			License_Manager::class,
			$this->makeEmpty(
				License_Manager::class,
				[
					'key_exists' => static function (): bool {
						throw new RuntimeException( 'Licensing is not available.' );
					},
				]
			)
		);

		$this->assertFalse( ( $this->action )( self::GOOD_KEY ) );
	}
}
