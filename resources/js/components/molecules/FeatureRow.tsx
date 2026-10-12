/**
 * A single feature row in the product feature list.
 *
 * Clicking the row header expands/collapses the feature description.
 * The toggle switch remains independently clickable.
 *
 * @package LiquidWeb\Harbor
 */
import { useId, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { ChevronRight, ChevronDown } from 'lucide-react';
import { cn } from '@/lib/utils';
import { FeatureIcon } from '@/components/atoms/FeatureIcon';
import { LicenseBadge } from '@/components/atoms/LicenseBadge';
import { StatusBadge } from '@/components/atoms/StatusBadge';
import { VersionDisplay } from '@/components/molecules/VersionDisplay';
import { FeatureAccessSources } from '@/components/molecules/FeatureAccessSources';
import { Switch } from '@/components/ui/switch';
import { Button } from '@/components/ui/button';
import { Dialog, DialogHeader, DialogFooter } from '@/components/ui/dialog';
import { useFeatureRow } from '@/hooks/useFeatureRow';
import type { Feature } from '@/types/api';
import { isInstallableFeature } from '@/types/utils';

interface FeatureRowProps {
	feature:          Feature;
	/** Tier display name passed by TierGroup; enables the upsell tooltip on the update button. */
	upgradeTierName?: string;
}

/**
 * @since 1.0.0
 */
export function FeatureRow( { feature, upgradeTierName }: FeatureRowProps ) {
	const [ expanded, setExpanded ] = useState( false );
	const detailsId = useId();
	const {
		pendingAction,
		installableBusy,
		badgeStatus,
		showSwitch,
		switchChecked,
		licenseBadgeType,
		showDeactivateConfirm,
		handleToggle,
		handleUpdate,
		handleConfirmDeactivate,
		handleCancelDeactivate,
	} = useFeatureRow( feature );

	const Chevron = expanded ? ChevronDown : ChevronRight;

	// Legacy-licensed and revoked features are not marked available by the API
	// but should render with the full available layout — controls visible, no muted style.
	// This override only applies to installable features (plugins/themes) since
	// non-installable features (services) have no controls to show.
	const isVisuallyAvailable =
		feature.is_available ||
		( isInstallableFeature( feature ) && ( licenseBadgeType === 'legacy' || licenseBadgeType === 'revoked' ) );
	const upgradeLabel = isVisuallyAvailable
		? ( licenseBadgeType === 'legacy'
			? __( 'Upgrade your license to manage updates from Nexcess Licensing.', '%TEXTDOMAIN%' )
			: undefined )
		: ( upgradeTierName
			? /* translators: %s is the name of the tier required to receive updates */
			  sprintf( __( 'Upgrade to %s to receive updates and support.', '%TEXTDOMAIN%' ), upgradeTierName )
			: undefined );

	return (
		<div className={ cn(
			'border-b last:border-b-0',
			isVisuallyAvailable
				? cn( 'bg-white', pendingAction && 'opacity-75' )
				: 'bg-muted/30'
		) }>
			<div className="flex flex-wrap items-center gap-x-4 gap-y-2 py-3 px-4">
				<div className="min-w-0 flex flex-wrap items-center gap-x-2 gap-y-1.5 flex-[1_1_12rem]">
					<button
						type="button"
						aria-expanded={ expanded }
						aria-controls={ detailsId }
						onClick={ () => setExpanded( ! expanded ) }
						className="flex items-center gap-2 min-w-0 cursor-pointer text-left rounded focus-visible:outline-2 focus-visible:outline-offset-2"
					>
						<Chevron className="w-4 h-4 text-muted-foreground shrink-0" />
						<FeatureIcon slug={ feature.slug } />
						<span className={ cn(
							'font-medium min-w-0 text-sm break-words',
							! isVisuallyAvailable && 'text-muted-foreground'
						) }>
							{ feature.name }
						</span>
						{ licenseBadgeType && <LicenseBadge type={ licenseBadgeType } /> }
					</button>
					{ licenseBadgeType !== 'free' && <FeatureAccessSources sources={ feature.access_sources } /> }
				</div>
				{ /* Keep every row's controls aligned, including services without versions or switches. */ }
				<div className="ml-auto grid w-[17rem] max-w-full shrink-0 grid-cols-[minmax(0,1fr)_6.25rem_2rem] items-center gap-3">
					<div className="min-w-0 text-right">
						{ ( isInstallableFeature( feature ) || ! isVisuallyAvailable ) && (
							<VersionDisplay
								feature={ feature }
								pendingAction={ pendingAction }
								installableBusy={ installableBusy }
								upgradeLabel={ upgradeLabel }
								onUpdate={ isVisuallyAvailable && licenseBadgeType !== 'legacy' && licenseBadgeType !== 'revoked' ? handleUpdate : undefined }
							/>
						) }
					</div>
					<div className="flex min-w-0 justify-end">
						{ isVisuallyAvailable && <StatusBadge status={ badgeStatus } /> }
					</div>
					<div className="flex justify-end">
						{ isVisuallyAvailable && isInstallableFeature( feature ) && showSwitch && (
							<Switch
								checked={ switchChecked }
								onCheckedChange={ handleToggle }
								disabled={ !! pendingAction || installableBusy || ( licenseBadgeType === 'revoked' && ! switchChecked ) }
								aria-label={
									switchChecked
										? /* translators: %s is the name of the feature to disable */
										  sprintf( __( 'Disable %s', '%TEXTDOMAIN%' ), feature.name )
										: /* translators: %s is the name of the feature to enable */
										  sprintf( __( 'Enable %s', '%TEXTDOMAIN%' ), feature.name )
								}
							/>
						) }
					</div>
				</div>
			</div>

			{ expanded && (
				<div id={ detailsId } className="px-4 pb-3 pl-11">
					<p className={ cn(
						'text-sm text-muted-foreground leading-relaxed',
						isVisuallyAvailable ? 'mt-[0.75em]! mb-0!' : 'mt-2 mb-0'
					) }>
						{ feature.description }
					</p>
				</div>
			) }

			<Dialog open={ showDeactivateConfirm } onClose={ handleCancelDeactivate } maxWidth="max-w-md">
				<DialogHeader
					title={
						/* translators: %s is the name of the feature being deactivated */
						sprintf( __( 'Deactivate %s?', '%TEXTDOMAIN%' ), feature.name )
					}
					description={ __( 'This plugin powers this page. Deactivating it will make this page unavailable until it is reactivated from the WordPress Plugins page.', '%TEXTDOMAIN%' ) }
					onClose={ handleCancelDeactivate }
				/>
				<DialogFooter>
					<Button variant="outline" onClick={ handleCancelDeactivate }>
						{ __( 'Cancel', '%TEXTDOMAIN%' ) }
					</Button>
					<Button variant="destructive" onClick={ handleConfirmDeactivate } disabled={ pendingAction === 'disabling' }>
						{ pendingAction === 'disabling'
							? __( 'Deactivating…', '%TEXTDOMAIN%' )
							: __( 'Deactivate', '%TEXTDOMAIN%' )
						}
					</Button>
				</DialogFooter>
			</Dialog>
		</div>
	);
}
