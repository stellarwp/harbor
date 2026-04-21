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
import type { LicenseStatus } from '@/types/api';

type LicenseBadgeProps =
	| { type: 'licensed';                    tierName: string;  className?: string; }
	| { type: Exclude<LicenseStatus, 'licensed'>; tierName?: never; className?: string; };

const variantMap = {
	licensed:        'gradient',
	unlicensed:      'outline',
	legacy:          'warning',
	free:            'secondary',
	bonus:           'warning',
	revoked:         'destructive',
	unactivated:     'warning',
	expired:         'destructive',
	cancelled:       'outline',
	suspended:       'destructive',
	over_limit:      'warning',
} as const;

const labelMap = {
	unlicensed:      () => __( 'Unlicensed',    '%TEXTDOMAIN%' ),
	legacy:          () => __( 'Legacy',        '%TEXTDOMAIN%' ),
	free:            () => __( 'Free',          '%TEXTDOMAIN%' ),
	bonus:           () => __( 'Bonus',         '%TEXTDOMAIN%' ),
	revoked:         () => __( 'Unavailable',   '%TEXTDOMAIN%' ),
	unactivated:     () => __( 'Unactivated',   '%TEXTDOMAIN%' ),
	expired:         () => __( 'Expired',       '%TEXTDOMAIN%' ),
	cancelled:       () => __( 'Cancelled',     '%TEXTDOMAIN%' ),
	suspended:       () => __( 'Suspended',     '%TEXTDOMAIN%' ),
	over_limit:      () => __( 'Over limit',    '%TEXTDOMAIN%' ),
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
