<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use LiquidWeb\Harbor\Tests\HarborTestCase;

final class ProviderTest extends HarborTestCase {

	public function test_it_registers_request_factory(): void {
		$this->assertInstanceOf(
			Psr17Factory::class,
			$this->container->get( RequestFactoryInterface::class )
		);
	}

	public function test_it_registers_stream_factory(): void {
		$this->assertInstanceOf(
			Psr17Factory::class,
			$this->container->get( StreamFactoryInterface::class )
		);
	}

	public function test_request_factory_is_singleton(): void {
		$first  = $this->container->get( RequestFactoryInterface::class );
		$second = $this->container->get( RequestFactoryInterface::class );

		$this->assertSame( $first, $second );
	}

	public function test_stream_factory_is_singleton(): void {
		$first  = $this->container->get( StreamFactoryInterface::class );
		$second = $this->container->get( StreamFactoryInterface::class );

		$this->assertSame( $first, $second );
	}

	public function test_psr17_factory_is_singleton(): void {
		$first  = $this->container->get( Psr17Factory::class );
		$second = $this->container->get( Psr17Factory::class );

		$this->assertSame( $first, $second );
	}
}
