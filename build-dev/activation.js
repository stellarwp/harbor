/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./resources/js/lib/activation-url.ts"
/*!********************************************!*\
  !*** ./resources/js/lib/activation-url.ts ***!
  \********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   buildActivationUrl: () => (/* binding */ buildActivationUrl)
/* harmony export */ });
/**
 * Appends product and tier params to the base activation URL supplied by the
 * API, producing a product-scoped URL the Liquid Web portal can use to
 * pre-select the right product and tier.
 *
 * The base URL is already fully assembled by the server and includes params
 * such as portal-referral, redirect_url (percent-encoded), refresh, and
 * domain. This function only adds the two params it owns and never touches
 * the others.
 *
 * Example base URL from the API:
 *   https://my.liquidweb.com/subscriptions/?portal-referral=plugin
 *     &redirect_url=https%3A%2F%2Fexample.com%2Fwp-admin%2Foptions-general.php%3Fpage%3Dlw-software-manager%26refresh%3Dauto
 *     &domain=example.com
 *
 * @param baseUrl     The raw activationUrl string from the API.
 * @param productSlug e.g. "givewp"
 * @param tier        e.g. "elite"
 *
 * @since 1.0.0
 */
function buildActivationUrl(baseUrl, productSlug, tier) {
  try {
    const url = new URL(baseUrl);
    url.searchParams.set('sku', `${productSlug}:${tier}`);
    return url.toString();
  } catch {
    return baseUrl;
  }
}

/***/ }

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		if (!(moduleId in __webpack_modules__)) {
/******/ 			delete __webpack_module_cache__[moduleId];
/******/ 			var e = new Error("Cannot find module '" + moduleId + "'");
/******/ 			e.code = 'MODULE_NOT_FOUND';
/******/ 			throw e;
/******/ 		}
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!******************************************!*\
  !*** ./resources/js/activation-entry.ts ***!
  \******************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   buildActivationUrl: () => (/* reexport safe */ _lib_activation_url__WEBPACK_IMPORTED_MODULE_0__.buildActivationUrl)
/* harmony export */ });
/* harmony import */ var _lib_activation_url__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @/lib/activation-url */ "./resources/js/lib/activation-url.ts");
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

})();

window.lwHarbor = __webpack_exports__;
/******/ })()
;
//# sourceMappingURL=activation.js.map