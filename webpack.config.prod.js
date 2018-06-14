var path        	= require( 'path' ),
	webpack       	= require( 'webpack' );

var OutputAnnotationsPlugin = require( '../webpack-buildtarget-plugin' );

module.exports = [
	{
		cache: true,
		watch: false,
		entry: OutputAnnotationsPlugin.generateEntries(
			'./src/js/_enqueues/**/*.js',
			{ ignore: './src/js/_enqueues/vendor/**/*.js' },
			'./build/'
		),
		output: {
			filename: '[name]',
		},
		plugins: [
			new webpack.optimize.ModuleConcatenationPlugin(),
			new webpack.optimize.UglifyJsPlugin({
				include: /\.min\.js$/,
				minimize: true
			})
		]
	}
];
