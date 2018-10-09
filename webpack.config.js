const mediaConfig = require( './tools/webpack/media' );
const packagesConfig = require( './tools/webpack/packages' );

module.exports = function( env = { environment: "production" } ) {
	const config = [
		mediaConfig( env, __dirname ),
		packagesConfig( env ),
	];

	return config;
};
