import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useSelect } from '@wordpress/data';
import { useFilter } from '@/context/filter-context';
import { useProductFeatureGroups } from '@/hooks/useProductFeatureGroups';
import { ProductSection } from '@/components/organisms/ProductSection';
import type { CatalogFeature, CatalogTier, LicenseProduct, PluginFeature, ProductCatalog } from '@/types/api';

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
function configure( overrides: Partial<CatalogTier> = {}, owned: LicenseProduct | null = null, unactivated = false, catalogs: ProductCatalog[] = [], packageLicenseProducts: LicenseProduct[] = [] ) {
    const catalogTier = { ...tier, ...overrides };
    ( useFilter as jest.Mock ).mockReturnValue( { searchQuery: '' } );
    ( useSelect as jest.Mock ).mockReturnValue( {
        catalogs,
        packageLicenseProducts,
        unactivatedPackage: packageLicenseProducts.find( ( entry ) => entry.activated_here === false ) ?? null,
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

const packageCatalog: ProductCatalog = {
    product_id: 'nexcess-plugin-stack', product_slug: 'nexcess-plugin-stack', product_name: 'Nexcess Plugin Stack', features: [],
    tiers: [ { ...tier, tier_slug: '1-site', name: '1 site', herald_slugs: [ 'sfwd-lms' ], purchase_url: 'https://portal.example/nps/' } ],
};
const brandCatalog: ProductCatalog = {
    product_id: 'learndash', product_slug: 'learndash', product_name: 'LearnDash', tiers: [ tier ],
    features: [ { slug: feature.slug, name: feature.name } as CatalogFeature ],
};
const packageCatalogs = [ brandCatalog, packageCatalog ];

describe( 'ProductSection with a published NPS offer', () => {
    it.each( [ true, false ] )( 'replaces tier sales prompts with the package offer, brand sales links: %s', async ( sellingBrand ) => {
        configure( sellingBrand ? {} : { purchase_url: '', upgrade_url: '' }, null, false, packageCatalogs );
        render( <ProductSection product={ product } /> );
        expect( screen.queryByText( 'Pro Features' ) ).toBeNull();
        expect( screen.queryByRole( 'link', { name: /Upgrade to/ } ) ).toBeNull();
        expect( screen.getByRole( 'link', { name: 'Get Nexcess Plugin Stack' } ).getAttribute( 'href' ) ).toBe( 'https://portal.example/nps/' );
        await userEvent.setup().click( screen.getByRole( 'button', { name: /Included in Nexcess Plugin Stack/ } ) );
        expect( screen.getByText( 'LearnDash LMS' ) ).not.toBeNull();
    } );

    it( 'keeps features outside NPS separate without a package purchase prompt', async () => {
        configure( {}, null, false, packageCatalogs );
        const groups = ( useProductFeatureGroups as jest.Mock ).getMockImplementation()!();
        ( useProductFeatureGroups as jest.Mock ).mockReturnValue( {
            ...groups,
            lockedByTier: { pro: [ feature, { ...feature, slug: 'outside-nps', name: 'Outside NPS' } ] },
        } );
        render( <ProductSection product={ product } /> );
        const user = userEvent.setup();
        await user.click( screen.getByRole( 'button', { name: /Not included in Nexcess Plugin Stack/ } ) );
        expect( screen.getByText( 'Outside NPS' ) ).not.toBeNull();
        expect( screen.queryByText( 'LearnDash LMS' ) ).toBeNull();
        expect( screen.getAllByRole( 'link', { name: 'Get Nexcess Plugin Stack' } ) ).toHaveLength( 1 );
    } );

    it( 'preserves owned brand activation instead of selling its features again', () => {
        configure( {}, { ...purchase, tier: 'pro', activated_here: false }, true, packageCatalogs );
        render( <ProductSection product={ product } /> );
        expect( screen.getByRole( 'link', { name: 'Activate plan' } ) ).not.toBeNull();
        expect( screen.getByText( 'Pro Features' ) ).not.toBeNull();
        expect( screen.queryByRole( 'link', { name: 'Get Nexcess Plugin Stack' } ) ).toBeNull();
    } );

    it( 'offers activation rather than another purchase when NPS is owned but unactivated', () => {
        configure( {}, null, false, packageCatalogs, [ { ...purchase, product_slug: 'nexcess-plugin-stack', tier: '1-site', activated_here: false } ] );
        render( <ProductSection product={ product } /> );
        expect( screen.queryByRole( 'link', { name: 'Get Nexcess Plugin Stack' } ) ).toBeNull();
        const url = new URL( screen.getByRole( 'link', { name: 'Activate Nexcess Plugin Stack' } ).getAttribute( 'href' )! );
        expect( url.searchParams.get( 'sku' ) ).toBe( 'nexcess-plugin-stack:1-site' );
    } );

    it( 'does not sell NPS again if an owned package is missing a feature grant', () => {
        configure( {}, null, false, packageCatalogs, [ { ...purchase, product_slug: 'nexcess-plugin-stack', tier: '1-site' } ] );
        render( <ProductSection product={ product } /> );
        expect( screen.getByRole( 'button', { name: /Included in Nexcess Plugin Stack/ } ) ).not.toBeNull();
        expect( screen.queryByRole( 'link' ) ).toBeNull();
    } );

    it( 'keeps already available features outside the package sales group while searching', () => {
        configure( {}, null, false, packageCatalogs );
        const groups = ( useProductFeatureGroups as jest.Mock ).getMockImplementation()!();
        ( useProductFeatureGroups as jest.Mock ).mockReturnValue( {
            ...groups,
            availableFeatures: [ { ...feature, slug: 'owned-feature', name: 'Already owned', is_available: true } ],
        } );
        ( useFilter as jest.Mock ).mockReturnValue( { searchQuery: 'LearnDash' } );
        render( <ProductSection product={ product } /> );
        expect( screen.getAllByText( 'Already owned' ) ).toHaveLength( 1 );
        expect( screen.getByText( 'LearnDash LMS' ) ).not.toBeNull();
        expect( screen.getByRole( 'button', { name: /Included in Nexcess Plugin Stack/ } ).getAttribute( 'aria-expanded' ) ).toBe( 'true' );
    } );

    it( 'does not offer NPS in a group containing only features it excludes', () => {
        configure( {}, null, false, packageCatalogs );
        const groups = ( useProductFeatureGroups as jest.Mock ).getMockImplementation()!();
        ( useProductFeatureGroups as jest.Mock ).mockReturnValue( {
            ...groups,
            lockedByTier: { pro: [ { ...feature, slug: 'outside-nps', name: 'Outside NPS' } ] },
        } );
        render( <ProductSection product={ product } /> );
        expect( screen.getByRole( 'button', { name: /Not included in Nexcess Plugin Stack/ } ) ).not.toBeNull();
        expect( screen.queryByRole( 'link' ) ).toBeNull();
    } );

    it.each( [
        'Another Package',
        '<img data-catalog-injection src=x onerror="alert(1)"> & <script data-catalog-injection>alert(1)</script>',
    ] )( 'renders the catalog name as text in every package label: %s', ( name ) => {
        const renamedCatalogs = [ brandCatalog, { ...packageCatalog, product_name: name } ];
        configure( {}, null, false, renamedCatalogs );
        const groups = ( useProductFeatureGroups as jest.Mock ).getMockImplementation()!();
        ( useProductFeatureGroups as jest.Mock ).mockReturnValue( {
            ...groups,
            lockedByTier: { pro: [ feature, { ...feature, slug: 'outside-nps', name: 'Outside NPS' } ] },
        } );
        const { container, rerender } = render( <ProductSection product={ product } /> );
        expect( screen.getByText( 'Included in ' + name ) ).not.toBeNull();
        expect( screen.getByText( 'Not included in ' + name ) ).not.toBeNull();
        expect( screen.getByRole( 'link', { name: 'Get ' + name } ).getAttribute( 'href' ) ).toBe( 'https://portal.example/nps/' );
        expect( container.querySelector( '[data-catalog-injection]' ) ).toBeNull();

        configure( {}, null, false, renamedCatalogs, [ { ...purchase, product_slug: 'nexcess-plugin-stack', tier: '1-site', activated_here: false } ] );
        rerender( <ProductSection product={ product } /> );
        expect( screen.getByRole( 'link', { name: 'Activate ' + name } ) ).not.toBeNull();
        expect( container.querySelector( '[data-catalog-injection]' ) ).toBeNull();
    } );

    it( 'keeps the old catalog presentation when NPS has no usable offer', () => {
        configure( {}, null, false, [ brandCatalog, { ...packageCatalog, tiers: [ { ...packageCatalog.tiers[ 0 ], purchase_url: '' } ] } ] );
        render( <ProductSection product={ product } /> );
        expect( screen.getByRole( 'link', { name: 'Upgrade to Pro' } ) ).not.toBeNull();
        expect( screen.queryByText( 'Included in Nexcess Plugin Stack' ) ).toBeNull();
    } );
} );
