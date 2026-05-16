/**
 * Welcome flow template.
 *
 * Centered single-column layout used by the welcome screen and the
 * boot-time AppLoader. Uses a fixed min-height because 100vh overflows
 * beneath the wp-admin bar and forces a vertical scroll.
 *
 * @package LiquidWeb\Harbor
 */
import { type ReactNode } from 'react';
import { __ }             from '@wordpress/i18n';
import { NexcessLogo }    from '@/components/atoms/NexcessLogo';

interface WelcomeShellProps {
    children?: ReactNode;
}

/**
 * @since 1.0.0
 */
export function WelcomeShell( { children }: WelcomeShellProps ) {
    return (
        <div className="flex flex-col items-center justify-center min-h-[600px] bg-neutral-50 px-4">
            <div className="w-full max-w-sm space-y-6">
                <div className="flex flex-col items-center gap-3 text-center">
                    <NexcessLogo className="w-16 h-16" />
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            { __( 'Software License Manager', '%TEXTDOMAIN%' ) }
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            { __( 'Liquid Web by Nexcess', '%TEXTDOMAIN%' ) }
                        </p>
                    </div>
                </div>
                { children }
            </div>
        </div>
    );
}
