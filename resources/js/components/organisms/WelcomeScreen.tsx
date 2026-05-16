/**
 * No-license welcome screen.
 *
 * Phase 1 renders nothing below the shell chrome. The notice banner and
 * license form are wired in Phase 2.
 *
 * @package LiquidWeb\Harbor
 */
import { WelcomeShell } from '@/components/templates/WelcomeShell';

/**
 * @since 1.0.0
 */
export function WelcomeScreen() {
    return (
        <WelcomeShell>
            { /* Phase 2: WelcomeNoticeBanner + WelcomeLicenseForm */ }
        </WelcomeShell>
    );
}
