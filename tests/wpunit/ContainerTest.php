<?php declare( strict_types=1 );

namespace wpunit;

use StellarWP\ContainerContract\ContainerInterface;
use LiquidWeb\Harbor\Config;
use LiquidWeb\Harbor\Tests\HarborTestCase;

class ContainerTest extends HarborTestCase {

	/**
	 * Test that the container is correctly instantiated.
	 */
	public function test_it_should_instantiate(): void {
		$container = Config::get_container();

		$this->assertInstanceOf( ContainerInterface::class, $container );
	}
}
