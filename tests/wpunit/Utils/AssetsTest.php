<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Utils;

use LiquidWeb\Harbor\Tests\HarborTestCase;
use LiquidWeb\Harbor\Utils\Assets;

final class AssetsTest extends HarborTestCase {

	/**
	 * The two compiled directories Harbor ships. Anything else means the
	 * resolver has invented a directory the build pipeline never writes to.
	 */
	private const BUILD_DIRS = [ 'build', 'build-dev' ];

	public function test_build_dir_is_one_of_the_compiled_directories(): void {
		$this->assertContains( Assets::build_dir(), self::BUILD_DIRS );
	}

	/**
	 * WP_DEBUG is a constant, so only the branch this test run was booted with
	 * can be exercised. Deriving the expectation from the constant separately
	 * still catches the ternary being inverted, which is the mistake worth
	 * guarding against here.
	 */
	public function test_build_dir_follows_the_debug_flag(): void {
		$expected = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? 'build-dev' : 'build';

		$this->assertSame( $expected, Assets::build_dir() );
	}

	/**
	 * The class walks three directories up from its own location to find
	 * Harbor's root. Finding this very file back down that path proves the walk
	 * landed in the right place, and fails loudly if the class is ever moved
	 * without the chain being adjusted.
	 */
	public function test_path_resolves_from_harbors_own_plugin_root(): void {
		$plugin_root = dirname( Assets::path() );

		$this->assertFileExists( $plugin_root . '/src/Harbor/Utils/Assets.php' );
	}

	public function test_path_points_at_a_directory_that_exists(): void {
		$this->assertDirectoryExists( Assets::path() );
	}

	public function test_path_has_no_trailing_separator_without_a_file(): void {
		$this->assertStringEndsWith( Assets::build_dir(), Assets::path() );
	}

	public function test_path_appends_a_file_with_a_single_separator(): void {
		$path = Assets::path( 'index.asset.php' );

		$this->assertStringEndsWith( Assets::build_dir() . '/index.asset.php', $path );
		$this->assertStringNotContainsString( '//', $path );
	}

	public function test_url_has_no_trailing_separator_without_a_file(): void {
		$this->assertStringEndsWith( Assets::build_dir(), Assets::url() );
	}

	public function test_url_appends_a_file_with_a_single_separator(): void {
		$url = Assets::url( 'index.js' );

		$this->assertStringEndsWith( Assets::build_dir() . '/index.js', $url );
		// Ignore the protocol's own double slash.
		$this->assertStringNotContainsString( '//', (string) preg_replace( '#^[a-z]+://#i', '', $url ) );
	}

	/**
	 * The invariant that actually matters. A URL pointing at one build directory
	 * while the asset file is read from the other serves stale or missing
	 * scripts, and does it silently.
	 */
	public function test_path_and_url_agree_on_the_build_directory(): void {
		$this->assertStringEndsWith( Assets::build_dir(), Assets::path() );
		$this->assertStringEndsWith( Assets::build_dir(), Assets::url() );
	}
}
