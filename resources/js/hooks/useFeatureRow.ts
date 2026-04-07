/**
 * Behavior hook for FeatureRow.
 *
 * Encapsulates store wiring, async action handlers, and all derived state
 * so FeatureRow itself stays a pure composition of atoms.
 *
 * @package LiquidWeb\Harbor
 */
import { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as harborStore } from '@/store';
import { getLicenseBadgeType } from '@/lib/feature-utils';
import type { LicenseBadgeType } from '@/lib/feature-utils';
import { useToast } from '@/context/toast-context';
import { useErrorModal } from '@/context/error-modal-context';
import { HarborError } from '@/errors';
import type { Feature } from '@/types/api';
import { isInstallableFeature } from '@/types/utils';
import type { FeatureStatus } from '@/components/atoms/StatusBadge';

export type PendingAction = 'enabling' | 'disabling' | 'installing' | 'updating' | null;

function getBadgeStatus(
    pendingAction:    PendingAction,
    licenseBadgeType: LicenseBadgeType | null,
    featureEnabled:   boolean
): FeatureStatus {
    if ( pendingAction ) {
        return pendingAction as FeatureStatus;
    }
    if ( licenseBadgeType === 'revoked' && ! featureEnabled ) {
        return 'locked';
    }
    return featureEnabled ? 'enabled' : 'available';
}

function getSwitchChecked( pendingAction: PendingAction, featureEnabled: boolean ): boolean {
    if ( pendingAction === 'enabling' || pendingAction === 'installing' ) {
        return true;
    }
    if ( pendingAction === 'disabling' ) {
        return false;
    }
    return featureEnabled;
}

export interface FeatureRowState {
	pendingAction:    PendingAction;
	installableBusy:  boolean;
	badgeStatus:      FeatureStatus;
	showSwitch:       boolean;
	switchChecked:    boolean;
	licenseBadgeType: LicenseBadgeType | null;
	handleToggle:     ( checked: boolean ) => Promise<void>;
	handleUpdate:     () => Promise<void>;
}

/**
 * @since 1.0.0
 */
export function useFeatureRow( feature: Feature ): FeatureRowState {
	const { addToast } = useToast();
	const { addError } = useErrorModal();
	const { enableFeature, disableFeature, updateFeature } = useDispatch( harborStore );

	const installableBusy = useSelect(
		( select ) =>
			isInstallableFeature( feature ) &&
			select( harborStore ).isAnyInstallableBusy(),
		[ feature.type ]
	);

	const isLegacy = useSelect(
		( select ) => {
			const activeLegacy = select( harborStore ).getActiveLegacyLicense( feature.slug );
			if ( ! activeLegacy ) return false;
			return ! select( harborStore ).isProductUnifiedLicensed( feature.product );
		},
		[ feature.slug, feature.product ]
	);

	const licenseBadgeType = getLicenseBadgeType( feature, isLegacy );

	const [ pendingAction, setPendingAction ] = useState<PendingAction>( null );

	// Non-installable features (services) have no install/toggle/update lifecycle.
	if ( ! isInstallableFeature( feature ) ) {
		return {
			pendingAction:    null,
			installableBusy:  false,
			badgeStatus:      'included' as FeatureStatus,
			showSwitch:       false,
			switchChecked:    false,
			licenseBadgeType,
			handleToggle:     async () => {},
			handleUpdate:     async () => {},
		};
	}

	const featureEnabled   = feature.is_enabled;
	const featureInstalled = feature.installed_version !== null;

	const handleToggle = async ( checked: boolean ) => {
		setPendingAction( checked ? featureInstalled ? 'enabling' : 'installing' : 'disabling' );
		if ( checked ) {
			const result = await enableFeature( feature.slug );
			if ( result instanceof HarborError ) {
				addError( result );
			} else {
				/* translators: %s is the name of the feature being enabled */
				addToast( sprintf( __( '%s enabled', '%TEXTDOMAIN%' ), feature.name ), 'success' );
			}
		} else {
			const result = await disableFeature( feature.slug );
			if ( result instanceof HarborError ) {
				addError( result );
			} else {
				/* translators: %s is the name of the feature being disabled */
				addToast( sprintf( __( '%s disabled', '%TEXTDOMAIN%' ), feature.name ), 'default' );
			}
		}
		setPendingAction( null );
	};

	const handleUpdate = async () => {
		setPendingAction( 'updating' );
		const result = await updateFeature( feature.slug );
		if ( result instanceof HarborError ) {
			addError( result );
		} else {
			/* translators: %s is the name of the feature being updated */
			addToast( sprintf( __( '%s updated.', '%TEXTDOMAIN%' ), feature.name ), 'success' );
		}
		setPendingAction( null );
	};

	const badgeStatus   = getBadgeStatus( pendingAction, licenseBadgeType, featureEnabled );
	const showSwitch    = pendingAction !== 'installing' && pendingAction !== 'updating';
	const switchChecked = getSwitchChecked( pendingAction, featureEnabled );

	return {
		pendingAction,
		installableBusy,
		badgeStatus,
		showSwitch,
		switchChecked,
		licenseBadgeType,
		handleToggle,
		handleUpdate,
	};
}
