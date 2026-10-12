/**
 * License sidebar panel.
 *
 * Always visible. Fetches license and catalog data from the store and passes
 * it to LicenseSection and UpsellSection.
 *
 * @package LiquidWeb\Harbor
 */
import { useMemo } from 'react';
import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import { LicenseSection } from '@/components/organisms/LicenseSection';
import { UpsellSection } from '@/components/organisms/UpsellSection';
import { store as harborStore } from '@/store';
import { getUpsellOffers } from '@/lib/upsell-offers';
import { useToast } from '@/context/toast-context';
import { useErrorModal } from '@/context/error-modal-context';
import { HarborError } from '@/errors';
import { getHarborDataValue } from '@/lib/harbor-data';

/**
 * @since 1.3.0   Read activationUrl through the getHarborDataValue helper.
 * @since 1.0.0
 */
export function LicensePanel() {
    const { addToast }      = useToast();
    const { addError }      = useErrorModal();
    const { deleteLicense, refreshLicense, refreshCatalog } = useDispatch( harborStore );

    const { licenseKey, licenseProducts, catalogs, isRefreshing, isLicenseLoading } = useSelect(
        ( select ) => ({
            licenseKey:       select( harborStore ).getLicenseKey(),
            licenseProducts:  select( harborStore ).getLicenseProducts(),
            catalogs:         select( harborStore ).getCatalog(),
            isRefreshing:     select( harborStore ).isLicenseRefreshing(),
            // @ts-expect-error -- hasFinishedResolution is injected at runtime by @wordpress/data but absent from the store's TypeScript surface.
            isLicenseLoading: ! select( harborStore ).hasFinishedResolution( 'getLicenseKey', [] ),
        }),
        []
    );

    // Flat tier slug → display name and rank lookups from all catalog tiers.
    const { tierNameMap, tierRankMap } = useMemo( () => {
        const names: Record<string, string> = {};
        const ranks: Record<string, number> = {};
        catalogs.forEach( ( catalog ) => {
            catalog.tiers.forEach( ( t ) => {
                names[ t.tier_slug ] = t.name;
                ranks[ t.tier_slug ] = t.rank;
            } );
        } );
        return { tierNameMap: names, tierRankMap: ranks };
    }, [ catalogs ] );

    const productNames = useMemo( () => Object.fromEntries( catalogs.map( ( c ) => [ c.product_slug, c.product_name ] ) ), [ catalogs ] );

    const activationUrl = licenseKey ? getHarborDataValue( 'activationUrl' ) : null;

    const upsellOffers = getUpsellOffers( catalogs, licenseProducts );

    const handleRemove = async (): Promise<HarborError | null> => {
        const result = await deleteLicense();
        if ( result instanceof HarborError ) {
            addError( result );
            return result;
        }
        addToast( __( 'License removed.', '%TEXTDOMAIN%' ), 'default' );
        return null;
    };

    const handleRefresh = async () => {
        const [ licenseResult, catalogResult ] = await Promise.all( [
            refreshLicense(),
            refreshCatalog(),
        ] );
        if ( licenseResult instanceof HarborError ) {
            addError( licenseResult );
        }
        if ( catalogResult instanceof HarborError ) {
            addError( catalogResult );
        }
        if ( ! ( licenseResult instanceof HarborError ) && ! ( catalogResult instanceof HarborError ) ) {
            addToast( __( 'License refreshed.', '%TEXTDOMAIN%' ), 'success' );
        }
    };

    return (
        <div className="sticky top-4 w-[285px] shrink-0 space-y-6">
            <LicenseSection
                licenseKey={ licenseKey }
                licenseProducts={ licenseProducts }
                tierNameMap={ tierNameMap }
                tierRankMap={ tierRankMap }
                productNames={ productNames }
                onRemove={ handleRemove }
                onRefresh={ handleRefresh }
                isRefreshing={ isRefreshing }
                isLoading={ isLicenseLoading }
                activationUrl={ activationUrl }
            />
            { ! isLicenseLoading && (
                <UpsellSection
                    offers={ upsellOffers }
                />
            ) }
        </div>
    );
}
