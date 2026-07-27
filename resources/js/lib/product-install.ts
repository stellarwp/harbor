import type { Feature, LicenseProduct } from '@/types/api';

/**
 * Whether a product is installed on this site.
 *
 * A product is installed when at least one of its plugin/theme features has a
 * non-empty installed_version. Services are non-installable and never count.
 *
 * Uses a truthy check because the backend serializes installed_version as ''
 * (empty string) when not installed — Plugin::to_array() / Theme::to_array()
 * coerce null to ''.
 *
 * @param features The product's features from getFeaturesByProduct().
 *
 * @since 1.5.1
 */
export function isProductInstalled( features: Feature[] ): boolean {
    return features.some(
        ( f ) => ( f.type === 'plugin' || f.type === 'theme' ) && !! f.installed_version,
    );
}

/**
 * Whether the user has an entitlement to a product.
 *
 * True when a license product entry exists for the slug (any activation state)
 * or an active legacy license covers it. Used only for sub-ordering the
 * Available section — never for section placement, which is install state.
 *
 * @param slug                 The product slug.
 * @param args                 Ownership inputs.
 * @param args.licenseProducts All non-cancelled license products from getLicenseProducts().
 * @param args.hasActiveLegacy Whether an active legacy license covers the product.
 *
 * @since 1.5.1
 */
export function isProductOwned(
    slug: string,
    args: { licenseProducts: LicenseProduct[]; hasActiveLegacy: boolean },
): boolean {
    if ( args.hasActiveLegacy ) {
        return true;
    }

    return args.licenseProducts.some( ( lp ) => lp.product_slug === slug );
}
