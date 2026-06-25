<?php declare( strict_types=1 );

namespace muwpunit\Site;

use LiquidWeb\Harbor;
use LiquidWeb\Harbor\Tests\HarborTestCase;

/**
 * Multisite coverage for Site\Data::get_domain().
 *
 * Each subsite must validate against its own subscription URL, so the
 * resolved domain has to track the current subsite (via home_url()) rather
 * than a single network-wide value.
 *
 * @since 1.5.0
 */
final class DataTest extends HarborTestCase {

	private Harbor\Site\Data $data;

	protected function setUp(): void {
		parent::setUp();
		$this->data = Harbor\Config::get_container()->make( Harbor\Site\Data::class );
	}

	/**
	 * The domain should be the host of the current site's home_url().
	 *
	 * @since 1.5.0
	 *
	 * @test
	 */
	public function it_should_return_the_current_sites_home_url_host(): void {
		$expected = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

		$this->assertSame( $expected, $this->data->get_site_domain() );
	}

	/**
	 * After switching to another subsite, the domain should reflect that
	 * subsite's URL — not the main site's — confirming per-subsite resolution.
	 *
	 * @since 1.5.0
	 *
	 * @test
	 */
	public function it_should_follow_the_switched_subsite(): void {
		$blog_id = (int) self::factory()->blog->create(
			[
				'domain' => 'harbor-subsite.example.org',
				'path'   => '/',
			]
		);

		switch_to_blog( $blog_id );

		try {
			$expected = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

			$this->assertSame( $expected, $this->data->get_site_domain() );
			$this->assertSame( 'harbor-subsite.example.org', $this->data->get_site_domain() );
		} finally {
			restore_current_blog();
		}
	}
}
