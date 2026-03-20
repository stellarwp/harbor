<?php declare( strict_types=1 );

namespace StellarWP\Uplink\Tests;

use StellarWP\Uplink\Config;
use StellarWP\Uplink\Storage\Drivers\Option_Storage;
use StellarWP\Uplink\Storage\Drivers\Transient_Storage;

final class ConfigTest extends UplinkTestCase {

	public function test_it_gets_and_sets_auth_token_cache_expiration(): void {
		$this->assertSame( Config::DEFAULT_AUTH_CACHE, Config::get_auth_cache_expiration() );

		Config::set_auth_cache_expiration( DAY_IN_SECONDS );

		$this->assertSame( DAY_IN_SECONDS, Config::get_auth_cache_expiration() );
	}

	public function test_it_gets_default_storage_driver(): void {
		$this->assertSame( Option_Storage::class, Config::get_storage_driver() );
	}

	public function test_it_gets_and_sets_storage_driver(): void {
		Config::set_storage_driver( Transient_Storage::class );

		$this->assertSame( Transient_Storage::class, Config::get_storage_driver() );
	}

	public function test_it_gets_default_api_base_url(): void {
		$this->assertSame( Config::DEFAULT_API_BASE_URL, Config::get_api_base_url() );
	}

	public function test_it_sets_and_gets_api_base_url(): void {
		Config::set_api_base_url( 'https://custom-api.example.com' );

		$this->assertSame( 'https://custom-api.example.com', Config::get_api_base_url() );
	}

	public function test_it_strips_trailing_slash_from_api_base_url(): void {
		Config::set_api_base_url( 'https://custom-api.example.com/' );

		$this->assertSame( 'https://custom-api.example.com', Config::get_api_base_url() );
	}

	public function test_reset_restores_default_api_base_url(): void {
		Config::set_api_base_url( 'https://custom-api.example.com' );
		Config::reset();

		$this->assertSame( Config::DEFAULT_API_BASE_URL, Config::get_api_base_url() );
	}
}
