/**
 * Product logo resolved from a slug-based SVG asset.
 *
 * Falls back to a neutral placeholder box when no asset is found.
 * Use variant="nobg" for the transparent (no background) logo variants.
 *
 * @package LiquidWeb\Harbor
 */
import { sprintf, __ } from '@wordpress/i18n';
import logoGive from '@img/logo-give.svg';
import logoTheEventsCalendar from '@img/logo-tec.svg';
import logoLearnDash from '@img/logo-learndash.svg';
import logoKadence from '@img/logo-kadence.svg';
import logoGiveNobg from '@img/logo-givewp-nobg.svg';
import logoLearnDashNobg from '@img/logo-learndash-nobg.svg';
import logoTecNobg from '@img/logo-tec-nobg.svg';
import logoKadenceNobg from '@img/logo-kadence-nobg.svg';
import logoNps from '@img/logo-nps.svg';

const LOGOS: Record<string, string> = {
    give:                  logoGive,
    'the-events-calendar': logoTheEventsCalendar,
    learndash:             logoLearnDash,
    kadence:               logoKadence,
    'nexcess-plugin-stack': logoNps,
};

const LOGOS_NOBG: Record<string, string> = {
    give:                  logoGiveNobg,
    'the-events-calendar': logoTecNobg,
    learndash:             logoLearnDashNobg,
    kadence:               logoKadenceNobg,
    'nexcess-plugin-stack': logoNps,
};

interface ProductLogoProps {
    slug:        string;
    size:        number;
    productName: string;
    variant?:    'default' | 'nobg';
}

/**
 * @since 1.0.0
 */
export function ProductLogo( { slug, size, productName, variant = 'default' }: ProductLogoProps ) {
    const src = ( variant === 'nobg' ? LOGOS_NOBG : LOGOS )[ slug ];

    /* translators: %s: product name (e.g. "Kadence", "Give") */
    const alt = sprintf( __( '%s logo', '%TEXTDOMAIN%' ), productName );

    if ( ! src ) {
        return (
            <div
                className="rounded bg-muted shrink-0"
                role="img"
                aria-label={ alt }
                style={ { width: size, height: size } }
            />
        );
    }

    return (
        <img
            src={ src }
            alt={ alt }
            className="shrink-0 rounded"
            style={ { width: size, height: size } }
        />
    );
}
