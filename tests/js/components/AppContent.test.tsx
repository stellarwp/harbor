import { render, screen }    from '@testing-library/react';
import { useSelect }          from '@wordpress/data';
import { useHarborData }      from '@/context/harbor-data-context';
import { App }                from '@/App';

jest.mock( '@wordpress/data', () => ( {
    useSelect:  jest.fn(),
    useDispatch: jest.fn( () => ( {} ) ),
} ) );

jest.mock( '@/store', () => ( { store: { name: 'harbor' } } ) );

jest.mock( '@/context/harbor-data-context', () => {
    const actual = jest.requireActual( '@/context/harbor-data-context' );
    return {
        ...actual,
        HarborDataProvider: ( { children }: { children: React.ReactNode } ) => <>{ children }</>,
        useHarborData:      jest.fn(),
    };
} );

jest.mock( '@/context/toast-context', () => ( {
    ToastProvider: ( { children }: { children: React.ReactNode } ) => <>{ children }</>,
    useToast:      () => ( { addToast: jest.fn() } ),
} ) );

jest.mock( '@/context/filter-context', () => ( {
    FilterProvider: ( { children }: { children: React.ReactNode } ) => <>{ children }</>,
    useFilter:      () => ( { productFilter: 'all', setProductFilter: jest.fn() } ),
} ) );

jest.mock( '@/context/error-modal-context', () => ( {
    ErrorModalProvider: ( { children }: { children: React.ReactNode } ) => <>{ children }</>,
    useErrorModal:      () => ( { addError: jest.fn(), removeError: jest.fn() } ),
} ) );

jest.mock( '@/context/reload-banner-context', () => ( {
    ReloadBannerProvider: ( { children }: { children: React.ReactNode } ) => <>{ children }</>,
    useReloadBanner:      () => ( { needsReload: false, setNeedsReload: jest.fn() } ),
} ) );

jest.mock( '@/components/templates/AppShell',          () => ( { AppShell:      () => <div data-testid="app-shell" /> } ) );
jest.mock( '@/components/organisms/AppLoader',         () => ( { AppLoader:     () => <div data-testid="app-loader" /> } ) );
jest.mock( '@/components/organisms/WelcomeScreen',     () => ( { WelcomeScreen: () => <div data-testid="welcome-screen" /> } ) );
jest.mock( '@/components/organisms/ErrorModal',        () => ( { ErrorModal:    () => null } ) );
jest.mock( '@/components/ui/toast',                    () => ( { Toaster:       () => null } ) );
jest.mock( '@/components/atoms/ErrorBoundary',         () => ( { ErrorBoundary: ( { children }: { children: React.ReactNode } ) => <>{ children }</> } ) );

const mockedUseSelect    = useSelect      as unknown as jest.Mock;
const mockedUseHarborData = useHarborData as unknown as jest.Mock;

function setLicenseState( isLicenseLoading: boolean, hasLicense: boolean ) {
    mockedUseHarborData.mockReturnValue( { isLoading: false, isLicenseLoading } );
    mockedUseSelect.mockImplementation( ( cb: ( select: unknown ) => unknown ) =>
        cb( () => ( { hasLicense: () => hasLicense } ) )
    );
}

describe( 'AppContent routing', () => {
    afterEach( () => {
        jest.clearAllMocks();
    } );

    it( 'renders AppLoader while the license is still resolving', () => {
        setLicenseState( true, false );

        render( <App /> );

        expect( screen.queryByTestId( 'app-loader' ) ).not.toBeNull();
        expect( screen.queryByTestId( 'welcome-screen' ) ).toBeNull();
        expect( screen.queryByTestId( 'app-shell' ) ).toBeNull();
    } );

    it( 'renders WelcomeScreen when resolved with no license', () => {
        setLicenseState( false, false );

        render( <App /> );

        expect( screen.queryByTestId( 'welcome-screen' ) ).not.toBeNull();
        expect( screen.queryByTestId( 'app-loader' ) ).toBeNull();
        expect( screen.queryByTestId( 'app-shell' ) ).toBeNull();
    } );

    it( 'renders AppShell when a license is present', () => {
        setLicenseState( false, true );

        render( <App /> );

        expect( screen.queryByTestId( 'app-shell' ) ).not.toBeNull();
        expect( screen.queryByTestId( 'app-loader' ) ).toBeNull();
        expect( screen.queryByTestId( 'welcome-screen' ) ).toBeNull();
    } );

    it( 'stays in AppShell after hasLicense flips false (latch holds)', () => {
        setLicenseState( false, true );

        const { rerender } = render( <App /> );
        expect( screen.queryByTestId( 'app-shell' ) ).not.toBeNull();

        // Simulate license removal: hasLicense becomes false on the same mount.
        setLicenseState( false, false );
        rerender( <App /> );

        expect( screen.queryByTestId( 'app-shell' ) ).not.toBeNull();
        expect( screen.queryByTestId( 'welcome-screen' ) ).toBeNull();
    } );

    it( 'stays in AppShell when a transient refresh re-flags isLicenseLoading', () => {
        setLicenseState( false, true );

        const { rerender } = render( <App /> );
        expect( screen.queryByTestId( 'app-shell' ) ).not.toBeNull();

        // Refresh button transiently clears the key while the resolver runs.
        setLicenseState( true, false );
        rerender( <App /> );

        expect( screen.queryByTestId( 'app-shell' ) ).not.toBeNull();
        expect( screen.queryByTestId( 'app-loader' ) ).toBeNull();
    } );
} );
