/**
 * Error modal organism.
 *
 * Renders when the ErrorModalContext holds active errors. Lists each error
 * and provides a Dismiss button so the user can close the modal and interact
 * with the UI (e.g. to update the license key).
 *
 * @package LiquidWeb\Harbor
 */
import { __ } from '@wordpress/i18n';
import { ExternalLink, Mail } from 'lucide-react';
import { useErrorModal } from '@/context/error-modal-context';
import { Dialog, DialogContent, DialogFooter, DialogHeader } from '@/components/ui/dialog';
import { ErrorItem } from '@/components/atoms/ErrorItem';
import { Button } from '@/components/ui/button';

const DOCS_URL    = 'https://go.liquidweb.com/harbor-docs';
const SUPPORT_URL = 'https://go.liquidweb.com/harbor-support';

/**
 * @since 1.0.0
 */
export function ErrorModal() {
    const { errors, clearAll } = useErrorModal();

    if ( errors.length === 0 ) return null;

    return (
        <Dialog open onClose={ clearAll } maxWidth="max-w-xl">
            <DialogHeader
                title={ __( 'There are issues that need your attention', '%TEXTDOMAIN%' ) }
                onClose={ clearAll }
            />
            <DialogContent>
                <ul className="flex flex-col gap-6">
                    { errors.map( ( error ) => (
                        <ErrorItem key={ error.code } error={ error } />
                    ) ) }
                </ul>
            </DialogContent>
            <DialogFooter className="justify-between items-center bg-gray-100 rounded-b-lg py-4">
                <div className="flex flex-col gap-1">
                    <p className="text-xs text-muted-foreground m-0">
                        { __( 'Need help resolving this?', '%TEXTDOMAIN%' ) }
                    </p>
                    <div className="flex gap-8">
                        <a
                            href={ DOCS_URL }
                            target="_blank"
                            rel="noreferrer"
                            className="flex items-center gap-1 text-xs font-bold uppercase text-muted-foreground hover:text-foreground transition-colors"
                        >
                            { __( 'View documentation', '%TEXTDOMAIN%' ) }
                            <ExternalLink className="w-3 h-3 -translate-y-px" />
                        </a>
                        <a
                            href={ SUPPORT_URL }
                            target="_blank"
                            rel="noreferrer"
                            className="flex items-center gap-1 text-xs font-bold uppercase text-muted-foreground hover:text-foreground transition-colors"
                        >
                            { __( 'Contact support', '%TEXTDOMAIN%' ) }
                            <Mail className="w-3 h-3 -translate-y-px" />
                        </a>
                    </div>
                </div>
                <Button size="lg" onClick={ clearAll }>
                    { __( 'Dismiss', '%TEXTDOMAIN%' ) }
                </Button>
            </DialogFooter>
        </Dialog>
    );
}
