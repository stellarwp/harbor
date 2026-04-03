/**
 * Pulse-skeleton for a single product section, shown while the Harbor data
 * resolvers are in flight on the first page load.
 *
 * Mirrors ProductSection's DOM structure: same sticky header (with real logo
 * and product name but no badge or counters) followed by a fixed number of
 * skeleton feature rows.
 *
 * @package LiquidWeb\Harbor
 */
import { ProductLogo } from '@/components/atoms/ProductLogo';
import type { Product } from '@/types/api';

const SKELETON_ROW_COUNT = 3;

function SkeletonFeatureRow( { isLast }: { isLast: boolean } ) {
    return (
        <div className={ `bg-white animate-pulse${ isLast ? '' : ' border-b' }` }>
            <div className="flex items-center gap-3 py-3 px-4">
                { /* chevron */ }
                <div className="w-4 h-4 rounded shrink-0 bg-muted" />
                { /* feature icon */ }
                <div className="w-8 h-8 rounded shrink-0 bg-muted" />
                { /* feature name */ }
                <div className="h-3.5 w-32 rounded bg-muted" />
                { /* right: status badge + switch */ }
                <div className="ml-auto flex items-center gap-3 shrink-0">
                    <div className="h-4 w-12 rounded bg-muted" />
                    <div className="h-5 w-9 rounded-full bg-muted" />
                </div>
            </div>
        </div>
    );
}

interface ProductSectionSkeletonProps {
    product: Product;
}

/**
 * @since 1.0.0
 */
export function ProductSectionSkeleton( { product }: ProductSectionSkeletonProps ) {
    return (
        <section id={ product.slug } className="scroll-mt-20">
            <div className="h-0" />
            <div className="flex items-center gap-3 px-4 py-3 bg-neutral-800 text-white sticky top-0 z-10 border-x border-neutral-800 transition-[border-radius] rounded-t-lg border-t">
                <ProductLogo slug={ product.slug } size={ 28 } />
                <h2 className="text-base font-semibold m-0 p-0 text-white">
                    { product.name }
                </h2>
            </div>
            <div className="border border-t-0 rounded-b-lg overflow-hidden">
                { Array.from( { length: SKELETON_ROW_COUNT }, ( _, i ) => (
                    <SkeletonFeatureRow key={ i } isLast={ i === SKELETON_ROW_COUNT - 1 } />
                ) ) }
            </div>
        </section>
    );
}
