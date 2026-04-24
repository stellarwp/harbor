import { buildChangePlanUrl } from '@/lib/change-plan-url';

// Realistic base URL as produced by Feature_Manager_Page — portal base + /subscriptions/.
const BASE_URL = 'https://my.liquidweb.com/subscriptions/';

describe( 'buildChangePlanUrl', () => {
    it( 'appends <product>/<tier>/change-plan/ to the subscriptions path', () => {
        const result = buildChangePlanUrl( BASE_URL, 'kadence', 'pro' );

        expect( result ).toBe( 'https://my.liquidweb.com/subscriptions/kadence/pro/change-plan/' );
    } );

    it( 'works when the base URL has no trailing slash', () => {
        const result = buildChangePlanUrl( 'https://my.liquidweb.com/subscriptions', 'givewp', 'elite' );

        expect( result ).toBe( 'https://my.liquidweb.com/subscriptions/givewp/elite/change-plan/' );
    } );

    it( 'preserves any query string on the base URL', () => {
        const baseWithQuery = `${ BASE_URL }?portal-referral=plugin&domain=example.com`;
        const url           = new URL( buildChangePlanUrl( baseWithQuery, 'kadence', 'pro' ) );

        expect( url.pathname ).toBe( '/subscriptions/kadence/pro/change-plan/' );
        expect( url.searchParams.get( 'portal-referral' ) ).toBe( 'plugin' );
        expect( url.searchParams.get( 'domain' ) ).toBe( 'example.com' );
    } );

    it( 'percent-encodes special characters in productSlug and tierSlug', () => {
        const result = buildChangePlanUrl( BASE_URL, 'give/wp', 'pro tier' );

        expect( result ).toContain( '/give%2Fwp/pro%20tier/change-plan/' );
    } );

    it( 'returns the original string unchanged when baseUrl is not a valid URL', () => {
        expect( buildChangePlanUrl( 'not-a-url', 'kadence', 'pro' ) ).toBe( 'not-a-url' );
    } );
} );
