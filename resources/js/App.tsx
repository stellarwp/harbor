/**
 * @package LiquidWeb\Harbor
 */
import { AppShell } from '@/components/templates/AppShell';
import { Toaster } from '@/components/ui/toast';
import { ErrorBoundary } from '@/components/atoms/ErrorBoundary';
import { ErrorModal } from '@/components/organisms/ErrorModal';
import { ToastProvider } from '@/context/toast-context';
import { FilterProvider } from '@/context/filter-context';
import { ErrorModalProvider } from '@/context/error-modal-context';
import { HarborDataProvider } from '@/context/harbor-data-context';

export const App = () => {
    return (
        <ToastProvider>
            <FilterProvider>
                <ErrorModalProvider>
                    <HarborDataProvider>
                        <ErrorBoundary>
                            <AppShell />
                            <Toaster />
                        </ErrorBoundary>
                        { /* ErrorModal sits outside ErrorBoundary so a render crash
                             does not prevent the modal from opening. */ }
                        <ErrorModal />
                    </HarborDataProvider>
                </ErrorModalProvider>
            </FilterProvider>
        </ToastProvider>
    );
};
