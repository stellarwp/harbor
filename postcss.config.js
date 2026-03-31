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
 * Rename Tailwind's internal --tw-* variables to --lw-harbor-tw-* throughout
 * Harbor's CSS output.
 *
 * WHY: External plugins built with Tailwind v3 (e.g. LearnDash) declare
 * --tw-translate-x, --tw-shadow, etc. on the universal selector (*) without
 * any CSS layer. Those unlayered declarations beat Harbor's layered utilities,
 * which also set --tw-* vars to drive composable transforms, shadows, rings,
 * etc. The result: the variable contains the wrong value and the utility
 * appears to have no effect.
 *
 * Renaming to --lw-harbor-tw-* makes Harbor's internal variable namespace private.
 * No external plugin knows about --lw-harbor-tw-*, so there is nothing to collide
 * with. This runs before the scoping plugin so @property declarations and
 * all var() references are consistently renamed in a single pass.
 */
function renameTailwindVars() {
    const rename = ( str ) => str.replace( /--tw-/g, '--lw-harbor-tw-' );

    const plugin = () => ( {
        postcssPlugin: 'postcss-rename-tw-vars',
        Declaration( decl ) {
            if ( decl.prop.startsWith( '--tw-' ) ) {
                decl.prop = rename( decl.prop );
            }
            if ( decl.value.includes( '--tw-' ) ) {
                decl.value = rename( decl.value );
            }
        },
        AtRule( atRule ) {
            // @property --tw-translate-x { ... }
            if ( atRule.name === 'property' && atRule.params.startsWith( '--tw-' ) ) {
                atRule.params = rename( atRule.params );
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
        renameTailwindVars(),
        scopeToHarborUI(),
        require( 'autoprefixer' ),
    ],
};
