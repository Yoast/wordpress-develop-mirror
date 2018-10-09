const mediaConfig = require( './tools/webpack/media' );
const packagesConfig = require( './tools/webpack/packages' );
const vendorsConfig = require( './tools/webpack/vendors' );

module.exports = function( env = { environment: "production", watch: false } ) {
	if ( ! env.watch ) {
		env.watch = false;
	}

	const config = [
		mediaConfig( env ),
		packagesConfig( env ),
		vendorsConfig( env ),
	];

	return config;
};
