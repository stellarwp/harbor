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
    isUnactivatedLicense:   boolean;        // true when the user owns the tier but has not activated it on this domain
}

/**
 * @since 1.0.0
 */
export function useProductFeatureGroups( productSlug: string ): FeatureGroups {
    const allFeatures = useFilteredFeatures( productSlug );

    const { catalogTiers, licenseProducts, isLicenseValid, legacyLicenses, unactivatedLicenseProduct } = useSelect(
        ( select ) => ({
            catalogTiers:              select( harborStore ).getProductCatalog( productSlug )?.tiers ?? [],
            licenseProducts:           select( harborStore ).getLicenseProducts(),
            isLicenseValid:            select( harborStore ).isProductLicenseValid( productSlug ),
            legacyLicenses:            select( harborStore ).getLegacyLicenses(),
            unactivatedLicenseProduct: select( harborStore ).getUnactivatedLicenseProduct( productSlug ),
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

        // When no activated product is found, fall back to an unactivated one so that
        // tiers the user already owns are not presented as upgrade opportunities.
        const isUnactivatedLicense    = licenseProduct === undefined && unactivatedLicenseProduct !== null;
        const effectiveLicenseForRank = licenseProduct ?? unactivatedLicenseProduct ?? null;

        // Always resolve the real licensed tier rank so upgrade tiers are computed
        // correctly even for invalid or unactivated licenses.
        const userTier = effectiveLicenseForRank?.tier
            ? sorted.find( ( t ) => t.tier_slug === effectiveLicenseForRank.tier )
            : null;
        const rank     = userTier?.rank ?? -1;  // -1 = unlicensed (show all tier groups)

        // Tiers strictly above the user's rank: features here need an upgrade.
        const upgrade = sorted.filter( ( t ) => t.rank > rank );

        // activationTiers covers two cases:
        //   1. isLicenseInvalid: activated_here=true but status is not 'valid' (expired, suspended, etc.)
        //   2. isUnactivatedLicense: user owns the tier but the domain is not in the activations list
        // Both render without an upgrade button.
        const activationTiers = ( isLicenseInvalid || isUnactivatedLicense )
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
            isUnactivatedLicense,
        };
    }, [ allFeatures, catalogTiers, licenseProducts, isLicenseValid, legacyLicenses, productSlug, unactivatedLicenseProduct ] );
}
