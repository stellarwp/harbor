<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Catalog\Results;

use LiquidWeb\Harbor\Portal\Results\Catalog_Feature;
use LiquidWeb\Harbor\Tests\HarborTestCase;

final class Catalog_FeatureTest extends HarborTestCase {

	private array $plugin_data = [
		'slug'              => 'kadence-security',
		'kind'              => 'plugin',
		'minimum_tier'      => 'pro',
		'main_file'         => 'kadence-security-pro/kadence-security-pro.php',
		'wporg_slug'        => null,
		'version'           => '2.1.0',
		'release_date'      => '2025-11-15',
		'changelog'         => '<h4>2.1.0</h4><ul><li>Bug fixes.</li></ul>',
		'name'              => 'Kadence Security Pro',
		'description'       => 'WordPress security hardening and monitoring.',
		'category'          => 'security',
		'authors'           => [ 'KadenceWP' ],
		'documentation_url' => 'https://www.kadencewp.com/help-center/',
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
	}

	public function test_to_array_produces_expected_shape(): void {
		$feature = Catalog_Feature::from_array( $this->plugin_data );
		$result  = $feature->to_array();

		$this->assertSame( 'kadence-security', $result['slug'] );
		$this->assertSame( 'plugin', $result['kind'] );
		$this->assertSame( 'pro', $result['minimum_tier'] );
		$this->assertSame( 'kadence-security-pro/kadence-security-pro.php', $result['plugin_file'] );
		$this->assertNull( $result['wporg_slug'] );
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
