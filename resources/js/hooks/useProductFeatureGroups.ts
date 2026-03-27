/**
 * Partitions features for a product into available and locked groups,
 * and groups locked features by catalog tier.
 *
 * @package LiquidWeb\Harbor
 */
import { useMemo } from 'react';
import { useSelect } from '@wordpress/data';
import { useFilteredFeatures } from '@/hooks/useFilteredFeatures';
import { store as harborStore } from '@/store';
import { isFreeFeature, getFeatureMismatch } from '@/lib/feature-utils';
import type { CatalogTier, Feature } from '@/types/api';

export interface FeatureGroups {
    availableFeatures:   Feature[];
    lockedByTier:        Record<string, Feature[]>;
    sortedCatalogTiers:  CatalogTier[];  // All tiers — used for header tier name lookup
    upgradeCatalogTiers: CatalogTier[];  // Tiers strictly above the user's rank — used for TierGroup rendering
}

/**
 * @since 1.0.0
 */
export function useProductFeatureGroups( productSlug: string ): FeatureGroups {
    const allFeatures = useFilteredFeatures( productSlug );

    const { catalogTiers, licenseProducts, isUnifiedLicensed, legacyLicenses } = useSelect(
        ( select ) => ({
            catalogTiers:      select( harborStore ).getProductCatalog( productSlug )?.tiers ?? [],
            licenseProducts:   select( harborStore ).getLicenseProducts(),
            isUnifiedLicensed: select( harborStore ).isProductUnifiedLicensed( productSlug ),
            legacyLicenses:    select( harborStore ).getLegacyLicenses(),
        }),
        [ productSlug ]
    );

    return useMemo( () => {
        const sorted         = catalogTiers.slice().sort( ( a, b ) => a.rank - b.rank );
        const licenseProduct = licenseProducts.find( ( lp ) => lp.product_slug === productSlug );
        const userTier       = licenseProduct?.tier ? sorted.find( ( t ) => t.slug === licenseProduct.tier ) : null;
        const rank           = userTier?.rank ?? -1;  // -1 = unlicensed (show all tier groups)
        const upgrade        = sorted.filter( ( t ) => t.rank > rank );
        const slugs          = isUnifiedLicensed
            ? new Set<string>()
            : new Set( legacyLicenses.filter( ( l ) => l.is_active ).map( ( l ) => l.slug ) );

        const isLegacyAvailable = ( f: Feature ) => slugs.has( f.slug );

        // Available: the standard set, PLUS revoked features.
        // Revoked features are in the user's tier but have had their capability removed.
        // They render as disabled rows in the available section (not in upgrade accordions),
        // since the user does not need to upgrade — the tier already covers them.
        const availableFeatures = allFeatures.filter( ( f ) =>
            f.is_available ||
            isFreeFeature( f.tier ) ||
            isLegacyAvailable( f ) ||
            getFeatureMismatch( f ) === 'revoked'
        );

        // Locked: not available, not free, not legacy, and not revoked.
        const lockedFeatures = allFeatures.filter( ( f ) =>
            ! f.is_available &&
            ! isFreeFeature( f.tier ) &&
            ! isLegacyAvailable( f ) &&
            getFeatureMismatch( f ) !== 'revoked'
        );

        const lockedByTier = sorted.reduce<Record<string, Feature[]>>(
            ( acc, tier ) => {
                acc[ tier.slug ] = lockedFeatures.filter( ( f ) => f.tier === tier.slug );
                return acc;
            },
            {}
        );

        return {
            availableFeatures,
            lockedByTier,
            sortedCatalogTiers:  sorted,
            upgradeCatalogTiers: upgrade,
        };
    }, [ allFeatures, catalogTiers, licenseProducts, isUnifiedLicensed, legacyLicenses, productSlug ] );
}
