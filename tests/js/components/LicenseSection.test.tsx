import { render, screen } from '@testing-library/react';
import { LicenseSection } from '@/components/organisms/LicenseSection';
import type { LicenseProduct } from '@/types/api';

jest.mock( '@/components/molecules/LicenseKeyInput', () => ( { LicenseKeyInput: () => null } ) );
jest.mock( '@/components/molecules/LicenseProductCard', () => ( { LicenseProductCard: () => null } ) );

let mockSubscriptionsUrl: string | null = 'https://portal.example/subscriptions/';
jest.mock( '@/lib/harbor-data', () => ( {
    getHarborDataValue: () => mockSubscriptionsUrl,
} ) );

const purchase = { product_slug: 'kadence', tier: 'pro', activated_here: true, is_valid: true } as LicenseProduct;

function renderSection() {
    render(
        <LicenseSection
            licenseKey="LWSW-TEST"
            licenseProducts={ [ purchase ] }
            tierNameMap={ { pro: 'Pro' } }
            tierRankMap={ { pro: 2 } }
            onRemove={ async () => null }
            onRefresh={ async () => {} }
            isRefreshing={ false }
            isLoading={ false }
            activationUrl={ null }
        />
    );
}

describe( 'LicenseSection manage link', () => {
    it( 'links to the subscriptions page', () => {
        mockSubscriptionsUrl = 'https://portal.example/subscriptions/';
        renderSection();
        expect( screen.getByRole( 'link', { name: 'Manage license in Nexcess' } ).getAttribute( 'href' ) ).toBe( 'https://portal.example/subscriptions/' );
    } );

    it.each( [ 'javascript:alert(1)//', 'not a url', null ] )( 'hides the manage link for a rejected subscriptions URL %p', ( url ) => {
        mockSubscriptionsUrl = url;
        renderSection();
        expect( screen.queryByText( 'Manage license in Nexcess' ) ).toBeNull();
    } );
} );
