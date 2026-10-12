import type { Feature, FeatureMismatchType } from '@/types/api';

export type LicenseBadgeType = 'free' | 'legacy' | 'bonus' | 'revoked';

/**
 * True when a feature requires no paid tier — either it has no tier at all
 * or its tier slug contains "free" (e.g. "give-free").
 *
 * @since 1.0.0
 */
export function isFreeFeature( tier: string | null ): boolean {
    return ! tier || tier.toLowerCase().includes( 'free' );
}

/**
 * Returns the single license badge type to display for a feature row, or null if none applies.
 *
 * Enforces mutual exclusivity: only the first matching condition wins.
 *
 * @since 1.0.0
 */
export function getLicenseBadgeType( feature: Feature, isLegacy: boolean ): LicenseBadgeType | null {
    // Resolved grants are authoritative; older responses omit access_sources.
    const isFree = feature.access_sources !== undefined
        ? feature.access_sources.some( ( source ) => source.type === 'free' )
        : isFreeFeature( feature.tier );

    if ( isFree ) {
        return 'free';
    }
    // A package can provide unified access without a separate brand subscription.
    const hasPurchase = feature.access_sources?.some( ( source ) => source.type === 'purchase' ) ?? false;
    if ( isLegacy && ! hasPurchase ) {
        return 'legacy';
    }
    return getFeatureMismatch( feature );
}

/**
 * Returns the mismatch type for a feature, or null if there is no mismatch.
 *
 * Both fields are pre-computed by the backend resolution layer.
 * No catalog or license cross-referencing is needed at call sites.
 *
 * @since 1.0.0
 */
export function getFeatureMismatch( feature: Feature ): FeatureMismatchType {
    if ( feature.is_available && ! feature.in_catalog_tier ) {
        return 'bonus';
    }
    if ( ! feature.is_available && feature.in_catalog_tier ) {
        return 'revoked';
    }
    return null;
}
