import { groupLicenseProducts } from '@/lib/group-license-products';
import type { LicenseProduct } from '@/types/api';

const RANK_MAP = {
    free:       0,
    essentials: 1,
    pro:        2,
    elite:      3,
};

function makeTier( product_slug: string, tier: string, overrides: Partial<LicenseProduct> = {} ): LicenseProduct {
    return {
        product_slug,
        tier,
        status:      'licensed',
        expires:     '2027-01-01',
        activations: { site_limit: 1, active_count: 1, over_limit: false, domains: [] },
        capabilities: [],
        ...overrides,
    };
}

describe( 'groupLicenseProducts', () => {
    it( 'returns an empty array when licenseProducts is empty', () => {
        expect( groupLicenseProducts( [], RANK_MAP ) ).toEqual( [] );
    } );

    it( 'groups a single product with a single tier', () => {
        const lp     = makeTier( 'give', 'elite' );
        const result = groupLicenseProducts( [ lp ], RANK_MAP );

        expect( result ).toHaveLength( 1 );
        expect( result[ 0 ].productSlug ).toBe( 'give' );
        expect( result[ 0 ].productName ).toBe( 'GiveWP' );
        expect( result[ 0 ].tiers ).toEqual( [ lp ] );
    } );

    it( 'groups multiple tiers for the same product into one card', () => {
        const free  = makeTier( 'give', 'free' );
        const elite = makeTier( 'give', 'elite' );
        const result = groupLicenseProducts( [ free, elite ], RANK_MAP );

        expect( result ).toHaveLength( 1 );
        expect( result[ 0 ].tiers ).toHaveLength( 2 );
    } );

    it( 'sorts tiers within a group ascending by rank', () => {
        const elite = makeTier( 'give', 'elite' );
        const free  = makeTier( 'give', 'free' );
        const pro   = makeTier( 'give', 'pro' );
        const result = groupLicenseProducts( [ elite, free, pro ], RANK_MAP );

        expect( result[ 0 ].tiers.map( ( t ) => t.tier ) ).toEqual( [ 'free', 'pro', 'elite' ] );
    } );

    it( 'orders product groups by the PRODUCTS constant', () => {
        const kadence = makeTier( 'kadence', 'pro' );
        const give    = makeTier( 'give', 'elite' );
        const result  = groupLicenseProducts( [ kadence, give ], RANK_MAP );

        expect( result.map( ( g ) => g.productSlug ) ).toEqual( [ 'give', 'kadence' ] );
    } );

    it( 'omits products present in licenseProducts but absent from the PRODUCTS constant', () => {
        const unknown = makeTier( 'unknown-plugin', 'pro' );
        const give    = makeTier( 'give', 'elite' );
        const result  = groupLicenseProducts( [ unknown, give ], RANK_MAP );

        expect( result ).toHaveLength( 1 );
        expect( result[ 0 ].productSlug ).toBe( 'give' );
    } );

    it( 'omits products from the PRODUCTS constant that have no license entries', () => {
        const give   = makeTier( 'give', 'elite' );
        const result = groupLicenseProducts( [ give ], RANK_MAP );

        const slugs = result.map( ( g ) => g.productSlug );
        expect( slugs ).not.toContain( 'kadence' );
        expect( slugs ).not.toContain( 'learndash' );
        expect( slugs ).not.toContain( 'the-events-calendar' );
    } );

    it( 'treats tiers with no rank entry in tierRankMap as rank 0', () => {
        const known   = makeTier( 'give', 'elite' );
        const unknown = makeTier( 'give', 'mystery-tier' );
        const result  = groupLicenseProducts( [ known, unknown ], RANK_MAP );

        // mystery-tier has no entry → rank 0, sorts before elite (rank 3)
        expect( result[ 0 ].tiers[ 0 ].tier ).toBe( 'mystery-tier' );
        expect( result[ 0 ].tiers[ 1 ].tier ).toBe( 'elite' );
    } );

    it( 'handles all four known products when all are licensed', () => {
        const lps = [
            makeTier( 'kadence', 'pro' ),
            makeTier( 'learndash', 'pro' ),
            makeTier( 'the-events-calendar', 'pro' ),
            makeTier( 'give', 'pro' ),
        ];
        const result = groupLicenseProducts( lps, RANK_MAP );

        expect( result.map( ( g ) => g.productSlug ) ).toEqual( [
            'give',
            'the-events-calendar',
            'learndash',
            'kadence',
        ] );
    } );
} );
