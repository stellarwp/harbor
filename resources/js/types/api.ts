/**
 * API type definitions for the License Manager Dashboard.
 *
 * @package LiquidWeb\Harbor
 */

// ---------------------------------------------------------------------------
// Feature types — GET /liquidweb/harbor/v1/features
// ---------------------------------------------------------------------------

/**
 * Feature type identifier returned by the REST API.
 *
 * @since 1.0.0
 */
export type FeatureType = 'plugin' | 'theme' | 'service';

/**
 * Base properties shared by all feature types.
 *
 * @since 1.0.0
 */
interface BaseFeature {
    /**
     * Unique feature slug (e.g. "give-recurring-donations").
     */
    slug: string;
    /**
     * Human-readable feature name.
     */
    name: string;
    /**
     * Short description.
     */
    description: string;
    /**
     * Product slug this feature belongs to (e.g. "give").
     */
    product: string;
    /**
     * Minimum tier slug required to access this feature, or null for free features.
     */
    tier: string | null;
    /**
     * Whether the feature is available on this site.
     */
    is_available: boolean;
    /**
     * Whether the user's licensed tier covers this feature's minimum tier.
     */
    in_catalog_tier: boolean;
    /**
     * URL to documentation or learn-more page.
     */
    documentation_url: string;
    /**
     * Whether the feature is currently enabled (persisted server-side).
     */
    is_enabled: boolean;
    /**
     * Latest available version string, if known.
     */
    version?: string;
    /**
     * Installed version string, if known.
     */
    installed_version?: string;
    /**
     * Version available in the WordPress update transient, or null when no update is pending.
     * Only present for installable features (plugin/theme).
     */
    update_version?: string | null;
}

/**
 * A feature delivered as a standalone WordPress plugin.
 *
 * @since 1.0.0
 */
export interface PluginFeature extends BaseFeature {
    type: 'plugin';
    /**
     * Plugin file path relative to the plugins directory.
     */
    plugin_file: string;
    /**
     * Slug used for plugins_api() lookups.
     */
    plugin_slug: string;
    /**
     * Expected plugin authors for ownership verification.
     */
    authors: string[];
    /**
     * WordPress.org slug for plugins_api() lookups, or null if not on WordPress.org.
     */
    wporg_slug: string | null;
}

/**
 * A feature delivered as a WordPress theme.
 *
 * @since 1.0.0
 */
export interface ThemeFeature extends BaseFeature {
    type: 'theme';
    /**
     * Expected theme authors for ownership verification.
     */
    authors: string[];
    /**
     * WordPress.org slug for themes_api() lookups, or null if not on WordPress.org.
     */
    wporg_slug: string | null;
}

/**
 * A non-installable service feature.
 *
 * Services are present-or-not: they have no artifact to install, no
 * version to track, and no toggle. The backend sets is_enabled = is_available.
 *
 * @since 1.0.0
 */
export interface ServiceFeature extends BaseFeature {
    type: 'service';
    /**
     * Services have no installable artifact. These fields are marked `never`
     * so TypeScript catches accidental access after narrowing.
     */
    version?: never;
    installed_version?: never;
    update_version?: never;
}

/**
 * Discriminated union of all feature types as returned by the REST API.
 *
 * @since 1.0.0
 */
export type Feature = PluginFeature | ThemeFeature | ServiceFeature;

/**
 * Features that trigger WordPress install/activate/deactivate operations.
 *
 * @since 1.0.0
 */
export type InstallableFeature = PluginFeature | ThemeFeature;

/**
 * Describes the relationship between a feature's catalog tier position
 * and what the license's capabilities array actually grants.
 *
 * - `'bonus'`   — Available (is_available: true) but outside the user's catalog
 *                 tier (in_catalog_tier: false). Promotional access.
 *
 * - `'revoked'` — In the user's catalog tier (in_catalog_tier: true) but not
 *                 available (is_available: false). Access was removed or not provisioned.
 *
 * - `null`      — No mismatch. Feature state is consistent with the user's tier.
 *
 * @since 1.0.0
 */
export type FeatureMismatchType = 'bonus' | 'revoked' | null;

// ---------------------------------------------------------------------------
// Catalog types — GET /liquidweb/harbor/v1/catalog
// ---------------------------------------------------------------------------

/**
 * A raw catalog feature entry before feature resolution.
 *
 * Field names match the Catalog_Feature PHP class and the catalog REST
 * endpoint response. These differ from the resolved Feature types above
 * (e.g. slug + kind vs slug + type, minimum_tier vs tier).
 *
 * @since 1.0.0
 */
export interface CatalogFeature {
    /**
     * Unique feature slug.
     */
    slug: string;
    /**
     * Feature delivery kind (plugin or theme).
     */
    kind: FeatureType;
    /**
     * Minimum tier slug required for this feature.
     */
    minimum_tier: string;
    /**
     * Top-level plugin directory (plugin kind only), or null for non-plugin features.
     */
    top_dir: string | null;
    /**
     * Plugin main file name without directory (plugin kind only), or null for non-plugin features.
     */
    main_file: string | null;
    /**
     * Derived plugin file path (`top_dir/main_file`), or null when either part is missing.
     */
    plugin_file: string | null;
    /**
     * WordPress.org slug, or null if not on WordPress.org.
     */
    wporg_slug: string | null;
    /**
     * Latest version string, or null when unavailable.
     */
    version: string | null;
    /**
     * Release date, or null when unavailable.
     */
    release_date: string | null;
    /**
     * Human-readable feature name.
     */
    name: string;
    /**
     * Short description.
     */
    description: string;
    /**
     * Feature category within the product.
     */
    category: string;
    /**
     * Feature authors, or null when not applicable.
     */
    authors: string[] | null;
    /**
     * URL to documentation or learn-more page.
     */
    documentation_url: string;
    /**
     * Homepage URL, or null when not available.
     */
    homepage: string | null;
}

/**
 * A tier entry from the product catalog.
 *
 * @since 1.0.0
 */
export interface CatalogTier {
    /**
     * Tier slug identifier.
     */
    tier_slug: string;
    /**
     * Display name (e.g. "Pro").
     */
    name: string;
    /**
     * Numeric rank for tier comparison.
     */
    rank: number;
    /**
     * Tier price.
     */
    price: number;
    /**
     * Currency code (e.g. "USD").
     */
    currency: string;
    /**
     * Marketing feature descriptions for this tier.
     */
    features: string[];
    /**
     * Herald slugs associated with this tier.
     */
    herald_slugs: string[];
    /**
     * Checkout URL to purchase or upgrade to this tier.
     */
    purchase_url: string;
}

/**
 * A product catalog entry as returned by GET /liquidweb/harbor/v1/catalog.
 *
 * @since 1.0.0
 */
export interface ProductCatalog {
    /**
     * Product ID from the Commerce Portal.
     */
    product_id: string;
    /**
     * Product slug identifier.
     */
    product_slug: string;
    /**
     * Product display name.
     */
    product_name: string;
    /**
     * Available tiers ordered by rank.
     */
    tiers: CatalogTier[];
    /**
     * Raw catalog features for this product.
     */
    features: CatalogFeature[];
}

// ---------------------------------------------------------------------------
// Product types (display layer)
// ---------------------------------------------------------------------------

/**
 * A product with tiered plans.
 *
 * Tier definitions come from the catalog API (CatalogTier[]) — not stored here.
 *
 * @since 1.0.0
 */
export interface Product {
    /**
     * Unique product slug, matches feature product field (e.g. "give", "kadence").
     */
    slug: string;
    /**
     * Display name (e.g. "GiveWP").
     */
    name: string;
    /**
     * Short tagline.
     */
    tagline: string;
}

// ---------------------------------------------------------------------------
// Legacy license types — GET /liquidweb/harbor/v1/legacy-licenses
// ---------------------------------------------------------------------------

/**
 * A legacy per-plugin license as returned by the legacy-licenses endpoint.
 *
 * @since 1.0.0
 */
export interface LegacyLicense {
	key: string;
	slug: string;
	name: string;
	product: string;
	is_active: boolean;
	page_url: string;
	expires_at: string;
}

// ---------------------------------------------------------------------------
// License types — GET/POST /liquidweb/harbor/v1/license
// ---------------------------------------------------------------------------

export type LicenseStatus =
	| 'licensed'
	| 'unlicensed'
	| 'legacy'
	| 'free'
	| 'bonus'
	| 'revoked'
	| 'unactivated'
	| 'expired'
	| 'cancelled'
	| 'suspended'
	| 'over_limit';

/**
 * A licensed product entry as returned alongside the license key.
 *
 * @since 1.0.0
 */
export interface LicenseProduct {
    product_slug: string;
    tier: string;
    status: LicenseStatus;
    expires: string;
    activations: {
        site_limit: number;
        active_count: number;
        over_limit: boolean;
        domains: string[];
    };
    capabilities: string[];
    activated_here?: boolean;
    validation_status?: string;
    is_valid?: boolean;
}

/**
 * Unified license key as returned by GET/POST /liquidweb/harbor/v1/license.
 *
 * @since 1.0.0
 */
export interface LicenseError {
    code: string;
    message: string;
}

export interface License {
    /**
     * The stored unified license key, or null if none is set.
     */
    key: string | null;
    /**
     * Licensed products associated with this key.
     */
    products: LicenseProduct[];
    /**
     * An error encountered while fetching the license, or null if none.
     */
    error: LicenseError | null;
}
