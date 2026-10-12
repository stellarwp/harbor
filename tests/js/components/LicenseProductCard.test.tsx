import { render, screen } from '@testing-library/react';
import { LicenseProductCard } from '@/components/molecules/LicenseProductCard';
import type { LicenseProduct } from '@/types/api';

const unactivated = {
    product_slug: 'kadence',
    tier: 'pro',
    status: 'active',
    expires: '2099-12-31 23:59:59',
    activations: { site_limit: 1, active_count: 0, over_limit: false, domains: [] },
    capabilities: [],
    activated_here: false,
    validation_status: 'not_activated',
    is_valid: false,
} as LicenseProduct;

function renderCard( activationUrl: string | null ) {
    render(
        <LicenseProductCard
            productSlug="kadence"
            productName="Kadence"
            tiers={ [ unactivated ] }
            tierNameMap={ { pro: 'Pro' } }
            activationUrl={ activationUrl }
        />
    );
}

describe( 'LicenseProductCard activation', () => {
    it( 'links an unactivated tier to the activation URL with its SKU', () => {
        renderCard( 'https://portal.example/activate' );
        const url = new URL( screen.getByRole( 'link', { name: 'Activate' } ).getAttribute( 'href' )! );
        expect( url.searchParams.get( 'sku' ) ).toBe( 'kadence:pro' );
    } );

    it.each( [ 'javascript:alert(1)//', 'not a url', null ] )( 'hides Activate for a rejected activation URL %p', ( url ) => {
        renderCard( url );
        expect( screen.queryByRole( 'link', { name: 'Activate' } ) ).toBeNull();
        expect( screen.queryByText( 'Activate' ) ).toBeNull();
    } );
} );
