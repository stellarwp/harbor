<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Licensing;

use LiquidWeb\Harbor\Admin\Feature_Manager_Page;
use LiquidWeb\Harbor\Config;
use LiquidWeb\Harbor\Contracts\Abstract_Provider;
use LiquidWeb\Harbor\Harbor;
use LiquidWeb\Harbor\Licensing\Registry\Product_Registry;
use LiquidWeb\Harbor\Licensing\Repositories\License_Repository;
use LiquidWeb\Harbor\Utils\Cast;
use LiquidWeb\LicensingApiClient\Config as LicensingConfig;
use LiquidWeb\LicensingApiClient\Contracts\LicensingClientInterface;
use LiquidWeb\LicensingApiClientWordPress\Http\WordPressHttpClient;
use LiquidWeb\LicensingApiClientWordPress\WordPressApiFactory;
use Nyholm\Psr7\Factory\Psr17Factory;

/**
 * Registers the Licensing subsystem in the DI container.
 *
 * @since 1.0.0
 */
final class Provider extends Abstract_Provider {

	/**
	 * @inheritDoc
	 */
	public function register(): void {
		$this->container->singleton(
			LicensingClientInterface::class,
			function () {
				$psr17   = $this->container->get( Psr17Factory::class );
				$factory = new WordPressApiFactory(
					$this->container->get( WordPressHttpClient::class ),
					$psr17,
					$psr17
				);
				return $factory->make(
					new LicensingConfig(
						Config::get_licensing_base_url(),
						null,
						'lw-harbor/' . Harbor::VERSION
					)
				);
			}
		);

		$this->container->singleton( License_Repository::class );
		$this->container->singleton( Product_Registry::class );
		$this->container->singleton( License_Manager::class );

		add_action(
			'lw-harbor/unified_license_key_changed',
			function () {
				/** @var License_Repository $license_repository */
				$license_repository = $this->container->get( License_Repository::class );
				$license_repository->delete_products();
			}
		);

		add_action(
			'activated_plugin',
			function () {
				/** @var License_Manager $license_manager */
				$license_manager = $this->container->get( License_Manager::class );
				$license_manager->store_embedded_key_if_present();
			}
		);

		// Fallback for when the plugin containing LWSW_KEY.php is itself being
		// activated — Harbor isn't initialized during that request so the
		// activated_plugin listener above never runs. Scoped to the software
		// manager page to avoid scanning on every admin request.
		add_action(
			'admin_init',
			function () {
				if ( sanitize_text_field( wp_unslash( Cast::to_string( $_GET['page'] ?? '' ) ) ) !== Feature_Manager_Page::PAGE_SLUG ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No nonce needed for this GET parameter.
					return;
				}

				/** @var License_Manager $license_manager */
				$license_manager = $this->container->get( License_Manager::class );
				$license_manager->store_embedded_key_if_present();
			}
		);
	}
}
