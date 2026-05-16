/**
 * No-license welcome screen.
 *
 * Composes WelcomeShell chrome with the hardcoded non-unified-license
 * notice and the unified license form. The notice copy lives here so
 * WelcomeNoticeBanner stays a generic container.
 *
 * createInterpolateElement turns a single translatable string with
 * marker tags into real React elements — translators see one
 * continuous sentence; React renders an actual <strong> node.
 *
 * @package LiquidWeb\Harbor
 */
import { createInterpolateElement } from '@wordpress/element';
import { __ }                       from '@wordpress/i18n';
import { WelcomeShell }             from '@/components/templates/WelcomeShell';
import { WelcomeNoticeBanner }      from '@/components/molecules/WelcomeNoticeBanner';
import { WelcomeLicenseForm }       from '@/components/molecules/WelcomeLicenseForm';

/**
 * @since 1.0.0
 */
export function WelcomeScreen() {
    return (
        <WelcomeShell>
            <WelcomeNoticeBanner>
                { createInterpolateElement(
                    __(
                        '<strong>Have a non-unified license?</strong> Licenses issued before May 12, 2026 are managed inside each plugin\'s own settings.',
                        '%TEXTDOMAIN%'
                    ),
                    { strong: <strong /> }
                ) }
            </WelcomeNoticeBanner>
            <WelcomeLicenseForm />
        </WelcomeShell>
    );
}
