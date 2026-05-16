/**
 * @package LiquidWeb\Harbor
 */
import { useRef }               from 'react';
import { useSelect }            from '@wordpress/data';
import { AppShell }             from '@/components/templates/AppShell';
import { AppLoader }            from '@/components/organisms/AppLoader';
import { WelcomeScreen }        from '@/components/organisms/WelcomeScreen';
import { Toaster }              from '@/components/ui/toast';
import { ErrorBoundary }        from '@/components/atoms/ErrorBoundary';
import { ErrorModal }           from '@/components/organisms/ErrorModal';
import { ToastProvider }        from '@/context/toast-context';
import { FilterProvider }       from '@/context/filter-context';
import { ErrorModalProvider }   from '@/context/error-modal-context';
import { HarborDataProvider }   from '@/context/harbor-data-context';
import { ReloadBannerProvider } from '@/context/reload-banner-context';
import { useHarborData }        from '@/context/harbor-data-context';
import { store as harborStore } from '@/store';

function AppContent() {
    const { isLicenseLoading } = useHarborData();
    const hasLicense = useSelect(
        ( select ) => select( harborStore ).hasLicense(),
        []
    );

    // Latch: once we've ever seen a license during this mount, stay in
    // AppShell. Guards against license removal and transient refresh
    // failures bouncing the user back to the welcome screen. Mirrors the
    // hasEverResolvedRef pattern in harbor-data-context.tsx.
    const hasEverHadLicenseRef = useRef( false );
    if ( hasLicense ) {
        hasEverHadLicenseRef.current = true;
    }

    if ( hasEverHadLicenseRef.current ) return <AppShell />;
    if ( isLicenseLoading )             return <AppLoader />;
    return <WelcomeScreen />;
}

export const App = () => {
    return (
        <ToastProvider>
            <ReloadBannerProvider>
            <FilterProvider>
                <ErrorModalProvider>
                    <HarborDataProvider>
                        <ErrorBoundary>
                            <AppContent />
                            <Toaster />
                        </ErrorBoundary>
                        { /* ErrorModal sits outside ErrorBoundary so a render crash
                             does not prevent the modal from opening. */ }
                        <ErrorModal />
                    </HarborDataProvider>
                </ErrorModalProvider>
            </FilterProvider>
            </ReloadBannerProvider>
        </ToastProvider>
    );
};
