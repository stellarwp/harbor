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
 * requires_wp, requires_php, wp_compatible and php_compatible describe the
 * version an update or an install would put on the site, and whether the site
 * meets its WordPress and PHP requirements:
 *
 * - With an update pending, the update's entry in the update transient.
 *   Harbor fills its own entries from the catalog; WordPress.org fills the
 *   entries of WordPress.org-hosted features.
 * - When the feature is not installed, the catalog version, which is the
 *   version installing it downloads.
 * - Otherwise there is no such version: the requirements are null and the
 *   compatible fields return true.
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
	 * The update version sourced from the WordPress update transient, or null.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	private ?string $update_version;

	/**
	 * The requirement headers of the version an update or an install would put on the site, or null when there is none.
	 *
	 * @since TBD
	 *
	 * @var Headers|null
	 */
	private ?Headers $headers;

	/**
	 * @since 1.0.0
	 * @since TBD Takes the requirement headers of the version an update or an install would put on the site.
	 *
	 * @param Feature      $feature        The resolved feature.
	 * @param string|null  $update_version Version from the update transient, or null.
	 * @param Headers|null $headers        The requirement headers of the version an update or an install would put on the site, or null when there is none.
	 */
	public function __construct( Feature $feature, ?string $update_version, ?Headers $headers = null ) {
		$this->feature        = $feature;
		$this->update_version = $update_version;
		$this->headers        = $headers;
	}

	/**
	 * Constructs a Feature_Resource from a resolved Feature.
	 *
	 * Should only be called after Feature_Repository has already cached its
	 * results so transient access does not trigger re-resolution.
	 *
	 * @since 1.0.0
	 * @since TBD Resolves the requirement headers of the pending update, or of the catalog version when the feature is not installed, with Resolve_Headers.
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

		$update = $resolve_headers( $feature );

		if ( $update !== null ) {
			return new self( $feature, $update->get_version(), $update );
		}

		$installed_version = $feature->to_array()['installed_version'] ?? null;
		$install           = empty( $installed_version ) ? $resolve_headers->from_catalog( $feature ) : null;

		return new self( $feature, null, $install );
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

		$headers = $this->headers;

		return array_merge(
			$this->feature->to_array(),
			[
				'update_version' => $this->update_version,
				'requires_wp'    => $headers !== null && $headers->get_wp() !== '' ? $headers->get_wp() : null,
				'requires_php'   => $headers !== null && $headers->get_php() !== '' ? $headers->get_php() : null,
				'wp_compatible'  => $headers === null || $headers->is_wp_met(),
				'php_compatible' => $headers === null || $headers->is_php_met(),
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
		return $this->update_version;
	}

	/**
	 * Returns the requirement headers of the version an update or an install would put on the site, or null when there is none.
	 *
	 * @since TBD
	 *
	 * @return Headers|null
	 */
	public function get_headers(): ?Headers {
		return $this->headers;
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
