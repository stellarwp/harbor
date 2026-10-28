<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Features\Update\Requirements;

use LiquidWeb\Harbor\Utils\Cast;
use WP_Error;

/**
 * The `Requires at least` and `Requires PHP` headers of a pending plugin or
 * theme update, read from its entry in the `update_plugins` or `update_themes`
 * transient, where they are stored as `requires` and `requires_php`.
 *
 * Plugin entries are objects and theme entries are arrays. The entry's
 * `new_version` and `id` are kept too, for error messages and to tell which
 * updater wrote the entry. An
 * empty requirement means the update declares none, which WordPress's own
 * compatibility checks treat as met.
 *
 * @since TBD
 *
 * @phpstan-type HeadersAttributes array{
 *     id: string,
 *     version: string,
 *     wp: string,
 *     php: string,
 * }
 */
final class Headers {

	/**
	 * The requirement attributes.
	 *
	 * @since TBD
	 *
	 * @var HeadersAttributes
	 */
	private array $attributes;

	/**
	 * Constructor.
	 *
	 * @since TBD
	 *
	 * @phpstan-param HeadersAttributes $attributes
	 *
	 * @param array $attributes The requirement attributes.
	 */
	public function __construct( array $attributes ) {
		$this->attributes = $attributes;
	}

	/**
	 * Creates the requirements from an update transient entry.
	 *
	 * @since TBD
	 *
	 * @param object|array<mixed> $entry A plugin entry (object) or theme entry (array).
	 *
	 * @return self
	 */
	public static function from_entry( $entry ): self {
		$data = is_object( $entry ) ? get_object_vars( $entry ) : $entry;

		return new self(
			[
				'id'      => Cast::to_string( $data['id'] ?? '' ),
				'version' => Cast::to_string( $data['new_version'] ?? '' ),
				'wp'      => Cast::to_string( $data['requires'] ?? '' ),
				'php'     => Cast::to_string( $data['requires_php'] ?? '' ),
			]
		);
	}

	/**
	 * Gets the version of the update.
	 *
	 * @since TBD
	 *
	 * @return string
	 */
	public function get_version(): string {
		return $this->attributes['version'];
	}

	/**
	 * Gets the minimum WordPress version, or an empty string when the update declares none.
	 *
	 * @since TBD
	 *
	 * @return string
	 */
	public function get_wp(): string {
		return $this->attributes['wp'];
	}

	/**
	 * Gets the minimum PHP version, or an empty string when the update declares none.
	 *
	 * @since TBD
	 *
	 * @return string
	 */
	public function get_php(): string {
		return $this->attributes['php'];
	}

	/**
	 * Whether the site's WordPress version meets the update's requirement.
	 *
	 * @since TBD
	 *
	 * @return bool
	 */
	public function is_wp_met(): bool {
		return is_wp_version_compatible( $this->attributes['wp'] );
	}

	/**
	 * Whether the site's PHP version meets the update's requirement.
	 *
	 * @since TBD
	 *
	 * @return bool
	 */
	public function is_php_met(): bool {
		return is_php_version_compatible( $this->attributes['php'] );
	}

	/**
	 * Whether the site meets both the WordPress and the PHP requirement.
	 *
	 * @since TBD
	 *
	 * @return bool
	 */
	public function is_met(): bool {
		return $this->is_wp_met() && $this->is_php_met();
	}

	/**
	 * Gets the entry's `id`, which says which updater wrote it, e.g.
	 * `stellarwp/plugins/kad-blocks-pro` or `w.org/plugins/kadence-blocks`.
	 *
	 * @since TBD
	 *
	 * @return string An empty string when the entry has none.
	 */
	public function get_id(): string {
		return $this->attributes['id'];
	}

	/**
	 * Builds an error that names the unmet requirements and the site's current versions.
	 *
	 * @since TBD
	 *
	 * @param string $name The display name of the plugin or theme.
	 * @param string $code The error code.
	 *
	 * @return WP_Error
	 */
	public function to_wp_error( string $name, string $code ): WP_Error {
		$wp_version  = Cast::to_string( get_bloginfo( 'version' ) );
		$php_version = PHP_VERSION;

		if ( ! $this->is_wp_met() && ! $this->is_php_met() ) {
			$message = sprintf(
				/* translators: 1: plugin or theme name, 2: version of the update, 3: required WordPress version, 4: required PHP version, 5: site's WordPress version, 6: site's PHP version */
				__( '"%1$s" %2$s requires WordPress %3$s and PHP %4$s or newer. This site runs WordPress %5$s and PHP %6$s.', '%TEXTDOMAIN%' ),
				$name,
				$this->get_version(),
				$this->get_wp(),
				$this->get_php(),
				$wp_version,
				$php_version
			);
		} elseif ( ! $this->is_wp_met() ) {
			$message = sprintf(
				/* translators: 1: plugin or theme name, 2: version of the update, 3: required WordPress version, 4: site's WordPress version */
				__( '"%1$s" %2$s requires WordPress %3$s or newer. This site runs WordPress %4$s.', '%TEXTDOMAIN%' ),
				$name,
				$this->get_version(),
				$this->get_wp(),
				$wp_version
			);
		} else {
			$message = sprintf(
				/* translators: 1: plugin or theme name, 2: version of the update, 3: required PHP version, 4: site's PHP version */
				__( '"%1$s" %2$s requires PHP %3$s or newer. This site runs PHP %4$s.', '%TEXTDOMAIN%' ),
				$name,
				$this->get_version(),
				$this->get_php(),
				$php_version
			);
		}

		return new WP_Error(
			$code,
			$message,
			[
				'requires'     => $this->get_wp(),
				'requires_php' => $this->get_php(),
			]
		);
	}
}
