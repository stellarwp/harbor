<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Licensing;

use LiquidWeb\Harbor\Licensing\Error_Code;
use LiquidWeb\Harbor\Licensing\Validation_State;
use LiquidWeb\Harbor\Tests\HarborTestCase;
use WP_Error;

/**
 * @since TBD
 */
final class Validation_StateTest extends HarborTestCase {

	/**
	 * Tests that get_failure_for returns null on a freshly constructed state.
	 *
	 * @return void
	 */
	public function test_get_failure_for_returns_null_when_state_empty(): void {
		$state = new Validation_State();

		$this->assertNull( $state->get_failure_for( 'hash', MINUTE_IN_SECONDS, 1000000 ) );
	}

	/**
	 * Tests that record_failure and get_failure_for round-trip a WP_Error for the same key hash.
	 *
	 * @return void
	 */
	public function test_record_failure_and_get_failure_for_round_trip(): void {
		$state = new Validation_State();
		$error = new WP_Error( Error_Code::INVALID_KEY, 'fail' );

		$state->record_failure( 'hash', $error, 1000000 );

		$result = $state->get_failure_for( 'hash', MINUTE_IN_SECONDS, 1000005 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Error_Code::INVALID_KEY, $result->get_error_code() );
	}

	/**
	 * Tests that get_failure_for returns null when the entry is older than the supplied TTL.
	 *
	 * @return void
	 */
	public function test_get_failure_for_returns_null_when_outside_ttl(): void {
		$state = new Validation_State();
		$state->record_failure( 'hash', new WP_Error( Error_Code::INVALID_KEY, 'fail' ), 1000000 );

		$this->assertNull( $state->get_failure_for( 'hash', MINUTE_IN_SECONDS, 1000061 ) );
	}

	/**
	 * Tests that get_failure_for returns null when the requested hash has not been recorded.
	 *
	 * @return void
	 */
	public function test_get_failure_for_returns_null_for_unknown_hash(): void {
		$state = new Validation_State();
		$state->record_failure( 'known', new WP_Error( Error_Code::INVALID_KEY, 'fail' ), 1000000 );

		$this->assertNull( $state->get_failure_for( 'unknown', MINUTE_IN_SECONDS, 1000005 ) );
	}

	/**
	 * Tests that clear_failure_for removes the per-key entry for the supplied hash.
	 *
	 * @return void
	 */
	public function test_clear_failure_for_removes_per_key_entry(): void {
		$state = new Validation_State();
		$state->record_failure( 'hash', new WP_Error( Error_Code::INVALID_KEY, 'fail' ), 1000000 );

		$state->clear_failure_for( 'hash' );

		$this->assertNull( $state->get_failure_for( 'hash', MINUTE_IN_SECONDS, 1000005 ) );
	}

	/**
	 * Tests that clear_failure_for leaves the rolling-window failure count untouched
	 * so successes cannot erase evidence of abusive traffic.
	 *
	 * @return void
	 */
	public function test_clear_failure_for_does_not_decrement_rolling_window(): void {
		$state = new Validation_State();
		$state->record_failure( 'hash', new WP_Error( Error_Code::INVALID_KEY, 'fail' ), 1000000 );

		$state->clear_failure_for( 'hash' );

		$this->assertSame( 1, $state->count_recent_failures( MINUTE_IN_SECONDS, 1000005 ) );
	}

	/**
	 * Tests that count_recent_failures counts every timestamp inside the supplied window.
	 *
	 * @return void
	 */
	public function test_count_recent_failures_counts_in_window(): void {
		$state = new Validation_State();

		for ( $i = 1; $i <= 4; $i++ ) {
			$state->record_failure( 'hash-' . $i, new WP_Error( Error_Code::INVALID_KEY, 'fail' ), 1000000 );
		}

		$this->assertSame( 4, $state->count_recent_failures( MINUTE_IN_SECONDS, 1000005 ) );
	}

	/**
	 * Tests that count_recent_failures excludes timestamps outside the window.
	 *
	 * @return void
	 */
	public function test_count_recent_failures_excludes_aged_out_entries(): void {
		$state = new Validation_State();

		$state->record_failure( 'old', new WP_Error( Error_Code::INVALID_KEY, 'fail' ), 1000000 );
		$state->record_failure( 'new', new WP_Error( Error_Code::INVALID_KEY, 'fail' ), 1000050 );

		$this->assertSame( 1, $state->count_recent_failures( MINUTE_IN_SECONDS, 1000061 ) );
	}

	/**
	 * Tests that prune drops per-key entries whose failed_at is older than the retention.
	 *
	 * @return void
	 */
	public function test_prune_drops_per_key_entries_outside_retention(): void {
		$state = new Validation_State();
		$state->record_failure( 'old', new WP_Error( Error_Code::INVALID_KEY, 'old' ), 1000000 );
		$state->record_failure( 'new', new WP_Error( Error_Code::INVALID_KEY, 'new' ), 1000050 );

		$state->prune( MINUTE_IN_SECONDS, 1000061 );

		$this->assertNull( $state->get_failure_for( 'old', MINUTE_IN_SECONDS, 1000061 ) );
		$this->assertInstanceOf(
			WP_Error::class,
			$state->get_failure_for( 'new', MINUTE_IN_SECONDS, 1000061 )
		);
	}

	/**
	 * Tests that prune drops rolling-window timestamps older than the retention.
	 *
	 * @return void
	 */
	public function test_prune_drops_rolling_window_entries_outside_retention(): void {
		$state = new Validation_State();
		$state->record_failure( 'a', new WP_Error( Error_Code::INVALID_KEY, 'fail' ), 1000000 );
		$state->record_failure( 'b', new WP_Error( Error_Code::INVALID_KEY, 'fail' ), 1000050 );

		$state->prune( MINUTE_IN_SECONDS, 1000061 );

		$this->assertSame( 1, $state->count_recent_failures( MINUTE_IN_SECONDS, 1000061 ) );
	}

	/**
	 * Tests that to_array and from_array round-trip per-key entries and the rolling window.
	 *
	 * @return void
	 */
	public function test_to_array_and_from_array_round_trip(): void {
		$original = new Validation_State();
		$original->record_failure( 'hash', new WP_Error( Error_Code::INVALID_KEY, 'fail' ), 1000000 );

		$restored = Validation_State::from_array( $original->to_array() );

		$result = $restored->get_failure_for( 'hash', MINUTE_IN_SECONDS, 1000005 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Error_Code::INVALID_KEY, $result->get_error_code() );
		$this->assertSame( 1, $restored->count_recent_failures( MINUTE_IN_SECONDS, 1000005 ) );
	}

	/**
	 * Tests that from_array returns an empty state when given an empty array.
	 *
	 * @return void
	 */
	public function test_from_array_returns_empty_state_for_empty_input(): void {
		$state = Validation_State::from_array( [] );

		$this->assertNull( $state->get_failure_for( 'hash', MINUTE_IN_SECONDS, 1000000 ) );
		$this->assertSame( 0, $state->count_recent_failures( MINUTE_IN_SECONDS, 1000000 ) );
	}

	/**
	 * Tests that from_array skips per-key entries with missing or wrongly typed fields.
	 *
	 * @return void
	 */
	public function test_from_array_skips_malformed_per_key_entries(): void {
		$raw = [
			'per_key'            => [
				'good'              => [
					'failed_at' => 1000000,
					'error'     => new WP_Error( 'x', 'y' ),
				],
				'missing-timestamp' => [ 'error' => new WP_Error( 'x', 'y' ) ],
				'wrong-error-type'  => [
					'failed_at' => 1000000,
					'error'     => 'not-an-error',
				],
				'not-array'         => 'scalar',
			],
			'failure_timestamps' => [ 1000000 ],
		];

		$state = Validation_State::from_array( $raw );

		$this->assertInstanceOf(
			WP_Error::class,
			$state->get_failure_for( 'good', MINUTE_IN_SECONDS, 1000005 )
		);
		$this->assertNull( $state->get_failure_for( 'missing-timestamp', MINUTE_IN_SECONDS, 1000005 ) );
		$this->assertNull( $state->get_failure_for( 'wrong-error-type', MINUTE_IN_SECONDS, 1000005 ) );
		$this->assertNull( $state->get_failure_for( 'not-array', MINUTE_IN_SECONDS, 1000005 ) );
	}

	/**
	 * Tests that from_array skips non-integer timestamps in the rolling window.
	 *
	 * @return void
	 */
	public function test_from_array_skips_non_int_failure_timestamps(): void {
		$raw = [
			'failure_timestamps' => [ 1000000, 'not-an-int', 1000010, null ],
		];

		$state = Validation_State::from_array( $raw );

		$this->assertSame( 2, $state->count_recent_failures( MINUTE_IN_SECONDS, 1000020 ) );
	}
}
