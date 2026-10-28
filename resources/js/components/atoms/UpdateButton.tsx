/**
 * Icon button that triggers a feature update.
 *
 * When disabledLabel is provided the button is rendered disabled with a
 * tooltip: an upsell, or the WordPress or PHP version the update requires.
 * The Tooltip component handles the span wrapper needed to
 * keep hover events working when pointer-events are disabled on the button.
 *
 * @package LiquidWeb\Harbor
 */
import { __, sprintf } from '@wordpress/i18n';
import { Download } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Tooltip } from '@/components/ui/tooltip';

interface UpdateButtonProps {
	featureName:   string;
	disabled?:     boolean;
	onClick?:      () => void;
	/** When set, the button is disabled and this text is shown as a tooltip: an upsell, or the WordPress or PHP version the update requires. */
	disabledLabel?: string;
}

/**
 * @since 1.0.0
 */
export function UpdateButton( { featureName, disabled = false, onClick, disabledLabel }: UpdateButtonProps ) {
	const button = (
		<Button
			variant="default"
			size="icon-xs"
			className="rounded-full"
			disabled={ !! disabledLabel || disabled }
			onClick={ onClick }
			aria-label={ sprintf( __( 'Update %s', '%TEXTDOMAIN%' ), featureName ) }
		>
			<Download className="w-3.5 h-3.5" />
		</Button>
	);

	return disabledLabel
		? <Tooltip label={ disabledLabel }>{ button }</Tooltip>
		: button;
}
