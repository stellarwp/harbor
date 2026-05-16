/**
 * Typed accessor for `window.harborData`.
 *
 * Single chokepoint for global config access. Three lookup tiers:
 *   1. Live value at `window.harborData[ key ]`.
 *   2. Per-call `fallback` (optional).
 *   3. Built-in default from the `DEFAULTS` map.
 *   4. `null` if none of those exist.
 *
 * Defaulting to `null` rather than `undefined` matches the surrounding
 * codebase convention (LicenseSection / LicensePanel already type their
 * optional URLs as `string | null`). The `window?.` chain protects test
 * paths that import the module without jsdom.
 *
 * @package LiquidWeb\Harbor
 */
import type { HarborData } from '@/types/harbor-data';

const DEFAULTS = {
    licenseKeyPrefix: 'LWSW-',
    pluginsUrl:       '/wp-admin/plugins.php',
} as const satisfies Partial<HarborData>;

type KeyWithDefault = keyof typeof DEFAULTS;

export function getHarborDataValue<K extends KeyWithDefault>(
    key: K
): HarborData[K];
export function getHarborDataValue<K extends Exclude<keyof HarborData, KeyWithDefault>>(
    key: K
): HarborData[K] | null;
export function getHarborDataValue<K extends keyof HarborData>(
    key: K,
    fallback: HarborData[K]
): HarborData[K];
export function getHarborDataValue<K extends keyof HarborData>(
    key: K,
    fallback?: HarborData[K]
): HarborData[K] | null {
    return (
        window?.harborData?.[ key ]
        ?? fallback
        ?? ( DEFAULTS as Partial<HarborData> )[ key ]
        ?? null
    );
}
