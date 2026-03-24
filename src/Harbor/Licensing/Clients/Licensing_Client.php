<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Licensing\Clients;

use LiquidWeb\Harbor\Licensing\Results\Product_Entry;
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

}
