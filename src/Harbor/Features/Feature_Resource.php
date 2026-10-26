<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Features;

use LiquidWeb\Harbor\Features\Types\Feature;
use LiquidWeb\Harbor\Features\Types\Service;
use LiquidWeb\Harbor\Features\Update\Requirements\Headers;
use LiquidWeb\Harbor\Features\Update\Requirements\Resolve_Headers;

/**
 * Decorates a resolved Feature with request-context data for REST API responses.
 *
 * After feature resolution is complete (Feature_Repository cache is warm),
 * reading the update transient via get_site_transient() is safe: the transient
 * filter chain hits the cached repository result, so there is no circular
 * dependency.
 *
 * update_version is the version of the pending update, or null when the
 * feature has none (e.g. unlicensed or not installed). The pending update is
 * the feature's entry in the transient's `response` list, or an entry in
 * `no_update` with a newer version whose requirements the site does not meet,
 * which is where WordPress.org lists an update that needs a newer WordPress.
 * See Update\Requirements\Resolve_Headers.
 *
 * The update_requires_* and update_*_compatible fields describe the WordPress
 * and PHP versions that update requires and whether the site meets them. The
 * update_*_compatible fields return true if there is no update, since the
 * installed version already runs on the site.
 *
 * requires_wp and requires_php are the WordPress and PHP versions the catalog
 * version requires, which is the version installing the feature downloads.
 * wp_compatible and php_compatible say whether the site meets them, and
 * return true when the catalog declares none.
 *
 * @since 1.0.0
 */
final class Feature_Resource {

	/**
	 * The resolved Feature.
	 *
	 * @since 1.0.0
	 *
	 * @var Feature
	 */
	private Feature $feature;

	/**
	 * The pending update's requirement headers, or null when no update is pending.
	 *
	 * @since TBD
	 *
	 * @var Headers|null
	 */
	private ?Headers $update;

	/**
	 * The catalog version's requirement headers, or null when the catalog has no version.
	 *
	 * @since TBD
	 *
	 * @var Headers|null
	 */
	private ?Headers $catalog;

	/**
	 * @since 1.0.0
	 * @since TBD Takes the pending update's requirement headers instead of its version.
	 *
	 * @since TBD Takes the catalog version's requirement headers.
	 *
	 * @param Feature      $feature The resolved feature.
	 * @param Headers|null $update  The pending update's requirement headers, or null when no update is pending.
	 * @param Headers|null $catalog The catalog version's requirement headers, or null when the catalog has no version.
	 */
	public function __construct( Feature $feature, ?Headers $update, ?Headers $catalog = null ) {
		$this->feature = $feature;
		$this->update  = $update;
		$this->catalog = $catalog;
	}

	/**
	 * Constructs a Feature_Resource from a resolved Feature.
	 *
	 * Should only be called after Feature_Repository has already cached its
	 * results so transient access does not trigger re-resolution.
	 *
	 * @since 1.0.0
	 * @since TBD Resolves the pending update's requirement headers with Resolve_Headers.
	 *
	 * @param Feature         $feature         The resolved feature.
	 * @param Resolve_Headers $resolve_headers Resolves the requirement headers of a feature's pending update from WordPress's update transients.
	 *
	 * @return self
	 */
	public static function from_feature( Feature $feature, Resolve_Headers $resolve_headers ) {
		if ( $feature instanceof Service ) {
			return new self( $feature, null );
		}

		return new self( $feature, $resolve_headers( $feature ), $resolve_headers->from_catalog( $feature ) );
	}

	/**
	 * Returns the data array for use in REST API responses.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		// Services do not support updates.
		if ( $this->feature instanceof Service ) {
			return $this->feature->to_array();
		}

		$update = $this->update;

		return array_merge(
			$this->feature->to_array(),
			[
				'update_version'        => $this->get_update_version(),
				'update_requires_wp'    => $update !== null && $update->get_wp() !== '' ? $update->get_wp() : null,
				'update_requires_php'   => $update !== null && $update->get_php() !== '' ? $update->get_php() : null,
				'update_wp_compatible'  => $update === null || $update->is_wp_met(),
				'update_php_compatible' => $update === null || $update->is_php_met(),
				'wp_compatible'         => $this->catalog === null || $this->catalog->is_wp_met(),
				'php_compatible'        => $this->catalog === null || $this->catalog->is_php_met(),
			]
		);
	}

	/**
	 * Returns the update version, or null if no update is pending.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	public function get_update_version(): ?string {
		return $this->update !== null ? $this->update->get_version() : null;
	}

	/**
	 * Returns the pending update's requirement headers, or null if no update is pending.
	 *
	 * @since TBD
	 *
	 * @return Headers|null
	 */
	public function get_update_headers(): ?Headers {
		return $this->update;
	}

	/**
	 * Returns the decorated Feature.
	 *
	 * @since 1.0.0
	 *
	 * @return Feature
	 */
	public function get_feature(): Feature {
		return $this->feature;
	}
}
