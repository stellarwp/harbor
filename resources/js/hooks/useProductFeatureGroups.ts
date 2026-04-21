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
    availableFeatures:      Feature[];
    lockedByTier:           Record<string, Feature[]>;
    sortedCatalogTiers:     CatalogTier[];  // All tiers — used for header tier name lookup
    upgradeCatalogTiers:    CatalogTier[];  // Tiers strictly above the user's rank — upgrade CTA shown
    activationCatalogTiers: CatalogTier[];  // Tiers within the user's rank, locked only because not activated — no upgrade CTA
}

/**
 * @since 1.0.0
 */
export function useProductFeatureGroups( productSlug: string ): FeatureGroups {
    const allFeatures = useFilteredFeatures( productSlug );

    const { catalogTiers, licenseProducts, isLicenseValid, legacyLicenses } = useSelect(
        ( select ) => ({
            catalogTiers:    select( harborStore ).getProductCatalog( productSlug )?.tiers ?? [],
            licenseProducts: select( harborStore ).getLicenseProducts(),
            isLicenseValid:  select( harborStore ).isProductLicenseValid( productSlug ),
            legacyLicenses:  select( harborStore ).getLegacyLicenses(),
        }),
        [ productSlug ]
    );

    return useMemo( () => {
        const sorted         = catalogTiers.slice().sort( ( a, b ) => a.rank - b.rank );
        const forProduct     = licenseProducts.filter( ( lp ) => lp.product_slug === productSlug );
        const licenseProduct = forProduct.find( ( lp ) => lp.activated_here === true );

        // A license is "invalid" when a validation status is known but not 'valid'
        // (e.g. not_activated, expired, suspended). The raw tier is still present on
        // the product, but features are locked — the user needs to activate, not upgrade.
        const isLicenseInvalid = licenseProduct !== undefined &&
            licenseProduct.validation_status !== null &&
            licenseProduct.validation_status !== 'valid';

        // Always resolve the real licensed tier rank so upgrade tiers are computed
        // correctly even for invalid licenses.
        const userTier = licenseProduct?.tier
            ? sorted.find( ( t ) => t.tier_slug === licenseProduct.tier )
            : null;
        const rank     = userTier?.rank ?? -1;  // -1 = unlicensed (show all tier groups)

        // Tiers strictly above the user's rank: features here need an upgrade.
        const upgrade = sorted.filter( ( t ) => t.rank > rank );

        // For invalid licenses: tiers within the user's licensed rank (excluding free)
        // are locked because the product is not activated, not because the tier is wrong.
        // These render without an upgrade button.
        const activationTiers = isLicenseInvalid
            ? sorted.filter( ( t ) => t.rank <= rank && t.rank > 0 )
            : [];
        const slugs          = isLicenseValid
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
                acc[ tier.tier_slug ] = lockedFeatures.filter( ( f ) => f.tier === tier.tier_slug );
                return acc;
            },
            {}
        );

        return {
            availableFeatures,
            lockedByTier,
            sortedCatalogTiers:     sorted,
            upgradeCatalogTiers:    upgrade,
            activationCatalogTiers: activationTiers,
        };
    }, [ allFeatures, catalogTiers, licenseProducts, isLicenseValid, legacyLicenses, productSlug ] );
}
