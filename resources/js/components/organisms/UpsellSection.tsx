/**
 * Upsell section: products not covered by the current license.
 *
 * @package LiquidWeb\Harbor
 */
import { __ } from '@wordpress/i18n';
import { Rocket } from 'lucide-react';
import { SectionHeader } from '@/components/atoms/SectionHeader';
import { UpsellCard } from '@/components/molecules/UpsellCard';
import type { UpsellOffer } from '@/lib/upsell-offers';

interface UpsellSectionProps {
    offers: UpsellOffer[];
}

/**
 * @since 1.0.0
 */
export function UpsellSection( { offers }: UpsellSectionProps ) {
    if ( offers.length === 0 ) return null;

    return (
        <>
            <hr className="border-t border-0 !border-b-0" />

            <div className="space-y-3">
                <SectionHeader
                    icon={ <Rocket className="w-4 h-4 text-muted-foreground" /> }
                    label={ __( 'Add to your plan', '%TEXTDOMAIN%' ) }
                />
                <div className="space-y-2">
                    { offers.map( ( offer ) => (
                        <UpsellCard
                            key={ offer.product.slug }
                            { ...offer }
                        />
                    ) ) }
                </div>
            </div>
        </>
    );
}
