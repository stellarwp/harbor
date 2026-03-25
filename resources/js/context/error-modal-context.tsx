/**
 * Error modal context — surfaces resolver and API errors in a dismissible
 * modal while keeping the full UI rendered.
 *
 * Mount <ErrorModalProvider> once in App.tsx; consume with useErrorModal()
 * anywhere in the component tree.
 *
 * @package LiquidWeb\Harbor
 */
import { createContext, useCallback, useContext, useState, type ReactNode } from 'react';
import type HarborError from '@/errors/harbor-error';

interface ErrorModalContextValue {
    errors:      HarborError[];
    addError:    ( error: HarborError ) => void;
    removeError: ( code: string ) => void;
    clearAll:    () => void;
}

const ErrorModalContext = createContext<ErrorModalContextValue>( {
    errors:      [],
    addError:    () => {},
    removeError: () => {},
    clearAll:    () => {},
} );

/**
 * @since 1.0.0
 */
export function ErrorModalProvider( { children }: { children: ReactNode } ) {
    const [ errors, setErrors ] = useState<HarborError[]>( [] );

    const addError = useCallback( ( error: HarborError ) => {
        setErrors( ( prev ) =>
            prev.some( ( e ) => e.code === error.code ) ? prev : [ ...prev, error ]
        );
    }, [] );

    const removeError = useCallback( ( code: string ) => {
        setErrors( ( prev ) => prev.filter( ( e ) => e.code !== code ) );
    }, [] );

    const clearAll = useCallback( () => setErrors( [] ), [] );

    return (
        <ErrorModalContext.Provider value={ { errors, addError, removeError, clearAll } }>
            { children }
        </ErrorModalContext.Provider>
    );
}

/**
 * @since 1.0.0
 */
export const useErrorModal = () => useContext( ErrorModalContext );
