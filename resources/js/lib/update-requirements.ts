/**
 * Reads whether a feature's pending update needs a newer WordPress or PHP
 * version than the site runs.
 *
 * @package LiquidWeb\Harbor
 */
import { __, sprintf } from '@wordpress/i18n';
import type { Feature } from '@/types/api';

export interface UnmetUpdateRequirements {
	/** The version of the pending update. */
	version: string;
	/** The required WordPress version, or null when the site meets it. */
	wp:      string | null;
	/** The required PHP version, or null when the site meets it. */
	php:     string | null;
}

/**
 * The requirements of the feature's pending update that the site does not
 * meet, or null when there is no update or the site meets them all.
 *
 * @since TBD
 */
export function getUnmetUpdateRequirements( feature: Feature ): UnmetUpdateRequirements | null {
	if ( ! feature.update_version ) {
		return null;
	}

	const wp = feature.update_wp_compatible === false ? feature.update_requires_wp ?? null : null;
	const php = feature.update_php_compatible === false ? feature.update_requires_php ?? null : null;

	if ( ! wp && ! php ) {
		return null;
	}

	return { version: feature.update_version, wp, php };
}

/**
 * Short text for the disabled Update button's tooltip.
 *
 * @since TBD
 */
export function getUpdateRequirementsLabel( unmet: UnmetUpdateRequirements ): string {
	if ( unmet.wp && unmet.php ) {
		return sprintf(
			/* translators: 1: required WordPress version, 2: required PHP version */
			__( 'This update requires WordPress %1$s and PHP %2$s or newer.', '%TEXTDOMAIN%' ),
			unmet.wp,
			unmet.php
		);
	}

	if ( unmet.wp ) {
		return sprintf(
			/* translators: %s: required WordPress version */
			__( 'This update requires WordPress %s or newer.', '%TEXTDOMAIN%' ),
			unmet.wp
		);
	}

	return sprintf(
		/* translators: %s: required PHP version */
		__( 'This update requires PHP %s or newer.', '%TEXTDOMAIN%' ),
		unmet.php ?? ''
	);
}
