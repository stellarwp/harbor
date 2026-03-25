/**
 * Strip !important from CSS custom property declarations.
 *
 * important: true in tailwind.config.js adds !important to every declaration,
 * including --custom-property definitions inside :root. Browsers treat
 * !important on custom properties as invalid, which breaks the entire variable.
 * This plugin runs immediately after @tailwindcss/postcss and undoes that for
 * any declaration whose property starts with --.
 */
function stripImportantFromCustomProps() {
    const plugin = () => ( {
        postcssPlugin: 'postcss-strip-important-from-custom-props',
        Declaration( decl ) {
            if ( decl.prop.startsWith( '--' ) && decl.important ) {
                decl.important = false;
            }
        },
    } );
    plugin.postcss = true;
    return plugin;
}

/**
 * Scope all Tailwind-generated CSS rules under .lw-harbor-ui.
 *
 * Tailwind v4's @config compatibility layer does not support the selector
 * strategy (important: '.selector'), so scoping is handled here instead.
 * Running after @tailwindcss/postcss (which expands all utilities) and before
 * autoprefixer (so vendor prefixes are added to the already-scoped selectors).
 *
 * Rules excluded from scoping:
 *   - :root   — CSS variables must remain global to be reachable by var()
 *   - @keyframes content — animation keyframes don't use ancestor selectors
 *   - Rules already containing .lw-harbor-ui — written that way intentionally
 *     (e.g. the base resets in globals.css)
 */
function scopeToHarborUI() {
    const plugin = () => ( {
        postcssPlugin: 'postcss-scope-to-lw-harbor-ui',
        Rule( rule ) {
            if (
                rule.selector.includes( '.lw-harbor-ui' ) ||
                /^:root\b/.test( rule.selector.trim() ) ||
                /^keyframes$/i.test( rule.parent?.name ?? '' )
            ) {
                return;
            }
            rule.selector = rule.selectors
                .map( ( s ) => `.lw-harbor-ui ${ s }` )
                .join( ',\n' );
        },
    } );
    plugin.postcss = true;
    return plugin;
}

module.exports = {
    plugins: [
        require( '@tailwindcss/postcss' ),
        stripImportantFromCustomProps(),
        scopeToHarborUI(),
        require( 'autoprefixer' ),
    ],
};
