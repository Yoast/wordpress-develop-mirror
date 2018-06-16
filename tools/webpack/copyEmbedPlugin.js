var CopyPlugin = require('copy-webpack-plugin');

var getMinifier = require( './getMinifier' );

module.exports = new CopyPlugin([
	{
		from: './src/js/_enqueues/wp/embed.js',
		to: './build/wp-includes/js/wp-embed.js'
	},
	{
		from: './src/js/_enqueues/wp/embed.js',
		to: './build/wp-includes/js/wp-embed.min.js',
		transform: getMinifier( { compress: { conditionals: false } } )
	}
]);
