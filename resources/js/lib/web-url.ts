/**
 * Links Harbor renders from server data, accepted only as http(s).
 *
 * Catalog and activation URLs arrive from the Portal and become wp-admin links.
 * Rejecting every other scheme keeps a malformed or hostile value from ever
 * rendering as a script or data link.
 *
 * @package LiquidWeb\Harbor
 */

/**
 * Parse a link, or return undefined when it is missing or not http(s).
 */
export function parseWebUrl( href: string | null | undefined ): URL | undefined {
    const trimmed = href?.trim();
    if ( ! trimmed ) {
        return undefined;
    }

    try {
        const url = new URL( trimmed );
        return url.protocol === 'https:' || url.protocol === 'http:' ? url : undefined;
    } catch {
        return undefined;
    }
}

/**
 * The trimmed link when it is http(s), otherwise undefined.
 */
export function webUrl( href: string | null | undefined ): string | undefined {
    return parseWebUrl( href ) ? href?.trim() : undefined;
}
