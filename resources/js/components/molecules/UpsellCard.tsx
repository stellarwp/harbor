/**
 * A catalog offer and, for a package, the features included in the advertised tier.
 *
 * @package LiquidWeb\Harbor
 */
import { __, sprintf } from '@wordpress/i18n';
import { ExternalLink } from 'lucide-react';
import { ProductLogo } from '@/components/atoms/ProductLogo';
import type { UpsellOffer } from '@/lib/upsell-offers';

const UPSELL_TAGLINES: Record<string, string> = {
    give:                  __( 'Beautiful donation forms & fundraising', '%TEXTDOMAIN%' ),
    'the-events-calendar': __( 'Tickets, RSVPs & event management', '%TEXTDOMAIN%' ),
    learndash:             __( 'Sell courses & manage learners', '%TEXTDOMAIN%' ),
    kadence:               __( 'Themes, blocks & design tools', '%TEXTDOMAIN%' ),
};

/**
 * Render an offer whose purchase destination has been validated by getUpsellOffers.
 */
export function UpsellCard( { product, href, includedProducts }: UpsellOffer ) {
    const content = (
        <>
            <ProductLogo slug={ product.slug } size={ 32 } variant="nobg" productName={ product.name } />
            <div className="flex-1 min-w-0">
                <span className="text-sm font-medium text-foreground block">{ product.name }</span>
                <span className="text-xs text-muted-foreground">{ UPSELL_TAGLINES[ product.slug ] ?? product.tagline }</span>
            </div>
            <ExternalLink className="w-3.5 h-3.5 text-muted-foreground shrink-0" />
        </>
    );
    const featureCount = includedProducts?.reduce( ( count, entry ) => count + entry.features.length, 0 ) ?? 0;

    return (
        <div className="rounded-xl border bg-card overflow-hidden">
            <a href={ href } target="_blank" rel="noopener noreferrer" className="flex items-center gap-2.5 px-4 py-3 hover:bg-muted/50 transition-colors">
                { content }
            </a>
            { featureCount > 0 && (
                <details className="border-t px-4 py-3 text-xs">
                    <summary className="cursor-pointer text-foreground">
                        { /* translators: %d: number of catalog features included in the package. */ }
                        { sprintf( __( 'See all %d included features', '%TEXTDOMAIN%' ), featureCount ) }
                    </summary>
                    <div className="mt-3 max-h-64 overflow-y-auto space-y-3">
                        { includedProducts?.map( ( entry ) => (
                            <div key={ entry.name }>
                                <p className="!m-0 font-medium text-foreground">{ entry.name }</p>
                                <ul className="!mt-1 !mb-0 list-disc pl-4 text-muted-foreground">
                                    { entry.features.map( ( feature ) => <li key={ feature.slug }>{ feature.name }</li> ) }
                                </ul>
                            </div>
                        ) ) }
                    </div>
                </details>
            ) }
        </div>
    );
}
