import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useSelect, useDispatch } from '@wordpress/data';
import { FeatureRow } from '@/components/molecules/FeatureRow';
import type { PluginFeature } from '@/types/api';

jest.mock( '@wordpress/data', () => ( { useSelect: jest.fn(), useDispatch: jest.fn() } ) );
jest.mock( '@/store', () => ( { store: { name: 'harbor' } } ) );
jest.mock( '@/context/toast-context', () => ( { useToast: () => ( { addToast: jest.fn() } ) } ) );
jest.mock( '@/context/reload-banner-context', () => ( { useReloadBanner: () => ( { setNeedsReload: jest.fn() } ) } ) );
jest.mock( '@/context/error-modal-context', () => ( { useErrorModal: () => ( { addError: jest.fn() } ) } ) );

const feature: PluginFeature = {
    slug: 'kadence-blocks-pro', name: 'Kadence Blocks Pro', description: 'Premium blocks.',
    product: 'kadence', tier: 'pro', type: 'plugin', is_available: true, in_catalog_tier: true,
    is_enabled: false, documentation_url: '', plugin_file: 'kadence-blocks-pro/main.php',
    plugin_slug: 'kadence-blocks-pro', authors: [], wporg_slug: null, is_harbor_host: false,
    installed_version: '1.0.0', version: '2.0.0', update_version: '2.0.0',
};
const purchaseSource = { type: 'purchase' as const, product_slug: 'nexcess-plugin-stack', tier: '1-site', product_name: 'Nexcess Plugin Stack', tier_name: '1 site' };
let updateFeature: jest.Mock;
let enableFeature: jest.Mock;
let disableFeature: jest.Mock;

function configure( { legacy = false, busy = false, lastHost = false } = {} ) {
    updateFeature = jest.fn().mockResolvedValue( undefined );
    enableFeature = jest.fn().mockResolvedValue( undefined );
    disableFeature = jest.fn().mockResolvedValue( undefined );
    ( useDispatch as jest.Mock ).mockReturnValue( { updateFeature, enableFeature, disableFeature } );
    const selectors = {
        isAnyInstallableBusy: () => busy,
        getEnabledHarborHostCount: () => lastHost ? 1 : 2,
        getHarborHostBasenames: () => lastHost ? [ feature.plugin_file ] : [],
        getActiveLegacyLicense: () => legacy ? { is_active: true } : null,
        isProductLicenseValid: () => false,
    };
    ( useSelect as jest.Mock ).mockImplementation( ( callback ) => callback( () => selectors ) );
}

describe( 'FeatureRow controls', () => {
    it( 'updates and enables an available plugin without expanding its description', async () => {
        configure();
        render( <FeatureRow feature={ feature } /> );
        const user = userEvent.setup();
        await user.click( screen.getByRole( 'button', { name: 'Update Kadence Blocks Pro' } ) );
        expect( updateFeature ).toHaveBeenCalledWith( feature.slug );
        await user.click( screen.getByRole( 'switch' ) );
        expect( enableFeature ).toHaveBeenCalledWith( feature.slug );
        expect( screen.queryByText( feature.description ) ).toBeNull();
    } );

    it( 'shows the Free badge for a free plugin in a paid catalog tier', () => {
        configure();
        render( <FeatureRow feature={ { ...feature, access_sources: [ { type: 'free' } ] } } /> );
        expect( screen.getByText( 'Free' ) ).not.toBeNull();
        expect( ( screen.getByRole( 'switch' ) as HTMLButtonElement ).disabled ).toBe( false );
    } );

    it( 'shows purchase pills without nesting buttons or expanding on a pill click', async () => {
        configure();
        render( <FeatureRow feature={ { ...feature, access_sources: [ purchaseSource ] } } /> );
        const user = userEvent.setup();
        const pill = screen.getByRole( 'button', { name: 'Included in your Nexcess Plugin Stack (1 site) subscription.' } );
        expect( pill.textContent ).toBe( 'Plugin Stack' );
        expect( pill.parentElement?.closest( 'button' ) ).toBeNull();
        await user.click( pill );
        expect( ( await screen.findByRole( 'tooltip' ) ).textContent ).toBe( 'Included in your Nexcess Plugin Stack (1 site) subscription.' );
        expect( screen.queryByText( feature.description ) ).toBeNull();
        await user.click( screen.getByRole( 'button', { name: 'Kadence Blocks Pro', exact: true } ) );
        expect( screen.getByText( feature.description ) ).not.toBeNull();
    } );

    it( 'shows only the Free pill when a free feature also has paid access sources', () => {
        configure();
        render( <FeatureRow feature={ { ...feature, access_sources: [ { type: 'free' }, purchaseSource ] } } /> );
        expect( screen.getByText( 'Free' ) ).not.toBeNull();
        expect( screen.queryByText( 'Plugin Stack' ) ).toBeNull();
        expect( screen.queryByRole( 'list', { name: 'Included with' } ) ).toBeNull();
    } );

    it( 'preserves legacy-only controls and prevents Harbor-managed updates', () => {
        configure( { legacy: true } );
        render( <FeatureRow feature={ { ...feature, is_available: false, access_sources: [ { type: 'legacy' } ] } } /> );
        expect( screen.getByText( 'Legacy' ) ).not.toBeNull();
        expect( ( screen.getByRole( 'button', { name: 'Update Kadence Blocks Pro' } ) as HTMLButtonElement ).disabled ).toBe( true );
        expect( screen.getByRole( 'switch' ) ).not.toBeNull();
    } );

    it( 'allows a package-covered update even when an old brand license is also registered', async () => {
        configure( { legacy: true } );
        render( <FeatureRow feature={ { ...feature, access_sources: [ purchaseSource, { type: 'legacy' } ] } } /> );
        expect( screen.queryByText( 'Legacy' ) ).toBeNull();
        await userEvent.setup().click( screen.getByRole( 'button', { name: 'Update Kadence Blocks Pro' } ) );
        expect( updateFeature ).toHaveBeenCalledWith( feature.slug );
    } );

    it.each( [ false, true ] )( 'blocks revoked updates and reactivation but permits disabling an active plugin: %s', ( enabled ) => {
        configure();
        render( <FeatureRow feature={ { ...feature, is_available: false, is_enabled: enabled, access_sources: [] } } /> );
        expect( screen.getByText( 'Unavailable' ) ).not.toBeNull();
        expect( screen.queryByRole( 'button', { name: 'Update Kadence Blocks Pro' } ) ).toBeNull();
        expect( ( screen.getByRole( 'switch' ) as HTMLButtonElement ).disabled ).toBe( ! enabled );
    } );

    it( 'prevents conflicting installation and update actions while another plugin is busy', () => {
        configure( { busy: true } );
        render( <FeatureRow feature={ feature } /> );
        expect( ( screen.getByRole( 'switch' ) as HTMLButtonElement ).disabled ).toBe( true );
        expect( ( screen.getByRole( 'button', { name: 'Update Kadence Blocks Pro' } ) as HTMLButtonElement ).disabled ).toBe( true );
    } );

    it( 'keeps the confirmation before deactivating the last Harbor host', async () => {
        configure( { lastHost: true } );
        render( <FeatureRow feature={ { ...feature, is_enabled: true } } /> );
        const user = userEvent.setup();
        await user.click( screen.getByRole( 'switch' ) );
        expect( screen.getByRole( 'dialog' ) ).not.toBeNull();
        expect( disableFeature ).not.toHaveBeenCalled();
        await user.click( screen.getByRole( 'button', { name: 'Cancel' } ) );
        expect( screen.queryByRole( 'dialog' ) ).toBeNull();
        expect( disableFeature ).not.toHaveBeenCalled();
    } );
} );
