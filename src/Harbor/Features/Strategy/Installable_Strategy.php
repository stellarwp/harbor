<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Features\Strategy;

use LiquidWeb\Harbor\Features\Error_Code;
use LiquidWeb\Harbor\Features\Types\Feature;
use LiquidWeb\Harbor\Features\Update\Requirements\Headers;
use LiquidWeb\Harbor\Features\Update\Requirements\Resolve_Headers;
use LiquidWeb\Harbor\Utils\Cast;
use WP_Ajax_Upgrader_Skin;
use WP_Error;
use WP_Upgrader;

/**
 * Abstract base for strategies that install extensions (plugins, themes) from ZIP files.
 *
 * Templates the shared enable/disable/is_active/ensure_installed control flow.
 * Subclasses provide WP-specific behavior via abstract hook methods (do_install,
 * do_activate, do_deactivate, etc.).
 *
 * @since 1.0.0
 */
abstract class Installable_Strategy extends Abstract_Strategy {

	/**
	 * Transient lock TTL in seconds.
	 *
	 * 120 seconds is generous enough to cover slow downloads on shared hosting,
	 * but short enough that a crashed install won't block retries for long.
	 *
	 * @since 1.0.0
	 */
	protected const LOCK_TTL = MINUTE_IN_SECONDS * 2;

	/**
	 * Key for the global install lock.
	 *
	 * Only one installable feature can be installed at a time, regardless of
	 * type (plugin or theme). This prevents filesystem conflicts when multiple
	 * install requests arrive concurrently.
	 *
	 * @since 1.0.0
	 */
	protected const LOCK_KEY = 'lw_harbor_install_lock';

	/**
	 * Resolves the requirement headers of a feature's pending update from WordPress's update transients.
	 *
	 * @since TBD
	 *
	 * @var Resolve_Headers
	 */
	protected Resolve_Headers $resolve_headers;

	/**
	 * Construct the strategy.
	 *
	 * @since TBD
	 *
	 * @param Feature         $feature         The feature this strategy operates on.
	 * @param Resolve_Headers $resolve_headers Resolves the requirement headers of a feature's pending update from WordPress's update transients.
	 */
	public function __construct( Feature $feature, Resolve_Headers $resolve_headers ) {
		parent::__construct( $feature );

		$this->resolve_headers = $resolve_headers;
	}


	// ── Abstract hooks ──────────────────────────────────────────────────

	/**
	 * Check whether the extension is currently active in WordPress.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	abstract protected function check_active(): bool;

	/**
	 * Check whether the extension is installed on disk.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	abstract protected function check_installed(): bool;

	/**
	 * Install the extension from its download source.
	 *
	 * @since 1.0.0
	 *
	 * @return true|WP_Error
	 */
	abstract protected function do_install();

	/**
	 * Activate the extension.
	 *
	 * The subclass owns the full activation flow because activation logic
	 * varies significantly between plugins and themes (e.g. fatal error
	 * protection for plugins).
	 *
	 * @since 1.0.0
	 *
	 * @return true|WP_Error
	 */
	abstract protected function do_activate();

	/**
	 * Deactivate the extension.
	 *
	 * The subclass owns the full deactivation flow after the common prefix
	 * (type-guard, includes) handled by the template's disable().
	 *
	 * @since 1.0.0
	 *
	 * @return true|WP_Error
	 */
	abstract protected function do_deactivate();


	/**
	 * Error code for "extension not found after install".
	 *
	 * @since 1.0.0
	 *
	 * @return string An Error_Code constant value.
	 */
	abstract protected function get_not_found_after_install_error_code(): string;

	/**
	 * Check whether an update is available for this extension.
	 *
	 * @since 1.0.0
	 * @deprecated TBD Use get_update_headers(), which also returns the update's requirements.
	 *
	 * @return bool True if an update is available.
	 */
	abstract protected function check_update_available(): bool;

	/**
	 * Run the upgrade for this extension.
	 *
	 * @since 1.0.0
	 *
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	abstract protected function do_update();

	/**
	 * Load WordPress admin includes required for extension management.
	 *
	 * Subclasses implement this to load the specific files needed for their
	 * extension type (plugin.php, theme.php, upgrader classes, etc.).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	abstract protected function load_wp_admin_includes(): void;

	// ── Template methods ────────────────────────────────────────────────

	/**
	 * Enable the feature: install (if needed) and activate the extension.
	 *
	 * Idempotent: returns true if the extension is already active. Uses a
	 * global lock to prevent concurrent installs of any extension. Refuses to
	 * install an extension whose catalog version needs a newer WordPress or PHP
	 * than the site runs.
	 *
	 * @since 1.0.0
	 *
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	final public function enable() {
		// Ensure WordPress admin functions are available. These may not be
		// loaded when called from REST API or AJAX contexts.
		$this->load_wp_admin_includes();

		// Idempotent: if the extension is already active, bail.
		if ( $this->check_active() ) {
			static::debug_log(
				sprintf(
					'Feature "%s" already active.',
					$this->feature->get_slug()
				)
			);

			return true;
		}

		/**
		 * An extension that is not installed is downloaded at its catalog
		 * version, so refuse before downloading when the site cannot run it.
		 * An installed but inactive extension is checked by its own header
		 * when it is activated.
		 */
		if ( ! $this->check_installed() ) {
			$headers = $this->resolve_headers->from_catalog( $this->feature );

			if ( $headers !== null && ! $headers->is_met() ) {
				$error = $headers->to_wp_error( $this->feature->get_name(), Error_Code::REQUIREMENTS_NOT_MET );

				static::debug_log(
					sprintf(
						'Cannot install "%s": %s',
						$this->feature->get_slug(),
						$error->get_error_message()
					)
				);

				return $error;
			}
		}

		// Ensure the extension is on disk — install from ZIP if needed.
		$ensure_result = $this->ensure_installed();

		if ( is_wp_error( $ensure_result ) ) {
			static::debug_log_wp_error(
				$ensure_result,
				sprintf( 'Installation failed for "%s"', $this->feature->get_slug() )
			);

			return $ensure_result;
		}

		// Activate — subclass owns state update.
		return $this->do_activate();
	}

	/**
	 * Disable the feature: deactivate the extension.
	 *
	 * Loads includes and delegates to the subclass's do_deactivate() because
	 * plugin and theme disable flows diverge fundamentally.
	 *
	 * @since 1.0.0
	 *
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	final public function disable() {
		$this->load_wp_admin_includes();

		// Subclass owns the full deactivation flow.
		return $this->do_deactivate();
	}

	/**
	 * Update the feature: upgrade the extension to the latest available version.
	 *
	 * Checks the WordPress update transient for an available update. If one
	 * exists and the site meets its WordPress and PHP requirements, acquires
	 * the global install lock and runs the upgrade.
	 *
	 * @since 1.0.0
	 *
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	final public function update() {
		$this->load_wp_admin_includes();

		// Can't update what's not installed or active.
		if ( ! $this->check_active() ) {
			static::debug_log(
				sprintf(
					'Cannot update "%s": not active.',
					$this->feature->get_slug()
				)
			);

			return new WP_Error(
				Error_Code::FEATURE_NOT_ACTIVE,
				sprintf(
					/* translators: %s: feature name */
					__( '"%s" is not installed or active. Enable it first before updating.', '%TEXTDOMAIN%' ),
					$this->feature->get_name()
				)
			);
		}

		$headers = $this->get_update_headers();

		if ( $headers === null ) {
			static::debug_log(
				sprintf(
					'No update available for "%s".',
					$this->feature->get_slug()
				)
			);

			return new WP_Error(
				Error_Code::NO_UPDATE_AVAILABLE,
				sprintf(
					/* translators: %s: feature name */
					__( 'No update is available for "%s".', '%TEXTDOMAIN%' ),
					$this->feature->get_name()
				)
			);
		}

		if ( ! $headers->is_met() ) {
			$error = $headers->to_wp_error( $this->feature->get_name(), Error_Code::REQUIREMENTS_NOT_MET );

			static::debug_log(
				sprintf(
					'Cannot update "%s": %s',
					$this->feature->get_slug(),
					$error->get_error_message()
				)
			);

			return $error;
		}

		if ( ! $this->acquire_lock( self::LOCK_KEY ) ) {
			static::debug_log(
				sprintf(
					'Install lock held, cannot update "%s".',
					$this->feature->get_slug()
				)
			);

			return new WP_Error(
				Error_Code::INSTALL_LOCKED,
				sprintf(
					/* translators: %s: feature name */
					__( 'Another installable feature is already being installed. Cannot update "%s" right now. Please try again in a few moments.', '%TEXTDOMAIN%' ),
					$this->feature->get_name()
				)
			);
		}

		try {
			$result = $this->do_update();

			/**
			 * Make sure the extension is active after the update. The bulk
			 * upgraders keep an active plugin active, so for plugins this only
			 * changes something when the update left the plugin inactive.
			 */
			if ( $result === true ) {
				$result = $this->do_activate();
			}

			return $result;
		} finally {
			$this->release_lock( self::LOCK_KEY );
		}
	}

	/**
	 * Check whether the feature's extension is currently active.

	 * @since 1.0.0
	 *
	 * @return bool
	 */
	final public function is_active(): bool {
		$this->load_wp_admin_includes();

		return $this->check_active();
	}

	/**
	 * Ensure the extension is installed on disk, downloading if needed.
	 *
	 * Acquires a global lock to prevent concurrent installs of any
	 * feature, delegates to do_install() for the actual download, and verifies
	 * the expected file exists on disk afterward.
	 *
	 * If the extension is already installed, returns true immediately (no lock needed).
	 *
	 * @since 1.0.0
	 *
	 * @return true|WP_Error True if installed (or already was), WP_Error on failure.
	 */
	final protected function ensure_installed() {
		// Already on disk — ready for activation. Requirements are verified
		// by the caller (enable()) after this method returns.
		if ( $this->check_installed() ) {
			static::debug_log(
				sprintf(
					'Feature "%s" already installed on disk.',
					$this->feature->get_slug()
				)
			);

			return true;
		}

		// Acquire a global lock to prevent concurrent installs.
		// Only one feature can be installed at a time — two simultaneous
		// requests could race the installer, causing file conflicts or corruption.
		if ( ! $this->acquire_lock( self::LOCK_KEY ) ) {
			static::debug_log(
				sprintf(
					'Install lock held, cannot install "%s".',
					$this->feature->get_slug()
				)
			);

			return new WP_Error(
				Error_Code::INSTALL_LOCKED,
				sprintf(
					/* translators: %s: feature name */
					__( 'Another installable feature is already being installed. Cannot install "%s" right now. Please try again in a few moments.', '%TEXTDOMAIN%' ),
					$this->feature->get_name()
				)
			);
		}

		try {
			$install_result = $this->do_install();

			if ( is_wp_error( $install_result ) ) {
				static::debug_log_wp_error(
					$install_result,
					sprintf( 'do_install() failed for "%s"', $this->feature->get_slug() )
				);

				return $install_result;
			}

			// Post-install verification: the download's directory structure might not
			// match the expected path. Catch this early with a clear error rather than
			// a confusing "not found" during activation.
			// @phpstan-ignore-next-line booleanNot.alwaysTrue -- (do_install() creates files on disk; side effects invisible to static analysis).
			if ( ! $this->check_installed() ) {
				static::debug_log(
					sprintf(
						'Feature "%s" not found on disk after install — unexpected directory structure.',
						$this->feature->get_slug()
					)
				);

				return new WP_Error(
					$this->get_not_found_after_install_error_code(),
					sprintf(
						/* translators: %s: feature name */
						__( 'The extension was not found after installing "%s". The downloaded package may have an unexpected directory structure.', '%TEXTDOMAIN%' ),
						$this->feature->get_name()
					)
				);
			}

			return true; // @phpstan-ignore deadCode.unreachable (The check above is a double check)
		} finally {
			// Always release the lock, even on early returns or exceptions.
			$this->release_lock( self::LOCK_KEY );
		}
	}

	// ── Shared helpers ──────────────────────────────────────────────────

	/**
	 * Run an upgrader operation with standard error handling.
	 *
	 * Wraps the upgrader call in a try/catch, checks the result for WP_Error
	 * and falsy values, and inspects the skin for errors. This eliminates
	 * duplicated error handling across install and update paths.
	 *
	 * @since 1.0.0
	 *
	 * @param callable              $operation  Returns the upgrader result.
	 * @param WP_Ajax_Upgrader_Skin $skin       The upgrader skin collecting errors.
	 * @param string                $error_code Error_Code constant for failures.
	 * @param bool                  $is_update  True for update operations, false for install.
	 *
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	protected function run_upgrader( callable $operation, WP_Ajax_Upgrader_Skin $skin, string $error_code, bool $is_update ) {
		$name = $this->feature->get_name();

		try {
			$result = $operation();
		} catch ( \Throwable $e ) {
			static::debug_log(
				sprintf(
					'Fatal error %s "%s": %s %s:%s',
					$is_update ? 'updating' : 'installing',
					$this->feature->get_slug(),
					$e->getMessage(),
					$e->getFile(),
					$e->getLine()
				)
			);

			return new WP_Error(
				$error_code,
				$is_update
					? sprintf(
						/* translators: %s: feature name */
						__( 'A fatal error occurred while updating "%s".', '%TEXTDOMAIN%' ),
						$name
					)
					: sprintf(
						/* translators: %s: feature name */
						__( 'A fatal error occurred while installing "%s".', '%TEXTDOMAIN%' ),
						$name
					)
			);
		}

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$this->has_requirements_error( $result, $skin->get_errors() ) ? Error_Code::REQUIREMENTS_NOT_MET : $error_code,
				$is_update
					? sprintf(
						/* translators: %1$s: feature name, %2$s: error message */
						__( 'Failed updating "%1$s": %2$s', '%TEXTDOMAIN%' ),
						$name,
						$result->get_error_message()
					)
					: sprintf(
						/* translators: %1$s: feature name, %2$s: error message */
						__( 'Failed installing "%1$s": %2$s', '%TEXTDOMAIN%' ),
						$name,
						$result->get_error_message()
					)
			);
		}

		if ( $result !== true ) {
			$skin_errors = $skin->get_errors();
			$actual_code = $this->has_requirements_error( $skin_errors ) ? Error_Code::REQUIREMENTS_NOT_MET : $error_code;

			$message = $skin_errors->has_errors()
				? $skin->get_error_messages()
				: ( $is_update
					? __( 'An unknown error occurred while updating.', '%TEXTDOMAIN%' )
					: __( 'An unknown error occurred while installing.', '%TEXTDOMAIN%' )
				);

			return new WP_Error(
				$actual_code,
				$is_update
					? sprintf(
						/* translators: %1$s: feature name, %2$s: error message */
						__( 'Failed updating "%1$s": %2$s', '%TEXTDOMAIN%' ),
						$name,
						$message
					)
					: sprintf(
						/* translators: %1$s: feature name, %2$s: error message */
						__( 'Failed installing "%1$s": %2$s', '%TEXTDOMAIN%' ),
						$name,
						$message
					)
			);
		}

		return true;
	}

	/**
	 * Get the requirement headers of the update WordPress has for this extension.
	 *
	 * Reads the extension's entry in the update transient's `response` list.
	 * WordPress.org lists an update whose WordPress requirement the site does
	 * not meet under `no_update` instead, so a newer `no_update` entry whose
	 * requirements the site does not meet is read too. See
	 * Resolve_Headers.
	 *
	 * @since TBD
	 *
	 * @return Headers|null Null when WordPress has no update for the extension.
	 */
	protected function get_update_headers(): ?Headers {
		return ( $this->resolve_headers )( $this->feature );
	}

	/**
	 * WordPress error codes that indicate requirements failures.
	 *
	 * Override in subclasses that need to detect PHP/WP version mismatches
	 * from the upgrader skin.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	protected function get_requirements_error_codes(): array {
		return [];
	}

	/**
	 * Whether any of the given errors carries a requirements error code.
	 *
	 * The bulk upgraders return a requirements failure as the result, and the
	 * upgrader skin collects the errors raised while the package is checked,
	 * so run_upgrader() passes both.
	 *
	 * @since TBD
	 *
	 * @param WP_Error ...$errors The errors to check.
	 *
	 * @return bool
	 */
	private function has_requirements_error( WP_Error ...$errors ): bool {
		$requirements = $this->get_requirements_error_codes();

		if ( $requirements === [] ) {
			return false;
		}

		foreach ( $errors as $error ) {
			$codes = array_map( [ Cast::class, 'to_string' ], $error->get_error_codes() );

			if ( array_intersect( $codes, $requirements ) !== [] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Attempt to acquire an atomic install lock.
	 *
	 * Delegates to WP_Upgrader::create_lock() which uses INSERT IGNORE
	 * on wp_options for atomicity. The lock auto-expires after LOCK_TTL
	 * seconds even if the process crashes without releasing it.
	 *
	 * @since 1.0.0
	 *
	 * @param string $lock_key Lock name.
	 *
	 * @return bool True if lock acquired, false if already held.
	 */
	protected function acquire_lock( string $lock_key ): bool {
		if ( ! class_exists( 'WP_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		return WP_Upgrader::create_lock(
			$lock_key,
			self::LOCK_TTL
		);
	}

	/**
	 * Release the install lock.
	 *
	 * @since 1.0.0
	 *
	 * @param string $lock_key Lock name.
	 *
	 * @return void
	 */
	protected function release_lock( string $lock_key ): void {
		WP_Upgrader::release_lock( $lock_key );
	}
}
