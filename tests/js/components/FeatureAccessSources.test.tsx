import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { FeatureAccessSources } from '@/components/molecules/FeatureAccessSources';
import type { FeatureAccessSource } from '@/types/api';

const sources: FeatureAccessSource[] = [
    { type: 'purchase', product_slug: 'nss', tier: 'complete', product_name: 'Nexcess Software Suite', tier_name: '10 sites' },
    { type: 'purchase', product_slug: 'kadence', tier: 'pro', product_name: 'Kadence', tier_name: 'Pro' },
];

describe( 'FeatureAccessSources', () => {
    it( 'shows every effective purchase for an overlapping feature', () => {
        render( <FeatureAccessSources sources={ sources } /> );
        expect( screen.getAllByRole( 'button' ).map( ( button ) => button.getAttribute( 'aria-label' ) ) ).toEqual( [
            'Included with Nexcess Software Suite (10 sites)',
            'Included with Kadence (Pro)',
        ] );
    } );

    it( 'updates the explanation when a purchase stops granting access', () => {
        const { rerender } = render( <FeatureAccessSources sources={ sources } /> );
        rerender( <FeatureAccessSources sources={ sources.slice( 0, 1 ) } /> );
        expect( screen.queryByRole( 'button', { name: 'Included with Kadence (Pro)' } ) ).toBeNull();
        expect( screen.getByRole( 'button', { name: 'Included with Nexcess Software Suite (10 sites)' } ) ).not.toBeNull();
    } );

    it( 'reveals the purchase on focus and click and dismisses with Escape', async () => {
        const user = userEvent.setup();
        render( <FeatureAccessSources sources={ sources } /> );
        await user.tab();
        expect( ( await screen.findByRole( 'tooltip' ) ).textContent ).toBe( 'Included with Nexcess Software Suite (10 sites)' );
        await user.keyboard( '{Escape}' );
        expect( screen.queryByRole( 'tooltip' ) ).toBeNull();
        await user.click( screen.getByRole( 'button', { name: 'Included with Nexcess Software Suite (10 sites)' } ) );
        expect( ( await screen.findByRole( 'tooltip' ) ).textContent ).toBe( 'Included with Nexcess Software Suite (10 sites)' );
    } );

    it( 'leaves free and legacy status to the existing license badges', () => {
        const { container } = render( <FeatureAccessSources sources={ [ { type: 'free' }, { type: 'legacy' } ] } /> );
        expect( container.textContent ).toBe( '' );
    } );

    it( 'omits the explanation when sources are absent or empty', () => {
        const { container, rerender } = render( <FeatureAccessSources /> );
        expect( container.textContent ).toBe( '' );
        rerender( <FeatureAccessSources sources={ [] } /> );
        expect( container.textContent ).toBe( '' );
    } );
} );
