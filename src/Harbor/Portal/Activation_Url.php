<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Portal;

use LiquidWeb\Harbor\Admin\Feature_Manager_Page;
use LiquidWeb\Harbor\Config;
use LiquidWeb\Harbor\Site\Data;

/**
 * Builds Liquid Web portal activation URLs.
 *
 * Sending a user to one of these URLs drops them into the portal's
 * subscriptions screen, from where they can activate a product against this
 * site. The portal reads the `domain` param to know which site to activate,
 * and the `redirect_url` param to know where to send the user afterwards.
 *
 * Callers supply their own return destination, so a plugin can bring the user
 * back to its own onboarding screen rather than to the Software Manager page.
 * When no destination is given, the Software Manager page is used.
 *
 * URL format:
 * `{portal_base_url}/subscriptions/?portal-referral=plugin&redirect_url={url}&domain={domain}[&sku={slug}:{tier}]`
 *
 * @since TBD
 */
final class Activation_Url {

	/**
	 * Site data provider.
	 *
	 * @since TBD
	 *
	 * @var Data
	 */
	private Data $site_data;

	/**
	 * Constructor.
	 *
	 * @since TBD
	 *
	 * @param Data $site_data Site data provider.
	 */
	public function __construct( Data $site_data ) {
		$this->site_data = $site_data;
	}

	/**
	 * Builds the base activation URL.
	 *
	 * @since TBD
	 *
	 * @param string|null $redirect_url Where the portal returns the user after
	 *                                  activating. Defaults to the Software
	 *                                  Manager page with a refresh triggered.
	 *
	 * @return string
	 */
	public function get_base( ?string $redirect_url = null ): string {
		$query = http_build_query(
			[
				'portal-referral' => 'plugin',
				'redirect_url'    => $redirect_url ?? $this->get_default_redirect_url(),
				'domain'          => $this->site_data->get_domain(),
			],
			'',
			'&',
			PHP_QUERY_RFC3986
		);

		return Config::get_portal_base_url() . '/subscriptions/?' . $query;
	}

	/**
	 * Builds an activation URL scoped to a single product and tier.
	 *
	 * The `sku` param lets the portal pre-select the right product and tier
	 * instead of dropping the user on an unfiltered subscriptions list.
	 *
	 * @since TBD
	 *
	 * @param string      $product_slug The product slug, e.g. "givewp".
	 * @param string      $tier         The tier slug, e.g. "elite".
	 * @param string|null $redirect_url Where the portal returns the user after
	 *                                  activating. Defaults to the Software
	 *                                  Manager page with a refresh triggered.
	 *
	 * @return string
	 */
	public function for_product( string $product_slug, string $tier, ?string $redirect_url = null ): string {
		$sku = http_build_query(
			[ 'sku' => $product_slug . ':' . $tier ],
			'',
			'&',
			PHP_QUERY_RFC3986
		);

		return $this->get_base( $redirect_url ) . '&' . $sku;
	}

	/**
	 * Returns the fallback destination: the Software Manager page, with a
	 * refresh so freshly activated products show up straight away.
	 *
	 * @since TBD
	 *
	 * @return string
	 */
	private function get_default_redirect_url(): string {
		return admin_url( 'admin.php?page=' . Feature_Manager_Page::PAGE_SLUG . '&refresh=auto' );
	}
}
