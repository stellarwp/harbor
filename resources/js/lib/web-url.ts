/**
 * Catalog and activation links, accepted only as http(s) so Portal data never renders as a script link.
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
