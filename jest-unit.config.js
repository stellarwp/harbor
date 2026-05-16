const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config.js' );

module.exports = {
	...defaultConfig,
	moduleNameMapper: {
		'^@/(.*)$':    '<rootDir>/resources/js/$1',
		'\\.svg$':     '<rootDir>/tests/js/__mocks__/svg-mock.js',
	},
};
