const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

const isProduction = process.env.NODE_ENV === 'production';
const buildMode    = process.env.BUILD_MODE || ( isProduction ? 'prod' : 'dev' );
const outputPath   = path.resolve(
    process.cwd(),
    buildMode === 'prod' ? 'build' : 'build-dev'
);

module.exports = {
    ...defaultConfig,
    entry: {
        index:    path.resolve( process.cwd(), 'resources', 'js', 'index.tsx' ),
    },
    output: {
        path:          outputPath,
        filename:      '[name].js',
        chunkFilename: '[name].js?ver=[contenthash]',
    },
    module: {
        ...defaultConfig.module,
        // Inline images into the bundle. Only the tracked build files ship, so
        // the separate files wp-scripts emits to build/images/ would be lost.
        rules: defaultConfig.module.rules.map( ( rule ) =>
            String( rule.test ).includes( 'png' )
                ? { test: rule.test, type: 'asset/inline' }
                : rule
        ),
    },
    resolve: {
        ...defaultConfig.resolve,
        alias: {
            '@':           path.resolve( process.cwd(), 'resources', 'js' ),
            '@components': path.resolve( process.cwd(), 'resources', 'js', 'components' ),
            '@lib':        path.resolve( process.cwd(), 'resources', 'js', 'lib' ),
            '@css':        path.resolve( process.cwd(), 'resources', 'css' ),
            '@img':        path.resolve( process.cwd(), 'resources', 'img' ),
        },
    },
    optimization: {
        ...defaultConfig.optimization,
        ...( buildMode === 'prod' && { minimize: true, usedExports: true } ),
    },
    devtool: buildMode === 'prod' ? false : 'source-map',
};
