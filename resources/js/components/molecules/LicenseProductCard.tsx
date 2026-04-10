/**
 * Card showing a single licensed product: logo, name, tier badge, and expiry.
 *
 * @package LiquidWeb\Harbor
 */
import { __ } from '@wordpress/i18n';
import { LicenseBadge } from '@/components/atoms/LicenseBadge';
import { ProductLogo } from '@/components/atoms/ProductLogo';
import { formatDate, getExpiryStatus, expiryCardClass, expiryTextClass } from '@/lib/license-utils';
import type { LicenseProduct } from '@/types/api';

interface LicenseProductCardProps {
	lp:          LicenseProduct;
	productName: string;
	tierName:    string;
}

/**
 * @since 1.0.0
 */
export function LicenseProductCard( { lp, productName, tierName }: LicenseProductCardProps ) {
	const expiryStatus   = getExpiryStatus( lp.expires );
	const isNotActivated = lp.validation_status === 'not_activated' || lp.validation_status === 'activation_required';

	return (
		<div className={ `rounded-lg border bg-card px-3 py-2.5 space-y-2.5 ${ expiryCardClass[ expiryStatus ] }` }>
			<div className="flex items-center gap-2">
				<ProductLogo slug={ lp.product_slug } size={ 24 } variant="nobg" productName={ productName } />
				<span className="text-sm font-medium text-foreground flex-1 min-w-0">
					{ productName }
				</span>
				{ isNotActivated
					? <LicenseBadge type="unactivated" className="text-[10px]" />
					: <LicenseBadge type="licensed" tierName={ tierName } className="text-[10px]" />
				}
			</div>
			<span className={ `text-xs ${ expiryTextClass[ expiryStatus ] }` }>
				{ expiryStatus === 'expired'
					? __( 'Expired', '%TEXTDOMAIN%' )
					: __( 'Expires', '%TEXTDOMAIN%' ) }
				{ ' ' }
				{ formatDate( lp.expires ) }
			</span>
		</div>
	);
}
