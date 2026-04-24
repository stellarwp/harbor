/**
 * Appends product and tier params to the base activation URL supplied by the
 * API, producing a product-scoped URL the Liquid Web portal can use to
 * pre-select the right product and tier.
 *
 * The base URL is already fully assembled by the server and includes params
 * such as portal-referral, redirect_url (percent-encoded), refresh, and
 * domain. This function only adds the two params it owns and never touches
 * the others.
 *
 * Example base URL from the API:
 *   https://my.liquidweb.com/subscriptions/?portal-referral=plugin
 *     &redirect_url=https%3A%2F%2Fexample.com%2Fwp-admin%2Fadmin.php%3Fpage%3Dlw-software-manager%26refresh%3Dauto
 *     &domain=example.com
 *
 * @param baseUrl     The raw activationUrl string from the API.
 * @param productSlug e.g. "givewp"
 * @param tier        e.g. "elite"
 *
 * @since 1.0.0
 */
export function buildActivationUrl(
    baseUrl:     string,
    productSlug: string,
    tier:        string,
): string {
    try {
        const url = new URL( baseUrl );
        url.searchParams.set( 'sku', `${ productSlug }:${ tier }` );
        return url.toString();
    } catch {
        return baseUrl;
    }
}
