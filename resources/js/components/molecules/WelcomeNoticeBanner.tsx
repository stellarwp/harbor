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
 * @since TBD
 */
export function WelcomeNoticeBanner( { children }: WelcomeNoticeBannerProps ) {
    return (
        <div className="flex gap-3 rounded-lg border border-neutral-200 bg-blue-50 pl-4 pr-11 py-4 text-sm text-blue-900">
            <Info className="color-blue-600 mt-0.5 h-4 w-4 shrink-0" />
            <p className="text-[13px] leading-6 m-0">{ children }</p>
        </div>
    );
}
