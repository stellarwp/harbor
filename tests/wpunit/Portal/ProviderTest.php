<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Catalog;

use LiquidWeb\Harbor\Catalog\Catalog_Repository;
use LiquidWeb\Harbor\Catalog\Clients\Portal_Client;
use LiquidWeb\Harbor\Catalog\Clients\Http_Client;
use LiquidWeb\Harbor\Tests\HarborTestCase;

final class ProviderTest extends HarborTestCase {

	public function test_it_registers_catalog_client(): void {
		$this->assertInstanceOf(
			Http_Client::class,
			$this->container->get( Portal_Client::class )
		);
	}

	public function test_it_registers_catalog_repository(): void {
		$this->assertInstanceOf(
			Catalog_Repository::class,
			$this->container->get( Catalog_Repository::class )
		);
	}

	public function test_client_is_singleton(): void {
		$first  = $this->container->get( Portal_Client::class );
		$second = $this->container->get( Portal_Client::class );

		$this->assertSame( $first, $second );
	}

	public function test_repository_is_singleton(): void {
		$first  = $this->container->get( Catalog_Repository::class );
		$second = $this->container->get( Catalog_Repository::class );

		$this->assertSame( $first, $second );
	}
}
