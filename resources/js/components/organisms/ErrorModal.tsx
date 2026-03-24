/**
 * Error modal organism.
 *
 * Renders when the ErrorModalContext holds active errors. Lists each error,
 * provides a Dismiss button (closes the modal, leaves the UI intact) and a
 * Retry button (invalidates all resolver caches so @wordpress/data re-fetches
 * on the next render cycle).
 *
 * @package LiquidWeb\Harbor
 */
import { __ } from '@wordpress/i18n';
import { dispatch } from '@wordpress/data';
import { store as harborStore } from '@/store';
import { useErrorModal } from '@/context/error-modal-context';
import { Dialog, DialogContent, DialogFooter, DialogHeader } from '@/components/ui/dialog';
import { ErrorItem } from '@/components/atoms/ErrorItem';
import { Button } from '@/components/ui/button';

type StoreDispatchWithMeta = ReturnType<typeof dispatch> & {
    invalidateResolutionForStoreSelector: ( selectorName: string, args?: unknown[] ) => void;
};

/**
 * @since 1.0.0
 */
export function ErrorModal() {
    const { errors, clearAll } = useErrorModal();

    if ( errors.length === 0 ) return null;

    const handleRetry = () => {
        const storeDispatch = dispatch( harborStore ) as StoreDispatchWithMeta;
        storeDispatch.invalidateResolutionForStoreSelector( 'getLicenseKey', [] );
        storeDispatch.invalidateResolutionForStoreSelector( 'getFeatures', [] );
        storeDispatch.invalidateResolutionForStoreSelector( 'getCatalog', [] );
        storeDispatch.invalidateResolutionForStoreSelector( 'getLegacyLicenses', [] );
        clearAll();
    };

    return (
        <Dialog open onClose={ clearAll }>
            <DialogHeader
                title={ __( 'There are issues that need your attention', '%TEXTDOMAIN%' ) }
                onClose={ clearAll }
            />
            <DialogContent>
                <ul className="space-y-3">
                    { errors.map( ( error ) => (
                        <ErrorItem key={ error.code } error={ error } />
                    ) ) }
                </ul>
            </DialogContent>
            <DialogFooter>
                <Button variant="ghost" onClick={ clearAll }>
                    { __( 'Dismiss', '%TEXTDOMAIN%' ) }
                </Button>
                <Button onClick={ handleRetry }>
                    { __( 'Retry', '%TEXTDOMAIN%' ) }
                </Button>
            </DialogFooter>
        </Dialog>
    );
}
