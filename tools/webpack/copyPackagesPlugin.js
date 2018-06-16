var CopyPlugin = require('copy-webpack-plugin');
var UglifyJS   = require( 'uglify-js' );

module.exports = new CopyPlugin([
	{ to: './build/wp-includes/js/backbone.js',                  from: './node_modules/backbone/backbone.js' },
	{ to: './build/wp-includes/js/backbone.min.js',              from: './node_modules/backbone/backbone-min.js' },
	{ to: './build/wp-includes/js/hoverIntent.js',               from: './node_modules/jquery-hoverintent/jquery.hoverIntent.js' },
	{ to: './build/wp-includes/js/imagesloaded.min.js',          from: './node_modules/imagesloaded/imagesloaded.pkgd.min.js' },
	{ to: './build/wp-includes/js/jquery/jquery-migrate.js',     from: './node_modules/jquery-migrate/dist/jquery-migrate.js' },
	{ to: './build/wp-includes/js/jquery/jquery-migrate.min.js', from: './node_modules/jquery-migrate/dist/jquery-migrate.min.js' },
	{ to: './build/wp-includes/js/jquery/jquery.form.js',        from: './node_modules/jquery-form/src/jquery.form.js' },
	{ to: './build/wp-includes/js/jquery/jquery.form.min.js',    from: './node_modules/jquery-form/dist/jquery.form.min.js' },
	{ to: './build/wp-includes/js/masonry.min.js',               from: './node_modules/masonry-layout/dist/masonry.pkgd.min.js' },
	{ to: './build/wp-includes/js/underscore.min.js',            from: './node_modules/underscore/underscore-min.js' },
	{ to: './build/wp-includes/js/twemoji.js',                   from: './node_modules/twemoji/2/twemoji.js' },
	{
		to: './build/wp-includes/js/jquery/jquery.js',
		from: './node_modules/jquery/dist/jquery.min.js',
		transform: function ( contents ) {
			return contents.toString() + '\njQuery.noConflict();';
		}
	},
	{
		from: '*.js',
		context: './node_modules/jquery-ui/ui/',
		to: './build/wp-includes/js/jquery/ui/[name].min.js',
		toType: 'template',
		transform: function ( contents ) {
			return UglifyJS.minify( contents.toString(), { fromString: true, preserveComments: /^!/ } ).code.toString();
		}
	}
]);
