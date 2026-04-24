import { buildActivationUrl } from '@/lib/activation-url';

// Realistic base URL as produced by the server.
// redirect_url is percent-encoded; its decoded value is a WP admin URL that
// itself carries ?page=lw-software-manager&refresh=auto as its own params.
// refresh=auto is NOT a top-level param — it lives inside redirect_url.
const BASE_URL =
    'https://my.liquidweb.com/subscriptions/' +
    '?portal-referral=plugin' +
    '&redirect_url=https%3A%2F%2Fexample.com%2Fwp-admin%2Fadmin.php%3Fpage%3Dlw-software-manager%26refresh%3Dauto' +
    '&domain=example.com';

describe( 'buildActivationUrl', () => {
    it( 'appends sku as a combined product:tier param', () => {
        const result = buildActivationUrl( BASE_URL, 'givewp', 'elite' );
        const url    = new URL( result );

        expect( url.searchParams.get( 'sku' ) ).toBe( 'givewp:elite' );
    } );

    it( 'preserves all server-supplied top-level params unchanged', () => {
        const result = buildActivationUrl( BASE_URL, 'givewp', 'elite' );
        const url    = new URL( result );

        expect( url.searchParams.get( 'portal-referral' ) ).toBe( 'plugin' );
        expect( url.searchParams.get( 'domain' ) ).toBe( 'example.com' );
        // refresh=auto is inside redirect_url, not a top-level param
        expect( url.searchParams.get( 'refresh' ) ).toBeNull();
    } );

    it( 'keeps redirect_url intact — decoded value includes refresh=auto', () => {
        const result      = buildActivationUrl( BASE_URL, 'givewp', 'elite' );
        const redirectVal = new URL( result ).searchParams.get( 'redirect_url' );

        expect( redirectVal ).toBe(
            'https://example.com/wp-admin/admin.php?page=lw-software-manager&refresh=auto'
        );
        // and remains percent-encoded in the raw string
        expect( result ).toContain(
            'redirect_url=https%3A%2F%2Fexample.com%2Fwp-admin'
        );
    } );

    it( 'does not add sku inside redirect_url', () => {
        const result      = buildActivationUrl( BASE_URL, 'givewp', 'elite' );
        const redirectVal = new URL( result ).searchParams.get( 'redirect_url' ) ?? '';

        expect( redirectVal ).not.toContain( 'sku=' );
    } );

    it( 'overwrites a stale sku param already present in the base URL', () => {
        const staleBase = BASE_URL + '&sku=old:free';
        const result    = buildActivationUrl( staleBase, 'kadence', 'plus' );

        expect( new URL( result ).searchParams.get( 'sku' ) ).toBe( 'kadence:plus' );
    } );

    it( 'returns the original string unchanged when baseUrl is not a valid URL', () => {
        expect( buildActivationUrl( 'not-a-url', 'givewp', 'elite' ) ).toBe( 'not-a-url' );
    } );

    it( 'percent-encodes special characters in productSlug and tier', () => {
        const result = buildActivationUrl( BASE_URL, 'give/wp', 'pro?tier' );
        const url    = new URL( result );

        expect( url.searchParams.get( 'sku' ) ).toBe( 'give/wp:pro?tier' );
        expect( result ).toContain( 'sku=give%2Fwp%3Apro%3Ftier' );
    } );
} );
