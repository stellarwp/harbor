/**
 * Pulse-skeleton that mirrors the LicenseKeyInput locked state while the
 * license resolver is still in flight.
 *
 * @package LiquidWeb\Harbor
 */

/**
 * @since 1.0.0
 */
export function LicenseKeyInputSkeleton() {
	return (
		<div className="flex items-center gap-2 animate-pulse">
			{ /* input */ }
			<div className="h-9 flex-1 rounded-md bg-muted" />
			{ /* edit button */ }
			<div className="h-3.5 w-8 rounded bg-muted shrink-0" />
		</div>
	);
}
