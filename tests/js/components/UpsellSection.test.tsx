import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { UpsellSection } from '@/components/organisms/UpsellSection';
import { getUpsellOffers } from '@/lib/upsell-offers';
import type { CatalogFeature, CatalogTier, LicenseProduct, ProductCatalog } from '@/types/api';

function feature( slug: string, name: string ): CatalogFeature {
    return {
        slug, name, kind: 'plugin', minimum_tier: 'pro', top_dir: slug, main_file: slug + '.php',
        plugin_file: slug + '/' + slug + '.php', wporg_slug: null, version: null, release_date: null,
        description: '', category: '', authors: null, documentation_url: '', homepage: null,
    };
}

function catalog( slug: string, name: string, features: CatalogFeature[], capabilities = features.map( ( entry ) => entry.slug ) ): ProductCatalog {
    const tier: CatalogTier = {
        tier_slug: 'pro', name: 'Pro', rank: 1, price: 350, currency: 'USD', herald_slugs: capabilities,
        purchase_url: 'https://portal.example/checkout/?product=' + slug, upgrade_url: '',
    };
    return { product_id: slug, product_slug: slug, product_name: name, features, tiers: [ tier ] };
}

function license( slug: string, capabilities: string[] ): LicenseProduct {
    return {
        product_slug: slug, tier: 'pro', status: 'licensed', expires: '2027-01-01', capabilities,
        activations: { site_limit: 1, active_count: 1, over_limit: false, domains: [] },
    };
}

const kadence = catalog( 'kadence', 'Kadence', [ feature( 'kadence-blocks-pro', 'Kadence Blocks Pro' ), feature( 'kadence-conversions', 'Kadence Conversions' ) ] );
const give = catalog( 'give', 'Give', [ feature( 'give-recurring-donations', 'Recurring Donations' ) ] );
const suite = catalog( 'nps', 'Nexcess Plugin Stack', [], [ 'kadence-blocks-pro', 'give-recurring-donations' ] );
const catalogs = [ kadence, give, suite ];
const brandLicense = license( 'kadence', [ 'kadence-blocks-pro', 'kadence-conversions' ] );

describe( 'Catalog upsell offers', () => {
    it( 'offers NPS to a brand customer and lists only features granted by its advertised tier', async () => {
        render( <UpsellSection offers={ getUpsellOffers( catalogs, [ brandLicense ] ) } /> );
        const links = screen.getAllByRole( 'link' );
        expect( links ).toHaveLength( 1 );
        expect( links[ 0 ].textContent ).toContain( 'Nexcess Plugin Stack' );
        expect( links[ 0 ].getAttribute( 'href' ) ).toBe( suite.tiers[ 0 ].purchase_url );
        await userEvent.setup().click( screen.getByText( 'See all 2 included features' ) );
        expect( screen.getByText( 'Kadence Blocks Pro' ) ).not.toBeNull();
        expect( screen.getByText( 'Recurring Donations' ) ).not.toBeNull();
        expect( screen.queryByText( 'Kadence Conversions' ) ).toBeNull();
    } );

    it.each( [ false, true ] )( 'does not advertise a package already owned, with mixed ownership: %s', ( mixed ) => {
        const licenses = [ license( 'nps', suite.tiers[ 0 ].herald_slugs ), ...( mixed ? [ brandLicense ] : [] ) ];
        expect( getUpsellOffers( catalogs, licenses ) ).toEqual( [] );
    } );

    it( 'offers the published package without a license', () => {
        expect( getUpsellOffers( catalogs, [] ).map( ( offer ) => offer.product.slug ) ).toEqual( [ 'nps' ] );
    } );

    it( 'keeps brand offers when Portal has not published NPS', () => {
        const offers = getUpsellOffers( [ kadence, give ], [ brandLicense ] );
        expect( offers.map( ( offer ) => offer.product.slug ) ).toEqual( [ 'give' ] );
        expect( offers[ 0 ].href ).toBe( give.tiers[ 0 ].purchase_url );
    } );

    it( 'does not treat an empty NPS product without paid tiers as an available offer', () => {
        const offers = getUpsellOffers( [ kadence, give, { ...suite, tiers: [] } ], [ brandLicense ] );
        expect( offers ).toEqual( [] );
    } );

    it( 'hides an incomplete package and the entire offer section when its purchase URL is missing', () => {
        const pending = { ...suite, tiers: [ { ...suite.tiers[ 0 ], purchase_url: '' } ] };
        render( <UpsellSection offers={ getUpsellOffers( [ kadence, give, pending ], [ brandLicense ] ) } /> );
        expect( screen.queryByRole( 'link' ) ).toBeNull();
        expect( screen.queryByText( 'Nexcess Plugin Stack' ) ).toBeNull();
        expect( screen.queryByText( 'Add to your plan' ) ).toBeNull();
        expect( screen.queryByText( 'Give' ) ).toBeNull();
    } );

    it( 'uses catalog names and the lowest paid tier link even when tiers arrive out of order', () => {
        const renamed = { ...suite, product_name: 'Renamed Suite', tiers: [ { ...suite.tiers[ 0 ], rank: 2, purchase_url: 'https://portal.example/larger' }, suite.tiers[ 0 ] ] };
        const [ offer ] = getUpsellOffers( [ kadence, give, renamed ], [ brandLicense ] );
        expect( offer.product.name ).toBe( 'Renamed Suite' );
        expect( offer.href ).toBe( suite.tiers[ 0 ].purchase_url );
    } );
    it( 'renders nothing while the catalog is empty or unavailable', () => {
        const { container } = render( <UpsellSection offers={ getUpsellOffers( [], [ brandLicense ] ) } /> );
        expect( container.innerHTML ).toBe( '' );
    } );

    it.each( [ '', '   ', 'not-a-url', 'javascript:alert(1)', 'ftp://portal.example/package' ] )( 'hides NPS with an unusable purchase URL: %s', ( purchase_url ) => {
        const pending = { ...suite, tiers: [ { ...suite.tiers[ 0 ], purchase_url } ] };
        expect( getUpsellOffers( [ kadence, give, pending ], [ brandLicense ] ) ).toEqual( [] );
    } );

    it.each( [ { capabilities: [] }, { capabilities: [ 'not-in-the-catalog' ] } ] )( 'hides NPS when its capabilities resolve to no features: %j', ( { capabilities } ) => {
        const pending = { ...suite, tiers: [ { ...suite.tiers[ 0 ], herald_slugs: capabilities } ] };
        expect( getUpsellOffers( [ kadence, give, pending ], [ brandLicense ] ) ).toEqual( [] );
    } );

    it( 'hides NPS until its referenced brand features are published', () => {
        expect( getUpsellOffers( [ suite ], [] ) ).toEqual( [] );
    } );

    it( 'does not substitute a higher package tier when the advertised entry tier has no link', () => {
        const pending = { ...suite, tiers: [
            { ...suite.tiers[ 0 ], purchase_url: '' },
            { ...suite.tiers[ 0 ], rank: 2, purchase_url: 'https://portal.example/larger' },
        ] };
        expect( getUpsellOffers( [ kadence, give, pending ], [] ) ).toEqual( [] );
    } );

    it( 'hides the section when legacy brand catalogs have no purchase destinations', () => {
        const unavailable = [ kadence, give ].map( ( entry ) => ( {
            ...entry, tiers: entry.tiers.map( ( tier ) => ( { ...tier, purchase_url: '' } ) ),
        } ) );
        const { container } = render( <UpsellSection offers={ getUpsellOffers( unavailable, [] ) } /> );
        expect( container.innerHTML ).toBe( '' );
    } );

    it( 'omits brands with no features or paid tier', () => {
        expect( getUpsellOffers( [ { ...kadence, features: [] }, { ...give, tiers: [] } ], [] ) ).toEqual( [] );
    } );

    it( 'preserves pre-NPS brand links without depending on new tier fields', () => {
        const older = [ kadence, give ].map( ( entry ) => ( {
            ...entry, tiers: entry.tiers.map( ( tier ) => ( { ...tier, herald_slugs: [] } ) ),
        } ) );
        const offers = getUpsellOffers( older, [ brandLicense ] );
        expect( offers.map( ( offer ) => offer.product.slug ) ).toEqual( [ 'give' ] );
        expect( offers[ 0 ].href ).toBe( give.tiers[ 0 ].purchase_url );
    } );

    it( 'keeps paid brand offers hidden when an existing purchase already covers their features', () => {
        expect( getUpsellOffers( [ kadence, give ], [ license( 'another-package', [ ...brandLicense.capabilities, 'give-recurring-donations' ] ) ] ) ).toEqual( [] );
    } );
    it( 'keeps a brand offer when another purchase covers only part of the advertised tier', () => {
        const offers = getUpsellOffers( [ kadence ], [ license( 'another-package', [ 'kadence-blocks-pro' ] ) ] );
        expect( offers.map( ( offer ) => offer.product.slug ) ).toEqual( [ 'kadence' ] );
    } );

    it( 'hides an already-covered entry tier even when a higher tier includes more features', () => {
        const entry = { ...kadence, tiers: [
            { ...kadence.tiers[ 0 ], herald_slugs: [ 'kadence-blocks-pro' ] },
            { ...kadence.tiers[ 0 ], tier_slug: 'elite', rank: 2 },
        ] };
        expect( getUpsellOffers( [ entry ], [ license( 'another-package', [ 'kadence-blocks-pro' ] ) ] ) ).toEqual( [] );
    } );

    it( 'uses minimum tiers to distinguish partial and full coverage in older catalogs', () => {
        const older = { ...kadence, features: [
            ...kadence.features,
            { ...feature( 'kadence-extra', 'Extra Feature' ), minimum_tier: 'elite' },
            { ...feature( 'kadence-blocks', 'Kadence Blocks' ), minimum_tier: 'free' },
        ], tiers: [
            { ...kadence.tiers[ 0 ], tier_slug: 'free', rank: 0, herald_slugs: [] },
            { ...kadence.tiers[ 0 ], herald_slugs: [] },
            { ...kadence.tiers[ 0 ], tier_slug: 'elite', rank: 2, herald_slugs: [] },
        ] };
        expect( getUpsellOffers( [ older ], [ license( 'another-package', [ 'kadence-blocks-pro' ] ) ] ).map( ( offer ) => offer.product.slug ) ).toEqual( [ 'kadence' ] );
        expect( getUpsellOffers( [ older ], [ license( 'another-package', brandLicense.capabilities ) ] ) ).toEqual( [] );
    } );


} );
