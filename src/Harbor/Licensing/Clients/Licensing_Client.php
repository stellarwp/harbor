<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Licensing\Clients;

use LiquidWeb\Harbor\Licensing\Results\Product_Entry;
use LiquidWeb\Harbor\Licensing\Results\Validation_Result;
use WP_Error;

/**
 * Contract for the v4 licensing API client.
 *
 * @since 1.0.0
 */
interface Licensing_Client {

	/**
	 * Fetch the products associated with a license and domain.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key    License key.
	 * @param string $domain Site domain.
	 *
	 * @return Product_Entry[]|WP_Error
	 */
	public function get_products( string $key, string $domain );

	/**
	 * Validate a license for a specific product on a domain.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key          License key.
	 * @param string $domain       Site domain.
	 * @param string $product_slug Product identifier.
	 *
	 * @return Validation_Result|WP_Error
	 */
	public function validate( string $key, string $domain, string $product_slug );
}
