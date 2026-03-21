/**
 * Error utility functions.
 *
 * @package LiquidWeb\Harbor
 */
import type { WpRestError } from './types';

/**
 * Type guard. Checks whether an unknown value matches the WP REST error shape.
 */
export function isWpRestError( value: unknown ): value is WpRestError {
	return (
		typeof value === 'object' &&
		value !== null &&
		'code' in value &&
		typeof ( value as WpRestError ).code === 'string' &&
		'message' in value &&
		typeof ( value as WpRestError ).message === 'string'
	);
}
