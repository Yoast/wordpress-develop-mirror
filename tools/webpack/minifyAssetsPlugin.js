var webpack = require( 'webpack' );

module.exports = new webpack.optimize.UglifyJsPlugin({
	include: /\.min\.js$/,
	exclude: 'wp-includes/js/wp-embed.min.js',
	minimize: true
});
