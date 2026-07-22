<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Admin;

use LiquidWeb\Harbor\Config;
use LiquidWeb\Harbor\Harbor;
use LiquidWeb\Harbor\Licensing\License_Manager;
use LiquidWeb\Harbor\Portal\Activation_Url;
use LiquidWeb\Harbor\Site\Data;
use LiquidWeb\Harbor\Utils\License_Key;
use LiquidWeb\Harbor\Utils\Version;

/**
 * Manages the unified feature manager admin page.
 *
 * @since 1.0.0
 *
 * @package LiquidWeb\Harbor
 */
class Feature_Manager_Page {

	/**
	 * The admin page slug.
	 *
	 * @since 1.0.0
	 */
	public const PAGE_SLUG = 'lw-software-manager';

	/**
	 * Site data provider.
	 *
	 * @since 1.0.0
	 *
	 * @var Data
	 */
	private Data $site_data;

	/**
	 * License manager.
	 *
	 * @since 1.0.0
	 *
	 * @var License_Manager
	 */
	private License_Manager $license_manager;

	/**
	 * Portal activation URL builder.
	 *
	 * @since TBD
	 *
	 * @var Activation_Url
	 */
	private Activation_Url $activation_url;

	/**
	 * Hook suffix returned by add_submenu_page().
	 * Empty string until the page is registered.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private string $page_hook = '';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Data            $site_data       Site data provider.
	 * @param License_Manager $license_manager License manager.
	 * @param Activation_Url  $activation_url  Portal activation URL builder.
	 */
	public function __construct( Data $site_data, License_Manager $license_manager, Activation_Url $activation_url ) {
		$this->site_data       = $site_data;
		$this->license_manager = $license_manager;
		$this->activation_url  = $activation_url;
	}

	/**
	 * Registers the unified feature manager page if this instance is the version leader.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function maybe_register_page(): void {
		if ( ! Version::should_handle( 'admin_page' ) ) {
			return;
		}

		$this->page_hook = (string) add_submenu_page(
			'options-general.php',
			__( 'Liquid Web Software Manager', '%TEXTDOMAIN%' ),
			__( 'Liquid Web Products', '%TEXTDOMAIN%' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render' ]
		);

		/**
		 * Filters whether to hide the Liquid Web Products item from the Settings menu.
		 *
		 * Hiding the menu item does not unregister the page. The Software Manager
		 * UI remains accessible at options-general.php?page=lw-software-manager
		 * for users who reach it via a direct link or a product plugin's submenu.
		 *
		 * @since 1.1.0
		 *
		 * @param bool $hide Whether to hide the menu item. Default false.
		 *
		 * @return bool
		 */
		if ( apply_filters( 'lw-harbor/hide_menu_item', false ) ) {
			remove_submenu_page( 'options-general.php', self::PAGE_SLUG );
		}

		add_action( 'admin_enqueue_scripts', [ $this, 'maybe_enqueue_assets' ] );
	}

	/**
	 * Enqueues the React Feature Manager UI assets only on the lw-software-manager page.
	 *
	 * Called on admin_enqueue_scripts. The hook suffix is compared against
	 * $this->page_hook — the value returned by add_menu_page() — to ensure
	 * the React bundle is loaded only on this specific admin page.
	 *
	 * @since 1.0.0
	 * @since 1.4.0 Dropped string type-hint for $hook_suffix.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 *
	 * @return void
	 */
	public function maybe_enqueue_assets( $hook_suffix ): void {
		if ( $hook_suffix !== $this->page_hook ) {
			return;
		}

		$this->enqueue_assets();
	}

	/**
	 * Registers and enqueues the React Feature Manager UI JS and CSS.
	 *
	 * Loads from build-dev/ when WP_DEBUG is true (source maps included),
	 * from build/ otherwise (minified, no source maps).
	 *
	 * Path resolution from this file:
	 *   __DIR__                               → src/Harbor/Admin
	 *   dirname(__DIR__)                      → src/Harbor
	 *   dirname(dirname(__DIR__))             → src
	 *   dirname(dirname(dirname(__DIR__)))    → plugin root (harbor/)
	 *
	 * @since 1.3.0   Expose License_Key::PREFIX to the React app via the localized payload.
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function enqueue_assets(): void {
		$build_dir       = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? 'build-dev' : 'build';
		$plugin_root_dir = dirname( dirname( dirname( __DIR__ ) ) );
		$plugin_root_url = trailingslashit(
			plugin_dir_url( $plugin_root_dir . '/index.php' )
		);
		$handle          = 'lw-harbor-ui';

		// Load asset file for dependencies and version.
		$asset_file = $plugin_root_dir . '/' . $build_dir . '/index.asset.php';

		/** @var array{dependencies: array<string>, version: string} $asset_data */
		$asset_data = file_exists( $asset_file ) ? require $asset_file : [
			'dependencies' => [],
			'version'      => null,
		];

		wp_register_script(
			$handle,
			$plugin_root_url . $build_dir . '/index.js',
			$asset_data['dependencies'],
			$asset_data['version'],
			[ 'in_footer' => true ]
		);

		wp_localize_script(
			$handle,
			'harborData',
			[
				'restUrl'          => rest_url( 'liquidweb/harbor/v1/' ),
				'nonce'            => wp_create_nonce( 'wp_rest' ),
				'pluginsUrl'       => admin_url( 'plugins.php' ),
				'activationUrl'    => $this->activation_url->get_base(),
				'subscriptionsUrl' => Config::get_portal_base_url() . '/subscriptions/',
				'domain'           => $this->site_data->get_domain(),
				'version'          => Harbor::VERSION,
				'licenseKeyPrefix' => License_Key::PREFIX,
			]
		);

		wp_register_style(
			$handle,
			$plugin_root_url . $build_dir . '/index.css',
			[],
			null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- version is content-hashed into the asset filename by the build pipeline.
		);

		wp_set_script_translations( $handle, '%TEXTDOMAIN%' );
		wp_enqueue_script( $handle );
		wp_enqueue_style( $handle );
	}

	/**
	 * Renders the unified feature manager page.
	 *
	 * Outputs the React application mount point. The React bundle
	 * (index.js + index.css) is registered and enqueued by enqueue_assets(),
	 * called via maybe_enqueue_assets() on admin_enqueue_scripts.
	 *
	 * The .lw-harbor-ui class activates CSS scoping for Tailwind styles,
	 * preventing conflicts with WordPress Admin global styles.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render(): void {
		// Store the embedded key if present.
		// This is a fallback for when the plugin containing LWSW_KEY.php is itself being
		// activated — Harbor isn't initialized during that request so the
		// activated_plugin listener above never runs.
		$this->license_manager->store_embedded_key_if_present();

		?>
		<div class="wrap">
			<div id="lw-harbor-root" class="lw-harbor-ui"></div>
		</div>
		<?php
	}

}
