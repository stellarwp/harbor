/**
 * Reads whether a feature's pending update, or the version installing it
 * would download, needs a newer WordPress or PHP version than the site runs.
 *
 * @package LiquidWeb\Harbor
 */
import { __, sprintf } from '@wordpress/i18n';
import type { Feature } from '@/types/api';

export interface UnmetRequirements {
	/** The version that has the requirements. */
	version: string;
	/** The required WordPress version, or null when the site meets it. */
	wp:      string | null;
	/** The required PHP version, or null when the site meets it. */
	php:     string | null;
}

/**
 * Builds the unmet requirements from a version and its compatibility flags,
 * or null when the site meets them all.
 */
function toUnmet(
	version: string | null | undefined,
	wpCompatible: boolean | undefined,
	requiresWp: string | null | undefined,
	phpCompatible: boolean | undefined,
	requiresPhp: string | null | undefined
): UnmetRequirements | null {
	if ( ! version ) {
		return null;
	}

	const wp = wpCompatible === false ? requiresWp ?? null : null;
	const php = phpCompatible === false ? requiresPhp ?? null : null;

	if ( ! wp && ! php ) {
		return null;
	}

	return { version, wp, php };
}

/**
 * The requirements of the feature's pending update that the site does not
 * meet, or null when there is no update or the site meets them all.
 *
 * @since 2.0.4
 */
export function getUnmetUpdateRequirements( feature: Feature ): UnmetRequirements | null {
	return toUnmet(
		feature.update_version,
		feature.wp_compatible,
		feature.requires_wp,
		feature.php_compatible,
		feature.requires_php
	);
}

/**
 * The requirements of the catalog version that the site does not meet, for a
 * feature that is not installed, or null when it is installed or the site
 * meets them all. The catalog version is the version installing the feature
 * downloads. The REST API reports its requirements in the same fields as a
 * pending update's, since a feature that is not installed has no update.
 *
 * @since 2.0.4
 */
export function getUnmetInstallRequirements( feature: Feature ): UnmetRequirements | null {
	if ( feature.installed_version ) {
		return null;
	}

	return toUnmet(
		feature.version,
		feature.wp_compatible,
		feature.requires_wp,
		feature.php_compatible,
		feature.requires_php
	);
}

/**
 * Short text for the tooltip of a control disabled by the requirements.
 *
 * @since 2.0.4
 */
export function getRequirementsLabel( unmet: UnmetRequirements ): string {
	if ( unmet.wp && unmet.php ) {
		return sprintf(
			/* translators: 1: required WordPress version, 2: required PHP version */
			__( 'Requires WordPress %1$s and PHP %2$s or newer.', '%TEXTDOMAIN%' ),
			unmet.wp,
			unmet.php
		);
	}

	if ( unmet.wp ) {
		return sprintf(
			/* translators: %s: required WordPress version */
			__( 'Requires WordPress %s or newer.', '%TEXTDOMAIN%' ),
			unmet.wp
		);
	}

	return sprintf(
		/* translators: %s: required PHP version */
		__( 'Requires PHP %s or newer.', '%TEXTDOMAIN%' ),
		unmet.php ?? ''
	);
}
