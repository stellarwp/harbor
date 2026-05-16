/**
 * Nexcess brand mark used in the welcome flow.
 *
 * Decorative — the visible "Software License Manager" heading carries the
 * accessible name. Uses the default URL-string export from the SVG loader,
 * matching ProductLogo and FilterBar.
 *
 * @package LiquidWeb\Harbor
 */
import nexcessLogoUrl from '@img/logo-nexcess.svg';

interface NexcessLogoProps {
    className?: string;
}

/**
 * @since 1.0.0
 */
export function NexcessLogo( { className }: NexcessLogoProps ) {
    return (
        <img
            src={ nexcessLogoUrl }
            alt=""
            className={ className }
        />
    );
}
