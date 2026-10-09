/**
 * Subscription pills using the backend's effective access sources.
 *
 * @package LiquidWeb\Harbor
 */
import { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Tooltip as TooltipPrimitive } from 'radix-ui';
import { badgeVariants } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { FeatureAccessSource } from '@/types/api';

interface FeatureAccessSourcesProps {
    sources?: FeatureAccessSource[];
}

type PurchaseAccessSource = Extract<FeatureAccessSource, { type: 'purchase' }>;

/**
 * Show a short subscription label, with the full purchase on hover, focus, or tap.
 */
function PurchaseAccessPill( { source }: { source: PurchaseAccessSource } ) {
    const [ open, setOpen ] = useState( false );
    const purchaseName = source.tier_name
        ? /* translators: 1: purchase name, 2: tier name. */
          sprintf( __( '%1$s (%2$s)', '%TEXTDOMAIN%' ), source.product_name, source.tier_name )
        : source.product_name;
    const isPackage = source.product_slug === 'nexcess-plugin-stack';
    const pillLabel = isPackage ? __( 'Plugin Stack', '%TEXTDOMAIN%' ) : source.tier_name || source.product_name;
    /* translators: %s: purchase and tier name. */
    const label = sprintf( __( 'Included in your %s subscription.', '%TEXTDOMAIN%' ), purchaseName );

    return (
        <TooltipPrimitive.Root open={ open } onOpenChange={ setOpen }>
            <TooltipPrimitive.Trigger asChild>
                <button
                    type="button"
                    aria-label={ label }
                    className={ cn(
                        badgeVariants( { variant: 'secondary' } ),
                        'px-2 py-px text-xs leading-4 cursor-help focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                        isPackage && 'bg-white text-blue-600 border-blue-500'
                    ) }
                    onClick={ ( event ) => {
                        // Radix normally closes a tooltip on click. Keep it visible for taps.
                        event.preventDefault();
                        setOpen( true );
                    } }
                >
                    { pillLabel }
                </button>
            </TooltipPrimitive.Trigger>
            <TooltipPrimitive.Portal>
                <TooltipPrimitive.Content
                    side="top"
                    sideOffset={ 6 }
                    // Portals sit outside Harbor's scoped CSS, matching the shared Tooltip.
                    style={ {
                        zIndex: 100001,
                        maxWidth: 240,
                        padding: '6px 10px',
                        borderRadius: 6,
                        fontSize: 12,
                        lineHeight: 1.45,
                        backgroundColor: '#1a1a1a',
                        color: '#fff',
                        boxShadow: '0 4px 12px rgba(0,0,0,0.2)',
                    } }
                >
                    { label }
                </TooltipPrimitive.Content>
            </TooltipPrimitive.Portal>
        </TooltipPrimitive.Root>
    );
}

/**
 * Show the purchases providing access without re-evaluating licensing rules.
 */
export function FeatureAccessSources( { sources = [] }: FeatureAccessSourcesProps ) {
    const purchases = sources.filter( ( source ) => source.type === 'purchase' );

    if ( purchases.length === 0 ) {
        return null;
    }

    return (
        <TooltipPrimitive.Provider delayDuration={ 150 }>
            <ul className="m-0! p-0! flex flex-wrap items-center gap-1.5 list-none" aria-label={ __( 'Included with', '%TEXTDOMAIN%' ) }>
                { purchases.map( ( source ) => (
                    <li className="m-0!" key={ `${ source.product_slug }:${ source.tier }` }>
                        <PurchaseAccessPill source={ source } />
                    </li>
                ) ) }
            </ul>
        </TooltipPrimitive.Provider>
    );
}
