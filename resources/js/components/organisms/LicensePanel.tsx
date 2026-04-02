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
import { HarborError } from '@/errors';

/**
 * @since 1.0.0
 */
export function LicensePanel() {
    const { addToast }      = useToast();
    const { deleteLicense } = useDispatch( harborStore );

    const { licenseKey, licenseProducts, catalogs } = useSelect(
        ( select ) => ({
            licenseKey:      select( harborStore ).getLicenseKey(),
            licenseProducts: select( harborStore ).getLicenseProducts(),
            catalogs:        select( harborStore ).getCatalog(),
        }),
        []
    );

    // Flat tier slug → display name lookup from all catalog tiers.
    const tierNameMap = useMemo( () => {
        const map: Record<string, string> = {};
        catalogs.forEach( ( catalog ) => {
            catalog.tiers.forEach( ( t ) => {
                map[ t.slug ] = t.name;
            } );
        } );
        return map;
    }, [ catalogs ] );

    // Product slug → purchase URL map.
    // TODO: Source purchase URLs once the Commerce Portal provides them.
    const upsellUrlMap: Record<string, string> = {};

    const licensedSlugs  = new Set( licenseProducts.map( ( lp ) => lp.product_slug ) );
    const upsellProducts = PRODUCTS.filter( ( p ) => ! licensedSlugs.has( p.slug ) );

    const handleRemove = async () => {
        const result = await deleteLicense();
        if ( result instanceof HarborError ) {
            addToast( result.message, 'error' );
        } else {
            addToast( __( 'License removed.', '%TEXTDOMAIN%' ), 'default' );
        }
    };

    return (
        <div className="sticky top-4 w-[280px] shrink-0 space-y-6">
            <LicenseSection
                licenseKey={ licenseKey }
                licenseProducts={ licenseProducts }
                tierNameMap={ tierNameMap }
                onRemove={ handleRemove }
            />
            <UpsellSection
                products={ upsellProducts }
                upsellUrlMap={ upsellUrlMap }
            />
        </div>
    );
}
