import { createRoot } from 'react-dom/client';
import { registerHarborStore } from '@/store';
import { App } from '@/App';
import '@css/globals.css';

registerHarborStore();

const rootElement = document.getElementById( 'uplink-root' );

if ( rootElement ) {
	// Delay execution until after the DOM is fully loaded.
	window.addEventListener( 'DOMContentLoaded', () => {
		createRoot( rootElement ).render( <App /> );
	} );
}
