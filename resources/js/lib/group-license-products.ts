/**
 * @package LiquidWeb\Harbor
 */
import { PRODUCTS } from '@/data/products';
import type { LicenseProduct } from '@/types/api';

export interface GroupedProduct {
    productSlug: string;
    productName: string;
    tiers:       LicenseProduct[];
}

/**
 * Groups LicenseProduct entries by product_slug, sorts tiers within each group
 * (ascending by rank), and orders groups by the
 * brand order followed by additional purchases. Catalog names override defaults.
 *
 * @since 1.0.0
 * @param licenseProducts Customer purchases returned by Licensing.
 * @param tierRankMap Catalog tier ordering.
 * @param productNames Catalog display names keyed by product slug.
 */
export function groupLicenseProducts(
    licenseProducts: LicenseProduct[],
    tierRankMap:     Record<string, number>,
    productNames:   Record<string, string> = {},
): GroupedProduct[] {
    const groups: Record<string, LicenseProduct[]> = Object.create( null );
    licenseProducts.forEach( ( lp ) => {
        if ( ! groups[ lp.product_slug ] ) {
            groups[ lp.product_slug ] = [];
        }
        groups[ lp.product_slug ].push( lp );
    } );

    Object.values( groups ).forEach( ( tiers ) => {
        tiers.sort( ( a, b ) => ( tierRankMap[ a.tier ] ?? 0 ) - ( tierRankMap[ b.tier ] ?? 0 ) );
    } );

    const known = PRODUCTS.filter( ( p ) => groups[ p.slug ] !== undefined );
    const knownSlugs = new Set( known.map( ( p ) => p.slug ) );
    const extra = Object.keys( groups ).filter( ( slug ) => ! knownSlugs.has( slug ) );

    return [ ...known.map( ( p ) => p.slug ), ...extra ].map( ( slug ) => ({
        productSlug: slug,
        productName: productNames[ slug ] ?? PRODUCTS.find( ( p ) => p.slug === slug )?.name ?? slug,
        tiers: groups[ slug ],
    }) );
}
