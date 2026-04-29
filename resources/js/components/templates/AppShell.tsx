/**
 * Application shell — full-width two-column layout.
 *
 * Main area: FilterBar header + product sections.
 * Sidebar: license panel.
 *
 * @package LiquidWeb\Harbor
 */
import { __, sprintf } from '@wordpress/i18n';
import { Shell } from '@/components/templates/Shell';
import { FilterBar } from '@/components/molecules/FilterBar';
import { LicensePanel } from '@/components/organisms/LicensePanel';
import { LegacyLicenseBanner } from '@/components/molecules/LegacyLicenseBanner';
import { NotActivatedBanner } from '@/components/molecules/NotActivatedBanner';
import { ReloadBanner } from '@/components/molecules/ReloadBanner';
import { ProductSection } from '@/components/organisms/ProductSection';
import { ProductSectionSkeleton } from '@/components/organisms/ProductSectionSkeleton';
import { ErrorBoundary } from '@/components/atoms/ErrorBoundary';
import { PRODUCTS } from '@/data/products';
import { useFilter } from '@/context/filter-context';
import { useHarborData } from '@/context/harbor-data-context';

/**
 * @since 1.0.0
 */
export function AppShell() {
    const { isLoading } = useHarborData();

    const { productFilter } = useFilter();

    const visibleProducts = productFilter === 'all'
        ? PRODUCTS
        : PRODUCTS.filter( ( p ) => p.slug === productFilter );

    return (
        <Shell
            header={ <><FilterBar /><ReloadBanner /></> }
            sideContent={ <LicensePanel /> }
        >
            <ErrorBoundary>
                <div className="space-y-8">
                    <LegacyLicenseBanner />
                    <NotActivatedBanner />

                    <div className="flex items-center !mt-8 !mb-6">
                        <h2 className="!text-2xl !font-normal !m-0 !p-0">{ __( 'Your Features', '%TEXTDOMAIN%' ) }</h2>
                    </div>

                    { isLoading
                        ? PRODUCTS.map( ( product ) => (
                            <ProductSectionSkeleton key={ product.slug } product={ product } />
                        ) )
                        : visibleProducts.map( ( product ) => (
                            <ProductSection
                                key={ product.slug }
                                product={ product }
                            />
                        ) )
                    }
                </div>

                { window.harborData?.version && (
                    <div className="flex items-center justify-end mt-auto">
                        <p className="text-[13px] text-gray-500 mt-8 mb-0">
							{ /* translators: %s: plugin version number */ }
							{ sprintf( __( 'Version %s', '%TEXTDOMAIN%' ), window.harborData.version ) }
						</p>
                    </div>
                ) }
            </ErrorBoundary>
        </Shell>
    );
}
