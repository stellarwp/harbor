/**
 * Wrapper around useResolvableSelect that throws resolution errors
 * during render so they are caught by the nearest React ErrorBoundary.
 *
 * This is a general-purpose hook. It has no knowledge of the error modal
 * or any other error-display mechanism — that is the caller's concern.
 * For the Harbor admin screen's core data loading, use HarborDataProvider
 * which pipes resolver errors to the error modal while keeping the UI alive.
 *
 * @package LiquidWeb\Harbor
 */
import type { DependencyList } from 'react';
import { __ } from '@wordpress/i18n';
import useResolvableSelect from './use-resolvable-select';
import HarborError from '@/errors/harbor-error';
import { ErrorCode } from '@/errors/error-code';
import type { MapResolvableSelect, ResolvableSelectResponse } from './types';

/**
 * The consumer must return a record of resolvable results so the hook
 * can inspect each one for errors.
 */
type ResolvableRecord = Record<string, ResolvableSelectResponse<unknown>>;

/**
 * Find the first error among a set of resolvable results and normalize it
 * as a HarborError.
 */
function findError( results: ResolvableRecord ): HarborError | null {
    for ( const key in results ) {
        const entry = results[ key ];
        if ( entry.status === 'ERROR' ) {
            return HarborError.syncFrom(
                entry.error,
                ErrorCode.ResolutionFailed,
                __( 'Liquid Web failed to load your data.', '%TEXTDOMAIN%' ),
            );
        }
    }
    return null;
}

/**
 * Like useResolvableSelect, but throws resolution errors during render
 * so they are caught by the nearest React ErrorBoundary.
 *
 * @throws {HarborError} When any selector's resolution fails.
 *
 * @example
 * ```ts
 * const { features, catalog } = useResolvableSelectWithError(
 *     ( resolve ) => ( {
 *         features: resolve( harborStore ).getFeatures(),
 *         catalog: resolve( harborStore ).getCatalog(),
 *     } ),
 *     [],
 * );
 * ```
 */
export default function useResolvableSelectWithError<
    T extends ResolvableRecord,
>(
    mapResolvableSelect: MapResolvableSelect<T>,
    deps: DependencyList,
): T {
    const result = useResolvableSelect( mapResolvableSelect, deps );

    const found = findError( result );
    if ( found ) {
        throw found;
    }

    return result;
}
