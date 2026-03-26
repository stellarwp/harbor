/**
 * Partitions features for a product into available and locked groups,
 * and groups locked features by catalog tier.
 *
 * @package LiquidWeb\Harbor
 */
import { useSelect } from '@wordpress/data';
import { useFilteredFeatures } from '@/hooks/useFilteredFeatures';
import { store as harborStore } from '@/store';
import { isFreeFeature } from '@/lib/license-utils';
import { getFeatureMismatch } from '@/lib/feature-utils';
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

    const { catalogTiers, activeLegacySlugs, userTierRank } = useSelect(
        ( select ) => {
            const tiers = select( harborStore ).getProductCatalog( productSlug )?.tiers ?? [];

            // Derive the rank of the user's licensed tier for this product.
            const licenseProducts = select( harborStore ).getLicenseProducts();
            const licenseProduct  = licenseProducts.find( ( lp ) => lp.product_slug === productSlug );
            const userTier        = licenseProduct?.tier
                ? tiers.find( ( t ) => t.slug === licenseProduct.tier )
                : null;
            const rank = userTier?.rank ?? -1;  // -1 = unlicensed (show all tier groups)

            // When the product is covered by a unified tier, legacy slugs are irrelevant.
            if ( select( harborStore ).isProductUnifiedLicensed( productSlug ) ) {
                return { catalogTiers: tiers, activeLegacySlugs: new Set<string>(), userTierRank: rank };
            }

            const slugs = new Set(
                select( harborStore ).getLegacyLicenses()
                    .filter( ( l ) => l.is_active )
                    .map( ( l ) => l.slug )
            );

            return { catalogTiers: tiers, activeLegacySlugs: slugs, userTierRank: rank };
        },
        [ productSlug ]
    );

    const isLegacyAvailable = ( f: Feature ) => activeLegacySlugs.has( f.slug );

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

    const sortedCatalogTiers  = catalogTiers.slice().sort( ( a, b ) => a.rank - b.rank );
    const upgradeCatalogTiers = sortedCatalogTiers.filter( ( t ) => t.rank > userTierRank );

    const lockedByTier = sortedCatalogTiers.reduce<Record<string, Feature[]>>(
        ( acc, tier ) => {
            acc[ tier.slug ] = lockedFeatures.filter( ( f ) => f.tier === tier.slug );
            return acc;
        },
        {}
    );

    return { availableFeatures, lockedByTier, sortedCatalogTiers, upgradeCatalogTiers };
}
