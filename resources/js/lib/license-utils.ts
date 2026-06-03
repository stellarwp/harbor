/**
 * Pure utility functions for license expiry display.
 *
 * @package LiquidWeb\Harbor
 */

// cspell:ignore EFGH IJKL MNOP DJJT -- illustrative masking example fragments

/**
 * @since 1.0.0
 */
export function formatDate( dateStr: string ): string {
    return new Date( dateStr ).toLocaleDateString( 'en-US', {
        year:  'numeric',
        month: 'short',
        day:   'numeric',
    } );
}

/**
 * @since 1.0.0
 */
export function getExpiryStatus( dateStr: string ): 'expired' | 'expiring-soon' | 'ok' {
    const diff = new Date( dateStr ).getTime() - Date.now();
    if ( diff <= 0 ) return 'expired';
    if ( diff <= 30 * 24 * 60 * 60 * 1000 ) return 'expiring-soon';
    return 'ok';
}

export const expiryTextClass: Record<string, string> = {
    expired:          'text-destructive font-medium',
    'expiring-soon':  'text-amber-600 font-medium',
    ok:               'text-muted-foreground',
};

/**
 * Masks a unified license key for display so the full key is never exposed
 * on screen. The leading prefix segment and the final segment stay visible;
 * every segment in between is replaced with X's of matching length, e.g.
 * `LWSW-ABCD-EFGH-IJKL-MNOP-DJJT` becomes `LWSW-XXXX-XXXX-XXXX-XXXX-DJJT`.
 *
 * Keys with two or fewer dash-delimited segments have nothing safe to mask
 * (no middle to hide), so they are returned unchanged.
 *
 * @since TBD
 */
export function maskLicenseKey( key: string ): string {
    const segments = key.split( '-' );

    if ( segments.length <= 2 ) {
        return key;
    }

    return segments
        .map( ( segment, index ) => {
            const isFirst = index === 0;
            const isLast  = index === segments.length - 1;
            return isFirst || isLast ? segment : 'X'.repeat( segment.length );
        } )
        .join( '-' );
}
