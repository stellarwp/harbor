import { getLicenseBadgeType } from '@/lib/feature-utils';
import type { PluginFeature } from '@/types/api';

function makeFeature( overrides: Partial<PluginFeature> = {} ): PluginFeature {
    return {
        slug: 'give',
        name: 'GiveWP',
        description: '',
        product: 'give',
        tier: 'pro',
        type: 'plugin',
        is_available: true,
        in_catalog_tier: true,
        is_enabled: false,
        documentation_url: '',
        plugin_file: 'give/give.php',
        plugin_slug: 'give',
        authors: [],
        wporg_slug: 'give',
        is_harbor_host: false,
        ...overrides,
    };
}

describe( 'getLicenseBadgeType', () => {
    it( 'labels a free WordPress.org plugin even when its catalog tier is pro', () => {
        const feature = makeFeature( { access_sources: [ { type: 'free' } ] } );
        expect( getLicenseBadgeType( feature, false ) ).toBe( 'free' );
    } );

    it( 'keeps the free label when a legacy license also exists', () => {
        const feature = makeFeature( { access_sources: [ { type: 'free' }, { type: 'legacy' } ] } );
        expect( getLicenseBadgeType( feature, true ) ).toBe( 'free' );
    } );

    it( 'does not infer free access from the tier when resolved sources are present', () => {
        const feature = makeFeature( { tier: 'free', access_sources: [], is_available: false, in_catalog_tier: false } );
        expect( getLicenseBadgeType( feature, false ) ).toBeNull();
    } );

    it( 'retains the tier-based badge for older responses without access sources', () => {
        expect( getLicenseBadgeType( makeFeature( { tier: 'free' } ), false ) ).toBe( 'free' );
        expect( getLicenseBadgeType( makeFeature( { tier: null } ), false ) ).toBe( 'free' );
    } );

    it( 'does not label a paid purchase as free', () => {
        const feature = makeFeature( {
            access_sources: [ { type: 'purchase', product_slug: 'nps', tier: 'complete', product_name: 'Nexcess Plugin Stack', tier_name: '1 site' } ],
        } );
        expect( getLicenseBadgeType( feature, false ) ).toBeNull();
    } );

    it( 'preserves legacy and mismatch badges without a free grant', () => {
        expect( getLicenseBadgeType( makeFeature( { access_sources: [ { type: 'legacy' } ] } ), true ) ).toBe( 'legacy' );
        expect( getLicenseBadgeType( makeFeature( { access_sources: [], is_available: false } ), false ) ).toBe( 'revoked' );
        expect( getLicenseBadgeType( makeFeature( { in_catalog_tier: false } ), false ) ).toBe( 'bonus' );
    } );
} );
