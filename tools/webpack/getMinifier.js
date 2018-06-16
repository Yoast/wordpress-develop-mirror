var UglifyJS = require( 'uglify-js' );

module.exports = function ( options ) {
	options = Object.assign( {}, options, { fromString: true } );

	return function ( contents ) {
		return UglifyJS.minify( contents.toString(), options ).code.toString();
	};
};
