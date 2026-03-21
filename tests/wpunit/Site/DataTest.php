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
	 * It should collect base stats.
	 *
	 * @test
	 */
	public function it_should_collect_base_stats() {
		global $wp_version;

		$data  = $this->container->make( Harbor\Site\Data::class );
		$stats = $data->get_stats();

		$this->assertArrayHasKey( 'versions', $stats );
		$this->assertArrayHasKey( 'wp', $stats['versions'] );
		$this->assertArrayHasKey( 'multisite', $stats['network'] );
		$this->assertArrayHasKey( 'network_activated', $stats['network'] );
		$this->assertArrayHasKey( 'active_sites', $stats['network'] );
		$this->assertArrayNotHasKey( 'totals', $stats );

		$this->assertEquals( $wp_version, $stats['versions']['wp'] );
	}

	/**
	 * It should collect full stats.
	 *
	 * @test
	 */
	public function it_should_collect_full_stats() {
		add_filter( 'lw-harbor/test/use_full_stats', '__return_true' );

		$data  = $this->container->make( Harbor\Site\Data::class );
		$stats = $data->get_stats();

		$this->assertArrayHasKey( 'versions', $stats );
		$this->assertArrayHasKey( 'wp', $stats['versions'] );
		$this->assertArrayHasKey( 'multisite', $stats['network'] );
		$this->assertArrayHasKey( 'network_activated', $stats['network'] );
		$this->assertArrayHasKey( 'active_sites', $stats['network'] );
		$this->assertArrayHasKey( 'totals', $stats );

		$this->assertEquals( phpversion(), $stats['versions']['php'] );

		remove_filter( 'lw-harbor/test/use_full_stats', '__return_true' );
	}
}
