/**
 * Behavior hook for WelcomeLicenseForm.
 *
 * Owns input state, derived validation, the storeLicense dispatch,
 * and the pickWelcomeErrorMessage helper. Mirrors the existing
 * useFeatureRow pattern so the form's component file stays JSX-only.
 *
 * Deliberately not generic — the LWSW-prefix rule and the error-message
 * composition are specific to this form. We do not reuse LicenseKeyInput
 * because that component routes failures through the global error modal
 * and exposes a tri-state UI we do not want here.
 *
 * @package LiquidWeb\Harbor
 */
import { useCallback, useState }  from 'react';
import { __ }                     from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as harborStore }   from '@/store';
import { HarborError }            from '@/errors';
import { getHarborDataValue }     from '@/lib/harbor-data';

const SERVER_INVALID_KEY_CODE = 'lw-harbor-invalid-key';

/**
 * Compose the user-facing error message for a failed activation.
 *
 * storeLicense wraps any WP_Error from the licensing service via
 * HarborError.wrap(), which preserves the server error as `cause`.
 * The outer message is the generic wrapper; the cause carries the
 * server's diagnostic code and message.
 *
 * The plugin-settings redirection is appended only when the server
 * specifically rejected the key as unrecognized — for expired or
 * suspended subscriptions that framing would be misleading.
 *
 * @since TBD
 */
export function pickWelcomeErrorMessage( error: HarborError ): string {
    const serverError = error.cause instanceof HarborError ? error.cause : null;
    const baseMessage = serverError?.message?.trim() || error.message;

    if ( serverError?.code === SERVER_INVALID_KEY_CODE ) {
        return __(
            "We couldn't verify this key. If this is a non-unified license, activate it in that plugin's own settings page.",
            '%TEXTDOMAIN%'
        );
    }

    return baseMessage;
}

export interface UseWelcomeLicenseForm {
    key:              string;
    serverError:      string | null;
    isStoring:        boolean;
    canModifyLicense: boolean;
    showFormatHint:   boolean;
    canSubmit:        boolean;
    onKeyChange:      ( value: string ) => void;
    onActivate:       () => Promise<void>;
}

/**
 * @since TBD
 */
export function useWelcomeLicenseForm(): UseWelcomeLicenseForm {
    const [ key, setKey ]                 = useState( '' );
    const [ serverError, setServerError ] = useState<string | null>( null );

    const { storeLicense } = useDispatch( harborStore );

    const { isStoring, canModifyLicense } = useSelect(
        ( select ) => ( {
            isStoring:        select( harborStore ).isLicenseStoring(),
            canModifyLicense: select( harborStore ).canModifyLicense(),
        } ),
        []
    );

    const prefix             = getHarborDataValue( 'licenseKeyPrefix' );
    const trimmed           = key.trim();
    const hasInput          = trimmed.length > 0;
	const hasInputMinLength = trimmed.length >= prefix.length;
    const isLwswFormat      = trimmed.startsWith( prefix.substring( 0, trimmed.length ) );
    const showFormatHint    = hasInput && ! isLwswFormat && ! serverError;
    const canSubmit         = canModifyLicense && hasInput && hasInputMinLength && isLwswFormat && ! isStoring;

    const onKeyChange = useCallback( ( value: string ) => {
        setKey( value.toUpperCase() );
        setServerError( null );
    }, [] );

    const onActivate = useCallback( async () => {
        if ( ! canSubmit ) {
            return;
        }
        setServerError( null );
        const result = await storeLicense( trimmed );
        if ( result instanceof HarborError ) {
            setServerError( pickWelcomeErrorMessage( result ) );
        }
        // On success: hasLicense becomes true → AppContent re-renders to AppShell automatically.
    }, [ canSubmit, storeLicense, trimmed ] );

    return {
        key,
        serverError,
        isStoring,
        canModifyLicense,
        showFormatHint,
        canSubmit,
        onKeyChange,
        onActivate,
    };
}
