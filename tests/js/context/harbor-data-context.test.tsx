import { render } from '@testing-library/react';
import { HarborDataProvider, useHarborData } from '@/context/harbor-data-context';
import useResolvableSelect from '@/hooks/use-resolvable-select/use-resolvable-select';
import { useSelect } from '@wordpress/data';
import type { ResolvableSelectResponse } from '@/hooks/use-resolvable-select/types';

jest.mock( '@/hooks/use-resolvable-select/use-resolvable-select', () => jest.fn() );

jest.mock( '@wordpress/data', () => ( {
    useSelect: jest.fn().mockReturnValue( null ),
} ) );

jest.mock( '@/store', () => ( { store: { name: 'harbor' } } ) );

jest.mock( '@/context/error-modal-context', () => ( {
    useErrorModal: () => ( { addError: jest.fn(), removeError: jest.fn() } ),
} ) );

const mockedUseResolvableSelect = useResolvableSelect as unknown as jest.Mock;
const mockedUseSelect           = useSelect           as unknown as jest.Mock;

function resolver( isResolving: boolean, hasResolved: boolean ): ResolvableSelectResponse<unknown> {
    return {
        data:        null,
        status:      isResolving ? 'RESOLVING' : ( hasResolved ? 'SUCCESS' : 'IDLE' ),
        error:       null,
        isResolving,
        hasStarted:  isResolving || hasResolved,
        hasResolved,
    };
}

function setResolvers( cfg: {
    license:        { isResolving: boolean; hasResolved: boolean };
    features?:      { isResolving: boolean; hasResolved: boolean };
    catalog?:       { isResolving: boolean; hasResolved: boolean };
    legacyLicenses?: { isResolving: boolean; hasResolved: boolean };
} ) {
    const noop = { isResolving: false, hasResolved: true };
    mockedUseResolvableSelect.mockReturnValue( {
        license:        resolver( cfg.license.isResolving,        cfg.license.hasResolved ),
        features:       resolver( ( cfg.features        ?? noop ).isResolving, ( cfg.features        ?? noop ).hasResolved ),
        catalog:        resolver( ( cfg.catalog         ?? noop ).isResolving, ( cfg.catalog         ?? noop ).hasResolved ),
        legacyLicenses: resolver( ( cfg.legacyLicenses  ?? noop ).isResolving, ( cfg.legacyLicenses  ?? noop ).hasResolved ),
    } );
    mockedUseSelect.mockReturnValue( null );
}

let captured: { isLoading: boolean; isLicenseLoading: boolean } | null = null;

function Probe() {
    captured = useHarborData();
    return null;
}

describe( 'HarborDataContext', () => {
    afterEach( () => {
        jest.clearAllMocks();
        captured = null;
    } );

    it( 'splits isLoading and isLicenseLoading independently', () => {
        // license has resolved, catalog still resolving.
        setResolvers( {
            license: { isResolving: false, hasResolved: true },
            catalog: { isResolving: true,  hasResolved: false },
        } );

        render( <HarborDataProvider><Probe /></HarborDataProvider> );

        expect( captured ).not.toBeNull();
        expect( captured!.isLoading ).toBe( true );
        expect( captured!.isLicenseLoading ).toBe( false );
    } );

    it( 'flags both loading flags while the license resolver is on its first pass', () => {
        setResolvers( {
            license: { isResolving: true, hasResolved: false },
        } );

        render( <HarborDataProvider><Probe /></HarborDataProvider> );

        expect( captured!.isLoading ).toBe( true );
        expect( captured!.isLicenseLoading ).toBe( true );
    } );

    it( 'clears isLicenseLoading once the license resolver has finished, even if it later re-resolves', () => {
        setResolvers( {
            license: { isResolving: false, hasResolved: true },
        } );

        const { rerender } = render( <HarborDataProvider><Probe /></HarborDataProvider> );
        expect( captured!.isLicenseLoading ).toBe( false );

        // Subsequent re-runs (e.g. refresh) should not flip isLicenseLoading
        // back to true because the per-resolver hasEverResolved ref latches.
        setResolvers( {
            license: { isResolving: true, hasResolved: false },
        } );
        rerender( <HarborDataProvider><Probe /></HarborDataProvider> );

        expect( captured!.isLicenseLoading ).toBe( false );
    } );
} );
