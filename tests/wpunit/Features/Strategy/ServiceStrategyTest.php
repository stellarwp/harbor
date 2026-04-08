<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Features\Strategy;

use LiquidWeb\Harbor\Features\Error_Code;
use LiquidWeb\Harbor\Features\Strategy\Service_Strategy;
use LiquidWeb\Harbor\Features\Types\Service;
use LiquidWeb\Harbor\Tests\HarborTestCase;

/**
 * Tests for the Service_Strategy feature-gating strategy.
 *
 * Service features (e.g. Promoter) are managed exclusively through the
 * Commerce Portal. All enable, disable, and update operations return a
 * WP_Error with FEATURE_NOT_MODIFIABLE. Active state mirrors availability.
 *
 * @see Service_Strategy
 */
final class ServiceStrategyTest extends HarborTestCase {

	/**
	 * @var Service
	 */
	private $available_feature;

	/**
	 * @var Service
	 */
	private $unavailable_feature;

	/**
	 * @var Service_Strategy
	 */
	private $strategy;

	protected function setUp(): void {
		parent::setUp();

		$this->available_feature   = $this->make_service_feature( true );
		$this->unavailable_feature = $this->make_service_feature( false );
		$this->strategy            = new Service_Strategy( $this->available_feature );
	}

	// -------------------------------------------------------------------------
	// enable() tests
	// -------------------------------------------------------------------------

	/**
	 * enable() returns a FEATURE_NOT_MODIFIABLE error for service features.
	 */
	public function test_enable_returns_feature_not_modifiable(): void {
		$result = $this->strategy->enable();

		$this->assertWPError( $result );
		$this->assertSame( Error_Code::FEATURE_NOT_MODIFIABLE, $result->get_error_code() );
	}

	/**
	 * enable() error message mentions the feature name and the Commerce Portal.
	 */
	public function test_enable_error_message_references_feature_name_and_portal(): void {
		$result = $this->strategy->enable();

		$this->assertStringContainsString( 'Test Service Feature', $result->get_error_message() );
		$this->assertStringContainsString( 'Commerce Portal', $result->get_error_message() );
	}

	// -------------------------------------------------------------------------
	// disable() tests
	// -------------------------------------------------------------------------

	/**
	 * disable() returns a FEATURE_NOT_MODIFIABLE error for service features.
	 */
	public function test_disable_returns_feature_not_modifiable(): void {
		$result = $this->strategy->disable();

		$this->assertWPError( $result );
		$this->assertSame( Error_Code::FEATURE_NOT_MODIFIABLE, $result->get_error_code() );
	}

	/**
	 * disable() error message mentions the feature name and the Commerce Portal.
	 */
	public function test_disable_error_message_references_feature_name_and_portal(): void {
		$result = $this->strategy->disable();

		$this->assertStringContainsString( 'Test Service Feature', $result->get_error_message() );
		$this->assertStringContainsString( 'Commerce Portal', $result->get_error_message() );
	}

	// -------------------------------------------------------------------------
	// update() tests
	// -------------------------------------------------------------------------

	/**
	 * update() returns a FEATURE_NOT_MODIFIABLE error for service features.
	 */
	public function test_update_returns_feature_not_modifiable(): void {
		$result = $this->strategy->update();

		$this->assertWPError( $result );
		$this->assertSame( Error_Code::FEATURE_NOT_MODIFIABLE, $result->get_error_code() );
	}

	/**
	 * update() error message mentions the feature name and the Commerce Portal.
	 */
	public function test_update_error_message_references_feature_name_and_portal(): void {
		$result = $this->strategy->update();

		$this->assertStringContainsString( 'Test Service Feature', $result->get_error_message() );
		$this->assertStringContainsString( 'Commerce Portal', $result->get_error_message() );
	}

	// -------------------------------------------------------------------------
	// is_active() tests
	// -------------------------------------------------------------------------

	/**
	 * is_active() returns true when the feature is available.
	 */
	public function test_is_active_returns_true_when_feature_is_available(): void {
		$strategy = new Service_Strategy( $this->available_feature );

		$this->assertTrue( $strategy->is_active() );
	}

	/**
	 * is_active() returns false when the feature is not available.
	 */
	public function test_is_active_returns_false_when_feature_is_not_available(): void {
		$strategy = new Service_Strategy( $this->unavailable_feature );

		$this->assertFalse( $strategy->is_active() );
	}

	/**
	 * is_active() does not write any DB option.
	 */
	public function test_is_active_does_not_write_stored_state(): void {
		$strategy = new Service_Strategy( $this->available_feature );

		$strategy->is_active();

		$this->assertFalse( get_option( 'lw_harbor_feature_test-service_active', false ) );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Create a standard Service feature for testing.
	 *
	 * @param bool $is_available Whether the feature is available to the site.
	 *
	 * @return Service
	 */
	private function make_service_feature( bool $is_available = true ): Service {
		return new Service(
			[
				'slug'         => 'test-service',
				'product'      => 'Test',
				'tier'         => 'Tier 1',
				'name'         => 'Test Service Feature',
				'description'  => 'A test service for unit tests.',
				'is_available' => $is_available,
			]
		);
	}
}
