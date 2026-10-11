import { render, screen } from '@testing-library/react';
import { useSelect } from '@wordpress/data';
import { NotActivatedBanner } from '@/components/molecules/NotActivatedBanner';

jest.mock( '@wordpress/data', () => ( { useSelect: jest.fn() } ) );
jest.mock( '@/store', () => ( { store: { name: 'harbor' } } ) );

let mockActivationUrl: string | null = 'https://portal.example/activate';
jest.mock( '@/lib/harbor-data', () => ( {
    getHarborDataValue: () => mockActivationUrl,
} ) );

describe( 'NotActivatedBanner', () => {
    beforeEach( () => {
        ( useSelect as jest.Mock ).mockImplementation( ( selector ) => selector( () => ( {
            areAllProductsNotActivated: () => true,
            getLicenseKey: () => 'LWSW-TEST',
        } ) ) );
    } );

    it( 'links to the activation URL', () => {
        mockActivationUrl = 'https://portal.example/activate';
        render( <NotActivatedBanner /> );
        expect( screen.getByRole( 'link', { name: 'Activate now' } ).getAttribute( 'href' ) ).toBe( 'https://portal.example/activate' );
    } );

    it.each( [ 'javascript:alert(1)//', 'data:text/html,<b>x</b>', 'not a url', null ] )( 'renders nothing for a rejected activation URL %p', ( url ) => {
        mockActivationUrl = url;
        const { container } = render( <NotActivatedBanner /> );
        expect( container.innerHTML ).toBe( '' );
    } );
} );
