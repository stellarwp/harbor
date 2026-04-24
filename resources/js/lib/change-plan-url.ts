/**
 * Builds a Commerce Portal change-plan URL for an existing subscription.
 *
 * Used when an upgrade CTA needs to drive a licensed customer to their
 * existing subscription's change-plan flow, rather than adding a brand-new
 * plan to the basket via the catalog's purchase_url.
 *
 * The portal resolves the subscription from the authenticated session, so
 * only the product and tier slugs appear in the path.
 *
 * Example:
 *   base        = https://my.software.stellarwp.com/subscriptions/
 *   productSlug = kadence
 *   tierSlug    = pro
 *   → https://my.software.stellarwp.com/subscriptions/kadence/pro/change-plan/
 *
 * @param baseUrl     The subscriptionsUrl string from window.harborData. May
 *                    include a trailing slash and query string.
 * @param productSlug e.g. "kadence"
 * @param tierSlug    e.g. "pro"
 *
 * @since 1.0.0
 */
export function buildChangePlanUrl(
    baseUrl:     string,
    productSlug: string,
    tierSlug:    string,
): string {
    try {
        const url    = new URL( baseUrl );
        const prefix = url.pathname.endsWith( '/' ) ? url.pathname : `${ url.pathname }/`;
        url.pathname = `${ prefix }${ encodeURIComponent( productSlug ) }/${ encodeURIComponent( tierSlug ) }/change-plan/`;
        return url.toString();
    } catch {
        return baseUrl;
    }
}
