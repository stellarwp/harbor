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
import { useErrorModal } from '@/context/error-modal-context';
import { Dialog, DialogContent, DialogFooter, DialogHeader } from '@/components/ui/dialog';
import { ErrorItem } from '@/components/atoms/ErrorItem';
import { Button } from '@/components/ui/button';

/**
 * @since 1.0.0
 */
export function ErrorModal() {
    const { errors, clearAll } = useErrorModal();

    if ( errors.length === 0 ) return null;

    // Flatten the full error chain of every error (including cause/additionalErrors),
    // then deduplicate by message so the same API message does not appear twice when
    // multiple resolvers fail for the same root cause (e.g. an invalid license key).
    const messages = Array.from(
        new Set( errors.flatMap( ( e ) => e.toArray().map( ( entry ) => entry.message ) ) )
    );

    return (
        <Dialog open onClose={ clearAll }>
            <DialogHeader
                title={ __( 'There are issues that need your attention', '%TEXTDOMAIN%' ) }
                onClose={ clearAll }
            />
            <DialogContent>
                <ul className="space-y-2">
                    { messages.map( ( message ) => (
                        <ErrorItem key={ message } message={ message } />
                    ) ) }
                </ul>
            </DialogContent>
            <DialogFooter>
                <Button onClick={ clearAll }>
                    { __( 'Dismiss', '%TEXTDOMAIN%' ) }
                </Button>
            </DialogFooter>
        </Dialog>
    );
}
