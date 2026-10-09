/**
 * Displays the installed/available version of a feature, with an update
 * button when a newer version is available.
 *
 * When upgradeLabel is provided the update button is rendered fully disabled
 * (no onClick handler) with an upsell tooltip.
 *
 * @package LiquidWeb\Harbor
 */
import { sprintf, __ } from '@wordpress/i18n';
import { UpdateButton } from '@/components/atoms/UpdateButton';
import type { Feature } from '@/types/api';

export interface VersionDisplayProps {
	feature:          Feature;
	/** When set, the update button is disabled and this text is shown as an upsell tooltip. */
	upgradeLabel?:    string;
	pendingAction?:   'enabling' | 'disabling' | 'installing' | 'updating' | null;
	installableBusy?: boolean;
	/** Required when upgradeLabel is not set and the button should be active. */
	onUpdate?:        () => void;
}

/**
 * @since 1.0.0
 */
export function VersionDisplay( {
	feature,
	upgradeLabel,
	pendingAction   = null,
	installableBusy = false,
	onUpdate,
}: VersionDisplayProps ) {
	if ( feature.update_version ) {
		return (
			<div className="flex flex-col items-end gap-1">
				<span className="text-xs font-mono text-muted-foreground line-through break-all">
					v{ feature.installed_version }
				</span>
				<div className="flex flex-wrap items-center justify-end gap-1.5">
					<span className="text-xs font-mono font-bold break-all">
						v{ feature.update_version }
					</span>
					{ ( upgradeLabel || onUpdate ) && (
						<UpdateButton
							featureName={ feature.name }
							disabled={ !! pendingAction || installableBusy }
							onClick={ onUpdate }
							upgradeLabel={ upgradeLabel }
						/>
					) }
				</div>
			</div>
		);
	}

	if ( ! feature.version && ! feature.installed_version ) {
		return null;
	}

	return (
		<span className="text-xs font-mono text-muted-foreground text-right break-all">
			{ `v${ feature.installed_version ?? feature.version }` }
		</span>
	);
}
