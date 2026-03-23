<?php declare( strict_types=1 );

namespace LiquidWeb\Harbor\View;

use LiquidWeb\Harbor\Contracts\Abstract_Provider;
use LiquidWeb\Harbor\View\Contracts\View;

final class Provider extends Abstract_Provider {

	/**
	 * Configure the View Renderer.
	 */
	public function register() {
		$this->container->singleton(
			WordPress_View::class,
			new WordPress_View( __DIR__ . '/../../views' )
		);

		$this->container->bind( View::class, $this->container->get( WordPress_View::class ) );
	}
}
