/**
 * Entry point for the shared activation helper script.
 *
 * Compiled to `build/activation.js` and exposed as `window.lwHarbor` so host
 * plugins can build activation URLs in the browser without bundling their own
 * copy. Registered as the `lw-harbor-activation` script handle.
 *
 * Only one Harbor instance registers the script, and it is whichever active
 * copy has the highest version. Consumers must therefore feature-detect
 * rather than assume a given API is present:
 *
 *     if ( window.lwHarbor?.buildActivationUrl ) { ... }
 *
 * Keep this entry dependency-free. It loads on admin pages that have nothing
 * to do with Harbor's own UI, so it must not pull in React or the store.
 *
 * @package LiquidWeb\Harbor
 */
export { buildActivationUrl } from '@/lib/activation-url';
