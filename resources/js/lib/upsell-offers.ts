/**
 * Offers shown beside the license, using the catalog's current purchase links and grants.
 *
 * @package LiquidWeb\Harbor
 */
import { __, sprintf } from '@wordpress/i18n';
import { PRODUCTS } from '@/data/products';
import { isFreeFeature } from '@/lib/feature-utils';
import type { CatalogFeature, CatalogTier, LicenseProduct, Product, ProductCatalog } from '@/types/api';

export interface UpsellOffer {
    product: Product;
    href: string;
    includedProducts?: {
        name: string;
        features: Pick<CatalogFeature, 'slug' | 'name'>[];
    }[];
}

/**
 * Find the entry-level paid tier without changing the catalog's order.
 */
function paidTier( catalog: ProductCatalog ): CatalogTier | undefined {
    return catalog.tiers.slice().sort( ( a, b ) => a.rank - b.rank ).find( ( tier ) => tier.rank > 0 );
}

/**
 * Paid features in the advertised tier, with minimum-tier fallback for older catalogs.
 */
function paidFeatures( catalog: ProductCatalog, tier: CatalogTier ): CatalogFeature[] {
    const capabilities = new Set( tier.herald_slugs );
    return catalog.features.filter( ( feature ) => {
        const minimumTier = catalog.tiers.find( ( entry ) => entry.tier_slug === feature.minimum_tier );
        if ( isFreeFeature( feature.minimum_tier ) || minimumTier?.rank === 0 ) {
            return false;
        }
        if ( capabilities.size > 0 ) {
            return capabilities.has( feature.slug );
        }
        return ! minimumTier || minimumTier.rank <= tier.rank;
    } );
}

/**
 * Accept an explicit web purchase destination without inventing a fallback URL.
 */
function purchaseUrl( tier: CatalogTier | undefined ): string | undefined {
    const href = tier?.purchase_url?.trim();
    if ( ! href ) {
        return undefined;
    }

    try {
        const url = new URL( href );
        return url.protocol === 'https:' || url.protocol === 'http:' ? href : undefined;
    } catch {
        return undefined;
    }
}

/**
 * Resolve the advertised package and its included features, independently of customer ownership.
 */
export function getPackageOffer( catalogs: ProductCatalog[] ): UpsellOffer | undefined {
    const packageCatalog = catalogs.find( ( catalog ) => catalog.product_slug === 'nexcess-plugin-stack' );

    if ( ! packageCatalog ) {
        return undefined;
    }

    const packageTier = paidTier( packageCatalog );
    const href = purchaseUrl( packageTier );
    if ( ! packageTier || ! href ) {
        return undefined;
    }

    const capabilities = new Set( packageTier.herald_slugs );
    const includedProducts = catalogs.map( ( catalog ) => ( {
        name: catalog.product_name,
        features: catalog.features.filter( ( feature ) => capabilities.has( feature.slug ) ),
    } ) ).filter( ( product ) => product.features.length > 0 );

    if ( includedProducts.length === 0 ) {
        return undefined;
    }

    return {
        product: {
            slug: packageCatalog.product_slug,
            name: packageCatalog.product_name,
            // translators: %s: names of brands with features included in the package.
            tagline: sprintf( __( 'Includes features from %s.', '%TEXTDOMAIN%' ), includedProducts.map( ( product ) => product.name ).join( ', ' ) ),
        },
        href,
        includedProducts,
    };
}

/**
 * NPS replaces brand offers when present; incomplete catalog offers stay hidden.
 */
export function getUpsellOffers( catalogs: ProductCatalog[], licenseProducts: LicenseProduct[] ): UpsellOffer[] {
    const licensedSlugs = new Set( licenseProducts.map( ( product ) => product.product_slug ) );
    if ( catalogs.some( ( catalog ) => catalog.product_slug === 'nexcess-plugin-stack' ) ) {
        const offer = getPackageOffer( catalogs );
        return offer && ! licensedSlugs.has( offer.product.slug ) ? [ offer ] : [];
    }

    const ownedCapabilities = new Set( licenseProducts.flatMap( ( product ) => product.capabilities ) );
    const offers: UpsellOffer[] = [];
    for ( const product of PRODUCTS ) {
        const catalog = catalogs.find( ( entry ) => entry.product_slug === product.slug );
        if ( ! catalog || catalog.features.length === 0 || licensedSlugs.has( product.slug ) ) {
            continue;
        }
        const tier = paidTier( catalog );
        const href = purchaseUrl( tier );
        if ( ! tier || ! href ) {
            continue;
        }
        const features = paidFeatures( catalog, tier );
        if ( features.length > 0 && features.every( ( feature ) => ownedCapabilities.has( feature.slug ) ) ) {
            continue;
        }
        offers.push( { product, href } );
    }
    return offers;
}
