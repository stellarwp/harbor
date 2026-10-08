/**
 * Purchase logos using the backend's effective access sources.
 *
 * @package LiquidWeb\Harbor
 */
import { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Tooltip as TooltipPrimitive } from 'radix-ui';
import { ProductLogo } from '@/components/atoms/ProductLogo';
import type { FeatureAccessSource } from '@/types/api';

interface FeatureAccessSourcesProps {
    sources?: FeatureAccessSource[];
}

type PurchaseAccessSource = Extract<FeatureAccessSource, { type: 'purchase' }>;

/** Reveals the full purchase name on hover, keyboard focus, or tap. */
function PurchaseAccessIcon( { source }: { source: PurchaseAccessSource } ) {
    const [ open, setOpen ] = useState( false );
    const purchaseName = source.tier_name
        ? /* translators: 1: purchase name, 2: tier name. */
          sprintf( __( '%1$s (%2$s)', '%TEXTDOMAIN%' ), source.product_name, source.tier_name )
        : source.product_name;
    /* translators: %s: purchase and tier name. */
    const label = sprintf( __( 'Included with %s', '%TEXTDOMAIN%' ), purchaseName );

    return (
        <TooltipPrimitive.Root open={ open } onOpenChange={ setOpen }>
            <TooltipPrimitive.Trigger asChild>
                <button
                    type="button"
                    aria-label={ label }
                    className="flex size-6 items-center justify-center rounded cursor-help hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    onClick={ ( event ) => {
                        // Radix normally closes a tooltip on click. Keep it visible for taps.
                        event.preventDefault();
                        setOpen( true );
                    } }
                >
                    <span aria-hidden="true">
                        <ProductLogo slug={ source.product_slug } size={ 20 } productName={ source.product_name } variant="nobg" />
                    </span>
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

/** Shows the purchases providing access without re-evaluating licensing rules. */
export function FeatureAccessSources( { sources = [] }: FeatureAccessSourcesProps ) {
    const purchases = sources.filter( ( source ) => source.type === 'purchase' );

    if ( purchases.length === 0 ) {
        return null;
    }

    return (
        <TooltipPrimitive.Provider delayDuration={ 150 }>
            <ul className="m-0! p-0! flex flex-wrap justify-end gap-1 list-none" aria-label={ __( 'Included with', '%TEXTDOMAIN%' ) }>
                { purchases.map( ( source ) => (
                    <li className="m-0!" key={ `${ source.product_slug }:${ source.tier }` }>
                        <PurchaseAccessIcon source={ source } />
                    </li>
                ) ) }
            </ul>
        </TooltipPrimitive.Provider>
    );
}
