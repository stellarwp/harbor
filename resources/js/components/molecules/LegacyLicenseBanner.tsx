/**
 * Amber warning banner shown when one or more legacy licenses are active.
 *
 * Legacy license data is fetched from the REST API via the store's
 * getLegacyLicenses resolver.
 *
 * @package LiquidWeb\Harbor
 */
import { __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { AlertTriangle } from 'lucide-react';
import { store as harborStore } from '@/store';

/**
 * @since 1.0.0
 */
export function LegacyLicenseBanner() {
    const hasLegacy = useSelect(
        ( select ) => select( harborStore ).hasLegacyLicenses(),
        []
    );

    if ( ! hasLegacy || ! window.harborData ) {
			return null;
		}

    const portalUrl = window.harborData.subscriptionsUrl;

    return (
        <div
            role="alert"
            className="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 mt-8 mb-0 px-4 py-3 text-sm text-amber-800"
        >
            <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5" />
            <p className="m-0">
                { createInterpolateElement(
                    __( 'You have one or more legacy licenses active. They continue to receive product updates while valid. Consider <a>switching to a unified license</a> to manage all your products with a single key.', '%TEXTDOMAIN%' ),
                    {
                        a: <a href={ portalUrl } target="_blank" rel="noopener noreferrer" className="underline font-medium" />,
                    }
                ) }
            </p>
        </div>
    );
}
