/**
 * A single feature row in the product feature list.
 *
 * Clicking the row header expands/collapses the feature description.
 * The toggle switch remains independently clickable.
 *
 * @package LiquidWeb\Harbor
 */
import { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { ChevronRight, ChevronDown } from 'lucide-react';
import { cn } from '@/lib/utils';
import { FeatureIcon } from '@/components/atoms/FeatureIcon';
import { LicenseBadge } from '@/components/atoms/LicenseBadge';
import { StatusBadge } from '@/components/atoms/StatusBadge';
import { VersionDisplay } from '@/components/molecules/VersionDisplay';
import { Switch } from '@/components/ui/switch';
import { useFeatureRow } from '@/hooks/useFeatureRow';
import type { Feature } from '@/types/api';

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
	const {
		pendingAction,
		installableBusy,
		badgeStatus,
		showSwitch,
		switchChecked,
		showLegacyBadge,
		showFreeBadge,
		mismatch,
		handleToggle,
		handleUpdate,
	} = useFeatureRow( feature );

	const Chevron = expanded ? ChevronDown : ChevronRight;

	// Legacy-licensed and revoked features are not marked available by the API
	// but should render with the full available layout — controls visible, no muted style.
	const isVisuallyAvailable = feature.is_available || showLegacyBadge || mismatch === 'revoked';

	return (
		<div className={ cn(
			'border-b last:border-b-0',
			isVisuallyAvailable
				? cn( 'bg-white', pendingAction && 'opacity-75' )
				: 'bg-muted/30'
		) }>
			<div className="flex items-center gap-3 py-3 px-4">
				<div
					onClick={ () => setExpanded( ! expanded ) }
					className="flex items-center gap-3 min-w-0 cursor-pointer"
				>
					<Chevron className="w-4 h-4 text-muted-foreground shrink-0" />
					<FeatureIcon slug={ feature.slug } />
					<span className={ cn(
						'font-medium min-w-0 text-sm truncate',
						! isVisuallyAvailable && 'text-muted-foreground'
					) }>
						{ feature.name }
					</span>
					{ showFreeBadge   && <LicenseBadge type="free" /> }
					{ showLegacyBadge && <LicenseBadge type="legacy" /> }
					{ mismatch        && <LicenseBadge type={ mismatch } /> }
				</div>

				{ isVisuallyAvailable ? (
					<div className="flex items-center gap-3 ml-auto shrink-0">
						<VersionDisplay
							feature={ feature }
							pendingAction={ pendingAction }
							installableBusy={ installableBusy }
							upgradeLabel={ showLegacyBadge
								? __( 'Upgrade your license to receive updates and support.', '%TEXTDOMAIN%' )
								: undefined
							}
							onUpdate={ showLegacyBadge ? undefined : handleUpdate }
						/>
						<StatusBadge status={ badgeStatus } />
						{ showSwitch && (
							<Switch
								checked={ switchChecked }
								onCheckedChange={ handleToggle }
								disabled={ !! pendingAction || installableBusy || mismatch === 'revoked' }
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
				) : (
					<div className="ml-auto shrink-0">
						<VersionDisplay
							feature={ feature }
							upgradeLabel={ upgradeTierName
								? /* translators: %s is the name of the tier required to receive updates */
								  sprintf( __( 'Upgrade to %s to receive updates and support.', '%TEXTDOMAIN%' ), upgradeTierName )
								: undefined
							}
						/>
					</div>
				) }
			</div>

			{ expanded && (
				<div className="px-4 pb-3 pl-[2.75rem]">
					<p className={ cn(
						'text-sm text-muted-foreground leading-relaxed',
						isVisuallyAvailable ? '!mt-[0.75em] !mb-0' : 'mt-2 mb-0'
					) }>
						{ feature.description }
					</p>
				</div>
			) }
		</div>
	);
}
