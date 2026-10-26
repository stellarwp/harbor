<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Catalog\Results;

use LiquidWeb\Harbor\Portal\Results\Catalog_Feature;
use LiquidWeb\Harbor\Tests\HarborTestCase;
use Generator;

final class Catalog_FeatureTest extends HarborTestCase {

	private array $plugin_data = [
		'slug'              => 'kadence-security',
		'kind'              => 'plugin',
		'minimum_tier'      => 'pro',
		'top_dir'           => 'kadence-security-pro',
		'main_file'         => 'kadence-security-pro.php',
		'wporg_slug'        => null,
		'version'           => '2.1.0',
		'release_date'      => '2025-11-15',
		'changelog'         => '<h4>2.1.0</h4><ul><li>Bug fixes.</li></ul>',
		'name'              => 'Kadence Security Pro',
		'description'       => 'WordPress security hardening and monitoring.',
		'category'          => 'security',
		'authors'           => [ 'KadenceWP' ],
		'documentation_url' => 'https://www.kadencewp.com/help-center/',
		'wp_requires'       => '6.6',
		'php_requires'      => '7.4',
		'wp_tested'         => '6.9',
	];

	public function test_from_array_hydrates_all_fields(): void {
		$feature = Catalog_Feature::from_array( $this->plugin_data );

		$this->assertSame( 'kadence-security', $feature->get_slug() );
		$this->assertSame( 'plugin', $feature->get_kind() );
		$this->assertSame( 'pro', $feature->get_minimum_tier() );
		$this->assertSame( 'kadence-security-pro/kadence-security-pro.php', $feature->get_plugin_file() );
		$this->assertFalse( $feature->is_wporg() );
		$this->assertNull( $feature->get_wporg_slug() );
		$this->assertSame( 'Kadence Security Pro', $feature->get_name() );
		$this->assertSame( 'WordPress security hardening and monitoring.', $feature->get_description() );
		$this->assertSame( 'security', $feature->get_category() );
		$this->assertSame( [ 'KadenceWP' ], $feature->get_authors() );
		$this->assertSame( 'https://www.kadencewp.com/help-center/', $feature->get_documentation_url() );
		$this->assertSame( '2.1.0', $feature->get_version() );
		$this->assertSame( '2025-11-15', $feature->get_release_date() );
		$this->assertSame( '<h4>2.1.0</h4><ul><li>Bug fixes.</li></ul>', $feature->get_changelog() );
		$this->assertSame( '6.6', $feature->get_wp_requires() );
		$this->assertSame( '7.4', $feature->get_php_requires() );
		$this->assertSame( '6.9', $feature->get_wp_tested() );
	}

	public function test_to_array_produces_expected_shape(): void {
		$feature = Catalog_Feature::from_array( $this->plugin_data );
		$result  = $feature->to_array();

		$this->assertSame( 'kadence-security', $result['slug'] );
		$this->assertSame( 'plugin', $result['kind'] );
		$this->assertSame( 'pro', $result['minimum_tier'] );
		$this->assertSame( 'kadence-security-pro', $result['top_dir'] );
		$this->assertSame( 'kadence-security-pro.php', $result['main_file'] );
		$this->assertSame( 'kadence-security-pro/kadence-security-pro.php', $result['plugin_file'] );
		$this->assertNull( $result['wporg_slug'] );
		$this->assertSame( '6.6', $result['wp_requires'] );
		$this->assertSame( '7.4', $result['php_requires'] );
		$this->assertSame( '6.9', $result['wp_tested'] );
	}

	public function test_round_trip(): void {
		$feature = Catalog_Feature::from_array( $this->plugin_data );
		$second  = Catalog_Feature::from_array( $feature->to_array() );

		$this->assertSame( $feature->to_array(), $second->to_array() );
	}

	public function test_nullable_fields_default_when_missing(): void {
		$data = [
			'slug'         => 'patchstack',
			'kind'         => 'plugin',
			'minimum_tier' => 'pro',
			'name'         => 'PatchStack Firewall',
			'description'  => 'Virtual patching.',
			'category'     => 'security',
		];

		$feature = Catalog_Feature::from_array( $data );

		$this->assertNull( $feature->get_plugin_file() );
		$this->assertNull( $feature->get_wporg_slug() );
		$this->assertNull( $feature->get_authors() );
		$this->assertNull( $feature->get_version() );
		$this->assertNull( $feature->get_release_date() );
		$this->assertNull( $feature->get_changelog() );
		$this->assertNull( $feature->get_wp_requires() );
		$this->assertNull( $feature->get_php_requires() );
		$this->assertNull( $feature->get_wp_tested() );
	}

	public function test_top_dir_and_main_file_accessors(): void {
		$feature = Catalog_Feature::from_array( $this->plugin_data );

		$this->assertSame( 'kadence-security-pro', $feature->get_top_dir() );
		$this->assertSame( 'kadence-security-pro.php', $feature->get_main_file() );
		$this->assertSame(
			$feature->get_top_dir() . '/' . $feature->get_main_file(),
			$feature->get_plugin_file()
		);
	}

	/**
	 * @dataProvider partial_plugin_file_cases
	 *
	 * @param array<string, mixed> $overrides
	 */
	public function test_plugin_file_is_null_when_either_part_is_missing_or_empty( array $overrides ): void {
		$data = array_merge(
			[
				'slug'         => 'partial',
				'kind'         => 'plugin',
				'minimum_tier' => 'free',
				'name'         => 'Partial',
				'description'  => '',
				'category'     => '',
			],
			$overrides
		);

		$feature = Catalog_Feature::from_array( $data );

		$this->assertNull( $feature->get_plugin_file() );
	}

	/**
	 * @return Generator<string, array{0: array<string, mixed>}>
	 */
	public function partial_plugin_file_cases(): Generator {
		yield 'only top_dir'    => [ [ 'top_dir' => 'give' ] ];
		yield 'only main_file'  => [ [ 'main_file' => 'give.php' ] ];
		yield 'empty top_dir'   => [
			[
				'top_dir'   => '',
				'main_file' => 'give.php',
			],
		];
		yield 'empty main_file' => [
			[
				'top_dir'   => 'give',
				'main_file' => '',
			],
		];
	}

	public function test_dot_org_theme(): void {
		$data = [
			'slug'         => 'kadence-theme',
			'kind'         => 'theme',
			'minimum_tier' => 'basic',
			'wporg_slug'   => 'kadence-theme',
			'name'         => 'Kadence Theme',
			'description'  => 'Starter theme for Kadence.',
			'category'     => 'core',
		];

		$feature = Catalog_Feature::from_array( $data );

		$this->assertTrue( $feature->is_wporg() );
		$this->assertSame( 'kadence-theme', $feature->get_wporg_slug() );
		$this->assertNull( $feature->get_plugin_file() );
	}
}
