var UglifyJS = require( 'uglify-js' );

module.exports = function ( options ) {
	options = options || {};
	options.output = Object.assign( {}, options.output, {
		ascii_only: true,
		ie8: true
	} );

	return function ( contents ) {
		var minified = UglifyJS.minify( contents.toString(), options );

		if ( minified.error ) {
			console.error( minified.error );
		}

		return minified.code.toString();
	};
};
