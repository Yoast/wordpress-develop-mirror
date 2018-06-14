var webpack = require( 'webpack' );
var generateEntries = require( './tools/webpack/generateEntries' );

module.exports = [
	{
		cache: true,
		watch: false,
		entry: generateEntries({
			pattern: './src/js/_enqueues/**/*.js',
			globOptions: { ignore: './src/js/_enqueues/vendor/**/*.js' },
			prefix: './build/'
		}),
		output: { filename: '[name]' },
		plugins: [
			new webpack.optimize.ModuleConcatenationPlugin(),
			new webpack.optimize.UglifyJsPlugin({
				include: /\.min\.js$/,
				minimize: true
			})
		]
	}
];
