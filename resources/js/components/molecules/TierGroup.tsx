/**
 * A catalog tier's locked features and purchase or activation state.
 *
 * @package LiquidWeb\Harbor
 */
import { __, sprintf } from '@wordpress/i18n';
import { LicenseBadge } from '@/components/atoms/LicenseBadge';
import { PurchaseLink } from '@/components/atoms/PurchaseLink';
import { LockedFeatureGroup } from '@/components/molecules/LockedFeatureGroup';
import type { CatalogTier, Feature } from '@/types/api';

interface TierGroupProps {
    tier:             CatalogTier;
    features:         Feature[];
    forceOpen?:       boolean;
    showUpgrade?:     boolean;
    /**
     * When true, renders an Unactivated badge in place of the upgrade button.
     * Used when the user owns this tier but has not yet activated the license
     * on the current domain.
     */
    showUnactivated?: boolean;
    /**
     * Target URL for the upgrade button. Resolved by the parent so the
     * component doesn't need to know whether the user has an existing
     * subscription (change-plan URL) or is purchasing fresh (purchase_url).
     */
    buttonHref?:      string;
}

/**
 * Keep legacy tier labels and actions when presenting a catalog without a package offer.
 */
export function TierGroup( { tier, features, forceOpen = false, showUpgrade = true, showUnactivated = false, buttonHref }: TierGroupProps ) {
    return (
        <LockedFeatureGroup
            label={ /* translators: %s: catalog tier name. */ sprintf( __( '%s Features', '%TEXTDOMAIN%' ), tier.name ) }
            features={ features }
            forceOpen={ forceOpen }
            upgradeTierName={ tier.name }
            action={ ( showUnactivated || ( showUpgrade && buttonHref ) ) ? <>
                { showUpgrade && buttonHref && <PurchaseLink tierName={ tier.name } upgradeUrl={ buttonHref } /> }
                { showUnactivated && <LicenseBadge type="unactivated" className="text-xs" /> }
            </> : undefined }
        />
    );
}
