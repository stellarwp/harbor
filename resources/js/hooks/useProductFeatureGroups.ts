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
import type { CatalogTier, Feature } from '@/types/api';

interface FeatureGroups {
    availableFeatures:  Feature[];
    lockedByTier:       Record<string, Feature[]>;
    sortedCatalogTiers: CatalogTier[];
}

/**
 * @since 1.0.0
 */
export function useProductFeatureGroups( productSlug: string ): FeatureGroups {
    const allFeatures = useFilteredFeatures( productSlug );

    const { catalogTiers, activeLegacySlugs } = useSelect(
        ( select ) => {
            const tiers = select( harborStore ).getProductCatalog( productSlug )?.tiers ?? [];

            // When the product is covered by a unified tier, legacy slugs are irrelevant.
            if ( select( harborStore ).isProductUnifiedLicensed( productSlug ) ) {
                return { catalogTiers: tiers, activeLegacySlugs: new Set<string>() };
            }

            const slugs = new Set(
                select( harborStore ).getLegacyLicenses()
                    .filter( ( l ) => l.is_active )
                    .map( ( l ) => l.slug )
            );

            return { catalogTiers: tiers, activeLegacySlugs: slugs };
        },
        [ productSlug ]
    );

    const isLegacyAvailable = ( f: Feature ) => activeLegacySlugs.has( f.slug );

    const availableFeatures = allFeatures.filter( ( f ) => f.is_available || isFreeFeature( f.tier ) || isLegacyAvailable( f ) );
    const lockedFeatures    = allFeatures.filter( ( f ) => ! f.is_available && ! isFreeFeature( f.tier ) && ! isLegacyAvailable( f ) );

    const sortedCatalogTiers = catalogTiers.slice().sort( ( a, b ) => a.rank - b.rank );

    const lockedByTier = sortedCatalogTiers.reduce<Record<string, Feature[]>>(
        ( acc, tier ) => {
            acc[ tier.slug ] = lockedFeatures.filter( ( f ) => f.tier === tier.slug );
            return acc;
        },
        {}
    );

    return { availableFeatures, lockedByTier, sortedCatalogTiers };
}
