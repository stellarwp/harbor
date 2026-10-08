const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config.js' );

module.exports = {
	...defaultConfig,
	moduleNameMapper: {
		'\\.(svg|png)$': '<rootDir>/tests/js/__mocks__/svg-mock.js',
		'^@/(.*)$':    '<rootDir>/resources/js/$1',
	},
};
