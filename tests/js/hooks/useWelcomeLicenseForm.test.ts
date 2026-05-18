import { act, renderHook }      from '@testing-library/react';
import { useDispatch, useSelect } from '@wordpress/data';
import {
    pickWelcomeErrorMessage,
    useWelcomeLicenseForm,
}                                  from '@/hooks/useWelcomeLicenseForm';
import { HarborError, ErrorCode } from '@/errors';

jest.mock( '@wordpress/data', () => ( {
    useDispatch: jest.fn(),
    useSelect:   jest.fn(),
} ) );

jest.mock( '@/lib/harbor-data', () => ( {
    getHarborDataValue: jest.fn().mockReturnValue( 'LWSW-' ),
} ) );

jest.mock( '@/store', () => ( { store: { name: 'harbor' } } ) );

const mockedUseDispatch = useDispatch as unknown as jest.Mock;
const mockedUseSelect   = useSelect   as unknown as jest.Mock;

function setupSelectors( overrides: { isStoring?: boolean; canModifyLicense?: boolean } = {} ) {
    const { isStoring = false, canModifyLicense = true } = overrides;
    mockedUseSelect.mockImplementation( ( cb: ( select: unknown ) => unknown ) =>
        cb( () => ( {
            isLicenseStoring: () => isStoring,
            canModifyLicense: () => canModifyLicense,
        } ) )
    );
}

describe( 'useWelcomeLicenseForm', () => {
    let storeLicense: jest.Mock;

    beforeEach( () => {
        storeLicense = jest.fn().mockResolvedValue( null );
        mockedUseDispatch.mockReturnValue( { storeLicense } );
        setupSelectors();
    } );

    afterEach( () => {
        jest.clearAllMocks();
    } );

    it( 'starts with empty state and cannot submit', () => {
        const { result } = renderHook( () => useWelcomeLicenseForm() );

        expect( result.current.key ).toBe( '' );
        expect( result.current.serverError ).toBeNull();
        expect( result.current.showFormatHint ).toBe( false );
        expect( result.current.canSubmit ).toBe( false );
    } );

    it( 'shows format hint for non-LWSW input and disables submit', () => {
        const { result } = renderHook( () => useWelcomeLicenseForm() );

        act( () => result.current.onKeyChange( 'gpl-123' ) );

        expect( result.current.key ).toBe( 'GPL-123' );
        expect( result.current.showFormatHint ).toBe( true );
        expect( result.current.canSubmit ).toBe( false );
    } );

    it( 'enables submit for LWSW-prefixed input', () => {
        const { result } = renderHook( () => useWelcomeLicenseForm() );

        act( () => result.current.onKeyChange( 'lwsw-abc' ) );

        expect( result.current.key ).toBe( 'LWSW-ABC' );
        expect( result.current.showFormatHint ).toBe( false );
        expect( result.current.canSubmit ).toBe( true );
    } );

    it( 'allows partial-prefix typing without showing the hint but blocks submit until min length', () => {
        // "LWSW" matches the start of the prefix so the hint stays hidden,
        // but the length is below the prefix length so submit remains blocked.
        const { result } = renderHook( () => useWelcomeLicenseForm() );

        act( () => result.current.onKeyChange( 'LWSW' ) );

        expect( result.current.showFormatHint ).toBe( false );
        expect( result.current.canSubmit ).toBe( false );
    } );

    it( 'clears any prior server error when the key changes', async () => {
        storeLicense.mockResolvedValueOnce( new HarborError(
            { code: 'lw-harbor-invalid-key', message: 'Bad key.' }
        ) );

        const { result } = renderHook( () => useWelcomeLicenseForm() );

        act( () => result.current.onKeyChange( 'LWSW-XYZ' ) );
        await act( async () => { await result.current.onActivate(); } );
        expect( result.current.serverError ).not.toBeNull();

        act( () => result.current.onKeyChange( 'LWSW-ABC' ) );
        expect( result.current.serverError ).toBeNull();
    } );

    it( 'disables submit when canModifyLicense is false', () => {
        setupSelectors( { canModifyLicense: false } );
        const { result } = renderHook( () => useWelcomeLicenseForm() );

        act( () => result.current.onKeyChange( 'LWSW-OK' ) );

        expect( result.current.canSubmit ).toBe( false );
    } );

    it( 'disables submit while isStoring is true', () => {
        setupSelectors( { isStoring: true } );
        const { result } = renderHook( () => useWelcomeLicenseForm() );

        act( () => result.current.onKeyChange( 'LWSW-OK' ) );

        expect( result.current.canSubmit ).toBe( false );
    } );

    it( 'does not call storeLicense while canSubmit is false', async () => {
        const { result } = renderHook( () => useWelcomeLicenseForm() );

        // No input → canSubmit false.
        await act( async () => { await result.current.onActivate(); } );

        expect( storeLicense ).not.toHaveBeenCalled();
    } );

    it( 'leaves serverError null when storeLicense resolves successfully', async () => {
        const { result } = renderHook( () => useWelcomeLicenseForm() );

        act( () => result.current.onKeyChange( 'LWSW-ABC' ) );
        await act( async () => { await result.current.onActivate(); } );

        expect( storeLicense ).toHaveBeenCalledWith( 'LWSW-ABC' );
        expect( result.current.serverError ).toBeNull();
    } );
} );

describe( 'pickWelcomeErrorMessage', () => {
    function withCause( serverCode: string, serverMessage: string ): HarborError {
        const cause = new HarborError( { code: serverCode, message: serverMessage } );
        return new HarborError(
            ErrorCode.LicenseStoreFailed,
            'Liquid Web Software Manager failed to validate your license.',
            { cause }
        );
    }

    it( 'returns a single canned message for lw-harbor-invalid-key', () => {
        const error  = withCause( 'lw-harbor-invalid-key', 'License key not recognized.' );
        const result = pickWelcomeErrorMessage( error );

        // The canned message discards the server text and directs the user
        // toward the plugin-settings path.
        expect( result ).toMatch( /We couldn't verify this key/ );
        expect( result ).toMatch( /non-unified license/ );
        expect( result ).toMatch( /plugin's own settings page/ );
        // The server's diagnostic is intentionally not surfaced.
        expect( result ).not.toMatch( /License key not recognized\./ );
    } );

    it.each( [
        'lw-harbor-expired',
        'lw-harbor-suspended',
        'lw-harbor-cancelled',
        'lw-harbor-license-banned',
        'lw-harbor-out-of-activations',
        'lw-harbor-invalid-response',
    ] )( 'returns the server message verbatim for %s', ( code ) => {
        const error  = withCause( code, `Server said ${ code }.` );
        const result = pickWelcomeErrorMessage( error );

        expect( result ).toBe( `Server said ${ code }.` );
        expect( result ).not.toMatch( /non-unified license/ );
    } );

    it( 'falls back to error.message when no cause is attached', () => {
        const error  = new HarborError(
            ErrorCode.LicenseActionInProgress,
            'Another action in progress.'
        );
        const result = pickWelcomeErrorMessage( error );

        expect( result ).toBe( 'Another action in progress.' );
        expect( result ).not.toMatch( /non-unified license/ );
    } );

    it( 'returns the canned invalid-key message even when cause.message is whitespace', () => {
        const error  = withCause( 'lw-harbor-invalid-key', '   ' );
        const result = pickWelcomeErrorMessage( error );

        // Whitespace in the server message doesn't change anything — the
        // invalid-key branch returns the canned message regardless.
        expect( result ).toMatch( /We couldn't verify this key/ );
        expect( result ).toMatch( /plugin's own settings page/ );
    } );
} );
