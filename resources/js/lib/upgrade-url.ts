/**
 * Appends portal tracking parameters to a catalog upgrade URL.
 *
 * The upgrade_url comes from the catalog tier and is a base URL without
 * site context. This function appends domain and portal-referral so the
 * portal can identify the originating site and entry point.
 *
 * Example:
 *   baseUrl = https://my.liquidweb.com/upgrade/kadence/pro/
 *   domain  = example.com
 *   → https://my.liquidweb.com/upgrade/kadence/pro/?domain=example.com&portal-referral=plugin
 *
 * @param baseUrl The upgrade_url string from the catalog tier.
 * @param domain  The site domain from getHarborDataValue( 'domain' ).
 *
 * @since 1.3.0    Domain parameter is now string | null to match the getHarborDataValue helper.
 * @since 1.0.2
 */
export function buildUpgradeUrl(
    baseUrl: string,
    domain:  string | null,
): string {
    if ( ! domain ) {
        return baseUrl;
    }
    try {
        const url = new URL( baseUrl );
        url.searchParams.set( 'domain', domain );
        url.searchParams.set( 'portal-referral', 'plugin' );
        return url.toString();
    } catch {
        return baseUrl;
    }
}
