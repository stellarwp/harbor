/**
 * Wrapper around useResolvableSelect that routes resolution errors to the
 * ErrorModalContext instead of throwing them during render.
 *
 * When any resolver fails the error is passed to addError() so the error modal
 * opens while the rest of the UI stays rendered. When all resolvers succeed the
 * previously pushed error is automatically removed (auto-clear).
 *
 * @package LiquidWeb\Harbor
 */
import { useEffect, useRef, type DependencyList } from 'react';
import { __ } from '@wordpress/i18n';
import useResolvableSelect from './use-resolvable-select';
import HarborError from '@/errors/harbor-error';
import { ErrorCode } from '@/errors/error-code';
import { useErrorModal } from '@/context/error-modal-context';
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
                __( 'Liquid Web Software failed to load your data.', '%TEXTDOMAIN%' ),
            );
        }
    }
    return null;
}

/**
 * Like useResolvableSelect, but routes resolution errors to the
 * ErrorModalContext instead of throwing during render.
 *
 * The component tree renders with whatever data is available (usually the
 * store's default empty values). The modal opens automatically and clears
 * itself when the resolver eventually succeeds (e.g. after a Retry).
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
    const { addError, removeError } = useErrorModal();

    // Track the code of the last error this hook pushed so we can clear
    // exactly that entry when the resolver recovers.
    const lastErrorCodeRef = useRef<string | null>( null );

    useEffect( () => {
        const found = findError( result );

        if ( found ) {
            lastErrorCodeRef.current = found.code;
            addError( found );
        } else if ( lastErrorCodeRef.current !== null ) {
            removeError( lastErrorCodeRef.current );
            lastErrorCodeRef.current = null;
        }
    }, [ result, addError, removeError ] );

    return result;
}
