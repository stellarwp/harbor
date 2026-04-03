<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Tests\Cron\Jobs;

use LiquidWeb\Harbor\Portal\Catalog_Repository;
use LiquidWeb\Harbor\Cron\Jobs\Refresh_Catalog_Job;
use LiquidWeb\Harbor\Tests\HarborTestCase;

final class Refresh_Catalog_JobTest extends HarborTestCase {

	public function test_run_calls_catalog_refresh(): void {
		$called = false;

		$catalog = $this->makeEmpty(
			Catalog_Repository::class,
			[
				'refresh' => static function () use ( &$called ) {
					$called = true;
				},
			]
		);

		$job = new Refresh_Catalog_Job( $catalog );
		$job->run();

		$this->assertTrue( $called );
	}
}
