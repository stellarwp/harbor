/**
 * Card showing a single licensed product: logo, name, tier badge, and expiry.
 *
 * When a product is unactivated and an activation URL is provided, the card
 * renders a tinted footer strip with a status indicator and an Activate button
 * (Option E layout).
 *
 * @package LiquidWeb\Harbor
 */
import { __ } from '@wordpress/i18n';
import { LicenseBadge } from '@/components/atoms/LicenseBadge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ProductLogo } from '@/components/atoms/ProductLogo';
import { formatDate, getExpiryStatus, expiryCardClass, expiryTextClass } from '@/lib/license-utils';
import type { LicenseProduct } from '@/types/api';

interface LicenseProductCardProps {
	lp:             LicenseProduct;
	productName:    string;
	tierName:       string;
	activationUrl?: string;
}

/**
 * @since 1.0.0
 */
function getStatusBadgeType( lp: LicenseProduct ): 'unactivated' | 'expired' | 'cancelled' | 'suspended' | 'over_limit' | 'unlicensed' {
	if ( lp.is_valid && lp.activated_here === false ) return 'unactivated';
	switch ( lp.validation_status ) {
		case 'not_activated':
		case 'activation_required': return 'unactivated';
		case 'expired':             return 'expired';
		case 'cancelled':           return 'cancelled';
		case 'suspended':
		case 'license_suspended':
		case 'license_banned':      return 'suspended';
		case 'out_of_activations':  return 'over_limit';
		default:                    return 'unlicensed';
	}
}

export function LicenseProductCard( { lp, productName, tierName, activationUrl }: LicenseProductCardProps ) {
	const expiryStatus    = getExpiryStatus( lp.expires );
	const isActivatedHere = lp.is_valid && lp.activated_here === true;
	const statusBadgeType = ! isActivatedHere ? getStatusBadgeType( lp ) : null;
	const showFooter      = statusBadgeType === 'unactivated' && !! activationUrl;

	return (
		<div className={ `rounded-lg border bg-card overflow-hidden ${ expiryCardClass[ expiryStatus ] }` }>
			<div className="px-3 py-2.5 space-y-2.5">
				<div className="flex items-center gap-2">
					<ProductLogo slug={ lp.product_slug } size={ 24 } variant="nobg" productName={ productName } />
					<span className="text-sm font-medium text-foreground flex-1 min-w-0">
						{ productName }
					</span>
					<Badge variant={ isActivatedHere ? 'gradient' : 'secondary' } className="text-[10px] shrink-0">
						{ tierName }
					</Badge>
				</div>
				<div className="flex items-center justify-between">
					<span className={ `text-xs ${ expiryTextClass[ expiryStatus ] }` }>
						{ expiryStatus === 'expired'
							? __( 'Expired', '%TEXTDOMAIN%' )
							: __( 'Expires', '%TEXTDOMAIN%' ) }
						{ ' ' }
						{ formatDate( lp.expires ) }
					</span>
					{ statusBadgeType && ! showFooter && (
						<LicenseBadge type={ statusBadgeType } className="text-[10px]" />
					) }
				</div>
			</div>
			{ showFooter && (
				<div className="flex items-center justify-between px-3 py-1.5 bg-muted/50 border-t">
					<div className="flex items-center gap-1.5">
						<span className="size-1.5 rounded-full bg-amber-500 shrink-0" />
						<span className="text-xs text-muted-foreground">
							{ __( 'Not activated', '%TEXTDOMAIN%' ) }
						</span>
					</div>
					<Button variant="outline" size="xs" asChild>
						<a href={ activationUrl } target="_blank" rel="noopener noreferrer">
							{ __( 'Activate', '%TEXTDOMAIN%' ) }
						</a>
					</Button>
				</div>
			) }
		</div>
	);
}
