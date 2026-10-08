import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useSelect } from '@wordpress/data';
import { useFilter } from '@/context/filter-context';
import { useProductFeatureGroups } from '@/hooks/useProductFeatureGroups';
import { ProductSection } from '@/components/organisms/ProductSection';
import type { CatalogTier, LicenseProduct, PluginFeature } from '@/types/api';

jest.mock( '@wordpress/data', () => ( { useSelect: jest.fn() } ) );
jest.mock( '@/store', () => ( { store: { name: 'harbor' } } ) );
jest.mock( '@/context/filter-context', () => ( { useFilter: jest.fn() } ) );
jest.mock( '@/hooks/useProductFeatureGroups', () => ( { useProductFeatureGroups: jest.fn() } ) );
jest.mock( '@/lib/harbor-data', () => ( {
    getHarborDataValue: ( key: string ) => key === 'domain' ? 'customer.example' : 'https://portal.example/activate',
} ) );
jest.mock( '@/components/molecules/FeatureRow', () => ( {
    FeatureRow: ( { feature }: { feature: PluginFeature } ) => <div>{ feature.name }</div>,
} ) );

const product = { slug: 'learndash', name: 'LearnDash', tagline: '' };
const tier: CatalogTier = {
    tier_slug: 'pro', name: 'Pro', rank: 2, price: 299, currency: 'USD', herald_slugs: [ 'sfwd-lms' ],
    purchase_url: 'https://portal.example/checkout/?variation_id=12',
    upgrade_url: 'https://portal.example/change-plan/learndash/pro/',
};
const feature = { slug: 'sfwd-lms', name: 'LearnDash LMS', tier: 'pro', is_enabled: false } as PluginFeature;
const purchase = { product_slug: 'learndash', tier: 'essentials', activated_here: true, validation_status: 'valid' } as LicenseProduct;

/**
 * Keep the actual tier accordion and purchase link while supplying resolved store data.
 */
function configure( overrides: Partial<CatalogTier> = {}, owned: LicenseProduct | null = null, unactivated = false ) {
    const catalogTier = { ...tier, ...overrides };
    ( useFilter as jest.Mock ).mockReturnValue( { searchQuery: '' } );
    ( useSelect as jest.Mock ).mockReturnValue( {
        licenseProduct: unactivated ? null : owned,
        unactivatedLicenseProduct: unactivated ? owned : null,
        unactivatedLicenseProducts: unactivated && owned ? [ owned ] : [],
    } );
    ( useProductFeatureGroups as jest.Mock ).mockReturnValue( {
        availableFeatures: [],
        lockedByTier: { pro: [ feature ] },
        sortedCatalogTiers: [ catalogTier ],
        upgradeCatalogTiers: unactivated ? [] : [ catalogTier ],
        activationCatalogTiers: unactivated ? [ catalogTier ] : [],
        isUnactivatedLicense: unactivated,
    } );
}

describe( 'ProductSection catalog actions', () => {
    it( 'uses the catalog purchase URL and tier name for a new customer', () => {
        configure( { name: 'Professional' } );
        render( <ProductSection product={ product } /> );
        expect( screen.getByRole( 'link', { name: 'Upgrade to Professional' } ).getAttribute( 'href' ) ).toBe( tier.purchase_url );
    } );

    it( 'uses the upgrade URL with site context for an existing customer', () => {
        configure( {}, purchase );
        render( <ProductSection product={ product } /> );
        const url = new URL( screen.getByRole( 'link', { name: 'Upgrade to Pro' } ).getAttribute( 'href' )! );
        expect( url.origin + url.pathname ).toBe( tier.upgrade_url );
        expect( url.searchParams.get( 'domain' ) ).toBe( 'customer.example' );
        expect( url.searchParams.get( 'portal-referral' ) ).toBe( 'plugin' );
    } );

    it.each( [ null, purchase ] )( 'hides retired tier links while preserving its feature list, purchase: %j', async ( owned ) => {
        configure( { purchase_url: '', upgrade_url: '' }, owned );
        render( <ProductSection product={ product } /> );
        expect( screen.queryByRole( 'link' ) ).toBeNull();
        await userEvent.setup().click( screen.getByText( 'Pro Features' ) );
        expect( screen.getByText( 'LearnDash LMS' ) ).not.toBeNull();
    } );

    it( 'does not substitute a purchase link when an existing customer cannot upgrade', () => {
        configure( { upgrade_url: '' }, purchase );
        render( <ProductSection product={ product } /> );
        expect( screen.queryByRole( 'link' ) ).toBeNull();
    } );

    it( 'does not substitute an upgrade link when new purchases are disabled', () => {
        configure( { purchase_url: '' } );
        render( <ProductSection product={ product } /> );
        expect( screen.queryByRole( 'link' ) ).toBeNull();
    } );

    it( 'keeps activation available for an owned plan after its sales links are removed', () => {
        configure( { purchase_url: '', upgrade_url: '' }, { ...purchase, tier: 'pro', activated_here: false }, true );
        render( <ProductSection product={ product } /> );
        expect( screen.getByRole( 'link', { name: 'Activate plan' } ) ).not.toBeNull();
        expect( screen.getByText( 'Unactivated' ) ).not.toBeNull();
        expect( screen.queryByRole( 'link', { name: /Upgrade/ } ) ).toBeNull();
    } );

    it( 'preserves expanded feature details when a brand is collapsed and reopened', async () => {
        configure();
        render( <ProductSection product={ product } /> );
        const user = userEvent.setup();
        await user.click( screen.getByText( 'Pro Features' ) );
        const heading = screen.getByRole( 'button', { name: 'LearnDash features' } );
        await user.click( heading );
        expect( heading.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
        expect( screen.getByText( 'LearnDash LMS' ).closest( '[hidden]' ) ).not.toBeNull();
        await user.click( heading );
        expect( screen.getByText( 'LearnDash LMS' ).closest( '[hidden]' ) ).toBeNull();
    } );

    it( 'opens a collapsed brand for a search and restores its previous state afterward', async () => {
        configure();
        const { rerender } = render( <ProductSection product={ product } /> );
        const heading = screen.getByRole( 'button', { name: 'LearnDash features' } );
        await userEvent.setup().click( heading );
        ( useFilter as jest.Mock ).mockReturnValue( { searchQuery: 'LearnDash' } );
        rerender( <ProductSection product={ product } /> );
        expect( heading.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
        expect( screen.getByText( 'LearnDash LMS' ).closest( '[hidden]' ) ).toBeNull();
        ( useFilter as jest.Mock ).mockReturnValue( { searchQuery: '' } );
        rerender( <ProductSection product={ product } /> );
        expect( heading.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
    } );
} );
