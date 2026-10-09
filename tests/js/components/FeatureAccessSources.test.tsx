import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { FeatureAccessSources } from '@/components/molecules/FeatureAccessSources';
import type { FeatureAccessSource } from '@/types/api';

const sources: FeatureAccessSource[] = [
    { type: 'purchase', product_slug: 'nexcess-plugin-stack', tier: '1-site', product_name: 'Nexcess Plugin Stack', tier_name: '10 sites' },
    { type: 'purchase', product_slug: 'kadence', tier: 'pro', product_name: 'Kadence', tier_name: 'Pro' },
];

describe( 'FeatureAccessSources', () => {
    it( 'shows every effective purchase for an overlapping feature', () => {
        render( <FeatureAccessSources sources={ sources } /> );
        expect( screen.getByText( 'Plugin Stack' ) ).not.toBeNull();
        expect( screen.getByText( 'Pro' ) ).not.toBeNull();
        expect( screen.getAllByRole( 'button' ).map( ( button ) => button.getAttribute( 'aria-label' ) ) ).toEqual( [
            'Included in your Nexcess Plugin Stack (10 sites) subscription.',
            'Included in your Kadence (Pro) subscription.',
        ] );
    } );

    it( 'uses the product name when a purchase has no tier label', () => {
        render( <FeatureAccessSources sources={ [ { ...sources[ 1 ], tier_name: '' } as FeatureAccessSource ] } /> );
        expect( screen.getByRole( 'button', { name: 'Included in your Kadence subscription.' } ).textContent ).toBe( 'Kadence' );
    } );

    it( 'updates the explanation when a purchase stops granting access', () => {
        const { rerender } = render( <FeatureAccessSources sources={ sources } /> );
        rerender( <FeatureAccessSources sources={ sources.slice( 0, 1 ) } /> );
        expect( screen.queryByRole( 'button', { name: 'Included in your Kadence (Pro) subscription.' } ) ).toBeNull();
        expect( screen.getByRole( 'button', { name: 'Included in your Nexcess Plugin Stack (10 sites) subscription.' } ) ).not.toBeNull();
    } );

    it( 'reveals the purchase on focus and click and dismisses with Escape', async () => {
        const user = userEvent.setup();
        render( <FeatureAccessSources sources={ sources } /> );
        await user.tab();
        expect( ( await screen.findByRole( 'tooltip' ) ).textContent ).toBe( 'Included in your Nexcess Plugin Stack (10 sites) subscription.' );
        await user.keyboard( '{Escape}' );
        expect( screen.queryByRole( 'tooltip' ) ).toBeNull();
        await user.click( screen.getByRole( 'button', { name: 'Included in your Nexcess Plugin Stack (10 sites) subscription.' } ) );
        expect( ( await screen.findByRole( 'tooltip' ) ).textContent ).toBe( 'Included in your Nexcess Plugin Stack (10 sites) subscription.' );
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
