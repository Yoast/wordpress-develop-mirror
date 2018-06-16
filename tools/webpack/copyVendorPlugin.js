var CopyPlugin = require('copy-webpack-plugin');

var getMinifier = require( './getMinifier' );

module.exports = new CopyPlugin([
	{
		from: '**/*',
		context: './src/js/_enqueues/vendor/',
		ignore: [
			'farbtastic.js',
			'iris.min.js',
			'deprecated/**',
			'README.md',
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
		from: './src/js/_enqueues/vendor/imgareaselect/jquery.imgareaselect.js',
		to: './build/wp-includes/js/imgareaselect/jquery.imgareaselect.min.js',
		transform: getMinifier()
	},
	{
		from: './src/js/_enqueues/vendor/colorpicker.js',
		to: './build/wp-includes/js/colorpicker.min.js',
		transform: getMinifier()
	},
	{
		from: './src/js/_enqueues/vendor/json2.js',
		to: './build/wp-includes/js/json2.min.js',
		transform: getMinifier()
	},
	{
		from: './src/js/_enqueues/vendor/mediaelement/mediaelement-migrate.js',
		to: './build/wp-includes/js/mediaelement/mediaelement-migrate.min.js',
		transform: getMinifier()
	},
	{
		from: './src/js/_enqueues/vendor/mediaelement/wp-mediaelement.js',
		to: './build/wp-includes/js/mediaelement/wp-mediaelement.min.js',
		transform: getMinifier()
	},
	{
		from: './src/js/_enqueues/vendor/mediaelement/wp-playlist.js',
		to: './build/wp-includes/js/mediaelement/wp-playlist.min.js',
		transform: getMinifier()
	},
	{
		from: '*.js',
		context: './src/js/_enqueues/vendor/plupload/',
		to: './build/wp-includes/js/plupload/[name].min.js',
		toType: 'template',
		transform: getMinifier()
	},
	{
		from: '{wordpress,wp*}/plugin.js',
		context: './src/js/_enqueues/vendor/tinymce/plugins/',
		to: './build/wp-includes/js/tinymce/plugins/[path]plugin.min.js',
		toType: 'template',
		transform: getMinifier()
	},
	{
		from: './src/js/_enqueues/vendor/tw-sack.js',
		to: './build/wp-includes/js/tw-sack.min.js',
		transform: getMinifier()
	}
]);
