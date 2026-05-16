/**
 * License input + Activate button used on the welcome screen.
 *
 * Pure composition of UI primitives wired to useWelcomeLicenseForm.
 * No useState, no derived values, no async logic — the component file
 * is for layout and JSX wiring only.
 *
 * @package LiquidWeb\Harbor
 */
import { __ }                    from '@wordpress/i18n';
import { KeyRound, Loader2 }     from 'lucide-react';
import { Input }                 from '@/components/ui/input';
import { Button }                from '@/components/ui/button';
import { SectionHeader }         from '@/components/atoms/SectionHeader';
import { useWelcomeLicenseForm } from '@/hooks/useWelcomeLicenseForm';
import { getHarborDataValue }    from '@/lib/harbor-data';

/**
 * @since 1.0.0
 */
export function WelcomeLicenseForm() {
    const {
        key,
        serverError,
        isStoring,
        canModifyLicense,
        showFormatHint,
        canSubmit,
        onKeyChange,
        onActivate,
    } = useWelcomeLicenseForm();

    return (
        <div className="rounded-lg border bg-white p-4 space-y-3 shadow-sm">
            <SectionHeader
                icon={ <KeyRound className="w-4 h-4 text-muted-foreground" /> }
                label={ __( 'Unified License', '%TEXTDOMAIN%' ) }
            />
            <Input
                id="welcome-license-key-input"
                placeholder={ `${ getHarborDataValue( 'licenseKeyPrefix' ) }XXXX-XXXX-XXXX-XXXX-XXXX` }
                value={ key }
                onChange={ ( e ) => onKeyChange( e.target.value ) }
                onKeyDown={ ( e ) => e.key === 'Enter' && canSubmit && onActivate() }
                className="font-mono text-sm uppercase"
                aria-invalid={ !! serverError }
                aria-describedby={
                    serverError    ? 'welcome-license-error'
                  : showFormatHint ? 'welcome-license-hint'
                  : undefined
                }
                disabled={ ! canModifyLicense }
            />
            <Button
                className="w-full"
                onClick={ onActivate }
                disabled={ ! canSubmit }
            >
                { isStoring ? (
                    <>
                        <Loader2 className="w-4 h-4 animate-spin" />
                        { __( 'Verifying…', '%TEXTDOMAIN%' ) }
                    </>
                ) : (
                    __( 'Activate', '%TEXTDOMAIN%' )
                ) }
            </Button>
            { showFormatHint && (
                <p
                    id="welcome-license-hint"
                    className="text-sm text-muted-foreground"
                >
                    { __( "This doesn't look like a unified license key. If this is a non-unified license, activate it in that plugin's own settings page.", '%TEXTDOMAIN%' ) }
                </p>
            ) }
            { serverError && (
                <p
                    id="welcome-license-error"
                    className="text-sm text-destructive"
                    role="alert"
                >
                    { serverError }
                </p>
            ) }
        </div>
    );
}
