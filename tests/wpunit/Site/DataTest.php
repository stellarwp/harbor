<?php

namespace LiquidWeb\Harbor\Tests\Site;

use LiquidWeb\Harbor;
use LiquidWeb\Harbor\Tests\HarborTestCase;

class DataTest extends HarborTestCase {
	public $container;

	protected function setUp(): void {
		parent::setUp();
		$this->container = Harbor\Config::get_container();
	}

	/**
	 * It should return the site domain.
	 *
	 * @test
	 */
	public function it_should_return_the_site_domain() {
		$data   = $this->container->make( Harbor\Site\Data::class );
		$domain = $data->get_domain();

		$this->assertIsString( $domain );
		$this->assertNotEmpty( $domain );
	}
}
