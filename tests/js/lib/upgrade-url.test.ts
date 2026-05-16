import { buildUpgradeUrl } from '@/lib/upgrade-url';

const BASE_URL = 'https://my.liquidweb.com/upgrade/kadence/pro/';

describe( 'buildUpgradeUrl', () => {
    it( 'appends domain and portal-referral as query parameters', () => {
        const url = new URL( buildUpgradeUrl( BASE_URL, 'example.com' ) );

        expect( url.searchParams.get( 'domain' ) ).toBe( 'example.com' );
        expect( url.searchParams.get( 'portal-referral' ) ).toBe( 'plugin' );
    } );

    it( 'preserves any existing query string on the base URL', () => {
        const baseWithQuery = `${ BASE_URL }?portal-referral=plugin`;
        const url           = new URL( buildUpgradeUrl( baseWithQuery, 'example.com' ) );

        expect( url.searchParams.get( 'portal-referral' ) ).toBe( 'plugin' );
        expect( url.searchParams.get( 'domain' ) ).toBe( 'example.com' );
    } );

    it( 'overwrites a stale domain param already present in the base URL', () => {
        const staleBase = `${ BASE_URL }?domain=old.example.com`;
        const result    = buildUpgradeUrl( staleBase, 'new.example.com' );

        expect( new URL( result ).searchParams.get( 'domain' ) ).toBe( 'new.example.com' );
    } );

    it( 'returns the base URL unchanged when domain is null', () => {
        expect( buildUpgradeUrl( BASE_URL, null ) ).toBe( BASE_URL );
    } );

    it( 'returns the base URL unchanged when domain is an empty string', () => {
        expect( buildUpgradeUrl( BASE_URL, '' ) ).toBe( BASE_URL );
    } );

    it( 'returns the original string unchanged when baseUrl is not a valid URL', () => {
        expect( buildUpgradeUrl( 'not-a-url', 'example.com' ) ).toBe( 'not-a-url' );
    } );
} );
