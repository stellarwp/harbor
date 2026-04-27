/**
 * Card showing a single licensed product: logo, name, tier badge, and an
 * expandable list of per-tier rows with expiry dates and Activate buttons.
 *
 * @package LiquidWeb\Harbor
 */
import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { ChevronDown, ChevronUp, ExternalLink } from 'lucide-react';
import { LicenseBadge } from '@/components/atoms/LicenseBadge';
import { Button } from '@/components/ui/button';
import { Tooltip } from '@/components/ui/tooltip';
import { ProductLogo } from '@/components/atoms/ProductLogo';
import { buildActivationUrl } from '@/lib/activation-url';
import { cn } from '@/lib/utils';
import {
    formatDate,
    getExpiryStatus,
    expiryTextClass,
} from '@/lib/license-utils';
import type { LicenseProduct, LicenseStatus } from '@/types/api';

interface LicenseProductCardProps {
    productSlug:   string;
    productName:   string;
    /** All tiers for this product. Must arrive pre-sorted ascending by rank. */
    tiers:         LicenseProduct[];
    tierNameMap:   Record<string, string>;
    activationUrl: string | null;
}

function getStatusBadgeType( lp: LicenseProduct ): Exclude<LicenseStatus, 'licensed'> {
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

/**
 * @since 1.0.0
 */
export function LicenseProductCard( {
    productSlug,
    productName,
    tiers,
    tierNameMap,
    activationUrl,
}: LicenseProductCardProps ) {
    const activatedTiers   = tiers.filter( ( lp ) => lp.is_valid && lp.activated_here === true );
    const hasActivatedTier = activatedTiers.length > 0;

    const [ isOpen, setIsOpen ] = useState( ! hasActivatedTier );

    const Chevron = isOpen ? ChevronUp : ChevronDown;

    const topActivatedTier = activatedTiers[ activatedTiers.length - 1 ];

    const headerBadge = hasActivatedTier && topActivatedTier
        ? (
            <LicenseBadge
                type="licensed"
                tierName={ tierNameMap[ topActivatedTier.tier ] ?? topActivatedTier.tier }
                className="text-[10px] shrink-0"
            />
        )
        : (
            <LicenseBadge
                type={ getStatusBadgeType( tiers[ 0 ] ) }
                className="text-[10px] shrink-0"
            />
        );

    return (
        <div className="rounded-lg border bg-card overflow-hidden">
            <button
                type="button"
                aria-expanded={ isOpen }
                onClick={ () => setIsOpen( ( v ) => ! v ) }
                className="w-full flex items-center gap-2 px-3 py-2.5 text-left cursor-pointer"
            >
                <ProductLogo slug={ productSlug } size={ 24 } variant="nobg" productName={ productName } />
                <span className="text-sm font-medium text-foreground flex-1 min-w-0 truncate text-left">
                    { productName }
                </span>
                { headerBadge }
                <Chevron className="w-3.5 h-3.5 text-muted-foreground shrink-0 transition-transform duration-150" />
            </button>

            { isOpen && (
                <div className="border-t">
                    { tiers.map( ( lp ) => {
                        const expiryStatus    = getExpiryStatus( lp.expires );
                        const isActivatedHere = lp.is_valid && lp.activated_here === true;
                        const showActivate    = ! isActivatedHere && !! activationUrl;

                        return (
                            <div
                                key={ `${ lp.product_slug }:${ lp.tier }` }
                                className="flex items-center justify-between px-3 py-2 bg-muted/50 border-b last:border-b-0"
                            >
                                <div className="flex flex-col gap-0.5 min-w-0">
									<Tooltip label={ isActivatedHere ? __( 'Activated', '%TEXTDOMAIN%' ) : __( 'Not activated', '%TEXTDOMAIN%' ) } className="flex items-center gap-2">
                                        <span className="font-bold text-[11px] w-fit whitespace-nowrap">
                                            { tierNameMap[ lp.tier ] ?? lp.tier }
                                        </span>
										<span
											aria-hidden="true"
											className={ cn( 'size-1.5 rounded-full shrink-0', isActivatedHere ? 'bg-green-500' : 'bg-amber-500' ) }
										/>
									</Tooltip>
                                    <span className={ cn( 'text-[11px]', expiryTextClass[ expiryStatus ] ) }>
                                        { expiryStatus === 'expired'
                                            ? __( 'Expired', '%TEXTDOMAIN%' )
                                            : __( 'Expires', '%TEXTDOMAIN%' ) }
                                        { ' ' }
                                        { formatDate( lp.expires ) }
                                    </span>
                                </div>
                                { showActivate && (
                                    <Button variant="outline" size="xs" asChild className="shrink-0 ml-2">
                                        <a
                                            href={ buildActivationUrl( activationUrl, lp.product_slug, lp.tier ) }
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            { __( 'Activate', '%TEXTDOMAIN%' ) }
                                            <ExternalLink className="w-3 h-3 -translate-y-px" />
                                        </a>
                                    </Button>
                                ) }
                            </div>
                        );
                    } ) }
                </div>
            ) }
        </div>
    );
}
