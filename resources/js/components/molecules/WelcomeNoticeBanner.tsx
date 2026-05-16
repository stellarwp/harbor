/**
 * Generic info callout used by the welcome flow.
 *
 * The molecule is a pure passthrough — the caller composes the message
 * (with createInterpolateElement, plain JSX, or a string) and passes
 * it as children. Reusable for any future welcome-screen message
 * without coupling to a specific copy block.
 *
 * @package LiquidWeb\Harbor
 */
import { type ReactNode } from 'react';
import { Info }          from 'lucide-react';

interface WelcomeNoticeBannerProps {
    children: ReactNode;
}

/**
 * @since 1.0.0
 */
export function WelcomeNoticeBanner( { children }: WelcomeNoticeBannerProps ) {
    return (
        <div className="flex gap-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            <Info className="mt-0.5 h-4 w-4 shrink-0" />
            <p className="m-0 leading-relaxed">{ children }</p>
        </div>
    );
}
