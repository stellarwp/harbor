/**
 * Collapsible product section with a sticky header and tier group accordions.
 *
 * Available features render as FeatureRow entries. Locked features are
 * grouped by tier and rendered inside collapsible TierGroup accordions.
 *
 * Header counts (active / deactivated) always reflect the full unfiltered
 * feature set so they remain stable while the user searches.
 *
 * @package LiquidWeb\Harbor
 */
import { useEffect, useId, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { ExternalLink, ChevronDown, ChevronRight, ChevronUp } from 'lucide-react';
import { cn } from '@/lib/utils';
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
    hideActivation?: boolean;
}

/**
 * @since 1.6.0      Suppress the header activation controls for Available cards.
 * @since 1.3.0    Read domain through the getHarborDataValue helper for upgrade URLs.
 * @since 1.0.2  Route upgrade CTA to catalog upgrade_url for existing subscribers, purchase_url for new subscribers.
 * @since 1.0.1  Show Unactivated badge on tier groups and product header for unactivated licenses.
 * @since 1.0.0
 */
export function ProductSection( { product, hideActivation = false }: ProductSectionProps ) {
    const { searchQuery } = useFilter();
    const query = searchQuery.trim();
    const isSearching = query.length > 0;
    const contentId = useId();
    const [ expanded, setExpanded ] = useState( true );
    const [ searchExpanded, setSearchExpanded ] = useState( true );
    const isOpen = isSearching ? searchExpanded : expanded;
    const SectionChevron = isOpen ? ChevronDown : ChevronRight;

    // Reveal each new search without overwriting the user's normal section state.
    useEffect( () => {
        setSearchExpanded( true );
    }, [ query ] );

    // Tracks the header tier-picker's open state so its chevron can flip.
    const [ tierMenuOpen, setTierMenuOpen ] = useState( false );

    // Full unfiltered set — used only for header counts so they stay stable.
    const { licenseProduct, unactivatedLicenseProduct, unactivatedLicenseProducts } = useSelect(
        ( select ) => {
            const licenseProducts = select( harborStore ).getLicenseProducts();
            const forProduct      = licenseProducts.filter( ( lp ) => lp.product_slug === product.slug );
            return {
                licenseProduct:             forProduct.find( ( lp ) => lp.activated_here === true ) ?? null,
                unactivatedLicenseProduct:  select( harborStore ).getUnactivatedLicenseProduct( product.slug ),
                unactivatedLicenseProducts: select( harborStore ).getUnactivatedLicenseProducts( product.slug ),
            };
        },
        [ product.slug ],
    );

    const { availableFeatures, lockedByTier, sortedCatalogTiers, upgradeCatalogTiers, activationCatalogTiers, isUnactivatedLicense } = useProductFeatureGroups( product.slug );

    const activeCount      = availableFeatures.filter( ( f ) => f.is_enabled ).length;
    const deactivatedCount = availableFeatures.filter( ( f ) => ! f.is_enabled ).length;

    // Keep activation actions for a separately owned brand plan. A brand heading
    // does not identify the purchases supplying its individual features.
    const isNotActivated = ( licenseProduct === null && isUnactivatedLicense ) || (
        licenseProduct !== null && (
            licenseProduct.validation_status === 'not_activated' ||
            licenseProduct.validation_status === 'activation_required'
        )
    );

    // Owned-but-unactivated products keep their activation action,
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

    const hasContent = availableFeatures.length > 0 ||
        Object.values( lockedByTier ).some( ( f ) => f.length > 0 );

    return (
        <section id={ product.slug } className="scroll-mt-20">
			<div className="h-0"></div>
            <div className={ cn(
                'flex items-center bg-neutral-800 text-white sticky top-0 z-10 border-x border-neutral-800 border-t',
                isOpen ? 'rounded-t-lg' : 'rounded-lg border-b'
            ) }>
                <h2 className="flex-1 min-w-0 text-base font-semibold m-0 p-0 text-white" aria-label={ product.name }>
                    <button
                        type="button"
                        aria-label={ /* translators: %s: product name. */ sprintf( __( '%s features', '%TEXTDOMAIN%' ), product.name ) }
                        aria-expanded={ isOpen }
                        aria-controls={ contentId }
                        aria-describedby={ `${ contentId }-counts` }
                        onClick={ () => isSearching ? setSearchExpanded( ! isOpen ) : setExpanded( ! isOpen ) }
                        className="flex items-center gap-3 w-full px-4 py-3 text-left cursor-pointer rounded-[inherit] focus-visible:outline-2 focus-visible:-outline-offset-4 focus-visible:outline-white hover:bg-white/5"
                    >
                        <SectionChevron className="size-4 shrink-0 text-white/70" aria-hidden="true" />
                        <span aria-hidden="true">
                            <ProductLogo slug={ product.slug } size={ 28 } productName={ product.name } />
                        </span>
                        <span>{ product.name }</span>
                        <span id={ `${ contentId }-counts` } className="ml-auto text-xs font-normal text-white/70 text-right">
                            { activeCount } { __( 'active', '%TEXTDOMAIN%' ) }
                            { ' · ' }
                            { deactivatedCount } { __( 'deactivated', '%TEXTDOMAIN%' ) }
                        </span>
                    </button>
                </h2>
                { ! hideActivation && showHeaderActivate && defaultActivateTier && (
                    showTierPicker ? (
                        <DropdownMenu modal={ false } open={ tierMenuOpen } onOpenChange={ setTierMenuOpen }>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" size="xs" className="shrink-0 mr-4">
                                    { __( 'Activate plan', '%TEXTDOMAIN%' ) }
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
                        <Button variant="outline" size="xs" asChild className="shrink-0 mr-4">
                            <a
                                href={ buildActivationUrl( activationUrl, product.slug, defaultActivateTier ) }
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                { __( 'Activate plan', '%TEXTDOMAIN%' ) }
                                <ExternalLink className="w-3 h-3 -translate-y-px" />
                            </a>
                        </Button>
                    )
                ) }
            </div>

            { /* Keep rows mounted so collapsing does not reset actions or feature details. */ }
            <div id={ contentId } hidden={ ! isOpen }>
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
            </div>
        </section>
    );
}
