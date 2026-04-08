/**
 * License section: header, key input, and licensed-product cards.
 *
 * @package LiquidWeb\Harbor
 */
import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { KeyRound, Loader2, RefreshCw } from 'lucide-react';
import { SectionHeader } from '@/components/atoms/SectionHeader';
import { LicenseKeyInputSkeleton } from '@/components/atoms/LicenseKeyInputSkeleton';
import { LicenseKeyInput } from '@/components/molecules/LicenseKeyInput';
import { LicenseProductCard } from '@/components/molecules/LicenseProductCard';
import { PRODUCTS } from '@/data/products';
import type { LicenseProduct } from '@/types/api';
import type HarborError from '@/errors/harbor-error';

interface LicenseSectionProps {
    licenseKey:      string | null;
    licenseProducts: LicenseProduct[];
    tierNameMap:     Record<string, string>;
    onRemove:        () => Promise<HarborError | null>;
    onRefresh:       () => Promise<void>;
    isRefreshing:    boolean;
    isLoading:       boolean;
}

/**
 * Pulse-skeleton that mirrors LicenseProductCard's layout while the license
 * resolver is still in flight.
 */
function LicenseSectionSkeleton() {
    return (
        <div className="space-y-3">
            { PRODUCTS.map( ( p ) => (
                <div key={ p.slug } className="rounded-lg border bg-card px-3 py-2.5 space-y-2.5 animate-pulse">
                    <div className="flex items-center gap-2">
                        { /* logo */ }
                        <div className="w-6 h-6 rounded shrink-0 bg-muted" />
                        { /* product name */ }
                        <div className="h-3.5 flex-1 rounded bg-muted" />
                        { /* tier badge */ }
                        <div className="h-4 w-14 rounded-full shrink-0 bg-muted" />
                    </div>
                    { /* expiry */ }
                    <div className="h-3 w-24 rounded bg-muted" />
                </div>
            ) ) }
        </div>
    );
}

/**
 * @since 1.0.0
 */
export function LicenseSection( { licenseKey, licenseProducts, tierNameMap, onRemove, onRefresh, isRefreshing, isLoading }: LicenseSectionProps ) {
    const [ isEditing, setIsEditing ] = useState( false );

    const hasLicense = licenseKey !== null;

    const handleRemove = async (): Promise<HarborError | null> => {
        const error = await onRemove();
        if ( ! error ) {
            setIsEditing( false );
        }
        return error;
    };

    return (
        <div className="space-y-3">
            <SectionHeader
                icon={ <KeyRound className="w-4 h-4 text-muted-foreground" /> }
                label={ __( 'License', '%TEXTDOMAIN%' ) }
                action={ hasLicense && (
                    <button
                        type="button"
                        onClick={ onRefresh }
                        disabled={ isRefreshing }
                        className="flex cursor-pointer items-center gap-1 text-[11px] text-muted-foreground transition-colors hover:opacity-75 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        { isRefreshing
                            ? <Loader2 className="w-3 h-3 animate-spin" />
                            : <RefreshCw className="w-3 h-3" />
                        }
                        { isRefreshing
                            ? __( 'Refreshing...', '%TEXTDOMAIN%' )
                            : __( 'Refresh', '%TEXTDOMAIN%' )
                        }
                    </button>
                ) }
            />

            { isLoading ? (
                <>
                    <LicenseKeyInputSkeleton />
                    <LicenseSectionSkeleton />
                </>
            ) : (
                <>
                    <LicenseKeyInput
                        currentKey={ licenseKey }
                        isEditing={ isEditing }
                        onEdit={ () => setIsEditing( true ) }
                        onCancel={ () => setIsEditing( false ) }
                        onRemove={ handleRemove }
                        onSuccess={ () => setIsEditing( false ) }
                    />
                    { ! hasLicense && (
                        <p className="text-xs text-muted-foreground leading-relaxed mt-0 mb-0">
                            { __( 'Enter your license key to unlock features.', '%TEXTDOMAIN%' ) }
                        </p>
                    ) }
                </>
            ) }

            { ! isLoading && hasLicense && licenseProducts.length > 0 && (
                <div className="space-y-3">
                    { licenseProducts.map( ( lp ) => (
                        <LicenseProductCard
                            key={ lp.product_slug }
                            lp={ lp }
                            productName={ PRODUCTS.find( ( p ) => p.slug === lp.product_slug )?.name ?? lp.product_slug }
                            tierName={ tierNameMap[ lp.tier ] ?? lp.tier }
                        />
                    ) ) }
                </div>
            ) }
        </div>
    );
}
