import { render, screen } from '@testing-library/react';
import { WelcomeShell }   from '@/components/templates/WelcomeShell';

describe( 'WelcomeShell', () => {
    it( 'renders the heading, subtitle, logo, and children slot', () => {
        render(
            <WelcomeShell>
                <p data-testid="welcome-children">child content</p>
            </WelcomeShell>
        );

        expect( screen.queryByText( 'Software License Manager' ) ).not.toBeNull();
        expect( screen.queryByText( 'Liquid Web by Nexcess' ) ).not.toBeNull();
        // The decorative logo has empty alt text so it has no accessible role —
        // assert via the rendered <img> element instead.
        expect( document.querySelector( 'img[alt=""]' ) ).not.toBeNull();
        expect( screen.queryByTestId( 'welcome-children' ) ).not.toBeNull();
    } );
} );
