<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\Features\Strategy;

use InvalidArgumentException;
use LiquidWeb\Harbor\Features\Contracts\Strategy;
use LiquidWeb\Harbor\Features\Types\Feature;
use LiquidWeb\Harbor\Features\Types\Plugin;
use LiquidWeb\Harbor\Features\Types\Service;
use LiquidWeb\Harbor\Features\Types\Theme;
use LiquidWeb\Harbor\Features\Update\Requirements\Resolve_Headers;

/**
 * Factory that creates Strategy instances for features.
 *
 * Maps feature type strings to their corresponding Strategy classes.
 * Each call creates a new Strategy instance bound to the given Feature.
 *
 * @since 1.0.0
 */
class Strategy_Factory {

	/**
	 * Resolves the requirement headers of a feature's pending update from WordPress's update transients.
	 *
	 * @since TBD
	 *
	 * @var Resolve_Headers
	 */
	private Resolve_Headers $resolve_headers;

	/**
	 * Constructor.
	 *
	 * @since TBD
	 *
	 * @param Resolve_Headers $resolve_headers Resolves the requirement headers of a feature's pending update for the installable strategies.
	 */
	public function __construct( Resolve_Headers $resolve_headers ) {
		$this->resolve_headers = $resolve_headers;
	}

	/**
	 * Creates the correct strategy for a given feature.
	 *
	 * @since 1.0.0
	 *
	 * @param Feature $feature The feature to create a strategy for.
	 *
	 * @throws InvalidArgumentException If no strategy exists for the feature's type.
	 *
	 * @return Strategy A new Strategy instance bound to the given Feature.
	 */
	public function make( Feature $feature ): Strategy {
		switch ( $feature->get_type() ) {
			case Feature::TYPE_PLUGIN:
				if ( ! $feature instanceof Plugin ) {
					throw new InvalidArgumentException(
						sprintf( 'Feature type "%s" requires a Plugin instance.', Feature::TYPE_PLUGIN )
					);
				}

				return new Plugin_Strategy( $feature, $this->resolve_headers );
			case Feature::TYPE_THEME:
				if ( ! $feature instanceof Theme ) {
					throw new InvalidArgumentException(
						sprintf( 'Feature type "%s" requires a Theme instance.', Feature::TYPE_THEME )
					);
				}

				return new Theme_Strategy( $feature, $this->resolve_headers );
			case Feature::TYPE_SERVICE:
				if ( ! $feature instanceof Service ) {
					throw new InvalidArgumentException(
						sprintf( 'Feature type "%s" requires a Service instance.', Feature::TYPE_SERVICE )
					);
				}

				return new Service_Strategy( $feature );
			default:
				throw new InvalidArgumentException(
					sprintf( 'No strategy for feature type "%s".', $feature->get_type() )
				);
		}
	}
}
