const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config.js' );

module.exports = {
	...defaultConfig,
	moduleNameMapper: {
		'^@/(.*)$': '<rootDir>/resources/js/$1',
	},
};
