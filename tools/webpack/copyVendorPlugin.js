var CopyPlugin = require('copy-webpack-plugin');
var UglifyJS   = require( 'uglify-js' );


module.exports = new CopyPlugin([
	{
		from: '**/*.js',
		context: './src/js/_enqueues/vendor/',
		ignore: [
			'farbtastic.js',
			'iris.min.js',
			'deprecated/**',
			// Ignore unminified version of vendor lib we don't ship.
			'jquery/jquery.masonry.js',
			'tinymce/tinymce.js'
		],
		to: './build/wp-includes/js/'
	},
	{
		from: '{farbtastic,iris.min}.js',
		context: './src/js/_enqueues/vendor/',
		to: './build/wp-admin/js/'
	},
	{
		from: 'suggest*',
		context: './src/js/_enqueues/vendor/deprecated',
		to: 'build/wp-includes/js/jquery/'
	},
	{
		from: 'imgareaselect/jquery.imgareaselect.js',
		context: './src/js/_enqueues/vendor/',
		to: './build/wp-includes/js/imgareaselect/jquery.imgareaselect.min.js',
		transform: function ( contents ) {
			return UglifyJS.minify( contents.toString(), { fromString: true } ).code.toString();
		}
	}
]);
