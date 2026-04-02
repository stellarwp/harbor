<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests;

use LiquidWeb\Harbor\Config;

final class ConfigTest extends HarborTestCase {

	public function test_it_gets_default_licensing_base_url(): void {
		$this->assertSame( Config::DEFAULT_LICENSING_BASE_URL, Config::get_licensing_base_url() );
	}

	public function test_it_sets_and_gets_licensing_base_url(): void {
		Config::set_licensing_base_url( 'https://custom-api.example.com' );

		$this->assertSame( 'https://custom-api.example.com', Config::get_licensing_base_url() );
	}

	public function test_it_strips_trailing_slash_from_licensing_base_url(): void {
		Config::set_licensing_base_url( 'https://custom-api.example.com/' );

		$this->assertSame( 'https://custom-api.example.com', Config::get_licensing_base_url() );
	}

	public function test_reset_restores_default_licensing_base_url(): void {
		Config::set_licensing_base_url( 'https://custom-api.example.com' );
		Config::reset();

		$this->assertSame( Config::DEFAULT_LICENSING_BASE_URL, Config::get_licensing_base_url() );
	}
}
