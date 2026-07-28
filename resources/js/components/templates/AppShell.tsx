/**
 * Application shell — full-width two-column layout.
 *
 * Main area: FilterBar header + product sections.
 * Sidebar: license panel.
 *
 * @package LiquidWeb\Harbor
 */
import { __, sprintf } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { Shell } from '@/components/templates/Shell';
import { FilterBar } from '@/components/molecules/FilterBar';
import { LicensePanel } from '@/components/organisms/LicensePanel';
import { LegacyLicenseBanner } from '@/components/molecules/LegacyLicenseBanner';
import { NotActivatedBanner } from '@/components/molecules/NotActivatedBanner';
import { ReloadBanner } from '@/components/molecules/ReloadBanner';
import { ProductSection } from '@/components/organisms/ProductSection';
import { ProductSectionSkeleton } from '@/components/organisms/ProductSectionSkeleton';
import { ErrorBoundary } from '@/components/atoms/ErrorBoundary';
import { store as harborStore } from '@/store';
import { PRODUCTS } from '@/data/products';
import { useFilter } from '@/context/filter-context';
import { useHarborData } from '@/context/harbor-data-context';
import { getHarborDataValue } from '@/lib/harbor-data';
import { isProductInstalled, isProductOwned } from '@/lib/product-install';
import type { Product } from '@/types/api';

/**
 * @since 1.6.0     Split products into Installed Features and Available Features sections.
 * @since 1.3.0   Read version through the getHarborDataValue helper.
 * @since 1.0.0
 */
export function AppShell() {
    const { isLoading } = useHarborData();
    const version       = getHarborDataValue( 'version' );

    const { productFilter } = useFilter();

    const visibleProducts = productFilter === 'all'
        ? PRODUCTS
        : PRODUCTS.filter( ( p ) => p.slug === productFilter );

    const { installedProducts, availableProducts } = useSelect(
        ( select ) => {
            const s = select( harborStore );
            const installed: Product[] = [];
            const available: Product[] = [];

            for ( const product of visibleProducts ) {
                if ( isProductInstalled( s.getFeaturesByProduct( product.slug ) ) ) {
                    installed.push( product );
                } else {
                    available.push( product );
                }
            }

            // Stable-sort owned-but-not-installed products above un-owned ones.
            const licenseProducts = s.getLicenseProducts();
            const owned: Product[]   = [];
            const unowned: Product[] = [];
            for ( const product of available ) {
                const owns = isProductOwned( product.slug, {
                    licenseProducts,
                    hasActiveLegacy: s.hasActiveLegacyLicenseForProduct( product.slug ),
                } );
                ( owns ? owned : unowned ).push( product );
            }

            return { installedProducts: installed, availableProducts: [ ...owned, ...unowned ] };
        },
        [ visibleProducts ],
    );

    return (
        <Shell
            header={ <><FilterBar /><ReloadBanner /></> }
            sideContent={ <LicensePanel /> }
        >
            <ErrorBoundary>
                <div className="space-y-8">
                    <LegacyLicenseBanner />
                    <NotActivatedBanner />

                    { isLoading
                        ? <>
                            <div className="flex items-center !mt-8 !mb-6">
                                <div className="h-7 w-48 rounded bg-muted animate-pulse" />
                            </div>
                            { Array.from( { length: PRODUCTS.length }, ( _, i ) => (
                                <ProductSectionSkeleton key={ i } />
                            ) ) }
                        </>
                        : <>
                            { installedProducts.length > 0 && (
                                <>
                                    <div className="flex items-center !mt-8 !mb-6">
                                        <h2 className="!text-2xl !font-normal !m-0 !p-0">{ __( 'Installed Features', '%TEXTDOMAIN%' ) }</h2>
                                    </div>
                                    { installedProducts.map( ( product ) => (
                                        <ProductSection key={ product.slug } product={ product } />
                                    ) ) }
                                </>
                            ) }
                            { availableProducts.length > 0 && (
                                <>
                                    <div className="flex items-center !mt-8 !mb-6">
                                        <h2 className="!text-2xl !font-normal !m-0 !p-0">{ __( 'Available Features', '%TEXTDOMAIN%' ) }</h2>
                                    </div>
                                    { availableProducts.map( ( product ) => (
                                        <ProductSection key={ product.slug } product={ product } hideLicenseBadge />
                                    ) ) }
                                </>
                            ) }
                        </>
                    }
                </div>

                { version && (
                    <div className="flex items-center justify-end mt-auto">
                        <p className="text-[13px] text-gray-500 mt-8 mb-0">
							{ /* translators: %s: plugin version number */ }
							{ sprintf( __( 'Version %s', '%TEXTDOMAIN%' ), version ) }
						</p>
                    </div>
                ) }
            </ErrorBoundary>
        </Shell>
    );
}
