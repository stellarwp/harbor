/**
 * Collapsible locked-feature list with an optional purchase or activation action.
 *
 * @package LiquidWeb\Harbor
 */
import { useId, useState, type ReactNode } from 'react';
import { ChevronRight, ChevronDown, Lock } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { FeatureRow } from '@/components/molecules/FeatureRow';
import type { Feature } from '@/types/api';

interface LockedFeatureGroupProps {
    label: string;
    features: Feature[];
    forceOpen?: boolean;
    action?: ReactNode;
    upgradeTierName?: string;
}

/**
 * Share the accordion presentation while keeping tier and package purchase rules with their callers.
 */
export function LockedFeatureGroup( { label, features, forceOpen = false, action, upgradeTierName }: LockedFeatureGroupProps ) {
    const [ expanded, setExpanded ] = useState( false );
    const contentId = useId();
    const isOpen = expanded || forceOpen;
    const Chevron = isOpen ? ChevronDown : ChevronRight;

    return (
        <>
            <div className="w-full flex flex-wrap items-center gap-2 px-4 py-3 bg-muted/50 border-b">
                <button
                    type="button"
                    aria-expanded={ isOpen }
                    aria-controls={ contentId }
                    onClick={ () => setExpanded( ! expanded ) }
                    className="flex items-center gap-2 cursor-pointer text-left hover:opacity-80"
                >
                    <Chevron className="w-4 h-4 shrink-0" aria-hidden="true" />
                    <span className="font-medium text-sm">{ label }</span>
                    <Badge variant="secondary" className="text-xs">{ features.length }</Badge>
                    <Lock className="w-3.5 h-3.5 shrink-0 text-muted-foreground ml-1" aria-hidden="true" />
                </button>
                { action && <div className="ml-auto shrink-0">{ action }</div> }
            </div>
            <div id={ contentId }>
                { isOpen && features.map( ( feature ) => (
                    <FeatureRow key={ feature.slug } feature={ feature } upgradeTierName={ upgradeTierName } />
                ) ) }
            </div>
        </>
    );
}
