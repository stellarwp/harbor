/**
 * Product section: sticky dark header + feature list + tier group accordions.
 *
 * Available features render as FeatureRow entries. Locked features are
 * grouped by tier and rendered inside collapsible TierGroup accordions.
 *
 * Header counts (active / deactivated) always reflect the full unfiltered
 * feature set so they remain stable while the user searches.
 *
 * @package LiquidWeb\Harbor
 */
import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { ExternalLink, ChevronDown, ChevronUp } from 'lucide-react';
import { LicenseBadge } from '@/components/atoms/LicenseBadge';
import { ProductLogo } from '@/components/atoms/ProductLogo';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuItem,
} from '@/components/ui/dropdown-menu';
import { FeatureRow } from '@/components/molecules/FeatureRow';
import { TierGroup } from '@/components/molecules/TierGroup';
import { store as harborStore } from '@/store';
import { useFilter } from '@/context/filter-context';
import { useProductFeatureGroups } from '@/hooks/useProductFeatureGroups';
import { buildUpgradeUrl } from '@/lib/upgrade-url';
import { buildActivationUrl } from '@/lib/activation-url';
import { getHarborDataValue } from '@/lib/harbor-data';
import type { Product } from '@/types/api';

interface ProductSectionProps {
    product: Product;
}

/**
 * @since 1.3.0    Read domain through the getHarborDataValue helper for upgrade URLs.
 * @since 1.0.2  Route upgrade CTA to catalog upgrade_url for existing subscribers, purchase_url for new subscribers.
 * @since 1.0.1  Show Unactivated badge on tier groups and product header for unactivated licenses.
 * @since 1.0.0
 */
export function ProductSection( { product }: ProductSectionProps ) {
    const { searchQuery } = useFilter();
    const isSearching = searchQuery.trim().length > 0;

    // Tracks the header tier-picker's open state so its chevron can flip.
    const [ tierMenuOpen, setTierMenuOpen ] = useState( false );

    // Full unfiltered set — used only for header counts so they stay stable.
    const { licenseProduct, hasActiveLegacy, unactivatedLicenseProduct, unactivatedLicenseProducts } = useSelect(
        ( select ) => {
            const licenseProducts = select( harborStore ).getLicenseProducts();
            const forProduct      = licenseProducts.filter( ( lp ) => lp.product_slug === product.slug );
            return {
                licenseProduct:             forProduct.find( ( lp ) => lp.activated_here === true ) ?? null,
                hasActiveLegacy:            select( harborStore ).hasActiveLegacyLicenseForProduct( product.slug ),
                unactivatedLicenseProduct:  select( harborStore ).getUnactivatedLicenseProduct( product.slug ),
                unactivatedLicenseProducts: select( harborStore ).getUnactivatedLicenseProducts( product.slug ),
            };
        },
        [ product.slug ],
    );

    const { availableFeatures, lockedByTier, sortedCatalogTiers, upgradeCatalogTiers, activationCatalogTiers, isUnactivatedLicense } = useProductFeatureGroups( product.slug );

    const activeCount      = availableFeatures.filter( ( f ) => f.is_enabled ).length;
    const deactivatedCount = availableFeatures.filter( ( f ) => ! f.is_enabled ).length;

    // Show "Unactivated" in the header only when there is no activated product at all.
    // An unactivated upgrade tier alongside an active lower tier (e.g. pro active + elite
    // unactivated) should still show the active tier's name — not "Unactivated".
    const isNotActivated = ( licenseProduct === null && isUnactivatedLicense ) || (
        licenseProduct !== null && (
            licenseProduct.validation_status === 'not_activated' ||
            licenseProduct.validation_status === 'activation_required'
        )
    );

    // Owned-but-unactivated products get an Activate CTA beside the header badge,
    // falling back to the unactivated product record when no tier is active here.
    const activationUrl             = getHarborDataValue( 'activationUrl' );
    const effectiveLicenseProduct   = licenseProduct ?? unactivatedLicenseProduct;
    const showHeaderActivate        = isNotActivated && !! activationUrl && !! effectiveLicenseProduct;

    // A unified key can cover multiple unactivated tiers of one product (each a
    // distinct SKU). When it does, offer a picker defaulting to the highest tier
    // instead of silently activating an arbitrary one.
    const activatableTiers = unactivatedLicenseProducts
        .map( ( lp ) => {
            const catalogTier = sortedCatalogTiers.find( ( t ) => t.tier_slug === lp.tier );
            return { lp, rank: catalogTier?.rank ?? -1, name: catalogTier?.name ?? lp.tier };
        } )
        .sort( ( a, b ) => b.rank - a.rank );

    const defaultActivateTier = activatableTiers[ 0 ]?.lp.tier ?? effectiveLicenseProduct?.tier;
    const showTierPicker      = showHeaderActivate && activatableTiers.length > 1;

    const tierName = licenseProduct
        ? ( sortedCatalogTiers.find( ( t ) => t.tier_slug === licenseProduct.tier )?.name ?? licenseProduct.tier )
        : null;

    const hasContent = availableFeatures.length > 0 ||
        Object.values( lockedByTier ).some( ( f ) => f.length > 0 );

    return (
        <section id={ product.slug } className="scroll-mt-20">
			<div className="h-0"></div>
            <div className="flex items-center gap-3 px-4 py-3 bg-neutral-800 text-white sticky top-0 z-10 border-x border-neutral-800 transition-[border-radius] rounded-t-lg border-t">
                <ProductLogo slug={ product.slug } size={ 28 } productName={ product.name } />
                <h2 className="text-base font-semibold m-0 p-0 text-white">
                    { product.name }
                </h2>
                { isNotActivated ? (
                    <>
                        <LicenseBadge type="unactivated" />
                        { showHeaderActivate && defaultActivateTier && (
                            showTierPicker ? (
                                <DropdownMenu modal={ false } open={ tierMenuOpen } onOpenChange={ setTierMenuOpen }>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="outline" size="xs" className="shrink-0">
                                            { __( 'Activate', '%TEXTDOMAIN%' ) }
                                            { tierMenuOpen
                                                ? <ChevronUp className="w-3 h-3 -translate-y-px" />
                                                : <ChevronDown className="w-3 h-3 -translate-y-px" /> }
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        { activatableTiers.map( ( { lp, name } ) => (
                                            <DropdownMenuItem key={ `${ lp.product_slug }:${ lp.tier }` } asChild>
                                                <a
                                                    href={ buildActivationUrl( activationUrl, product.slug, lp.tier ) }
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                >
                                                    { name }
                                                    <ExternalLink className="w-3 h-3 ml-auto" />
                                                </a>
                                            </DropdownMenuItem>
                                        ) ) }
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            ) : (
                                <Button variant="outline" size="xs" asChild className="shrink-0">
                                    <a
                                        href={ buildActivationUrl( activationUrl, product.slug, defaultActivateTier ) }
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        { __( 'Activate', '%TEXTDOMAIN%' ) }
                                        <ExternalLink className="w-3 h-3 -translate-y-px" />
                                    </a>
                                </Button>
                            )
                        ) }
                    </>
                ) : tierName ? (
                    <LicenseBadge type="licensed" tierName={ tierName } />
                ) : hasActiveLegacy ? (
                    <LicenseBadge type="legacy" />
                ) : (
                    <LicenseBadge type="unlicensed" className="text-white border-white/40" />
                ) }
                <span className="ml-auto text-xs text-white/70">
                    { activeCount } { __( 'active', '%TEXTDOMAIN%' ) }
                    { ' · ' }
                    { deactivatedCount } { __( 'deactivated', '%TEXTDOMAIN%' ) }
                </span>
            </div>

            { isSearching && ! hasContent && (
                <div className="border border-t-0 rounded-b-lg">
                    <p className="px-4 py-6 text-sm text-muted-foreground text-center">
                        { __( 'No features match your search.', '%TEXTDOMAIN%' ) }
                    </p>
                </div>
            ) }

            { ! isSearching && ! hasContent && (
                <div className="border border-t-0 rounded-b-lg">
                    <p className="px-4 py-6 text-sm text-muted-foreground text-center">
                        { __( 'No features are available for this product.', '%TEXTDOMAIN%' ) }
                    </p>
                </div>
            ) }

            { hasContent && (
                <div className="border border-t-0 rounded-b-lg overflow-hidden">
                    { availableFeatures.map( ( feature ) => (
                        <FeatureRow
                            key={ feature.slug }
                            feature={ feature }
                        />
                    ) ) }

                    { activationCatalogTiers.map( ( tier ) => {
                        const locked = lockedByTier[ tier.tier_slug ] ?? [];
                        if ( locked.length === 0 ) return null;
                        return (
                            <TierGroup
                                key={ tier.tier_slug }
                                tier={ tier }
                                features={ locked }
                                forceOpen={ isSearching }
                                showUpgrade={ false }
                                showUnactivated={ isUnactivatedLicense }
                            />
                        );
                    } ) }

                    { upgradeCatalogTiers.map( ( tier ) => {
                        const locked = lockedByTier[ tier.tier_slug ] ?? [];
                        if ( locked.length === 0 ) return null;

                        const effectiveLicenseProduct = licenseProduct ?? unactivatedLicenseProduct;
                        const buttonHref              = effectiveLicenseProduct
                            ? ( tier.upgrade_url ? buildUpgradeUrl( tier.upgrade_url, getHarborDataValue( 'domain' ) ) : undefined )
                            : ( tier.purchase_url || undefined );

                        return (
                            <TierGroup
                                key={ tier.tier_slug }
                                tier={ tier }
                                features={ locked }
                                forceOpen={ isSearching }
                                buttonHref={ buttonHref }
                            />
                        );
                    } ) }
                </div>
            ) }
        </section>
    );
}
