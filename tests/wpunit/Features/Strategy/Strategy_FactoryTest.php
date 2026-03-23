<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Features\Strategy;

use InvalidArgumentException;
use LiquidWeb\Harbor\Features\Strategy\Flag_Strategy;
use LiquidWeb\Harbor\Features\Strategy\Plugin_Strategy;
use LiquidWeb\Harbor\Features\Strategy\Strategy_Factory;
use LiquidWeb\Harbor\Features\Strategy\Theme_Strategy;
use LiquidWeb\Harbor\Features\Types\Flag;
use LiquidWeb\Harbor\Features\Types\Plugin;
use LiquidWeb\Harbor\Features\Types\Theme;
use LiquidWeb\Harbor\Tests\HarborTestCase;

final class Strategy_FactoryTest extends HarborTestCase {

	/**
	 * The strategy factory under test.
	 *
	 * @var Strategy_Factory
	 */
	private Strategy_Factory $factory;

	/**
	 * Sets up the strategy factory before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->factory = new Strategy_Factory();
	}

	/**
	 * Tests that the factory creates a Plugin_Strategy for plugin features.
	 *
	 * @return void
	 */
	public function test_it_creates_plugin_strategy(): void {
		$feature = Plugin::from_array(
			[
				'slug'        => 'test-plugin',
				'type'        => 'plugin',
				'name'        => 'Test Plugin',
				'plugin_file' => 'test-plugin/test-plugin.php',
			]
		);

		$this->assertInstanceOf( Plugin_Strategy::class, $this->factory->make( $feature ) );
	}

	/**
	 * Tests that the factory creates a Flag_Strategy for flag features.
	 *
	 * @return void
	 */
	public function test_it_creates_flag_strategy(): void {
		$feature = Flag::from_array(
			[
				'slug' => 'test-flag',
				'type' => 'flag',
				'name' => 'Test Flag',
			]
		);

		$this->assertInstanceOf( Flag_Strategy::class, $this->factory->make( $feature ) );
	}

	/**
	 * Tests that the factory creates a Theme_Strategy for theme features.
	 *
	 * @return void
	 */
	public function test_it_creates_theme_strategy(): void {
		$feature = Theme::from_array(
			[
				'slug' => 'test-theme',
				'type' => 'theme',
				'name' => 'Test Theme',
			]
		);

		$this->assertInstanceOf( Theme_Strategy::class, $this->factory->make( $feature ) );
	}

	/**
	 * Tests that an exception is thrown for an unknown feature type.
	 *
	 * @return void
	 */
	public function test_it_throws_for_unknown_type(): void {
		$feature = $this->makeEmpty( \LiquidWeb\Harbor\Features\Types\Feature::class, [ 'get_type' => 'unknown' ] );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'No strategy for feature type "unknown".' );

		$this->factory->make( $feature );
	}
}
