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

	/**
	 * The domain should be the host of home_url(), lowercased.
	 *
	 * @since TBD
	 *
	 * @test
	 */
	public function it_should_return_the_host_of_home_url() {
		$data = $this->container->make( Harbor\Site\Data::class );

		$expected = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

		$this->assertSame( $expected, $data->get_site_domain() );
	}

	/**
	 * A host-environment plugin that rewrites home_url (e.g. Hostinger's
	 * preview-domain mu-plugin) must be honored, so the domain Harbor
	 * validates against matches the URL used during activation.
	 *
	 * @since TBD
	 *
	 * @test
	 */
	public function it_should_follow_the_home_url_filter() {
		$data = $this->container->make( Harbor\Site\Data::class );

		$filter = static function () {
			return 'https://staging.preview.example.com';
		};

		add_filter( 'home_url', $filter );

		try {
			$this->assertSame( 'staging.preview.example.com', $data->get_site_domain() );
		} finally {
			remove_filter( 'home_url', $filter );
		}
	}

	/**
	 * Temporary-domain access leaves DB URLs on the production domain
	 * but rewrites home_url() to the current temporary host at runtime.
	 *
	 * @since TBD
	 *
	 * @test
	 */
	public function it_should_derive_the_domain_from_home_url_not_the_siteurl_option() {
		$data = $this->container->make( Harbor\Site\Data::class );

		$production_url = 'https://production.com';
		$temporary_host = 'staging.com';

		update_option( 'siteurl', $production_url );
		update_option( 'home', $production_url );

		$filter = static function ( $url ) use ( $temporary_host ) {
			return str_replace(
				[
					'http://production.com',
					'https://production.com',
				],
				'https://' . $temporary_host,
				$url
			);
		};

		add_filter( 'home_url', $filter );

		try {
			$this->assertSame( $temporary_host, $data->get_site_domain() );
		} finally {
			remove_filter( 'home_url', $filter );
		}
	}

	/**
	 * The host should always be returned in lowercase.
	 *
	 * @since TBD
	 *
	 * @test
	 */
	public function it_should_lowercase_the_host() {
		$data = $this->container->make( Harbor\Site\Data::class );

		$filter = static function () {
			return 'https://Staging.EXAMPLE.com';
		};

		add_filter( 'home_url', $filter );

		try {
			$this->assertSame( 'staging.example.com', $data->get_site_domain() );
		} finally {
			remove_filter( 'home_url', $filter );
		}
	}

	/**
	 * The lw-harbor/get_domain filter should be able to override the domain.
	 *
	 * @since TBD
	 *
	 * @test
	 */
	public function it_should_allow_the_get_domain_filter_to_override() {
		$data = $this->container->make( Harbor\Site\Data::class );

		$filter = static function () {
			return 'override.example.com';
		};

		add_filter( 'lw-harbor/get_domain', $filter );

		try {
			$this->assertSame( 'override.example.com', $data->get_domain() );
		} finally {
			remove_filter( 'lw-harbor/get_domain', $filter );
		}
	}
}
