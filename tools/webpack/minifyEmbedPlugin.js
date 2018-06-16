var webpack = require( 'webpack' );

module.exports = new webpack.optimize.UglifyJsPlugin({
	include: 'wp-includes/js/wp-embed.min.js',
	uglifyOptions: {
		compress: {
			conditionals: false
		}
	},
	minimize: true
});
