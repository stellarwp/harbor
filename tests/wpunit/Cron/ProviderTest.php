<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Cron;

use LiquidWeb\Harbor\Portal\Catalog_Collection;
use LiquidWeb\Harbor\Portal\Catalog_Repository;
use LiquidWeb\Harbor\Portal\Clients\Portal_Client;
use LiquidWeb\Harbor\Cron\Actions\Handle_Unschedule_Cron_Data_Refresh;
use LiquidWeb\Harbor\Cron\Jobs\Refresh_Catalog_Job;
use LiquidWeb\Harbor\Cron\Jobs\Refresh_License_Job;
use LiquidWeb\Harbor\Cron\ValueObjects\CronHook;
use LiquidWeb\Harbor\Tests\HarborTestCase;
use LiquidWeb\LicensingApiClient\Contracts\LicensingClientInterface;

final class ProviderTest extends HarborTestCase {

	protected function setUp(): void {
		parent::setUp();

		// WPTestCase shallow-copies wp_filter, so closures that capture
		// $this->container from previous tests' Harbor::init() accumulate.
		// Clear the hooks this test exercises and re-add with the current container.
		remove_all_actions( 'deactivated_plugin' );
		remove_all_actions( 'switch_theme' );

		add_action(
			'deactivated_plugin',
			function () {
				$this->container->get( Handle_Unschedule_Cron_Data_Refresh::class )();
			}
		);
		add_action(
			'switch_theme',
			function () {
				$this->container->get( Handle_Unschedule_Cron_Data_Refresh::class )();
			}
		);

		wp_clear_scheduled_hook( CronHook::DATA_REFRESH );

		$this->container->singleton(
			Portal_Client::class,
			$this->makeEmpty( Portal_Client::class )
		);

		$this->container->singleton(
			LicensingClientInterface::class,
			$this->makeEmpty( LicensingClientInterface::class )
		);
	}

	public function test_it_registers_refresh_catalog_job(): void {
		$this->assertInstanceOf(
			Refresh_Catalog_Job::class,
			$this->container->get( Refresh_Catalog_Job::class )
		);
	}

	public function test_it_registers_refresh_license_job(): void {
		$this->assertInstanceOf(
			Refresh_License_Job::class,
			$this->container->get( Refresh_License_Job::class )
		);
	}

	public function test_it_registers_handle_unschedule_action(): void {
		$this->assertInstanceOf(
			Handle_Unschedule_Cron_Data_Refresh::class,
			$this->container->get( Handle_Unschedule_Cron_Data_Refresh::class )
		);
	}

	public function test_jobs_are_singletons(): void {
		$this->assertSame(
			$this->container->get( Refresh_Catalog_Job::class ),
			$this->container->get( Refresh_Catalog_Job::class )
		);

		$this->assertSame(
			$this->container->get( Refresh_License_Job::class ),
			$this->container->get( Refresh_License_Job::class )
		);
	}

	public function test_cron_is_scheduled_on_init(): void {
		$this->assertFalse( wp_next_scheduled( CronHook::DATA_REFRESH ) );

		do_action( 'init' );

		$this->assertNotFalse( wp_next_scheduled( CronHook::DATA_REFRESH ) );
	}

	public function test_cron_unscheduled_when_no_catalog_plugins_remain_active(): void {
		$this->store_catalog_with_plugin( 'give/give.php' );
		wp_schedule_event( time(), 'twicedaily', CronHook::DATA_REFRESH );

		update_option( 'active_plugins', [] );

		do_action( 'deactivated_plugin', 'give/give.php' );

		$this->assertFalse( wp_next_scheduled( CronHook::DATA_REFRESH ) );
	}

	public function test_cron_not_unscheduled_when_catalog_plugin_still_active(): void {
		$this->store_catalog_with_plugin( 'give/give.php' );
		wp_schedule_event( time(), 'twicedaily', CronHook::DATA_REFRESH );

		update_option( 'active_plugins', [ 'give/give.php' ] );

		do_action( 'deactivated_plugin', 'some-other-plugin/plugin.php' );

		$this->assertNotFalse( wp_next_scheduled( CronHook::DATA_REFRESH ) );
	}

	public function test_cron_not_unscheduled_when_catalog_has_no_plugin_features(): void {
		$this->container->get( Catalog_Repository::class )->set_catalog(
			Catalog_Collection::from_array( [] )
		);
		wp_schedule_event( time(), 'twicedaily', CronHook::DATA_REFRESH );

		do_action( 'deactivated_plugin', 'any-plugin/plugin.php' );

		$this->assertNotFalse( wp_next_scheduled( CronHook::DATA_REFRESH ) );
	}

	/**
	 * Store a minimal catalog containing one plugin feature with the given plugin file.
	 *
	 * @param string $plugin_file Plugin basename, e.g. 'give/give.php'.
	 *
	 * @return void
	 */
	private function store_catalog_with_plugin( string $plugin_file ): void {
		$catalog = Catalog_Collection::from_array(
			[
				[
					'product_slug' => 'test-product',
					'tiers'        => [],
					'features'     => [
						[
							'slug'              => 'test-feature',
							'kind'              => 'plugin',
							'minimum_tier'      => '',
							'main_file'         => $plugin_file,
							'wporg_slug'        => null,
							'name'              => 'Test Feature',
							'description'       => '',
							'category'          => '',
							'documentation_url' => '',
						],
					],
				],
			] 
		);

		$this->container->get( Catalog_Repository::class )->set_catalog( $catalog );
	}
}
