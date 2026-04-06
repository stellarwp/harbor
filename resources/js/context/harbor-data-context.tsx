/**
 * Harbor admin screen data context.
 *
 * Owns the four core resolvers for the Harbor admin screen (license, features,
 * catalog, legacy licenses). Errors from any resolver are pushed to the
 * ErrorModalContext so the error modal opens while the full UI stays rendered.
 * Errors are automatically cleared when all resolvers recover.
 *
 * Mount <HarborDataProvider> inside <ErrorModalProvider> and outside
 * <ErrorBoundary> so it remains alive through render crashes.
 *
 * @package LiquidWeb\Harbor
 */
import { createContext, useContext, useEffect, useRef, type ReactNode } from 'react';
import { __ } from '@wordpress/i18n';
import { store as harborStore } from '@/store';
import useResolvableSelect from '@/hooks/use-resolvable-select/use-resolvable-select';
import HarborError from '@/errors/harbor-error';
import { ErrorCode } from '@/errors/error-code';
import { useErrorModal } from '@/context/error-modal-context';
import type { ResolvableSelectResponse } from '@/hooks/use-resolvable-select/types';

interface HarborDataContextValue {
    isLoading: boolean;
}

const HarborDataContext = createContext<HarborDataContextValue>( {
    isLoading: true,
} );

type ResolvableRecord = Record<string, ResolvableSelectResponse<unknown>>;

const RESOLVER_KEYS = [ 'license', 'features', 'catalog', 'legacyLicenses' ] as const;
type ResolverKey = typeof RESOLVER_KEYS[ number ];

function findErrors( results: ResolvableRecord ): HarborError[] {
    const errors: HarborError[] = [];
    for ( const key in results ) {
        const entry = results[ key ];
        if ( entry.status === 'ERROR' ) {
            errors.push( HarborError.syncFrom(
                entry.error,
                ErrorCode.ResolutionFailed,
                __( 'Liquid Web Software Manager failed to load your data.', '%TEXTDOMAIN%' ),
            ) );
        }
    }
    return errors;
}

/**
 * @since 1.0.0
 */
export function HarborDataProvider( { children }: { children: ReactNode } ) {
    const { addError, removeError } = useErrorModal();
    const lastErrorCodesRef = useRef<string[]>( [] );

    const result = useResolvableSelect(
        ( resolve ) => ( {
            license:        resolve( harborStore ).getLicenseKey(),
            features:       resolve( harborStore ).getFeatures(),
            catalog:        resolve( harborStore ).getCatalog(),
            legacyLicenses: resolve( harborStore ).getLegacyLicenses(),
        } ),
        [],
    );

    const hasEverResolvedRef = useRef<Record<ResolverKey, boolean>>( {
        license:        false,
        features:       false,
        catalog:        false,
        legacyLicenses: false,
    } );

    for ( const key of RESOLVER_KEYS ) {
        if ( result[ key ].hasResolved ) {
			hasEverResolvedRef.current[ key ] = true;
		}
    }

    const isLoading = RESOLVER_KEYS.some( ( key ) => result[ key ].isResolving && ! hasEverResolvedRef.current[ key ] );

    useEffect( () => {
        const found = findErrors( result );

        if ( found.length > 0 ) {
            lastErrorCodesRef.current = found.map( ( e ) => e.code );
            found.forEach( ( error ) => addError( error ) );
        } else if ( lastErrorCodesRef.current.length > 0 ) {
            lastErrorCodesRef.current.forEach( ( code ) => removeError( code ) );
            lastErrorCodesRef.current = [];
        }
    }, [ result, addError, removeError ] );

    return (
        <HarborDataContext.Provider value={ { isLoading } }>
            { children }
        </HarborDataContext.Provider>
    );
}

/**
 * @since 1.0.0
 */
export const useHarborData = () => useContext( HarborDataContext );
