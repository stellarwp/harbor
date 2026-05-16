/**
 * Boot-time loader.
 *
 * Mounted by AppContent while the license resolver is on its first
 * resolve. Borrows WelcomeShell chrome because that is the only chrome
 * available before license resolution decides which screen to show.
 *
 * @package LiquidWeb\Harbor
 */
import { __ }           from '@wordpress/i18n';
import { Loader2 }      from 'lucide-react';
import { WelcomeShell } from '@/components/templates/WelcomeShell';

/**
 * @since 1.0.0
 */
export function AppLoader() {
    return (
        <WelcomeShell>
            <div className="flex flex-col items-center gap-2 text-sm text-muted-foreground">
                <Loader2 className="w-5 h-5 animate-spin" />
                { __( 'Loading…', '%TEXTDOMAIN%' ) }
            </div>
        </WelcomeShell>
    );
}
