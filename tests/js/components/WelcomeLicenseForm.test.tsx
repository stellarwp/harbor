import { render, screen }            from '@testing-library/react';
import { WelcomeLicenseForm }        from '@/components/molecules/WelcomeLicenseForm';
import { useWelcomeLicenseForm }     from '@/hooks/useWelcomeLicenseForm';
import type { UseWelcomeLicenseForm } from '@/hooks/useWelcomeLicenseForm';

jest.mock( '@/hooks/useWelcomeLicenseForm', () => ( {
    useWelcomeLicenseForm: jest.fn(),
} ) );

jest.mock( '@/lib/harbor-data', () => ( {
    getHarborDataValue:        jest.fn().mockReturnValue( 'LWSW-' ),
    getLicenseKeyPlaceholder:  jest.fn().mockReturnValue( 'LWSW-XXXX-XXXX-XXXX-XXXX-XXXX' ),
} ) );

const mockedHook = useWelcomeLicenseForm as unknown as jest.Mock;

function stub( overrides: Partial<UseWelcomeLicenseForm> = {} ): UseWelcomeLicenseForm {
    return {
        key:              '',
        serverError:      null,
        isStoring:        false,
        canModifyLicense: true,
        showFormatHint:   false,
        canSubmit:        false,
        onKeyChange:      jest.fn(),
        onActivate:       jest.fn(),
        ...overrides,
    };
}

describe( 'WelcomeLicenseForm', () => {
    afterEach( () => {
        jest.clearAllMocks();
    } );

    it( 'renders the section header, input, and activate button', () => {
        mockedHook.mockReturnValue( stub() );

        render( <WelcomeLicenseForm /> );

        expect( screen.queryByText( 'Unified License' ) ).not.toBeNull();
        expect( screen.queryByPlaceholderText( /^LWSW-/ ) ).not.toBeNull();
        expect( screen.queryByRole( 'button', { name: 'Activate' } ) ).not.toBeNull();
    } );

    it( 'renders the format hint paragraph without role="alert" and links it via aria-describedby', () => {
        mockedHook.mockReturnValue( stub( { key: 'GPL-123', showFormatHint: true } ) );

        render( <WelcomeLicenseForm /> );

        const hint = document.getElementById( 'welcome-license-hint' );
        expect( hint ).not.toBeNull();
        expect( hint?.getAttribute( 'role' ) ).toBeNull();

        const input = screen.getByPlaceholderText( /^LWSW-/ );
        expect( input.getAttribute( 'aria-describedby' ) ).toBe( 'welcome-license-hint' );
    } );

    it( 'renders the server error with role="alert" and links it via aria-describedby', () => {
        mockedHook.mockReturnValue( stub( { key: 'LWSW-XYZ', serverError: 'Boom.' } ) );

        render( <WelcomeLicenseForm /> );

        const errorEl = document.getElementById( 'welcome-license-error' );
        expect( errorEl ).not.toBeNull();
        expect( errorEl?.getAttribute( 'role' ) ).toBe( 'alert' );

        const input = screen.getByPlaceholderText( /^LWSW-/ );
        expect( input.getAttribute( 'aria-describedby' ) ).toBe( 'welcome-license-error' );
    } );

    it( 'disables the activate button when canSubmit is false', () => {
        mockedHook.mockReturnValue( stub( { canSubmit: false } ) );

        render( <WelcomeLicenseForm /> );

        const button = screen.getByRole( 'button', { name: 'Activate' } ) as HTMLButtonElement;
        expect( button.disabled ).toBe( true );
    } );

    it( 'enables the activate button when canSubmit is true', () => {
        mockedHook.mockReturnValue( stub( { key: 'LWSW-OK', canSubmit: true } ) );

        render( <WelcomeLicenseForm /> );

        const button = screen.getByRole( 'button', { name: 'Activate' } ) as HTMLButtonElement;
        expect( button.disabled ).toBe( false );
    } );
} );
