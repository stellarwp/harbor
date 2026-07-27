/**
 * Pulse-skeleton for a single product section, shown while the Harbor data
 * resolvers are in flight on the first page load.
 *
 * Mirrors ProductSection's DOM structure: same sticky header followed by a
 * fixed number of skeleton feature rows. The header is intentionally nameless
 * (placeholder logo and title bars, no real product) because install state and
 * ownership — which decide each product's section and order — are not known
 * until the data resolves. A nameless skeleton avoids named cards appearing to
 * reorder when the computed layout replaces it.
 *
 * @package LiquidWeb\Harbor
 */
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

/**
 * @since TBD    Render a nameless header instead of a real product logo/name.
 * @since 1.0.0
 */
export function ProductSectionSkeleton() {
    return (
        <section className="scroll-mt-20">
            <div className="h-0" />
            <div className="flex items-center gap-3 px-4 py-3 bg-neutral-800 text-white sticky top-0 z-10 border-x border-neutral-800 transition-[border-radius] rounded-t-lg border-t">
                { /* logo placeholder */ }
                <div className="w-7 h-7 rounded shrink-0 bg-white/20 animate-pulse" />
                { /* product name placeholder */ }
                <div className="h-4 w-32 rounded bg-white/20 animate-pulse" />
            </div>
            <div className="border border-t-0 rounded-b-lg overflow-hidden">
                { Array.from( { length: SKELETON_ROW_COUNT }, ( _, i ) => (
                    <SkeletonFeatureRow key={ i } isLast={ i === SKELETON_ROW_COUNT - 1 } />
                ) ) }
            </div>
        </section>
    );
}
