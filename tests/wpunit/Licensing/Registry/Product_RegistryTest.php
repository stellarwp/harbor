<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Licensing\Registry;

use LiquidWeb\Harbor\Licensing\Registry\Product_Registry;
use LiquidWeb\Harbor\Tests\HarborTestCase;

/**
 * @since 1.0.0
 */
final class Product_RegistryTest extends HarborTestCase {

	/** @var string[] */
	private array $temp_dirs = [];

	protected function tearDown(): void {
		foreach ( $this->temp_dirs as $dir ) {
			$key_file = $dir . '/' . Product_Registry::KEY_FILE;
			if ( file_exists( $key_file ) ) {
				unlink( $key_file );
			}
			if ( is_dir( $dir ) ) {
				rmdir( $dir );
			}
		}
		$this->temp_dirs = [];
		parent::tearDown();
	}

	/**
	 * Creates a temporary plugin directory and optionally writes an LWSW_KEY.php file into it.
	 *
	 * @param string $key_content PHP source to write, or empty string to skip creating the file.
	 *
	 * @return string The directory path.
	 */
	private function make_plugin_dir( string $key_content = '' ): string {
		$dir = sys_get_temp_dir() . '/harbor_test_' . uniqid();
		mkdir( $dir, 0755, true );
		$this->temp_dirs[] = $dir;

		if ( $key_content !== '' ) {
			file_put_contents( $dir . '/' . Product_Registry::KEY_FILE, $key_content );
		}

		return $dir;
	}

	public function test_first_with_embedded_key_returns_null_when_no_dirs(): void {
		$registry = new Product_Registry( [] );

		$this->assertNull( $registry->first_with_embedded_key() );
	}

	public function test_first_with_embedded_key_returns_null_when_no_key_file_present(): void {
		$dir      = $this->make_plugin_dir();
		$registry = new Product_Registry( [ $dir ] );

		$this->assertNull( $registry->first_with_embedded_key() );
	}

	public function test_first_with_embedded_key_returns_valid_key(): void {
		$dir      = $this->make_plugin_dir( '<?php return "LWSW-GIVE-KEY-2026";' );
		$registry = new Product_Registry( [ $dir ] );

		$this->assertSame( 'LWSW-GIVE-KEY-2026', $registry->first_with_embedded_key() );
	}

	public function test_first_with_embedded_key_returns_null_for_key_without_lwsw_prefix(): void {
		$dir      = $this->make_plugin_dir( '<?php return "INVALID-KEY";' );
		$registry = new Product_Registry( [ $dir ] );

		$this->assertNull( $registry->first_with_embedded_key() );
	}

	public function test_first_with_embedded_key_returns_null_when_file_returns_non_string(): void {
		$dir      = $this->make_plugin_dir( '<?php return 12345;' );
		$registry = new Product_Registry( [ $dir ] );

		$this->assertNull( $registry->first_with_embedded_key() );
	}

	public function test_first_with_embedded_key_returns_first_match(): void {
		$dir1     = $this->make_plugin_dir( '<?php return "LWSW-FIRST-KEY";' );
		$dir2     = $this->make_plugin_dir( '<?php return "LWSW-SECOND-KEY";' );
		$registry = new Product_Registry( [ $dir1, $dir2 ] );

		$this->assertSame( 'LWSW-FIRST-KEY', $registry->first_with_embedded_key() );
	}

	public function test_first_with_embedded_key_skips_dirs_without_key_file(): void {
		$dir_no_key   = $this->make_plugin_dir();
		$dir_with_key = $this->make_plugin_dir( '<?php return "LWSW-SECOND-KEY";' );
		$registry     = new Product_Registry( [ $dir_no_key, $dir_with_key ] );

		$this->assertSame( 'LWSW-SECOND-KEY', $registry->first_with_embedded_key() );
	}

	public function test_key_file_constant_is_lwsw_key_php(): void {
		$this->assertSame( 'LWSW_KEY.php', Product_Registry::KEY_FILE );
	}
}
