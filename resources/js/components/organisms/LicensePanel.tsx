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
import { PRODUCTS } from '@/data/products';
import { useToast } from '@/context/toast-context';
import { useErrorModal } from '@/context/error-modal-context';
import { HarborError } from '@/errors';
import { buildChangePlanUrl } from '@/lib/change-plan-url';

/**
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

    // Flat tier slug → display name lookup from all catalog tiers.
    const tierNameMap = useMemo( () => {
        const map: Record<string, string> = {};
        catalogs.forEach( ( catalog ) => {
            catalog.tiers.forEach( ( t ) => {
                map[ t.tier_slug ] = t.name;
            } );
        } );
        return map;
    }, [ catalogs ] );

    const activationUrl = licenseKey && window.harborData ? window.harborData.activationUrl : null;

    // Product slug → lowest paid-tier URL map. Uses the change-plan portal flow
    // when the user already has a subscription, otherwise falls back to purchase_url.
    const upsellUrlMap = useMemo( () => {
        const map: Record<string, string> = {};
        const subscriptionsUrl             = window.harborData?.subscriptionsUrl ?? null;

        catalogs.forEach( ( catalog ) => {
            const sorted   = catalog.tiers.slice().sort( ( a, b ) => a.rank - b.rank );
            const paidTier = sorted.find( ( t ) => t.rank > 0 );
            if ( ! paidTier ) {
                return;
            }

            map[ catalog.product_slug ] = ( licenseKey && subscriptionsUrl )
                ? buildChangePlanUrl( subscriptionsUrl, catalog.product_slug, paidTier.tier_slug )
                : paidTier.purchase_url;
        } );
        return map;
    }, [ catalogs, licenseKey ] );

    const licensedSlugs  = new Set( licenseProducts.map( ( lp ) => lp.product_slug ) );
    const upsellProducts = PRODUCTS.filter( ( p ) => ! licensedSlugs.has( p.slug ) );

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
        <div className="sticky top-4 w-[280px] shrink-0 space-y-6">
            <LicenseSection
                licenseKey={ licenseKey }
                licenseProducts={ licenseProducts }
                tierNameMap={ tierNameMap }
                onRemove={ handleRemove }
                onRefresh={ handleRefresh }
                isRefreshing={ isRefreshing }
                isLoading={ isLicenseLoading }
                activationUrl={ activationUrl }
            />
            { ! isLicenseLoading && (
                <UpsellSection
                    products={ upsellProducts }
                    upsellUrlMap={ upsellUrlMap }
                />
            ) }
        </div>
    );
}
