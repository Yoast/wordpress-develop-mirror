var webpack = require( 'webpack' );

module.exports = new webpack.optimize.UglifyJsPlugin({
	include: /\.min\.js$/,
	minimize: true,
	uglifyOptions: {
		ascii_only: true,
		ie8: true
	}
});
