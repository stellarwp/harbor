import { render }            from '@testing-library/react';
import { WelcomeNoticeBanner } from '@/components/molecules/WelcomeNoticeBanner';

describe( 'WelcomeNoticeBanner', () => {
    it( 'renders its children inside the styled callout', () => {
        render(
            <WelcomeNoticeBanner>
                <strong>Hi</strong>
            </WelcomeNoticeBanner>
        );

        const strong = document.querySelector( 'strong' );
        expect( strong ).not.toBeNull();
        expect( strong?.textContent ).toBe( 'Hi' );
    } );
} );
