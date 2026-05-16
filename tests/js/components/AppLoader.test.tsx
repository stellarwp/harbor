import { render, screen } from '@testing-library/react';
import { AppLoader }      from '@/components/organisms/AppLoader';

describe( 'AppLoader', () => {
    it( 'renders the welcome shell chrome and the loading indicator', () => {
        render( <AppLoader /> );

        // Shell chrome (title from WelcomeShell).
        expect( screen.queryByText( 'Software License Manager' ) ).not.toBeNull();
        // Subtitle.
        expect( screen.queryByText( 'Liquid Web by Nexcess' ) ).not.toBeNull();
        // Loading indicator label.
        expect( screen.queryByText( 'Loading…' ) ).not.toBeNull();
    } );
} );
