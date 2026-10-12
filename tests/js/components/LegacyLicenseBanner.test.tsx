import { render, screen } from '@testing-library/react';
import { useSelect } from '@wordpress/data';
import { LegacyLicenseBanner } from '@/components/molecules/LegacyLicenseBanner';

jest.mock( '@wordpress/data', () => ( { useSelect: jest.fn() } ) );
jest.mock( '@/store', () => ( { store: { name: 'harbor' } } ) );

let mockSubscriptionsUrl: string | null = 'https://portal.example/subscriptions/';
jest.mock( '@/lib/harbor-data', () => ( {
    getHarborDataValue: () => mockSubscriptionsUrl,
} ) );

describe( 'LegacyLicenseBanner', () => {
    beforeEach( () => {
        ( useSelect as jest.Mock ).mockReturnValue( true );
    } );

    it( 'links to the subscriptions page', () => {
        mockSubscriptionsUrl = 'https://portal.example/subscriptions/';
        render( <LegacyLicenseBanner /> );
        expect( screen.getByRole( 'link', { name: 'switching to a unified license' } ).getAttribute( 'href' ) ).toBe( 'https://portal.example/subscriptions/' );
    } );

    it.each( [ 'javascript:alert(1)//', 'not a url', null ] )( 'renders nothing for a rejected subscriptions URL %p', ( url ) => {
        mockSubscriptionsUrl = url;
        const { container } = render( <LegacyLicenseBanner /> );
        expect( container.innerHTML ).toBe( '' );
    } );
} );
