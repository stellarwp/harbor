<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Licensing;

use LiquidWeb\Harbor\Licensing\License_Manager;
use LiquidWeb\Harbor\Licensing\Registry\Product_Registry;
use LiquidWeb\Harbor\Licensing\Repositories\License_Repository;
use LiquidWeb\Harbor\Tests\HarborTestCase;
use LiquidWeb\LicensingApiClient\Api;
use LiquidWeb\LicensingApiClient\Contracts\LicensingClientInterface;

final class ProviderTest extends HarborTestCase {

	public function test_it_registers_licensing_client(): void {
		$this->assertInstanceOf(
			Api::class,
			$this->container->get( LicensingClientInterface::class )
		);
	}

	public function test_client_is_singleton(): void {
		$first  = $this->container->get( LicensingClientInterface::class );
		$second = $this->container->get( LicensingClientInterface::class );

		$this->assertSame( $first, $second );
	}

	public function test_it_registers_license_repository(): void {
		$this->assertInstanceOf(
			License_Repository::class,
			$this->container->get( License_Repository::class )
		);
	}

	public function test_license_repository_is_singleton(): void {
		$first  = $this->container->get( License_Repository::class );
		$second = $this->container->get( License_Repository::class );

		$this->assertSame( $first, $second );
	}

	public function test_it_registers_product_registry(): void {
		$this->assertInstanceOf(
			Product_Registry::class,
			$this->container->get( Product_Registry::class )
		);
	}

	public function test_product_registry_is_singleton(): void {
		$first  = $this->container->get( Product_Registry::class );
		$second = $this->container->get( Product_Registry::class );

		$this->assertSame( $first, $second );
	}

	public function test_it_registers_license_manager(): void {
		$this->assertInstanceOf(
			License_Manager::class,
			$this->container->get( License_Manager::class )
		);
	}

	public function test_license_manager_is_singleton(): void {
		$first  = $this->container->get( License_Manager::class );
		$second = $this->container->get( License_Manager::class );

		$this->assertSame( $first, $second );
	}
}
