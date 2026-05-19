/**
 * Info banner shown when all licensed products are unactivated on this domain.
 *
 * Fires when every product's validation_status is 'not_activated' or
 * 'activation_required'. Links to the Liquid Web portal so the user can
 * activate their license for this domain.
 *
 * @package LiquidWeb\Harbor
 */
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { Info } from 'lucide-react';
import { store as harborStore } from '@/store';
import { getHarborDataValue } from '@/lib/harbor-data';

/**
 * @since 1.3.0   Read activationUrl through the getHarborDataValue helper.
 * @since 1.0.0
 */
export function NotActivatedBanner() {
	const allNotActivated = useSelect(
		( select ) => select( harborStore ).areAllProductsNotActivated(),
		[]
	);
	const licenseKey = useSelect(
		( select ) => select( harborStore ).getLicenseKey(),
		[]
	);

	const activationUrl = getHarborDataValue( 'activationUrl' );
	if ( ! allNotActivated || ! licenseKey || ! activationUrl ) return null;

	return (
		<div
			role="alert"
			className="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 mt-8 mb-0 px-4 py-3 text-sm text-blue-800"
		>
			<Info className="w-4 h-4 shrink-0 mt-0.5" />
			<p className="m-0">
				{ __(
					'None of your products are activated for this domain. Activate your license to download and activate premium products.',
					'%TEXTDOMAIN%'
				) }
				{ ' ' }
				<a href={ activationUrl } target="_blank" rel="noopener noreferrer" className="underline font-medium">
					{ __( 'Activate now', '%TEXTDOMAIN%' ) }
				</a>
			</p>
		</div>
	);
}
