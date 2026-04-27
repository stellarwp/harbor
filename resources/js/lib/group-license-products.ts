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
 * (activated-here first, then ascending by rank), and orders groups by the
 * PRODUCTS constant. Products absent from licenseProducts are omitted.
 *
 * @since 1.0.0
 */
export function groupLicenseProducts(
    licenseProducts: LicenseProduct[],
    tierRankMap:     Record<string, number>,
): GroupedProduct[] {
    const groups: Record<string, LicenseProduct[]> = {};
    licenseProducts.forEach( ( lp ) => {
        if ( ! groups[ lp.product_slug ] ) {
            groups[ lp.product_slug ] = [];
        }
        groups[ lp.product_slug ].push( lp );
    } );

    Object.values( groups ).forEach( ( tiers ) => {
        tiers.sort( ( a, b ) => ( tierRankMap[ a.tier ] ?? 0 ) - ( tierRankMap[ b.tier ] ?? 0 ) );
    } );

    return PRODUCTS
        .filter( ( p ) => groups[ p.slug ] !== undefined )
        .map( ( p ) => ({ productSlug: p.slug, productName: p.name, tiers: groups[ p.slug ] }) );
}
