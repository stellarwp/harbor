/**
 * Unified badge for all license-related states.
 *
 * Covers tier name, unlicensed, legacy, and free indicators so that
 * all license badge rendering flows through a single atom.
 *
 * @package LiquidWeb\Harbor
 */
import { __ } from '@wordpress/i18n';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

type LicenseBadgeProps =
	| { type: 'licensed';                                              tierName: string; className?: string; }
	| { type: 'unlicensed' | 'legacy' | 'free' | 'bonus' | 'revoked'; tierName?: never; className?: string; };

const variantMap = {
	licensed:   'gradient',
	unlicensed: 'outline',
	legacy:     'warning',
	free:       'secondary',
	bonus:      'warning',
	revoked:    'destructive',
} as const;

const labelMap = {
	unlicensed: () => __( 'Unlicensed',  '%TEXTDOMAIN%' ),
	legacy:     () => __( 'Legacy',      '%TEXTDOMAIN%' ),
	free:       () => __( 'Free',        '%TEXTDOMAIN%' ),
	bonus:      () => __( 'Bonus',       '%TEXTDOMAIN%' ),
	revoked:    () => __( 'Unavailable', '%TEXTDOMAIN%' ),
} as const;

/**
 * @since 1.0.0
 */
export function LicenseBadge( { type, tierName, className }: LicenseBadgeProps ) {
	const label = type === 'licensed'
		? tierName
		: labelMap[ type ]();

	return (
		<Badge variant={ variantMap[ type ] } className={ cn( className ) }>
			{ label }
		</Badge>
	);
}
