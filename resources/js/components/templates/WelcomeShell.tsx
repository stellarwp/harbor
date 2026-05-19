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
 * @since 1.3.0
 */
export function WelcomeShell( { children }: WelcomeShellProps ) {
    return (
        <div className="absolute top-0 left-0 w-full max-w-full flex flex-col items-center justify-center h-[calc(100vh-32px)] bg-white">
            <div className="w-full max-w-104">
                <div className="flex flex-col items-center gap-1.5 text-center">
                    <NexcessLogo className="w-18 h-18" />
                    <div className="-space-y-1 mb-4">
                        <h1 className="text-2xl leading-8 font-semibold tracking-wide p-0 text-neutral-950">
                            { __( 'Software License Manager', '%TEXTDOMAIN%' ) }
                        </h1>
                        <p className="text-xs leading-8 font-semibold m-0 text-neutral-950">
                            { __( 'Liquid Web by Nexcess', '%TEXTDOMAIN%' ) }
                        </p>
                    </div>
                </div>
				<div className="space-y-8">
					{ children }
				</div>
            </div>
        </div>
    );
}
