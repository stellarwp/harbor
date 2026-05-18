<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Legacy;

/**
 * Provides access to legacy licenses reported by all Harbor
 * instances through the cross-instance filter.
 *
 * @since 1.0.0
 */
class License_Repository {

	/**
	 * In-memory cache for the current request cycle.
	 *
	 * @var Legacy_License[]|null
	 */
	private ?array $cache = null;

	/**
	 * Get all legacy licenses reported across all Harbor instances.
	 *
	 * @since 1.0.0
	 *
	 * @return Legacy_License[]
	 */
	public function all(): array {
		if ( $this->cache !== null ) {
			return $this->cache;
		}

		$filtered_licenses = (array) apply_filters( 'lw-harbor/legacy_licenses', [] );

		$licenses = [];

		foreach ( $filtered_licenses as $license ) {
			if ( ! is_array( $license ) ) {
				continue;
			}

			/** @var array<string, mixed> $license */
			$candidate = Legacy_License::from_data( $license );

			// Reject malformed entries that violate the integration contract.
			// Both `key` and `slug` are documented as required; entries missing
			// either are dropped here so they never reach UI, notices, or any
			// downstream consumer.
			if ( ! ( $candidate->key && $candidate->slug ) ) {
				continue;
			}

			$licenses[] = $candidate;
		}

		$this->cache = $licenses;

		return $licenses;
	}

	/**
	 * Get all legacy licenses that are currently active.
	 *
	 * @since 1.0.0
	 *
	 * @return Legacy_License[]
	 */
	public function all_active(): array {
		return array_values(
			array_filter(
				$this->all(),
				static function ( Legacy_License $l ): bool {
					return $l->is_active;
				} 
			) 
		);
	}

	/**
	 * Get all legacy licenses that are not currently active.
	 *
	 * @since 1.0.0
	 *
	 * @return Legacy_License[]
	 */
	public function all_inactive(): array {
		return array_values(
			array_filter(
				$this->all(),
				static function ( Legacy_License $l ): bool {
					return ! $l->is_active;
				} 
			) 
		);
	}

	/**
	 * Get a legacy license by resource slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The resource slug.
	 *
	 * @return Legacy_License|null
	 */
	public function find( string $slug ): ?Legacy_License {
		foreach ( $this->all() as $license ) {
			if ( $license->slug === $slug ) {
				return $license;
			}
		}

		return null;
	}

	/**
	 * Whether any legacy licenses exist across all instances.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function has_any(): bool {
		return count( $this->all() ) > 0;
	}

	/**
	 * Whether any reported legacy license has opted into Harbor's update pipeline.
	 *
	 * Used as the cheap pre-check for update handlers: if no reported entry has
	 * `use_for_updates = true`, there is no work to do regardless of how many
	 * informational legacy entries exist.
	 *
	 * @since TBD
	 *
	 * @return bool
	 */
	public function any_used_for_updates(): bool {
		foreach ( $this->all() as $license ) {
			if ( $license->use_for_updates ) {
				return true;
			}
		}

		return false;
	}
}
