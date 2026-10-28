/**
 * Amber notice shown under a feature row when its pending update, or the
 * version installing it would download, needs a newer WordPress or PHP
 * version than the site runs.
 *
 * Links to the WordPress update screen for the WordPress requirement and to
 * WordPress's page on updating PHP for the PHP requirement.
 *
 * @package LiquidWeb\Harbor
 */
import { __, sprintf } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import { AlertTriangle } from 'lucide-react';
import { getHarborDataValue } from '@/lib/harbor-data';
import type { UnmetRequirements } from '@/lib/requirements';

interface RequirementsNoticeProps {
	unmet: UnmetRequirements;
}

/**
 * @since TBD
 */
export function RequirementsNotice( { unmet }: RequirementsNoticeProps ) {
	const links = {
		wp:  <a href={ getHarborDataValue( 'updateCoreUrl' ) } className="underline font-medium" />,
		php: <a href={ getHarborDataValue( 'updatePhpUrl' ) } target="_blank" rel="noopener noreferrer" className="underline font-medium" />,
	};

	let message: string;

	if ( unmet.wp && unmet.php ) {
		message = sprintf(
			/* translators: 1: version of the update, 2: required WordPress version, 3: required PHP version */
			__( 'Version %1$s requires WordPress %2$s and PHP %3$s or newer. <wp>Update WordPress</wp> and <php>learn more about updating PHP</php> to install it.', '%TEXTDOMAIN%' ),
			unmet.version,
			unmet.wp,
			unmet.php
		);
	} else if ( unmet.wp ) {
		message = sprintf(
			/* translators: 1: version of the update, 2: required WordPress version */
			__( 'Version %1$s requires WordPress %2$s or newer. <wp>Update WordPress</wp> to install it.', '%TEXTDOMAIN%' ),
			unmet.version,
			unmet.wp
		);
	} else {
		message = sprintf(
			/* translators: 1: version of the update, 2: required PHP version */
			__( 'Version %1$s requires PHP %2$s or newer. <php>Learn more about updating PHP</php>.', '%TEXTDOMAIN%' ),
			unmet.version,
			unmet.php ?? ''
		);
	}

	return (
		<div
			role="status"
			className="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"
		>
			<AlertTriangle className="w-3.5 h-3.5 shrink-0 mt-0.5" />
			<p className="m-0">
				{ createInterpolateElement( message, links ) }
			</p>
		</div>
	);
}
