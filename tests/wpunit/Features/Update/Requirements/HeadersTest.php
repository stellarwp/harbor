<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Features\Update\Requirements;

use LiquidWeb\Harbor\Features\Error_Code;
use LiquidWeb\Harbor\Features\Update\Requirements\Headers;
use LiquidWeb\Harbor\Tests\HarborTestCase;

final class HeadersTest extends HarborTestCase {

	/**
	 * Tests that a plugin entry (object) is read.
	 *
	 * @return void
	 */
	public function test_from_entry_reads_a_plugin_entry(): void {
		$requirements = Headers::from_entry(
			(object) [
				'id'           => 'stellarwp/plugins/kad-blocks-pro',
				'new_version'  => '2.7.4',
				'requires'     => '6.6',
				'requires_php' => '7.4',
			]
		);

		$this->assertSame( '2.7.4', $requirements->get_version() );
		$this->assertSame( '6.6', $requirements->get_wp() );
		$this->assertSame( '7.4', $requirements->get_php() );
	}

	/**
	 * Tests that a theme entry (array) is read.
	 *
	 * @return void
	 */
	public function test_from_entry_reads_a_theme_entry(): void {
		$requirements = Headers::from_entry(
			[
				'id'           => 'stellarwp/themes/kadence',
				'new_version'  => '1.2.0',
				'requires'     => '6.0',
				'requires_php' => '7.2',
			]
		);

		$this->assertSame( '1.2.0', $requirements->get_version() );
		$this->assertSame( '6.0', $requirements->get_wp() );
		$this->assertSame( '7.2', $requirements->get_php() );
	}

	/**
	 * Tests that missing requirements are read as empty strings and count as met.
	 *
	 * @return void
	 */
	public function test_missing_requirements_are_met(): void {
		$requirements = Headers::from_entry( (object) [ 'new_version' => '2.0.0' ] );

		$this->assertSame( '', $requirements->get_wp() );
		$this->assertSame( '', $requirements->get_php() );
		$this->assertTrue( $requirements->is_wp_met() );
		$this->assertTrue( $requirements->is_php_met() );
		$this->assertTrue( $requirements->is_met() );
	}

	/**
	 * Tests the met state for each combination of requirements.
	 *
	 * @dataProvider requirement_cases
	 *
	 * @param string $wp          The required WordPress version.
	 * @param string $php         The required PHP version.
	 * @param bool   $wp_met      Whether the WordPress requirement is met.
	 * @param bool   $php_met     Whether the PHP requirement is met.
	 *
	 * @return void
	 */
	public function test_is_met( string $wp, string $php, bool $wp_met, bool $php_met ): void {
		$requirements = Headers::from_entry(
			(object) [
				'new_version'  => '2.0.0',
				'requires'     => $wp,
				'requires_php' => $php,
			]
		);

		$this->assertSame( $wp_met, $requirements->is_wp_met() );
		$this->assertSame( $php_met, $requirements->is_php_met() );
		$this->assertSame( $wp_met && $php_met, $requirements->is_met() );
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: bool, 3: bool}>
	 */
	public function requirement_cases(): array {
		return [
			'both met'        => [ '1.0', '1.0', true, true ],
			'wordpress unmet' => [ '99.0', '1.0', false, true ],
			'php unmet'       => [ '1.0', '99.0', true, false ],
			'both unmet'      => [ '99.0', '99.0', false, false ],
		];
	}

	/**
	 * Tests that the entry's id is kept.
	 *
	 * @return void
	 */
	public function test_get_id(): void {
		$this->assertSame( 'w.org/plugins/kadence-blocks', Headers::from_entry( (object) [ 'id' => 'w.org/plugins/kadence-blocks' ] )->get_id() );
		$this->assertSame( 'stellarwp/themes/kadence', Headers::from_entry( [ 'id' => 'stellarwp/themes/kadence' ] )->get_id() );
		$this->assertSame( '', Headers::from_entry( (object) [] )->get_id() );
	}

	/**
	 * Tests the error message for each unmet requirement.
	 *
	 * @dataProvider error_cases
	 *
	 * @param string   $wp       The required WordPress version.
	 * @param string   $php      The required PHP version.
	 * @param string[] $contains Strings the message contains.
	 *
	 * @return void
	 */
	public function test_to_wp_error_names_the_unmet_requirements( string $wp, string $php, array $contains ): void {
		$requirements = Headers::from_entry(
			(object) [
				'new_version'  => '5.0.0',
				'requires'     => $wp,
				'requires_php' => $php,
			]
		);

		$error = $requirements->to_wp_error( 'LearnDash LMS', Error_Code::REQUIREMENTS_NOT_MET );

		$this->assertSame( Error_Code::REQUIREMENTS_NOT_MET, $error->get_error_code() );
		$this->assertStringContainsString( '"LearnDash LMS" 5.0.0 requires', $error->get_error_message() );

		foreach ( $contains as $text ) {
			$this->assertStringContainsString( $text, $error->get_error_message() );
		}

		$this->assertSame(
			[
				'requires'     => $wp,
				'requires_php' => $php,
			],
			$error->get_error_data()
		);
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: string[]}>
	 */
	public function error_cases(): array {
		return [
			'wordpress' => [ '99.0', '', [ 'WordPress 99.0 or newer', 'This site runs WordPress ' . get_bloginfo( 'version' ) ] ],
			'php'       => [ '', '99.0', [ 'PHP 99.0 or newer', 'This site runs PHP ' . PHP_VERSION ] ],
			'both'      => [ '99.0', '98.0', [ 'WordPress 99.0 and PHP 98.0 or newer', 'This site runs WordPress ' . get_bloginfo( 'version' ) . ' and PHP ' . PHP_VERSION ] ],
		];
	}
}
