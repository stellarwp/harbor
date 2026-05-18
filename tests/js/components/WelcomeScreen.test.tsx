import { render, screen } from '@testing-library/react';
import { WelcomeScreen }   from '@/components/organisms/WelcomeScreen';

jest.mock( '@/components/molecules/WelcomeLicenseForm', () => ( {
    WelcomeLicenseForm: () => <div data-testid="welcome-license-form" />,
} ) );

describe( 'WelcomeScreen', () => {
    it( 'renders the non-unified-license notice with a real <strong> element', () => {
        render( <WelcomeScreen /> );

        const strong = Array
            .from( document.querySelectorAll( 'strong' ) )
            .find( ( el ) => /non-unified license/i.test( el.textContent ?? '' ) );

        expect( strong ).not.toBeUndefined();
    } );

    it( 'mounts the license form inside the shell', () => {
        render( <WelcomeScreen /> );

        expect( screen.queryByTestId( 'welcome-license-form' ) ).not.toBeNull();
    } );
} );
